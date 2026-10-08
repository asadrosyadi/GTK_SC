# Sumber Rujukan Basis Pengetahuan Rekomendasi

Semua ambang batas dan aksi di `knowledge_base.py` (Python, model Random Forest),
`RecommendationService.php` (Laravel, aturan cadangan), dan `app/Support/SensorStatus.php` (label status
kartu sensor di web, API v1, dan aplikasi mobile) mengikuti dokumen ini.

Isi klaim dicocokkan dengan teks lembaga sumber.

## B. Sumber data pertanian kredibel

| Kode | Lembaga / sumber | Dipakai untuk |
|---|---|---|
| [IRRI_AWD](http://www.knowledgebank.irri.org/training/fact-sheets/water-management/saving-water-alternate-wetting-drying-awd) | IRRI Rice Knowledge Bank | Irigasi saat muka air turun 15 cm, genangi kembali ±5 cm; tergenang seminggu sebelum s.d. sesudah berbunga |
| [IRRI_WATER](http://www.knowledgebank.irri.org/step-by-step-production/growth/water-management) | IRRI Rice Knowledge Bank | Sawah tergenang hingga 7–10 hari sebelum panen; air 5 cm dari bunting s.d. akhir berbunga |
| [IRRI_GROWTH](http://www.knowledgebank.irri.org/ericeproduction/0.2._Growth_stages_of_the_rice_plant.htm) | IRRI Rice Knowledge Bank | Fase reproduktif ±35 hari, pemasakan ±30 hari |
| [IRRI_BLAST](http://www.knowledgebank.irri.org/training/fact-sheets/pest-management/diseases/item/blast-leaf-collar) | IRRI Rice Knowledge Bank | Blas di tanah lembap rendah; *"Flood the field as often as possible"*; N dibagi; triazol/strobilurin |
| [UCIPM_BLAST](https://ipm.ucanr.edu/agriculture/rice/rice-blast/) | University of California IPM | Optimum 25–28 °C (77–82 °F), malam > 20 °C, RH > 90 %; genangan kontinu menekan blas; hindari drainase; N tinggi memperparah |
| [IRRI_BLB](http://www.knowledgebank.irri.org/decision-tools/rice-doctor/rice-doctor-fact-sheets/item/bacterial-blight) | IRRI Rice Knowledge Bank | Hawar bakteri 25–34 °C, RH > 70 %, angin kencang + hujan lebat; N berimbang, drainase, sanitasi |
| [IRRI_TUNGRO](http://www.knowledgebank.irri.org/training/fact-sheets/pest-management/diseases/item/tungro) | IRRI Rice Knowledge Bank | Insektisida wereng *"often not effective"*; varietas tahan, tanam serempak |
| [IRRI_HISPA](http://www.knowledgebank.irri.org/training/fact-sheets/pest-management/insects/item/rice-hispa) | IRRI Rice Knowledge Bank | Potong ujung daun berisi telur (menekan larva 75–92 %), hindari N berlebih |
| [IRRI_STEMBORER](http://www.knowledgebank.irri.org/training/fact-sheets/pest-management/insects/item/stem-borer) | IRRI Rice Knowledge Bank | Naikkan genangan, kumpulkan telur, N terbagi |
| [IRRI_BROWNSPOT](http://www.knowledgebank.irri.org/training/fact-sheets/pest-management/diseases/item/brown-spot) | IRRI Rice Knowledge Bank | Perbaikan kesuburan (K, silika), perlakuan benih |
| [IRRI_ZN](http://www.knowledgebank.irri.org/training/fact-sheets/nutrient-management/deficiencies-and-toxicities-fact-sheet/item/zinc-deficiency) | IRRI Rice Knowledge Bank | Kelarutan Zn turun dua orde per kenaikan 1 unit pH |
| [CABI_DOWNY](https://www.cabidigitallibrary.org/doi/abs/10.1079/cabicompendium.49243) | CABI Compendium | Embun bulu: tanah jenuh ≥ 24 jam pada tanaman muda; perlakuan benih metalaksil |
| [FAO29](https://www.fao.org/4/t0234e/t0234e00.htm) | FAO Irrigation & Drainage Paper 29 | Konversi TDS ≈ 640 × EC (dS/m) |
| [DOBERMANN_FAIRHURST2000](http://books.irri.org/9810427425_content.pdf) | IRRI/PPI (buku) | P-Olsen < 6 rendah, 6–10 sedang; K-dd < 0,2 cmol/kg rendah |
| [PUTS](https://pustaka.bppsdmp.pertanian.go.id/info-literasi/info-teknologi-perangkat-uji-tanah-sawah-puts-untuk-efisiensi-pupuk) | Balitbangtan | Dosis SP-36 per status P; KCl 100 / 50 kg/ha (jerami dikembalikan) |
| [BWD](https://bbpadi.litbang.pertanian.go.id/index.php/info-berita/info-teknologi/pemupukan-pada-tanaman-padi) | BB Padi Balitbangtan | Skala kritis BWD 4; urea 50–75 kg/ha |
| [BALITTANAH](https://id.scribd.com/doc/313325075/juknis-tanah) | Balai Penelitian Tanah (2009) | Kelas pH: masam 4,5–5,5; netral 6,6–7,5 |
| [BMKG_HUJAN](https://ntt.bmkg.go.id/uploads/buletin/2020/06/buletin%20_Juni_2020.pdf) | BMKG (Buletin) | Kategori hujan harian: sedang 20–50, lebat 50–100, sangat lebat > 100 mm/hari |
| [BMKG_SIKLON](https://www.bmkg.go.id/siaran-pers/bmkg-siklon-tropis-teratai-terbentuk-di-wilayah-indonesia) | BMKG (siaran pers, 2021) | Siklon Tropis Teratai: tekanan udara di pusatnya mencapai 1000 hPa, disertai potensi hujan sedang–lebat dan angin kencang → status tekanan **Rendah** (≤ 1000 hPa permukaan laut) |
| [HITAKA1969](https://www.jircas.go.jp/sites/default/files/publication/jarq/04-3-001-006_0.pdf) | JIRCAS (jurnal JARQ) | Batang padi mulai patah ±7 m/s; membungkuk 45° pada 7–8 m/s saat masak |

## C. Ambang batas → sumber

| Parameter | Nilai di sistem | Sumber | Status |
|---|---|---|---|
| Irigasi (AWD aman) | kadar air volumetrik (VWC) < 35 % | IRRI_AWD: muka air turun 15 cm | **Bersumber** |
| Genangan saat berbunga | VWC di bawah kondisi tergenang (bawaan 45 %) | IRRI_WATER | Prinsip bersumber; nilai jenuh = **kalibrasi** (bacaan sensor saat sawah tergenang) |
| Tidak diairi menjelang panen | ≥ 10 hari sebelum panen | IRRI_WATER | Bersumber |
| Fase tumbuh | reproduktif 35 h, pemasakan 30 h, berbunga ±7 h | IRRI_GROWTH | Bersumber |
| Stres panas | ≥ 35 °C; ≥ 33 °C saat berbunga | — | Belum ada sumber lembaga |
| Cuaca pendukung blas | RH ≥ 90 % dan 20–28 °C | UCIPM_BLAST | Bersumber |
| Cuaca pendukung hawar bakteri | 25–34 °C, RH > 70 %, hujan ≥ 50 mm/24 jam | IRRI_BLB (suhu/RH, hujan lebat); BMKG_HUJAN (hujan lebat ≥ 50 mm/hari) | Bersumber |
| Salinitas | EC ≥ 3000 µS/cm atau TDS ≥ 1920 ppm | FAO29 | Bersumber (EC sensor ≠ ECe lab) |
| pH masam / basa | ≤ 5,5 / > 7,5 | BALITTANAH; IRRI_ZN | Bersumber |
| P tersedia | < 6 rendah, 6–10 sedang mg/kg | DOBERMANN_FAIRHURST2000, PUTS | Bersumber (asumsi sensor ≈ P-Olsen; kalibrasi lab) |
| K dapat ditukar | < 78 mg/kg (0,2 cmol/kg) | DOBERMANN_FAIRHURST2000, PUTS | Bersumber (asumsi sensor ≈ K-dd; kalibrasi lab) |
| Nitrogen | **tanpa ambang sensor N**; urea pada fase vegetatif–reproduktif dengan BWD skala 4; tunda urea bila ada hama/penyakit yang diperparah N | BWD; UCIPM_BLAST, IRRI_BLB | Bersumber |
| Rebah | angin ≥ 8 m/s fase bunting–masak | HITAKA1969 (JIRCAS) | Data lembaga kredibel |
| VPD | ≥ 1,5 kPa ditandai "tinggi" | — | **Informasi saja** — tidak memicu aksi |

### C.2 Ambang status tampilan (tidak memicu aksi)

Label status pada kartu cuaca/tanah (web, API v1 `/dashboard`, aplikasi mobile) dihitung di server oleh
`backend/laravel/app/Support/SensorStatus.php`. Ambang yang sama dengan basis pengetahuan (suhu, RH, VPD,
angin, hujan, pH, kadar air, EC, TDS, P, K) mengikuti tabel C. Ambang berikut **hanya untuk status tampilan**:
tercatat di `knowledge_base.T` dan `THRESHOLD_SOURCES`, tetapi tidak dipakai label/aksi Random Forest.

| Parameter | Status di sistem | Sumber | Catatan |
|---|---|---|---|
| Intensitas cahaya | Rendah < 32.400 lux; Malam = 0 lux | — | Pagi/sore wajar di bawah batas; paling bermakna bila muncul siang hari. Tidak ada status "Tinggi" (tidak ada ambang bersumber). |
| Tekanan udara | Rendah ≤ 1000 hPa (dikoreksi ke permukaan laut dengan `ELEVASI_STASIUN_M`) | BMKG_SIKLON | Tekanan tidak berpengaruh langsung; menandai potensi cuaca buruk. |
| Suhu tanah | Rendah ≤ 17,5 °C; Tinggi > 34 °C | — | — |
| Nitrogen tersedia | Rendah < 80; Sedang 80–200; Tinggi > 200 mg/kg | — | Dosis urea tetap memakai BWD (tabel C); angka sensor N tidak memicu aksi. |
| Curah hujan 24 jam | Sedang 20–50; Tinggi ≥ 50 mm | BMKG_HUJAN | Sama dengan kategori hujan harian BMKG. |

## D. Aksi per hama/penyakit (CNN)

| Deteksi | Aksi sistem | Sumber |
|---|---|---|
| Blas | Genangi sawah, N dibagi, fungisida triazol/strobilurin (saat bunting–keluar malai) | UCIPM_BLAST, IRRI_BLAST |
| Hawar/garis daun bakteri | N berimbang (tunda urea), drainase kecuali saat berbunga, sanitasi, varietas tahan — **tanpa bakterisida** | IRRI_BLB |
| Tungro | Eradikasi rumpun bergejala, varietas tahan & tanam serempak — **bukan insektisida** | IRRI_TUNGRO |
| Hispa | Pengusir ultrasonik + lampu UV (perangkat sistem), potong ujung daun, hindari N berlebih | IRRI_HISPA |
| Pucuk mati (penggerek) | Naikkan genangan, kumpulkan telur, N terbagi; insektisida hanya bila di atas ambang | IRRI_STEMBORER |
| Bercak coklat | Perbaiki kesuburan (K, silika), jaga genangan, perlakuan benih | IRRI_BROWNSPOT |
| Embun bulu | Hindari tanah jenuh lama pada tanaman muda, cabut tanaman sakit, perlakuan benih | CABI_DOWNY |

## E. Keterbatasan

1. **Sensor NPK RS485 murah** mengestimasi hara dari konduktivitas, bukan ekstraksi kimia; angka P & K belum
   setara P-Olsen/K-dd laboratorium. Kalibrasi dengan uji lab/PUTS. N sensor tidak dipakai untuk keputusan.
2. **EC sensor in-situ** berbeda dengan ECe (ekstrak pasta jenuh) laboratorium.
3. **VWC jenuh** berbeda antar tekstur tanah; nilai bawaan 45 % harus diganti dengan bacaan sensor saat
   sawah tergenang (`soil_moisture_saturated`).
4. **Data latih Random Forest sintetis** (label dari aturan di atas); model setara aturan pakar. Untuk
   melampauinya diperlukan data lapangan berlabel (aksi penyuluh/petani dan hasil panennya).
5. Rekomendasi bukan pengganti diagnosis petugas POPT/PPL, terutama untuk keputusan pestisida.

## F. Audit status seluruh data sistem

| Komponen data | Sumber saat ini | Status | Tindak lanjut |
|---|---|---|---|
| Ambang & aksi basis pengetahuan rekomendasi | IRRI, Balitbangtan, BMKG, Balai Penelitian Tanah, UC IPM, CABI, FAO | **Sesuai** (kecuali VWC jenuh: kalibrasi lokal) | — |
| Uji skenario pakar (15 kasus) | Aturan di atas | **Sesuai** — 15/15 lulus pada RF dan aturan | — |
| Data latih Random Forest | Sintetis; label = basis pengetahuan bersumber; sebaran nilai sensor = asumsi rentang lapangan | Label **bersumber**, sebaran nilai **sintetis** | Ganti/tambah dengan data lapangan berlabel |
| Dataset citra CNN (Paddy Doctor, 10.407 citra) | Petchiammal et al. (2023), *Paddy Doctor: A visual image dataset for automated paddy disease classification and benchmarking*, CODS-COMAD (ACM, prosiding), [10.1145/3570991.3570994](https://doi.org/10.1145/3570991.3570994) | Dataset terbit & ditelaah sejawat (prosiding) | Validasi dengan citra lapangan lokal |
| Model LSTM kesehatan (`plant_health_lstm_model.h5`) | Dataset Kaggle *Plant Health Data* (sintetis) | **Tidak kredibel sebagai data pertanian** → output LSTM hanya ditampilkan, **tidak lagi memicu aksi** | Latih ulang dengan data sensor sawah nyata + label agronom |
| Eksperimen regresi LSTM (Tabel 5 naskah) | `make_synthetic_microclimate_dataset.py` (sintetis) | Sintetis (sudah diungkap di README) | Uji dengan data stasiun lapangan |
| Ambang status tampilan (web, API, mobile) & aturan PHP cadangan | `SensorStatus.php` dan `RecommendationService::THRESHOLDS`: sama dengan basis pengetahuan, ditambah ambang tampilan §C.2 | **Sesuai** — semua ambang bersumber (§C, §C.2) | — |
| Tren & tabel prediksi dashboard (15 sensor) | Ramalan statistik dari riwayat sensor petak sendiri (`rnn/app/services/sensor_forecast.py`), metode §G | **Sesuai** — tidak ada nilai rekaan; data < 4 hari → persistensi dan dilaporkan | Laporkan MAE/MASE dari deret lapangan nyata sebelum mengklaim akurasi |
| Curah hujan dari firmware | Tipping bucket | **Diperbaiki**: bug `curah_hujan = (curah_hujan, 2)` dihapus; nilai per interval, dijumlah 24 jam di Laravel | Flash ulang firmware ESP32 |

## G. Peramalan 15 masukan sensor (tren & tabel prediksi)

Dipakai oleh `backend/python/rnn/app/services/sensor_forecast.py` (endpoint `GET /sensor-forecast/<titik>?days=N`).

Pedoman: FAO-56 (Allen et al. 1998, Bab 3 pers. 11–19) untuk e<sub>s</sub> dan VPD; FAO-29 (Ayers & Westcot 1985) untuk hubungan EC–TDS: ramalan TDS = k × EC dengan k = median rasio TDS/EC terukur sensor 8-in-1 petak itu; bila belum ada pasangan terukur, k = 0,64 (FAO-29). Firmware memakai 0,64 yang sama bila sensor tidak punya register TDS. Perangkat lunak: statsmodels 0.14 (`statsmodels.tsa.holtwinters`).

Kolom status pada tabel prediksi **tidak** menambah ambang baru: nilai ramalan dibandingkan dengan ambang di §C (`knowledge_base.T`).

Batasan: ramalan mengekstrapolasi riwayat petak sendiri, bukan prakiraan cuaca numerik; curah hujan tidak dapat meramalkan kejadian hujan tertentu (gunakan prakiraan BMKG). Akurasi lapangan belum diukur; nilai MAE/MASE hasil backtest ditampilkan di grafik.
