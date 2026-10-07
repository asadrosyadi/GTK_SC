<?php

namespace App\Http\Controllers;

class RecommendationService
{
    public const THRESHOLDS = [
        'soil_moisture_irrigate' => 35,
        'temp_heat' => 35,
        'blb_rh' => 70, 'blb_t_min' => 25, 'blb_t_max' => 34,
        'blb_rain_24h' => 50,
        'blast_rh' => 90, 'blast_t_min' => 20, 'blast_t_max' => 28,
        'ec_saline' => 3000,
        'tds_saline' => 1920,
        'ph_acid' => 5.5, 'ph_alkaline' => 7.5,
        'p_low' => 6, 'p_medium' => 10,
        'k_low' => 78,
        'wind_lodging' => 8,
    ];

    public const PEST_ADVICE = [
        'Hawar daun bakteri' => ['danger', 'Tunda atau kurangi pupuk urea, buang kelebihan air dari sawah (kecuali saat padi berbunga), bersihkan rumput dan jerami yang sakit. Obat bakteri tidak dianjurkan; musim depan pakai varietas tahan.'],
        'Garis daun bakteri' => ['danger', 'Beri pupuk urea secukupnya, buang kelebihan air dari sawah (kecuali saat padi berbunga), bersihkan jerami dan rumput, musim depan pakai varietas tahan.'],
        'Hawar malai bakteri' => ['danger', 'Beri pupuk urea secukupnya, bersihkan sisa tanaman yang sakit, musim depan pakai varietas tahan.'],
        'Penyakit blas (jamur)' => ['danger', 'Usahakan sawah tetap tergenang, beri pupuk urea sedikit-sedikit beberapa kali, semprot obat jamur (berbahan triazol atau strobilurin) terutama saat padi keluar malai.'],
        'Bercak coklat' => ['warning', 'Sering muncul di tanah kurang subur atau kering: tambahkan pupuk kalium dan silika sesuai uji tanah, jaga sawah tetap tergenang; musim depan obati benih sebelum disemai.'],
        'Pucuk mati' => ['danger', 'Tanda hama penggerek batang: tinggikan air di sawah supaya telur tenggelam, kumpulkan dan musnahkan kelompok telur, beri urea dibagi beberapa kali; semprot obat hanya bila serangan sudah banyak (tanyakan ke petugas pengamat hama/penyuluh).'],
        'Embun bulu (jamur bulu halus)' => ['danger', 'Jangan biarkan tanah becek terlalu lama pada tanaman muda, cabut tanaman yang sakit; musim depan obati benih dengan obat jamur (metalaksil).'],
        'Hama penggerek daun (Hispa)' => ['danger', 'Nyalakan alat pengusir hama (suara) dan lampu perangkap, potong ujung daun yang ada telurnya, jangan memberi urea berlebihan.'],
        'Penyakit tungro (virus)' => ['danger', 'Tidak bisa diobati: cabut rumpun yang sakit lalu benamkan ke lumpur. Semprotan obat wereng sering tidak mempan; musim depan pakai varietas tahan dan tanam serempak.'],
    ];

    public function getRecommendations($sensorData, $sensorData2)
    {
        $t = self::THRESHOLDS;
        $recommendations = [];
        $titles = [];

        $add = function (string $type, string $icon, string $title, string $message) use (&$recommendations, &$titles) {
            if (! in_array($title, $titles, true)) {
                $recommendations[] = compact('type', 'icon', 'title', 'message');
                $titles[] = $title;
            }
        };
        $num = fn ($row, $field) => (isset($row[$field]) && $row[$field] !== '' && is_numeric($row[$field])) ? (float) $row[$field] : null;

        $nSensitive = false;
        foreach ($sensorData2 as $camera) {
            $penyakit = is_array($camera) ? ($camera['penyakit'] ?? null) : ($camera->penyakit ?? null);
            $prob = is_array($camera) ? ($camera['probabilitas'] ?? null) : ($camera->probabilitas ?? null);
            if (isset(self::PEST_ADVICE[$penyakit]) && (! is_numeric($prob) || (float) $prob >= 50)) {
                [$type, $message] = self::PEST_ADVICE[$penyakit];
                $add($type, 'bx bx-bug', $penyakit, $message);
                $nSensitive = $nSensitive || in_array($penyakit, ['Hawar daun bakteri', 'Garis daun bakteri', 'Hawar malai bakteri',
                    'Penyakit blas (jamur)', 'Pucuk mati', 'Hama penggerek daun (Hispa)'], true);
            }
        }

        foreach ($sensorData as $data) {
            $temperature = $num($data, 'temperature');
            $humidity = $num($data, 'humidity');
            if ($temperature !== null && $temperature >= $t['temp_heat']) {
                $add('danger', 'bx bx-sun', 'Lindungi padi dari udara panas', "Suhu udara {$temperature} °C (batas {$t['temp_heat']} °C) bisa membuat gabah hampa, terutama saat padi berbunga. Jaga air di sawah setinggi ±5 cm.");
            }
            if ($temperature !== null && $humidity !== null && $humidity >= $t['blast_rh']
                && $temperature >= $t['blast_t_min'] && $temperature <= $t['blast_t_max']) {
                $add('warning', 'bx bx-error', 'Waspada penyakit blas', "Kelembapan udara {$humidity}% dan suhu {$temperature} °C cocok untuk jamur blas. Jaga sawah tetap tergenang, jangan memberi urea berlebihan, periksa tanaman lebih sering.");
            }

            $rain = $num($data, 'rainfall');
            if ($temperature !== null && $humidity !== null && $rain !== null && $rain >= $t['blb_rain_24h']
                && $humidity > $t['blb_rh'] && $temperature >= $t['blb_t_min'] && $temperature <= $t['blb_t_max']) {
                $add('warning', 'bx bx-error', 'Waspada penyakit kresek', "Hujan lebat {$rain} mm sehari, suhu {$temperature} °C, kelembapan {$humidity}%: cocok untuk penyebaran kresek. Bersihkan rumput dan jerami yang sakit, jangan memberi urea berlebihan.");
            }

            $soilMoisture = $num($data, 'soil_moisture');
            if ($soilMoisture !== null && $soilMoisture < $t['soil_moisture_irrigate']) {
                $add('warning', 'bx bx-droplet', 'Airi sawah kembali', "Kadar air tanah {$soilMoisture}%");
            }

            $ec = $num($data, 'ec');
            $tds = $num($data, 'tds');
            if (($ec !== null && $ec >= $t['ec_saline']) || ($tds !== null && $tds >= $t['tds_saline'])) {
                $add('danger', 'bx bx-water', 'Tanah terlalu asin: bilas sawah', 'Kadar garam tanah melewati batas yang bisa ditahan padi (hasil panen turun ±12% setiap kenaikan 1 dS/m). Alirkan air irigasi tawar lalu buang lewat saluran pembuangan.');
            }

            $ph = $num($data, 'ph');
            if ($ph !== null && $ph <= $t['ph_acid']) {
                $add('warning', 'bx bx-test-tube', 'Beri kapur untuk tanah masam', "pH tanah {$ph} (masam, batas {$t['ph_acid']}). Taburkan kapur pertanian (dolomit) saat mengolah tanah dan tambahkan pupuk kandang/kompos.");
            } elseif ($ph !== null && $ph > $t['ph_alkaline']) {
                $add('warning', 'bx bx-test-tube', 'Tanah basa: cegah kekurangan seng (Zn)', "pH tanah {$ph} (basa, di atas {$t['ph_alkaline']}). Perhatikan gejala kekurangan seng, beri pupuk ZnSO4, dan pakai pupuk ZA sebagai sumber nitrogen.");
            }

            if ($nSensitive) {
                $add('warning', 'bx bx-leaf', 'Tunda atau kurangi pupuk urea', 'Ada hama/penyakit yang makin parah bila pupuk urea berlebihan. Tunda pemupukan urea, lalu berikan secukupnya dan dibagi beberapa kali.');
            }

            $p = $num($data, 'Phosphorus_Level');
            if ($p !== null && $p < $t['p_medium']) {
                $dose = $p < $t['p_low'] ? '100–125' : '75';
                $add('warning', 'bx bx-leaf', 'Tambah pupuk fosfat (SP-36)', "Fosfat tanah {$p} mg/kg (kurang, batas {$t['p_medium']}). Berikan {$dose} kg SP-36/ha sebagai pupuk dasar.");
            }

            $k = $num($data, 'Potassium_Level');
            if ($k !== null && $k < $t['k_low']) {
                $add('warning', 'bx bx-leaf', 'Tambah pupuk kalium (KCl)', "Kalium tanah {$k} mg/kg, kurang dari batas {$t['k_low']} mg/kg. Berikan KCl 100 kg/ha (cukup 50 kg/ha bila jerami dikembalikan ke sawah).");
            }

            $wind = $num($data, 'windspeed');
            if ($wind !== null && $wind >= $t['wind_lodging']) {
                $add('warning', 'bx bx-wind', 'Waspada padi rebah karena angin kencang', "Angin {$wind} m/s bisa merobohkan padi yang sedang bunting sampai masak. Tunda penyemprotan.");
            }
        }

        return $recommendations;
    }

    public function getActuation($sensorData, $cameraData, ?string $healthStatus = null): array
    {
        $HISPA   = 'Hama penggerek daun (Hispa)';
        $HEALTHY = 'Normal / Sehat';

        $penyakit = null;
        foreach ($cameraData as $row) {
            $penyakit = is_array($row) ? ($row['penyakit'] ?? null) : ($row->penyakit ?? null);
            break;
        }
        $pestDetected = $penyakit !== null && $penyakit !== '' && $penyakit !== $HEALTHY;

        $vwc = null;
        foreach ($sensorData as $row) {
            $value = is_array($row) ? ($row['soil_moisture'] ?? null) : ($row->soil_moisture ?? null);
            $vwc = is_numeric($value) ? (float) $value : null;
            break;
        }

        if ($pestDetected) {
            if ($penyakit === $HISPA) {
                return [
                    'action'    => 'activate_ultrasonic_repeller_and_uv_lamp',
                    'actuators' => ['ultrasonic_repeller', 'uv_lamp'],
                    'type'      => 'danger',
                    'icon'      => 'bx bx-broadcast',
                    'title'     => 'Aktuasi: Ultrasonik + Lampu UV',
                    'message'   => "Terdeteksi {$penyakit}. Sistem mengaktifkan pengusir ultrasonik dan lampu UV secara otomatis.",
                    'pest'      => $penyakit,
                    'reason'    => 'pest detected (Hispa)',
                ];
            }
            if (in_array($penyakit, ['Penyakit blas (jamur)', 'Pucuk mati'], true)) {
                return [
                    'action'    => 'recommend_selective_insecticide_spraying',
                    'actuators' => [],
                    'type'      => 'warning',
                    'icon'      => 'bx bx-spray-can',
                    'title'     => 'Rekomendasi: Penyemprotan Selektif',
                    'message'   => "Terdeteksi {$penyakit}. " . (self::PEST_ADVICE[$penyakit][1] ?? ''),
                    'pest'      => $penyakit,
                    'reason'    => 'pest detected',
                ];
            }

            return [
                'action'    => 'continue_monitoring',
                'actuators' => [],
                'type'      => 'warning',
                'icon'      => 'bx bx-bug',
                'title'     => 'Pengendalian Non-Kimia',
                'message'   => "Terdeteksi {$penyakit}. " . (self::PEST_ADVICE[$penyakit][1] ?? 'Tanyakan ke petugas pengamat hama/penyuluh.'),
                'pest'      => $penyakit,
                'reason'    => 'pest detected (non-chemical control)',
            ];
        }

        if ($vwc !== null && $vwc < self::THRESHOLDS['soil_moisture_irrigate']) {
            return [
                'action'    => 'irrigation_notification',
                'actuators' => ['irrigation_valve'],
                'type'      => 'danger',
                'icon'      => 'bx bx-water',
                'title'     => 'Notifikasi: Irigasi',
                'message'   => sprintf('Kadar air tanah %.1f%%', $vwc),
                'pest'      => null,
                'reason'    => 'soil VWC below AWD threshold',
            ];
        }

        return [
            'action'    => 'continue_monitoring',
            'actuators' => [],
            'type'      => 'success',
            'icon'      => 'bx bx-check-shield',
            'title'     => 'Pemantauan Berlanjut',
            'message'   => 'Tidak ada hama terdeteksi dan kondisi tanaman aman. Sistem melanjutkan pemantauan.',
            'pest'      => null,
            'reason'    => 'no pest, health OK',
        ];
    }
}
