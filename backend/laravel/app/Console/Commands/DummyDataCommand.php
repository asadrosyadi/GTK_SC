<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class DummyDataCommand extends Command
{
    protected $signature = 'taniverse:dummy
        {--iot= : iot_id tujuan (bawaan: iot_id akun pertama)}
        {--hari=5 : panjang riwayat yang dibuat (hari)}
        {--live : setelah riwayat, terus menambah data tiap --interval menit}
        {--interval=15 : jeda data sensor pada mode live (menit)}
        {--kamera : kirim foto dataset ke layanan deteksi CNN}
        {--gambar= : folder citra dataset (bawaan: Training_Penyakit Padi/val)}
        {--hapus : hapus semua data dummy lalu selesai}';

    protected $description = 'Buat atau hapus data dummy sensor & kamera (uji tanpa ESP32)';

    public function handle(): int
    {
        if ($this->option('hapus')) {
            return $this->hapus();
        }

        $iotId = $this->option('iot') ?: User::query()->orderBy('id')->value('iot_id');
        if (! $iotId || ! User::where('iot_id', $iotId)->exists()) {
            $this->error('Tidak ada akun dengan iot_id tersebut. Buat akun dulu atau isi --iot=.');

            return self::FAILURE;
        }

        $hari = max(1, (int) $this->option('hari'));
        $now = CarbonImmutable::now()->second(0);
        $sudahAda = DB::table('sensor_iots')->where('is_dummy', true)->where('iot_id', $iotId)->max('created_at');

        if ($sudahAda && ! $this->option('live') && ! $this->option('kamera')) {
            $this->warn("Data dummy untuk {$iotId} sudah ada (terakhir {$sudahAda}).");
            $this->line('Jalankan  php artisan taniverse:dummy --hapus  dulu, atau gunakan --live / --kamera untuk menambah.');

            return self::FAILURE;
        }

        if (! $sudahAda) {
            $n = $this->isiRiwayat($iotId, $now->subDays($hari), $now);
            $this->info("{$n} baris sensor dummy dibuat untuk {$iotId} ({$hari} hari, petak 1 & 2).");
        }

        if ($this->option('kamera')) {
            foreach ([1, 2, 1, 2] as $titik) {
                $this->kirimFoto($iotId, $titik);
            }
        }

        if (! $this->option('live')) {
            $this->line('Selesai. Hapus kapan saja dengan:  php artisan taniverse:dummy --hapus');

            return self::SUCCESS;
        }

        return $this->live($iotId);
    }

    private function bacaan(string $iotId, int $titik, CarbonImmutable $t): array
    {
        $jam = $t->hour + $t->minute / 60;
        $hariKe = $t->timestamp / 86400;
        $harian = sin(2 * M_PI * ($jam - 9) / 24);
        $acakHari = sin($hariKe * 1.7) * 0.8;
        $noise = fn (float $s) => (mt_rand() / mt_getrandmax() - 0.5) * 2 * $s;

        $hariHujan = crc32($t->format('Y-m-d')) % 3 === 0;
        $hujan = ($hariHujan && $jam >= 14 && $jam < 17) ? 0.4 * mt_rand(0, 5) : 0.0;

        $siklus = fmod($hariKe + ($titik === 2 ? 2.5 : 0), 6) / 6;
        $vwc = ($titik === 1 ? 47 : 44) - 13 * $siklus;

        $base = [
            'iot_id' => $iotId, 'titik' => $titik, 'is_dummy' => true,
            'temperature' => null, 'humidity' => null, 'windspeed' => null, 'rainfall' => null,
            'light_intensity' => null, 'pressure' => null,
            'ph' => round(($titik === 1 ? 6.4 : 5.6) + $noise(0.05), 2),
            'soil_moisture' => round($vwc + $noise(0.5), 2),
            'ec' => $ec = round(($titik === 1 ? 910 : 780) + $noise(25), 2),
            'tds' => round($ec * 0.5, 2),
            'soil_temp' => round(27 + 1.4 * sin(2 * M_PI * ($jam - 12) / 24) + $noise(0.2), 2),
            'Nitrogen_Level' => round(($titik === 1 ? 32 : 26) + $noise(1), 2),
            'Phosphorus_Level' => round(($titik === 1 ? 18 : 12) + $noise(0.8), 2),
            'Potassium_Level' => round(($titik === 1 ? 150 : 70) + $noise(3), 2),
            'battery_level' => (int) round(($titik === 1 ? 86 : 78) - 3 * $harian),
            'signal_strength' => $titik === 1 ? mt_rand(62, 70) : mt_rand(50, 58),
            'created_at' => $t->toDateTimeString(), 'updated_at' => $t->toDateTimeString(),
        ];

        if ($titik === 1) {
            $base['temperature'] = round(27.2 + 4.2 * $harian + $acakHari + $noise(0.4) - ($hujan > 0 ? 2 : 0), 2);
            $base['humidity'] = round(min(99, 80 - 13 * $harian + ($hujan > 0 ? 12 : 0) + $noise(1.5)), 2);
            $base['windspeed'] = round(max(0, 1.6 + 1.2 * $harian + $noise(0.6)), 2);
            $base['rainfall'] = $hujan;
            $base['light_intensity'] = (int) round(max(0, 58000 * sin(M_PI * ($jam - 6) / 12)) * ($hujan > 0 ? 0.3 : 0.85 + $noise(0.1)));
            $base['pressure'] = round(100820 + 70 * sin(4 * M_PI * $jam / 24) + $noise(15), 2);
        }

        return $base;
    }

    private function isiRiwayat(string $iotId, CarbonImmutable $dari, CarbonImmutable $sampai): int
    {
        $rows = [];
        $n = 0;
        for ($t = $dari; $t <= $sampai; $t = $t->addMinutes(15)) {
            foreach ([1, 2] as $titik) {
                $rows[] = $this->bacaan($iotId, $titik, $t);
            }
            if (count($rows) >= 400) {
                DB::table('sensor_iots')->insert($rows);
                $n += count($rows);
                $rows = [];
            }
        }
        if ($rows) {
            DB::table('sensor_iots')->insert($rows);
            $n += count($rows);
        }

        return $n;
    }

    private function kirimFoto(string $iotId, int $titik): void
    {
        $folder = $this->option('gambar') ?: base_path('../../Training_Penyakit Padi/val');
        $files = File::isDirectory($folder) ? File::glob(rtrim($folder, '/\\') . '/*/*.jpg') : [];
        if (! $files) {
            $this->warn("Foto dummy dilewati: tidak ada citra .jpg di {$folder} (gunakan --gambar=).");

            return;
        }

        $file = $files[array_rand($files)];
        $url = config('monitoring.detect.url') . '?' . http_build_query(['titik' => $titik, 'iot_id' => $iotId, 'dummy' => 1]);
        try {
            $res = Http::timeout(config('monitoring.detect.timeout', 60))
                ->withBody(File::get($file), 'image/jpeg')
                ->post($url);
        } catch (ConnectionException $e) {
            $this->warn('Foto dummy dilewati: layanan deteksi (' . config('monitoring.detect.url') . ') tidak berjalan.');

            return;
        }

        $j = $res->json() ?? [];
        if (! empty($j['saved'])) {
            $this->info("Foto dummy petak {$titik}: {$j['penyakit']} ({$j['probabilitas']}%) [" . basename(dirname($file)) . '/' . basename($file) . ']');
        } else {
            $this->warn("Foto dummy petak {$titik} tidak disimpan: " . ($j['reason'] ?? $j['error'] ?? 'HTTP ' . $res->status()));
        }
    }

    private function live(string $iotId): int
    {
        $interval = max(1, (int) $this->option('interval'));
        $this->info("Mode live: data dummy ditambah tiap {$interval} menit" . ($this->option('kamera') ? ', foto tiap 10 menit' : '') . '. Tekan Ctrl+C untuk berhenti.');
        $berikutSensor = CarbonImmutable::now();
        $berikutFoto = CarbonImmutable::now()->addMinutes(10);

        while (true) {
            $now = CarbonImmutable::now()->second(0);
            if ($now >= $berikutSensor) {
                foreach ([1, 2] as $titik) {
                    DB::table('sensor_iots')->insert($this->bacaan($iotId, $titik, $now));
                }
                $this->line('[' . $now->format('H:i') . '] data sensor dummy petak 1 & 2 ditambahkan');
                $berikutSensor = $now->addMinutes($interval);
            }
            if ($this->option('kamera') && $now >= $berikutFoto) {
                $this->kirimFoto($iotId, 1);
                $this->kirimFoto($iotId, 2);
                $berikutFoto = $now->addMinutes(10);
            }
            sleep(20);
        }
    }

    private function hapus(): int
    {
        $sensor = DB::table('sensor_iots')->where('is_dummy', true)->delete();

        $dir = env('HAMA_PADI_DIR', base_path('../html/img/hama_padi'));
        $gambar = 0;
        DB::table('sensor_kameras')->where('is_dummy', true)->orderBy('id')
            ->chunkById(500, function ($rows) use ($dir, &$gambar) {
                foreach ($rows as $row) {
                    if (preg_match('/^[0-9a-f]{32}\.jpg$/', (string) $row->image) && File::exists("{$dir}/{$row->image}")) {
                        File::delete("{$dir}/{$row->image}");
                        $gambar++;
                    }
                }
            });
        $kamera = DB::table('sensor_kameras')->where('is_dummy', true)->delete();

        $this->info("Data dummy dihapus: {$sensor} baris sensor, {$kamera} deteksi kamera, {$gambar} file gambar.");
        $this->line('Data asli (is_dummy = 0) tidak tersentuh.');

        return self::SUCCESS;
    }
}
