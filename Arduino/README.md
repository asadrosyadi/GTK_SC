# Firmware ESP32 A dan ESP32 B

```
 Kamera IP B ──WiFi (hotspot B)──► ESP32 B ── sensor tanah RS485 8-in-1 (petak 2)
                                     │
                                     │ ESP-NOW (radio WiFi langsung, terenkripsi)
                                     ▼
 Kamera IP A ──WiFi/LAN router────► ESP32 A ── DHT22, anemometer, tipping bucket,
                                     │          BH1750 (GY-302), BMP280,
                                     │          sensor tanah RS485 8-in-1 (petak 1)
                                     │ WiFi router → internet (HTTPS)
                                     ▼
                    VPS: Laravel (nginx) ──► Flask deteksi CNN (127.0.0.1:7000)
                                         ──► Flask ramalan/rekomendasi (127.0.0.1:5000)
```

| Sketch | Peran |
|---|---|
| [`esp32_a_gateway/`](esp32_a_gateway/) | Stasiun cuaca + tanah petak 1 + kamera A. Menerima data dan gambar ESP32 B lewat ESP-NOW, lalu mengirim semuanya ke VPS. Meneruskan perintah aktuator petak 2 ke B. |
| [`esp32_b_lapangan/`](esp32_b_lapangan/) | Sensor tanah petak 2 + kamera B, tanpa internet. Mengirim ke A lewat ESP-NOW. |

Semua pengaturan ada di `konfigurasi.h` masing-masing sketch. `taniverse_espnow.h` (protokol) dan `taniverse_common.h` (sensor 8-in-1, snapshot kamera, baterai) **harus identik** di kedua folder.

## Kamera RTSP: kenapa ESP32 mengambil snapshot

ESP32 tidak bisa men-decode video RTSP (H.264/H.265): RAM-nya sekitar 520 KB dan tidak punya decoder video. Kamera IP yang mendukung RTSP/ONVIF hampir selalu juga menyediakan **URL snapshot JPEG lewat HTTP**. URL itulah yang diambil ESP32 setiap `INTERVAL_FOTO_MS` (bawaan 10 menit, jam 06–18 WIB), dengan autentikasi Basic atau Digest otomatis.

Contoh URL snapshot (cek manual kamera Anda):

| Merek | URL snapshot |
|---|---|
| Hikvision | `http://IP/ISAPI/Streaming/channels/101/picture` |
| Dahua / IMOU (lokal) | `http://IP/cgi-bin/snapshot.cgi?channel=1` |
| Reolink | `http://IP/cgi-bin/api.cgi?cmd=Snap&channel=0&rs=a1&user=U&password=P` |
| ONVIF lain | `GetSnapshotUri` (lihat dengan ONVIF Device Manager) |

Kamera yang hanya punya RTSP tanpa snapshot HTTP tidak bisa dipakai dengan ESP32. Untuk kamera seperti itu, gunakan ESP32-CAM atau kirim RTSP langsung ke server (`CAMERAS=rtsp://...` di layanan deteksi).

## Papan, Arduino IDE, dan library

- **Papan:** ESP32 dengan PSRAM, misalnya ESP32-WROVER-E atau **WROVER-IE** (konektor antena eksternal, disarankan bila A–B berjauhan). Buffer gambar 200 KB disimpan di PSRAM.
- **Board package:** esp32 by Espressif **3.x** (diuji kompilasi dengan 3.3.11).
- **Tools:** Board "ESP32 Wrover Module", PSRAM "Enabled", Partition Scheme **"Huge APP (3MB No OTA/1MB SPIFFS)"**. Dengan partisi bawaan ESP32 A memakai 94 % flash dan tidak menyisakan ruang.
- **Library ESP32 A:** WiFiManager (tzapu) 2.0.17, ArduinoJson 7.x, DHT sensor library (Adafruit) 1.4.x, BH1750 (claws) 1.3.0, Adafruit BMP280 3.x, ModbusMaster 2.0.1. PubSubClient hanya bila `PAKAI_MQTT 1`.
- **Library ESP32 B:** ModbusMaster.

Hasil kompilasi (`arduino-cli`, esp32 3.3.11, Huge APP): ESP32 A 1,23 MB, ESP32 B 0,97 MB, tanpa error atau warning. Varian `PAKAI_MQTT 1`, `PETA_JXCT`, `DUMP_REGISTER 1`, `KANAL_TETAP`, tanpa enkripsi, dan tanpa aktuator juga terkompilasi.

## Wiring ESP32 A

| Komponen | Pin komponen | ESP32 A |
|---|---|---|
| DHT22 | DATA (+ pull-up 10 kΩ ke 3V3 bila modul tanpa resistor) | GPIO15 |
| GY-302 BH1750 | SDA / SCL, ADDR ke GND (0x23) | GPIO21 / GPIO22 |
| BMP280 | SDA / SCL (alamat 0x76 atau 0x77 dideteksi otomatis) | GPIO21 / GPIO22 |
| Anemometer | keluaran pulsa / GND | GPIO4 (INPUT_PULLUP) / GND |
| Tipping bucket | saklar reed / GND | GPIO18 (INPUT_PULLUP) / GND |
| Modul RS485 | RO / DI / DE+RE | GPIO32 / GPIO33 / GPIO25 |
| Sensor tanah 8-in-1 | A+ / B− ke modul RS485; catu sesuai datasheet (umumnya 5–24 V DC), GND bersama | — |
| Pembagi tegangan baterai | 82 kΩ (atas) / 10 kΩ (bawah) | GPIO34 (ADC1) |
| Relay aktif-LOW | lampu UV / pengusir ultrasonik / katup irigasi | GPIO19 / GPIO23 / GPIO27 |
| Kamera IP A | WiFi atau LAN ke router, IP statis | — |

## Wiring ESP32 B

| Komponen | Pin komponen | ESP32 B |
|---|---|---|
| Modul RS485 | RO / DI / DE+RE | GPIO32 / GPIO33 / GPIO25 |
| Sensor tanah 8-in-1 | A+ / B− ke modul RS485, catu sesuai datasheet, GND bersama | — |
| Pembagi tegangan baterai | 82 kΩ / 10 kΩ | GPIO34 |
| Relay aktif-LOW (petak 2) | UV / ultrasonik / irigasi | GPIO19 / GPIO23 / GPIO27 |
| Kamera IP B | tersambung ke hotspot `TaniVerse-CamB`, IP statis 192.168.4.10 | — |

Catatan wiring:
- **RS485 dipindah ke GPIO32/33** karena ESP32-WROVER memakai GPIO16/17 untuk PSRAM. Firmware lama memakai 16/17.
- **Level tegangan:** pakai modul RS485 3,3 V (MAX3485/SP3485), atau beri penurun level pada RO bila modul MAX485 diberi 5 V. Pin ESP32 tidak toleran 5 V.
- **Pulsa anemometer** 5–12 V harus diturunkan (optocoupler atau pembagi tegangan) sebelum masuk GPIO4.

## Langkah pemasangan

1. **Flash ESP32 B, lalu ESP32 A**, dengan konfigurasi bawaan. Di Serial Monitor (115200) catat:
   - ESP32 B: `>>> MAC ESP-NOW node B ...`
   - ESP32 A: `>>> MAC ESP-NOW node A ...`
2. **Tukar MAC:** isi `MAC_NODE_B` di `esp32_a_gateway/konfigurasi.h` dan `MAC_NODE_A` di `esp32_b_lapangan/konfigurasi.h`.
3. **Kunci ESP-NOW:** ganti `ESPNOW_PMK` dan `ESPNOW_LMK` (tepat 16 karakter) dengan nilai yang **sama** di A dan B.
4. **Server:** isi `URL_SERVER`, `IOT_ID`, dan `IOT_TOKEN` di konfigurasi A, sesuai kolom `users.iot_id` dan `users.iot_token` di Laravel. Opsional: tempel root CA di `ROOT_CA` agar identitas server HTTPS diverifikasi.
5. **Flash ulang A dan B.** Saat pertama menyala, A membuka portal WiFi `TaniVerse-A-Setup` (password `taniverse123`). Sambungkan HP, lalu pilih router dan isi password-nya.
6. **Kanal router:** ESP-NOW harus satu kanal dengan router tempat A tersambung. B mencari kanalnya otomatis (1–13) dan menyimpannya. Lebih stabil bila router disetel ke kanal tetap (mis. 6) dan `KANAL_TETAP 6` diisi di konfigurasi B.
7. **Kamera A:** sambungkan ke router, beri IP statis, setel resolusi snapshot sekitar 640×480, lalu uji URL snapshot di browser. Isi `URL_KAMERA_A`, `USER_KAMERA_A`, dan `PASS_KAMERA_A`.
8. **Kamera B:** selagi kamera masih tersambung ke router, atur dulu IP, resolusi, dan akun lokalnya. Setelah itu pindahkan WiFi-nya ke hotspot `TaniVerse-CamB` (password di `AP_KAMERA_PASS`) dengan IP statis 192.168.4.10, gateway 192.168.4.1. Aplikasi cloud kamera tidak bisa menjangkaunya lagi, tetapi snapshot lokal tetap berjalan.
9. **Sensor 8-in-1:** peta register berbeda antar-merek. Set `DUMP_REGISTER 1` untuk mencetak register 0x00–0x24, cocokkan dengan datasheet, lalu pilih `PETA_REGISTER` atau ubah tabel `REG_*` di `taniverse_common.h`. Nilai di luar rentang fisik dikirim `null`.

## Data yang dikirim

| Endpoint | Pengirim | Isi |
|---|---|---|
| `POST /api/kirim_sensor` (titik 1) | A, tiap 15 menit | suhu, RH, angin (rata-rata jendela 10 detik selama interval), hujan (mm sejak kiriman berhasil terakhir), cahaya, tekanan (Pa), pH, kadar air, EC, TDS, suhu tanah, N, P, K, baterai, sinyal WiFi |
| `POST /api/kirim_sensor` (titik 2) | A, saat paket B tiba | pH, kadar air, EC, TDS, suhu tanah, N, P, K, baterai B, sinyal ESP-NOW B |
| `POST /api/kirim_gambar` | A | JPEG mentah, header `X-IOT-ID`, `X-IOT-TOKEN`, `X-TITIK` (1 = kamera A, 2 = kamera B) |
| `GET /api/inference/{iot_id}?titik=N` | A, tiap 15 detik | keputusan aktuator per petak |

- **Sensor gagal:** dikirim `null`, bukan 0.
- **VPD:** dihitung di server dari suhu dan RH (FAO-56). A hanya mencetaknya di Serial.
- **TDS:** dibaca dari register sensor bila tersedia. Bila sensor tidak punya register TDS, TDS = 0,64 × EC (FAO-29) dan ditandai "(dari EC)" di Serial. Set `TDS_DARI_EC 0` untuk mengirim `null`.
- **Internet putus:** data sensor terakhir dicoba ulang tiap 60 detik. Curah hujan tidak hilang karena dihitung sejak kiriman yang berhasil. Waktu data dicatat server saat diterima.

## Protokol ESP-NOW

Payload ESP-NOW v1 maksimal 250 byte. Gambar dipecah menjadi chunk 239 byte, disusun ulang di A, dan dicek CRC32. Setiap paket dari B menunggu ACK lapisan MAC dari A. A membalas `START` dan `END` dengan status:

| Status | Arti |
|---|---|
| `OK` | gambar diterima utuh |
| `SIBUK` | A masih mengunggah gambar sebelumnya; B mencoba lagi 30 detik kemudian (maks. 3 kali) |
| `KURANG` / `CRC` | ada chunk hilang atau rusak; B mengirim ulang sekali |
| `MEMORI` | gambar lebih besar dari buffer; kecilkan resolusi snapshot |

Setiap balasan A membawa waktu NTP dan perintah aktuator petak 2, sehingga B tahu jam foto meski tanpa internet.

**Keselamatan aktuator:** A mematikan perintah model bila server tidak menjawab selama 30 menit. B mematikan relay bila tidak ada kabar dari A selama 30 menit. Katup irigasi tidak akan tertahan ON.

## Pemecahan masalah

| Gejala di Serial | Penyebab dan solusi |
|---|---|
| A: `paket dari MAC tak dikenal` | `MAC_NODE_B` salah; salin MAC yang tercetak di B. |
| B: `ESP32 A tidak ditemukan di kanal 1-13` | MAC/kunci tidak sama, A belum tersambung router, atau jarak terlalu jauh (gunakan WROVER-IE + antena). |
| `[kamera] snapshot gagal: 401` | User/password kamera salah. |
| `[kamera] respons bukan JPEG` | URL bukan snapshot; buka di browser untuk memastikan. |
| `[kamera] gambar > 204800 byte` | Kecilkan resolusi atau kualitas snapshot. |
| `kirim_gambar ... "reason": "di luar jam deteksi"` | Zona waktu VPS bukan WIB; set `TZ=Asia/Jakarta` di `/etc/taniverse/taniverse.env`. |
| Semua nilai tanah `nan` | Baud/alamat/peta register salah, atau DE/RE dan A/B tertukar; coba `DUMP_REGISTER 1`. |
