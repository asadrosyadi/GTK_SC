# AgroGuard Mobile (Flutter)

Aplikasi Android/iOS untuk pengguna AgroGuard. Isi dan fiturnya sama dengan website Laravel, disusun ulang untuk layar HP. Semua data diambil dari REST API v1 ([`backend/laravel/API.md`](../backend/laravel/API.md)). Status sensor, kelas LSTM, kesimpulan, dan rekomendasi dihitung di server, jadi aplikasi tidak menyimpan ambang sendiri dan hasilnya selalu sama dengan web.

## Layar

Navigasi bawah menggantikan sidebar web. Halaman **Akun** dibuka dari foto profil di kanan atas, seperti menu profil di navbar web.

| Halaman web | Layar aplikasi | Isi | Endpoint |
|---|---|---|---|
| Dashboard (bagian atas) | **Beranda** | Pemantauan Lingkungan Waktu Nyata (kartu VPD, cuaca, Kondisi Tanah per petak, Status Sistem perangkat); Deteksi Hama CNN per Petak (foto, Skor Deteksi Hama, Skor Kesehatan Tanaman LSTM); Aksi Rekomendasi; Aksi Rekomendasi Keseluruhan | `/dashboard`, `/recommendations` |
| Dashboard: Prediksi + Tren Prediksi | **Prediksi** | Pilihan Gabungan / Petak 1 / Petak 2 dan 1, 3, 7, 14 hari atau isian 1–30; tabel 5 pembacaan terukur + ramalan per 4 jam (15 sensor, P. Sehat/Sedang/Tinggi, P. Kondisi, Status, Penyebab, Kesimpulan); grafik tren (bawaan: kesehatan tanaman LSTM) | `/forecast` |
| Riwayat → Sensor IoT (`/history`) | **Riwayat** | Tabel data terukur (terbaru dulu) dengan kolom yang sama, grafik Tren Riwayat, tombol Ekspor Excel | `/history`, `/history/export` |
| Riwayat → Deteksi CNN (`/cnn`) | **Deteksi CNN** | 5 kartu ringkasan, galeri foto (bisa diperbesar) dengan saran penanganan, Distribusi Deteksi per petak, tombol Ekspor Excel | `/detections/summary`, `/detections/export` |
| Dashboard: Asisten AI | **Asisten AI** | Kesimpulan Keadaan Sawah dan percakapan Tanya Asisten | `/ai/summary`, `/ai/chat` |
| Akun → Profil | **Akun** | Foto profil (galeri/kamera), nama, email, bahasa, ganti password, pilih perangkat (khusus SuperAdmin), alamat server, keluar | `/auth/me`, `/auth/profile`, `/auth/password`, `/devices`, `/auth/logout` |
| Halaman masuk | **Masuk** | Email, password, pengaturan server, tautan **Lupa password?** | `/auth/login`, `/auth/forgot-password` |

Perilaku yang sama dengan web:

- **Kesimpulan bisa diketuk.** Di tabel Prediksi/Riwayat dan kartu Deteksi CNN, kesimpulan membuka lembar "Aksi Rekomendasi · Model Random Forest" untuk petak yang bersangkutan.
- **Bawaan pilihan:** Gabungan Petak 1 & 2; Prediksi 3 hari, Riwayat 1 hari, Deteksi CNN 7 hari.
- **Muat ulang:** Beranda memuat ulang tiap 30 detik; semua layar bisa ditarik ke bawah untuk memuat ulang.
- **Nilai kosong** (sensor gagal) tampil `-` atau "Belum ada data", bukan 0.
- **Ramalan tidak tersedia** (HTTP 503) tampil sebagai pesan, bukan angka pengganti.
- **Ekspor** mengunduh file `.xlsx` yang sama dengan tombol Ekspor web, lalu membuka lembar bagikan (simpan/kirim).

## Menjalankan

```bash
cd mobile
flutter pub get
flutter run                       # perangkat/emulator terhubung
flutter build apk --release       # APK rilis: build/app/outputs/flutter-apk/app-release.apk
```

Saat masuk, buka **Pengaturan server** dan isi alamat Laravel tanpa `/api/v1`:

| Lingkungan | Alamat |
|---|---|
| Produksi (VPS) | `https://petaniasik.my.id` (bawaan) |
| Emulator Android → XAMPP di PC | `http://10.0.2.2/gemastik/backend/html` |
| HP fisik → XAMPP di PC (satu WiFi) | `http://IP-PC/gemastik/backend/html` |

- **HTTP biasa (`http://`):** diizinkan di semua build (`android:usesCleartextTraffic="true"` di `android/app/src/main/AndroidManifest.xml`) agar bisa diuji ke XAMPP lokal. Server produksi tetap memakai HTTPS. Hapus atribut itu sebelum rilis bila aplikasi hanya akan dipakai ke server HTTPS.
- **iOS:** `ios/Runner/Info.plist` berisi `NSAllowsLocalNetworking` (server lokal) serta izin galeri dan kamera untuk foto profil.
- **Token** Sanctum disimpan di `SharedPreferences` (berlaku `API_TOKEN_DAYS` hari, bawaan 30). Bila server menjawab 401 (token kedaluwarsa atau dicabut), aplikasi kembali ke halaman masuk.
- **SuperAdmin** dapat memilih perangkat di halaman Akun; aplikasi lalu menambahkan `?iot_id=` ke setiap permintaan dan memuat ulang semua layar.

## Struktur

```
lib/
  main.dart                    tema, lokal id, gerbang login (layar dimuat ulang saat token/perangkat berganti)
  core/api_client.dart         klien REST API v1, pesan galat, unduh file (timeout 45 s; ramalan/AI 180 s)
  core/session.dart            login/logout, lupa password, token, alamat server, perangkat terpilih
  core/format.dart             format angka/waktu, objek status dari server, nama kelas LSTM
  core/theme.dart              palet AgroGuard (hijau #2E7D32) + palet Sneat
  core/export.dart             unduh ekspor Excel lalu buka lembar bagikan
  widgets/agro.dart            pil status, header panel, pemilih petak/hari, teks AI, lembar Aksi Rekomendasi
  widgets/forecast_views.dart  tabel Prediksi/Riwayat dan grafik tren (fl_chart)
  widgets/sneat.dart           kartu, lencana, kotak galat
  screens/                     login, home_shell, dashboard, prediction, history, cnn, ai, account
test/app_test.dart             tes format, login, Beranda, semua tab, token kedaluwarsa
test/fixtures/*.json           respons asli API v1 (dashboard, recommendations, forecast, history, detections/summary)
```

Nama paket Dart masih `taniverse` (`pubspec.yaml`); nama yang tampil di HP adalah **AgroGuard**.

## Verifikasi

| Pemeriksaan | Hasil |
|---|---|
| `flutter analyze` | Tanpa temuan |
| `flutter test` | 8 tes lulus: format angka/status/kelas LSTM, login gagal → pesan server, login berhasil → Beranda, isi Beranda, semua tab (Prediksi, Riwayat, Deteksi CNN, Asisten AI) memuat data tanpa galat tata letak, token kedaluwarsa → halaman masuk. Data uji diambil dari respons API lokal yang asli; hanya ringkasan AI yang memakai teks contoh agar tes tidak memanggil Gemini. Ukuran layar uji 412×915 dp. |
| `flutter build apk --debug` | Berhasil (Flutter 3.47.6) |

Belum diuji: aplikasi di HP fisik terhadap server produksi, dan build iOS (butuh macOS dengan Xcode).
