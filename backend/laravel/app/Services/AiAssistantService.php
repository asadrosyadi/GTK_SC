<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiAssistantService
{
    private const SYSTEM_PROMPT = <<<'TXT'
Anda adalah asisten pertanian padi sawah untuk petani di Indonesia di aplikasi AgroGuard.
Aturan:
- Jawab dalam Bahasa Indonesia yang sederhana dan mudah dipahami petani; hindari istilah teknis/singkatan
  (bila terpaksa, jelaskan singkat dalam kurung).
- Gunakan HANYA data pada bagian "DATA SAWAH SAAT INI". Jangan mengarang angka, dosis, atau hasil deteksi.
  Bila data tidak cukup untuk menjawab, katakan terus terang dan sarankan apa yang perlu dicek di lapangan.
- Rekomendasi sistem (dari model & pedoman IRRI/BMKG/Balittanah) adalah acuan utama; jangan bertentangan dengannya.
  Untuk dosis pupuk/obat, ikuti angka pada rekomendasi sistem.
- Untuk penggunaan pestisida/obat, sarankan konsultasi dengan penyuluh atau petugas pengamat hama.
- Jawaban ringkas dan langsung bisa dikerjakan: gunakan poin-poin pendek, sebutkan petak yang dimaksud.
TXT;

    public function __construct(private MultiTitikService $multiTitik)
    {
    }

    public function providerName(): string
    {
        $url = (string) config('services.openai.url');

        return match (true) {
            str_contains($url, 'googleapis.com') => 'Gemini',
            str_contains($url, 'x.ai') => 'Grok',
            str_contains($url, ':11434') => 'Ollama',
            str_contains($url, 'openai.com') => 'ChatGPT',
            default => 'AI',
        };
    }

    public function enabled(): bool
    {
        return (string) config('services.openai.key') !== '';
    }

    public function context(string $iotId): array
    {
        $snapshot = $this->multiTitik->snapshot($iotId);
        $rec = $this->multiTitik->recommend($iotId);
        $kelas = ['Healthy' => 'Sehat', 'Moderate Stress' => 'Stres sedang', 'High Stress' => 'Stres tinggi'];
        $f = function ($v, int $d = 1) {
            if (! is_numeric($v)) {
                return '-';
            }
            $s = number_format((float) $v, $d, ',', '');

            return $d > 0 ? rtrim(rtrim($s, '0'), ',') : $s;
        };

        $lines = ['Waktu: ' . now()->format('Y-m-d H:i')];
        $w = $snapshot['weather'] ?? null;
        if ($w) {
            $lines[] = 'Cuaca (stasiun Petak 1): suhu ' . $f($w['temperature'] ?? null) . ' °C, kelembapan ' . $f($w['humidity'] ?? null, 0)
                . ' %, angin ' . $f($w['windspeed'] ?? null) . ' m/s, hujan 24 jam ' . $f($w['rainfall'] ?? null) . ' mm, cahaya '
                . $f($w['light_intensity'] ?? null, 0) . ' lux, tekanan ' . $f(($w['pressure'] ?? 0) / 100) . ' hPa';
        }
        foreach ($snapshot['titik'] ?? [] as $no => $t) {
            $s = $t['soil'] ?? [];
            $lines[] = '';
            $lines[] = $this->multiTitik->label((int) $no) . ':';
            $lines[] = '- Tanah: pH ' . $f($s['ph'] ?? null, 2) . ', kadar air ' . $f($s['soil_moisture'] ?? null) . ' %, EC '
                . $f($s['ec'] ?? null, 0) . ' µS/cm, suhu tanah ' . $f($s['soil_temp'] ?? null) . ' °C, N ' . $f($s['Nitrogen_Level'] ?? null)
                . ' mg/kg, P ' . $f($s['Phosphorus_Level'] ?? null) . ' mg/kg, K ' . $f($s['Potassium_Level'] ?? null) . ' mg/kg';
            $cam = $t['camera'] ?? null;
            $lines[] = '- Kamera hama: ' . ($cam ? ($cam['penyakit'] ?? '-') . ' (yakin ' . $f($cam['probabilitas'] ?? null, 0) . ' %, '
                . ($cam['created_at'] ?? '-') . ')' : 'belum ada foto');
            $h = $rec['petak'][$no]['health'] ?? null;
            if (is_array($h) && $h) {
                arsort($h);
                $lines[] = '- Kondisi tanaman (model LSTM): ' . ($kelas[array_key_first($h)] ?? array_key_first($h)) . ' (sehat '
                    . $f(($h['Healthy'] ?? 0) * 100, 0) . ' %, stres sedang ' . $f(($h['Moderate Stress'] ?? 0) * 100, 0)
                    . ' %, stres tinggi ' . $f(($h['High Stress'] ?? 0) * 100, 0) . ' %)';
            }
            $fase = $rec['petak'][$no]['fase'] ?? null;
            $lines[] = '- Fase tumbuh: ' . ($fase ? $fase . ' (umur ' . ($rec['petak'][$no]['hst'] ?? '-') . ' hari setelah tanam)' : 'belum diatur');
        }

        $lines[] = '';
        $lines[] = 'Rekomendasi sistem saat ini (urut dari paling mendesak):';
        $tingkat = ['danger' => 'MENDESAK', 'warning' => 'perlu dilakukan', 'info' => 'informasi', 'success' => 'aman'];
        foreach ($rec['summary'] ?? [] as $i => $a) {
            $alasan = collect($a['reasons_by_petak'] ?? [])->map(fn ($r, $p) => $p . ': ' . implode('; ', $r))->implode(' | ');
            $lines[] = ($i + 1) . '. [' . ($tingkat[$a['type'] ?? ''] ?? $a['type'] ?? '-') . '] ' . ($a['title'] ?? '-')
                . ' — ' . implode(', ', $a['petak'] ?? []) . '. Alasan: ' . ($alasan ?: '-') . '. Saran: ' . ($a['message'] ?? '-');
        }
        if (empty($rec['summary'])) {
            $lines[] = '- (tidak ada rekomendasi)';
        }

        $text = implode("\n", $lines);

        return ['text' => $text, 'key' => md5(implode("\n", array_slice($lines, 1)))];
    }

    public function summary(string $iotId): array
    {
        $ctx = $this->context($iotId);

        return Cache::remember("ai_summary:{$iotId}:{$ctx['key']}", now()->addHours(6), function () use ($ctx) {
            $answer = $this->complete([
                ['role' => 'user', 'content' => "DATA SAWAH SAAT INI:\n{$ctx['text']}\n\n"
                    . "Buat KESIMPULAN keadaan sawah untuk petani, maksimal 150 kata, dengan format:\n"
                    . "1) Satu kalimat kondisi umum.\n2) 'Yang harus dikerjakan hari ini:' berisi poin-poin per petak, urut paling mendesak.\n"
                    . "3) 'Yang perlu dipantau:' 1–3 poin."],
            ], 500);

            return ['text' => $answer, 'generated_at' => now()->format('Y-m-d H:i')];
        });
    }

    public function chat(string $iotId, string $message, array $history = []): string
    {
        $ctx = $this->context($iotId);
        $messages = [['role' => 'user', 'content' => "DATA SAWAH SAAT INI:\n{$ctx['text']}\n\nGunakan data di atas untuk menjawab pertanyaan berikutnya."],
            ['role' => 'assistant', 'content' => 'Baik, saya akan menjawab berdasarkan data sawah tersebut.']];
        foreach (array_slice($history, -10) as $h) {
            if (in_array($h['role'] ?? null, ['user', 'assistant'], true) && is_string($h['content'] ?? null)) {
                $messages[] = ['role' => $h['role'], 'content' => mb_substr($h['content'], 0, 2000)];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        return $this->complete($messages, 700);
    }

    private function complete(array $messages, int $maxTokens): string
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Asisten AI belum aktif: isi kunci API (OPENAI_API_KEY) di file .env.');
        }
        $response = Http::withToken((string) config('services.openai.key'))
            ->timeout((int) config('services.openai.timeout', 60))
            ->acceptJson()
            ->post(config('services.openai.url') . '/chat/completions', [
                'model' => config('services.openai.model'),
                'messages' => array_merge([['role' => 'system', 'content' => self::SYSTEM_PROMPT]], $messages),
                'temperature' => 0.3,
                'max_tokens' => $maxTokens,
            ]);

        if (! $response->successful()) {
            report(new RuntimeException($this->providerName() . ' ' . $response->status() . ': ' . mb_substr($response->body(), 0, 500)));
            $pesan = match ($response->status()) {
                400, 401, 403 => 'kunci API atau nama model ' . $this->providerName() . ' tidak valid',
                404 => 'model ' . config('services.openai.model') . ' tidak ditemukan di ' . $this->providerName(),
                429 => 'batas permintaan ' . $this->providerName() . ' habis, coba lagi beberapa saat lagi',
                default => 'layanan ' . $this->providerName() . ' sedang bermasalah (kode ' . $response->status() . ')',
            };
            throw new RuntimeException('Asisten AI gagal menjawab: ' . $pesan . '.');
        }

        return trim((string) $response->json('choices.0.message.content', ''));
    }
}
