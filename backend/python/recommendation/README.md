# Sistem Rekomendasi Offline Multi-Sensor (Random Forest)

Menggabungkan seluruh sumber data menjadi aksi untuk petani, **dijalankan lokal di PC server** (tanpa API AI eksternal).

| Sumber | Titik 1 (Petak 1) | Titik 2 (Petak 2) |
|---|---|---|
| Cuaca: suhu, RH, VPD, angin, hujan, cahaya | ✔ (sensor) | memakai data titik 1 (satu area) |
| Tanah: pH, kelembapan, EC, TDS, suhu tanah, N, P, K | ✔ | ✔ |
| CNN deteksi hama/penyakit (kamera) | kamera 1 | kamera 2 |
| LSTM prediksi kesehatan tanaman | ✔ | ✔ |

## Alur

```
Laravel (MultiTitikService::snapshot)  --POST /recommendation-->  rnn/run.py (Flask, port 5000)
                                                                     ├─ LSTM per petak (kesehatan)
                                                                     └─ RecommendationEngine (Random Forest)
                                       <-- aksi per petak + ringkasan + aktuasi ESP32 --
```
Jika server Python tidak bisa dihubungi, Laravel memakai aturan PHP (`RecommendationService`) sebagai cadangan (label model di layar: **Aturan cadangan**; bila model aktif: **Model Random Forest**).

## Model

- 44 fitur per petak (`features.py`): 6 cuaca, 8 tanah, 10 kelas CNN × confidence, 8 kelompok hama/penyakit, 3 probabilitas LSTM, 7 fase tumbuh (dari tanggal tanam), 2 flag ketersediaan.
- 19 aksi (multi-label, `knowledge_base.py`) yang **ambang dan isinya dirujuk ke 20 artikel jurnal Scopus Q1/Q2 (DOI & tautan Scopus) serta sumber data pertanian kredibel (IRRI, Balitbangtan PUTS/BWD, BMKG, Balai Penelitian Tanah, UC IPM, CABI, FAO)** — lihat [SUMBER_REFERENSI.md](SUMBER_REFERENSI.md). Setiap rekomendasi membawa alasan (`explain`) dan daftar sumber (`sources_for`). Dashboard web dan aplikasi mobile menampilkan alasannya dengan bahasa petani; daftar sumber tidak ditampilkan di layar, tetapi tetap ada di keluaran model dan di dokumen ini.
- Satu Random Forest per aksi (100 pohon). Ambang 0,5.
- Data latih **sintetis**: kondisi lapangan acak → label dari basis pengetahuan agronomi; fitur yang dilihat model diberi derau sensor, sensor hilang, kamera/LSTM tidak tersedia.

Evaluasi (data uji 8.000 sampel) ada di `models/recommendation_rf_report.md`. Ringkasnya F1-micro 0,968 / F1-macro 0,958, setara aturan pakar yang diterapkan langsung pada sensor berderau — wajar, karena label berasal dari aturan tersebut. Model ini siap **dilatih ulang dengan data lapangan berlabel** (aksi yang benar-benar diambil petani/penyuluh) untuk melampaui aturan.

## Perintah

```bat
.venv-gpu\Scripts\python.exe -m recommendation.train           :: latih (buat dataset jika belum ada)
.venv-gpu\Scripts\python.exe -m recommendation.train --regen   :: buat ulang dataset lalu latih
.venv-gpu\Scripts\python.exe -m recommendation.test_expert_scenarios  :: uji 15 skenario sesuai pedoman
```

Fase tumbuh diatur di `backend/laravel/.env`: `TANGGAL_TANAM_TITIK_1`, `TANGGAL_TANAM_TITIK_2` (YYYY-MM-DD)
dan `UMUR_VARIETAS_TITIK_n` (hari dari semai). Tanpa tanggal tanam, aturan khusus fase (genangan wajib
saat berbunga, larangan urea setelah berbunga, tidak mengairi menjelang panen, pupuk P/K hanya sebagai
pupuk dasar) **tidak dapat diterapkan** — sebaiknya tanggal tanam selalu diisi.
Endpoint: `POST /recommendation` (snapshot), `GET /recommendation/model` (metadata & metrik).
