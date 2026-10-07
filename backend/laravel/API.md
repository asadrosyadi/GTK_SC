# AgroGuard REST API v1

Base URL: `https://petaniasik.my.id/api/v1` (ganti dengan domain VPS). Semua respons berformat JSON (UTF-8). Waktu memakai ISO 8601 dengan zona WIB (`+07:00`).

Ada dua jenis klien:

| Klien | Autentikasi | Endpoint |
|---|---|---|
| Aplikasi / integrasi (mobile, web lain) | Token Bearer Sanctum dari `POST /auth/login` | semua `GET` di bawah |
| Perangkat ESP32 A | `iot_id` + `iot_token` milik akun | `POST /device/...` |

## Ringkasan endpoint

| Metode | Path | Keterangan |
|---|---|---|
| POST | `/auth/login` | Tukar email + password dengan token (maks. 10 percobaan/menit) |
| GET | `/auth/me` | Profil pemilik token |
| POST | `/auth/logout` | Cabut token yang sedang dipakai |
| POST/PATCH | `/auth/profile` | Ubah nama, email, bahasa (`locale`: `id`/`en`), foto (`image`, multipart → pakai **POST**) |
| PUT | `/auth/password` | Ganti password `{current_password, password, password_confirmation}`; maks. 10/menit |
| POST | `/auth/forgot-password` | Kirim tautan reset password ke email `{email}` (tanpa token); maks. 5/menit |
| GET | `/devices` | **SuperAdmin**: daftar perangkat semua akun + status online (lalu pakai `?iot_id=`) |
| GET | `/plots` | Daftar petak/titik dan konfigurasinya |
| GET | `/plots/{titik}` | Petak + pembacaan sensor, cuaca area, dan deteksi kamera terbaru |
| GET | `/dashboard` | **Layar utama**: cuaca, tanah per petak, perangkat, kamera, kondisi LSTM, semua dengan status siap tampil |
| GET | `/recommendations` | **Aksi rekomendasi** (Model Random Forest): ringkasan, daftar aksi terurut, aksi per petak |
| GET | `/forecast?titik=0&days=1..30` | **Tabel/grafik Prediksi**: 5 pembacaan terukur + ramalan per 4 jam, LSTM, status, penyebab, kesimpulan |
| GET | `/history?titik=0&days=1..30` | **Riwayat terukur** (halaman Riwayat Sensor), dengan status & LSTM |
| GET | `/history/export?titik=0&days=1..30` | File Excel riwayat (sheet Riwayat + Grafik); maks. 10/menit |
| GET | `/detections/summary?titik=0&days=1..30` | **Halaman Deteksi CNN**: ringkasan, distribusi per penyakit, daftar + saran |
| GET | `/detections/export?titik=0&days=1..30` | File Excel deteksi CNN (sheet Deteksi + Grafik); maks. 10/menit |
| GET | `/ai/summary` | **Asisten AI**: kesimpulan keadaan sawah |
| POST | `/ai/chat` | **Asisten AI**: tanya jawab petani; maks. 20/menit |
| GET | `/plots/{titik}/forecast?days=1..30` | Ramalan satu petak (format lama; pakai `/forecast` untuk aplikasi) |
| GET | `/snapshot` | Kondisi terkini semua titik (masukan model rekomendasi) |
| GET | `/sensor-readings` | Riwayat pembacaan sensor (berhalaman) |
| GET | `/sensor-readings/latest` | Pembacaan terbaru tiap petak |
| GET | `/sensor-readings/{id}` | Satu pembacaan |
| GET | `/detections` | Riwayat deteksi penyakit kamera (berhalaman) |
| GET | `/detections/latest` | Deteksi terbaru tiap kamera/petak |
| GET | `/detections/{id}` | Satu deteksi |
| GET | `/inferences` | Riwayat keluaran model fusi CNN–LSTM + keputusan aktuasi |
| GET | `/inferences/latest` | Keluaran terbaru tiap petak |
| GET | `/inferences/{id}` | Satu keluaran |
| POST | `/device/sensor-readings` | Perangkat: kirim pembacaan sensor (sama dengan `/api/kirim_sensor`) |
| POST | `/device/images` | Perangkat: kirim JPEG kamera (sama dengan `/api/kirim_gambar`) |

Endpoint yang membutuhkan token dibatasi 120 permintaan per menit per pengguna.

## Autentikasi

### POST /auth/login

```bash
curl -X POST https://petaniasik.my.id/api/v1/auth/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"petani@example.test","password":"secret123","device_name":"android-app"}'
```

**201**

```json
{
  "token": "1|TPknvU1aqw80PA27JssHIyRJ5zp1FtCZ0C0TWQhlb1ba4a15",
  "token_type": "Bearer",
  "expires_at": "2026-11-04T08:00:00+07:00",
  "user": { "id": 1, "name": "Petani", "email": "petani@example.test", "iot_id": "jTZids5M", "roles": [] }
}
```

- **Kegagalan:** password salah → 422; email belum diverifikasi → 403.
- **Masa berlaku token:** `API_TOKEN_DAYS` hari (bawaan 30; `0` = tidak kedaluwarsa).
- **Satu token per perangkat klien:** `device_name` hanya label token.

Kirim token di setiap permintaan berikutnya:

```
Authorization: Bearer 1|TPknvU1aqw80PA27JssHIyRJ5zp1FtCZ0C0TWQhlb1ba4a15
Accept: application/json
```

### GET /auth/me, POST /auth/logout

`/auth/me` dan `/auth/profile` mengembalikan `{id, name, email, email_verified, iot_id, locale, image_url, roles}`.
Mengubah email membuat `email_verified` = false (sama dengan web; login API butuh email terverifikasi).

**Bahasa pesan** (validasi/error): mengikuti `locale` akun; bila kosong mengikuti header `Accept-Language` (`id`/`en`), bawaan Indonesia.

`/auth/me` mengembalikan `{"data": {…user…}}`. `iot_token` (rahasia perangkat) tidak pernah dikirim. `/auth/logout` mencabut token yang dipakai dan menjawab 204.

### Cakupan data

Data selalu dibatasi pada perangkat (`iot_id`) milik pemilik token. Pengguna dengan peran **SuperAdmin** boleh menambahkan `?iot_id=XXXX` untuk perangkat lain; pengguna biasa mendapat 403.

## Petak

### GET /plots

```json
{
  "data": [
    { "titik": 1, "label": "Petak 1", "has_weather_station": true,  "tanggal_tanam": null, "umur_varietas": 115, "umur_bibit": 21 },
    { "titik": 2, "label": "Petak 2", "has_weather_station": false, "tanggal_tanam": null, "umur_varietas": 115, "umur_bibit": 21 }
  ]
}
```

Isi diambil dari `config/monitoring.php` dan `.env` (`TANGGAL_TANAM_TITIK_n`, dst.).

### GET /plots/{titik}

Petak 2 tidak punya stasiun cuaca, jadi `area_weather` diambil dari titik 1 (`source_titik`). Untuk petak yang punya stasiun cuaca, `area_weather` bernilai `null` dan cuacanya ada di `latest_reading.weather`.

```json
{
  "data": {
    "titik": 2, "label": "Petak 2", "has_weather_station": false,
    "tanggal_tanam": null, "umur_varietas": 115, "umur_bibit": 21,
    "latest_reading": {
      "id": 2, "titik": 2, "recorded_at": "2026-10-05T08:00:00+07:00",
      "weather": { "temperature": null, "humidity": null, "vpd": null, "windspeed": null,
                   "rainfall": null, "light_intensity": null, "pressure": null },
      "soil": { "ph": 5.9, "soil_moisture": 38, "ec": 780, "tds": 390, "soil_temp": 27.4,
                "nitrogen": 26, "phosphorus": null, "potassium": 118 },
      "device": { "battery_level": 77, "signal_strength": 52 }
    },
    "area_weather": {
      "temperature": 29.4, "humidity": 81.5, "vpd": 0.758, "windspeed": 1.8, "rainfall": 0.4,
      "light_intensity": 21450, "pressure": 100812,
      "recorded_at": "2026-10-05T08:00:00+07:00", "source_titik": 1
    },
    "latest_detection": {
      "id": 1, "titik": 2, "penyakit": "Bercak coklat", "probabilitas": 91.37,
      "image_url": "https://petaniasik.my.id/img/hama_padi/3f2a9c0d6b1e4f7a8c5d2e1b0a9f8e7d.jpg",
      "detected_at": "2026-10-05T08:00:00+07:00"
    }
  }
}
```

### GET /plots/{titik}/forecast

- **Parameter:** `days` 1–30 (`SENSOR_FORECAST_MAX_DAYS`), bawaan 3.
- **Sumber:** diteruskan ke layanan peramalan Python. Isinya `variables` (metode, MAE, MASE per sensor), `history`, dan `forecast` per blok 4 jam dengan kolom `status`. Metode dan sumber dijelaskan di README §10.
- **Layanan mati atau belum ada data:** 503 `{"message": "Ramalan sensor belum tersedia (…)"}`. Tidak ada nilai pengganti.

### GET /recommendations, GET /snapshot

- **`/recommendations`:** lihat bagian *Layar aplikasi* di bawah (format berubah: kini sama dengan panel web).
- **`/snapshot`:** kondisi terkini yang dipakai sebagai masukan model (cuaca titik 1 dengan hujan 24 jam, tanah tiap petak, kamera, data tanam).

## Layar aplikasi (Flutter)

Endpoint berikut mengembalikan data **siap tampil** dan sama dengan halaman web, sehingga aplikasi tidak perlu menghitung ambang atau menggabungkan data sendiri.

**Objek status** dipakai di banyak tempat: `{"code": "dry", "label": "Kering", "level": "warning"}`.
`level` ∈ `success` (hijau), `warning` (kuning/oranye), `danger` (merah), `secondary` (abu-abu); `label` ditampilkan apa adanya.
`titik`: `0` = **Gabungan** semua petak (bawaan), `1`, `2`, … = satu petak.

### GET /dashboard

```json
{"data": {
  "generated_at": "2026-10-07T12:52:42+07:00", "dummy": true,
  "weather": {"source_titik": 1, "recorded_at": "…",
    "items": [{"key": "temperature", "label": "Suhu udara", "unit": "°C", "value": 26.27,
               "status": {"code": "normal", "label": "Normal", "level": "success"}}, …]},
  "plots": [{"titik": 2, "label": "Petak 2", "fase": null, "hst": null,
    "soil": [{"key": "soil_moisture", "label": "Kelembapan tanah", "unit": "%", "value": 31.78,
              "status": {"code": "dry", "label": "Kering", "level": "warning"}}, …],
    "device": {"battery_level": 78, "signal_strength": 50, "last_seen_at": "…", "minutes_ago": 248, "online": false},
    "camera": {"penyakit": "Penyakit tungro (virus)", "skor_deteksi": 100, "healthy": false,
               "status": {"code": "action", "label": "Perlu tindakan", "level": "danger"},
               "advice": "Tidak bisa diobati: cabut rumpun …", "image_url": "…", "detected_at": "…"},
    "health": {"class": "Moderate Stress", "label": "Stres Sedang", "level": "warning",
               "p_healthy": 16.4, "p_moderate": 83.4, "p_high": 0.2}}]
}}
```

- Cuaca: suhu, kelembapan, VPD, angin, hujan 24 jam (mm), cahaya (lux; `Malam` bila 0), tekanan (hPa).
- Tanah: pH, kelembapan, EC, TDS, suhu tanah, N, P, K. Ambang & sumbernya: `app/Support/SensorStatus.php`.
- `device.online` = data terakhir ≤ 35 menit (node mengirim tiap 15 menit).
- `health` = kelas model LSTM saat ini (peluang %, jumlah ketiganya 100).

### GET /recommendations

```json
{"data": {
  "generated_at": "…", "model": {"source": "random_forest", "label": "Model Random Forest"},
  "overall": {"level": "danger", "headline": "2 aksi mendesak perlu segera dilakukan", "total": 5, "urgent": 2, "todo": 3},
  "items": [{"action": "eradikasi_tungro", "title": "Cabut tanaman yang kena tungro", "message": "…",
             "type": "danger", "level_label": "Mendesak", "icon": "bx bx-trash", "petak": ["Petak 2"],
             "probability": 1, "reasons_by_petak": {"Petak 2": ["Kamera mendeteksi …"]}}, …],
  "plots": [{"titik": 2, "label": "Petak 2", "fase": null, "hst": null, "level": "danger", "level_label": "Mendesak",
             "actions": [{"title": "…", "type": "danger", "level_label": "Mendesak", "reasons": ["…"]}, …]}]
}}
```

`items` = panel "Aksi Rekomendasi" (urut paling mendesak), `plots` = panel "Aksi Rekomendasi Keseluruhan". Bila layanan model mati, `model.label` = `Aturan cadangan`.

### GET /forecast?titik=0&days=3

Isi tabel & grafik **Prediksi**. `days` 1–30. Respons bisa memakan 10–20 detik (ramalan + LSTM), beri timeout ≥ 60 detik di aplikasi.

- `variables`: daftar kolom `{key, label, unit}` (15 sensor).
- `measured`: 5 pembacaan terukur terbaru; `forecast`: ramalan per blok 4 jam; `history`: riwayat 2 hari (untuk grafik).
- Tiap baris: nilai sensor (kunci sesuai `variables`), `health` (`label`, `p_healthy`, `p_moderate`, `p_high` atau `null`),
  `status` (`[{code, label, reason}]`, kosong = Normal), `conclusion` `{code, label, color}`
  (`bahaya` / `waspada` / `cek_lapangan` / `aman`).
- Gabungan (`titik=0`): angka = rata-rata petak, `status`/`reason` ditulis per petak ("Petak 2: …"), `conclusion` = kondisi terburuk.
- 503 bila layanan peramalan mati.

### GET /history?titik=0&days=1

Riwayat terukur 1–30 hari: `{titik, days, last_observation, variables, rows}`. `rows` berisi kolom yang sama dengan baris `/forecast` (termasuk `health`, `status`, `conclusion`), **terbaru dulu**. Rentang panjang pertama kali bisa lambat (LSTM dihitung per pembacaan, lalu di-cache 5 menit).

`GET /history/export` mengunduh file `.xlsx` yang sama dengan tombol Ekspor web.

### GET /detections/summary?titik=0&days=7

```json
{"data": {"titik": 0, "days": 7, "last_detection_at": "…",
  "summary": {"total": 4, "healthy": 0, "sick": 4, "top": "4 jenis seri, masing-masing 1×", "avg": 100},
  "distribution": [{"penyakit": "Penyakit tungro (virus)", "total": 1, "per_petak": {"2": 1}}, …],
  "items": [{"id": 16, "timestamp": "2026-10-07 08:44", "titik": 2, "penyakit": "…", "probabilitas": 100,
             "image_url": "…/img/hama_padi/….jpg", "healthy": false, "advice": "…"}, …]}}
```

### GET /ai/summary, POST /ai/chat

Asisten AI memakai penyedia di `.env` (saat ini Google Gemini). Kunci API tidak pernah dikirim ke aplikasi.

- `GET /ai/summary` → `{"data": {"provider": "Gemini", "enabled": true, "text": "…", "generated_at": "…"}}`. `text` memakai format markdown ringan (`**tebal**`, baris `- ` / `* ` = poin).
- `GET /ai/summary` di-cache 6 jam selama data sawah (sensor, kamera, LSTM, rekomendasi) tidak berubah; begitu data berubah, ringkasan dibuat ulang.
- `POST /ai/chat` body `{"message": "…", "history": [{"role": "user|assistant", "content": "…"}]}` → `{"data": {"provider": "Gemini", "answer": "…"}}`. `message` maks. 1000 karakter; `history` maks. 20 pesan (masing-masing maks. 4000 karakter), dan server memakai 10 pesan terakhir sebagai konteks. Simpan riwayat percakapan di aplikasi dan kirim ulang.
- Konteks yang dikirim ke penyedia AI: cuaca petak 1, tanah, kamera, kondisi LSTM, fase tumbuh per petak, dan daftar rekomendasi saat ini. Konteks tidak memuat `iot_token`, email, atau data akun lain.
- 503 = asisten belum aktif (kunci kosong), 502 = penyedia AI gagal (kuota/model), 429 = lebih dari 20 pertanyaan/menit.

## Riwayat sensor, deteksi, inferensi

Ketiga sumber memakai parameter yang sama:

| Parameter | Arti |
|---|---|
| `titik` | filter petak (404 bila tidak terdaftar) |
| `from`, `to` | rentang waktu `created_at`, mis. `2026-10-01` atau `2026-10-01 06:00`; `to` ≥ `from` |
| `per_page` | 1–100, bawaan 25 |
| `page` | halaman |

Urutan terbaru dulu. Respons berhalaman berisi `data`, `links` (`first`, `last`, `prev`, `next`), dan `meta` (`current_page`, `last_page`, `per_page`, `total`, …).

### Objek pembacaan sensor

```json
{
  "id": 1, "titik": 1, "recorded_at": "2026-10-05T08:00:00+07:00",
  "weather": { "temperature": 29.4, "humidity": 81.5, "vpd": 0.758, "windspeed": 1.8,
               "rainfall": 0.4, "light_intensity": 21450, "pressure": 100812 },
  "soil": { "ph": 6.4, "soil_moisture": 42.3, "ec": 910, "tds": 455, "soil_temp": 27.1,
            "nitrogen": 31, "phosphorus": 17, "potassium": 142 },
  "device": { "battery_level": 83, "signal_strength": 64 }
}
```

| Field | Satuan |
|---|---|
| `temperature`, `soil_temp` | °C |
| `humidity` | % |
| `vpd` | kPa, dihitung FAO-56 dari suhu dan RH |
| `windspeed` | m/s, rata-rata interval kirim |
| `rainfall` | mm per interval kirim (15 menit); jumlah 24 jam ada di `/snapshot` |
| `light_intensity` | lux |
| `pressure` | Pa |
| `soil_moisture` | % kadar air volumetrik (VWC) |
| `ec` | µS/cm |
| `tds` | ppm |
| `nitrogen`, `phosphorus`, `potassium` | mg/kg (sensor RS485; belum setara uji laboratorium) |
| `battery_level`, `signal_strength` | % |

**`null` berarti sensor gagal atau tidak terpasang**, bukan nol. Untuk titik 2, semua field `weather` selalu `null`.

### Objek deteksi

`{id, titik, penyakit, probabilitas (%), healthy, status, advice, image_url, detected_at, is_dummy}` — `status` & `advice` sama dengan kartu halaman Deteksi CNN.

### Objek inferensi

`{id, titik, pest{label, class, confidence}, health{status, class, confidence}, vpd_kpa, microclimate, fusion_weights{w1, w2}, actuation{action, actuators, transmitted_via}, created_at}`

## Endpoint perangkat

### POST /device/sensor-readings

Body JSON `{iot_id, iot_token, titik, temperature, humidity, windspeed, rainfall, light_intensity, pressure, ph, soil_moisture, ec, tds, soil_temp, Nitrogen_Level, Phosphorus_Level, Potassium_Level, battery_level, signal_strength}`. Semua field sensor boleh `null`, dan field cuaca untuk titik 2 diabaikan.

- **201:** tersimpan.
- **401:** `iot_id` atau `iot_token` salah.
- **422:** validasi gagal.

### POST /device/images

Body: JPEG mentah (≤ 2 MB). Header: `X-IOT-ID`, `X-IOT-TOKEN`, `X-TITIK`, `Content-Type: image/jpeg`. Batas 30 permintaan per menit.

- **201:** deteksi tersimpan.
- **200:** gambar diterima, tetapi tidak ada yang disimpan (di luar jam deteksi).
- **401 / 422:** ditolak.
- **502 / 503:** layanan deteksi gagal atau mati.

## Endpoint lama (di luar `/api/v1`)

Tetap aktif untuk firmware dan layanan Python yang sudah berjalan:

| Metode | Path | Pemakai | Autentikasi |
|---|---|---|---|
| POST | `/api/kirim_sensor` | ESP32 A (petak 1 dan 2) | `iot_id` + `iot_token` di body |
| POST | `/api/kirim_gambar` | ESP32 A (kamera A dan B) | header `X-IOT-ID` + `X-IOT-TOKEN`; maks. 30/menit |
| POST | `/api/inference` | `hybrid_cnn_lstm/algorithm1.py` | `iot_id` + `iot_token` di body |
| GET | `/api/inference/{iot_id}?titik=N` | ESP32 A (perintah aktuator) | **tanpa autentikasi** |
| GET | `/api/sensor?iot_id=…` | layanan Python ramalan (`HISTORY_URL`) | **tanpa autentikasi** |

Dua endpoint `GET` terakhir bisa dibaca siapa pun yang mengetahui `iot_id`. Menambah autentikasi di sana juga mengharuskan perubahan firmware ESP32 A dan layanan Python.

Dihapus pada Oktober 2026 karena tidak dipakai: `GET /api/bacajson/{iot_id}` (data LiDAR; pengisinya sudah tidak ada) dan `GET /api/user` (route bawaan Laravel).

## Format error

| Kode | Contoh body |
|---|---|
| 401 | `{"message": "Unauthenticated."}` |
| 403 | `{"message": "Tidak berhak mengakses perangkat lain"}` |
| 404 | `{"message": "Titik tidak dikenal"}` |
| 422 | `{"message": "per page tidak boleh lebih dari 100.", "errors": {"per_page": ["per page tidak boleh lebih dari 100."]}}` |
| 429 | `{"message": "Too Many Attempts."}` |
| 502 | `{"message": "Asisten AI gagal menjawab: batas permintaan Gemini habis, …"}` |
| 503 | `{"message": "Ramalan belum tersedia (…)"}` atau `{"message": "Asisten AI belum aktif: …"}` |

Contoh respons di dokumen ini diambil dari keluaran API sebenarnya (data uji), dengan host diganti domain produksi.
