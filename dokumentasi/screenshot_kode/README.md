# Screenshot Kode Inti AgroGuard

| No | Gambar | Fungsi |
|---|---|---|
| 1 | `01_esp32_gateway_baca_kirim_sensor.png` | Membaca sensor cuaca dan tanah di sawah, lalu mengirim datanya ke server. |
| 2 | `02_laravel_terima_data_sensor.png` | Menerima data sensor dari alat dan menyimpannya ke database. |
| 3 | `03_laravel_terima_gambar_kamera.png` | Menerima foto dari kamera sawah dan meneruskannya ke AI pendeteksi hama. |
| 4 | `04_cnn_klasifikasi_daun.png` | Mengenali penyakit atau hama padi dari foto daun. |
| 5 | `05_cnn_endpoint_detect.png` | Memproses foto yang masuk dan menyimpan hasil deteksinya. |
| 6 | `06_training_efficientnetb0.png` | Melatih model AI agar bisa mengenali 10 jenis kondisi daun padi. |
| 7 | `07_lstm_prakiraan_kesehatan.png` | Memperkirakan kesehatan tanaman beberapa hari ke depan dari data sensor. |
| 8 | `08_fusi_hybrid_cnn_lstm.png` | Menggabungkan hasil foto dan data sensor agar keputusan lebih akurat. |
| 9 | `09_keputusan_aktuasi.png` | Menentukan alat mana yang dinyalakan: lampu UV, pengusir hama, atau irigasi. |
| 10 | `10_rekomendasi_random_forest.png` | Memberi saran tindakan untuk petani di tiap petak. |
