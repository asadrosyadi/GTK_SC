# Sumber Rujukan Basis Pengetahuan Rekomendasi

Semua ambang batas dan aksi di `knowledge_base.py` (Python, model Random Forest),
`RecommendationService.php` (Laravel, aturan cadangan), dan `app/Support/SensorStatus.php` (label status
kartu sensor di web, API v1, dan aplikasi mobile) mengikuti dokumen ini.

**Cara verifikasi yang dipakai**

1. **Keberadaan artikel**: DOI, judul, penulis, tahun, volume, dan halaman dicek melalui
   [Crossref API](https://api.crossref.org).
2. **Kuartil jurnal**: dicek di [SCImago Journal Rank](https://www.scimagojr.com) (berbasis data Scopus).
   ID jurnal SCImago = **Scopus Source ID**, sehingga tautan `scopus.com/sourceid/<ID>` mengarah ke
   halaman jurnal di Scopus. Kuartil = kuartil terbaik terbaru di SCImago (diakses Oktober 2026).
3. **Isi klaim**: dicocokkan dengan abstrak (OpenAlex) atau teks lembaga sumber. Kolom *Klaim* hanya
   memuat hal yang benar-benar terbaca di sumber; yang tidak dapat diverifikasi dari abstrak ditandai.

Tautan rekaman artikel di Scopus: `https://www.scopus.com/results/results.uri?src=s&s=DOI(<doi>)`
(memerlukan akses Scopus institusi).

## A. Artikel jurnal terindeks Scopus (Q1/Q2)

| # | Sitasi | Jurnal | Kuartil | DOI | Scopus | SJR | Dipakai untuk / klaim yang diverifikasi |
|---|---|---|---|---|---|---|---|
| 1 | Carrijo, Lundy & Linquist (2017) | Field Crops Research 203:173–180 | **Q1** | [10.1016/j.fcr.2016.12.002](https://doi.org/10.1016/j.fcr.2016.12.002) | [78796](https://www.scopus.com/sourceid/78796) | [SJR](https://www.scimagojr.com/journalsearch.php?q=78796&tip=sid) | Irigasi/AWD. Meta-analisis 56 studi: AWD ringan (muka air tidak turun > 15 cm atau potensial air tanah ≥ −20 kPa) tidak menurunkan hasil secara nyata; AWD berat menurunkan hasil 22,6 %. |
| 2 | Lampayan et al. (2015) | Field Crops Research 170:95–108 | **Q1** | [10.1016/j.fcr.2014.10.013](https://doi.org/10.1016/j.fcr.2014.10.013) | [78796](https://www.scopus.com/sourceid/78796) | [SJR](https://www.scimagojr.com/journalsearch.php?q=78796&tip=sid) | Adopsi & ekonomi AWD di sawah irigasi (pendukung; abstrak tidak tersedia di OpenAlex). |
| 3 | Bouman & Tuong (2001) | Agricultural Water Management 49:11–30 | **Q1** | [10.1016/S0378-3774(00)00128-1](https://doi.org/10.1016/S0378-3774(00)00128-1) | [35824](https://www.scopus.com/sourceid/35824) | [SJR](https://www.scimagojr.com/journalsearch.php?q=35824&tip=sid) | Pengelolaan air sawah irigasi (pendukung kondisi tergenang; abstrak tidak tersedia di OpenAlex). |
| 4 | Jagadish, Craufurd & Wheeler (2007) | Journal of Experimental Botany 58(7):1627–1635 | **Q1** | [10.1093/jxb/erm003](https://doi.org/10.1093/jxb/erm003) | [19210](https://www.scopus.com/sourceid/19210) | [SJR](https://www.scimagojr.com/journalsearch.php?q=19210&tip=sid) | Stres panas: suhu ≥ 33,7 °C saat antesis menyebabkan sterilitas; ambang 33 °C (Azucena), fertilitas turun ±7 %/°C di atas 29,6 °C (IR64). |
| 5 | Asibi, Chai & Coulter (2019) | Agronomy 9(8):451 | **Q1** | [10.3390/agronomy9080451](https://doi.org/10.3390/agronomy9080451) | [21100447811](https://www.scopus.com/sourceid/21100447811) | [SJR](https://www.scimagojr.com/journalsearch.php?q=21100447811&tip=sid) | Blas: pengendalian terpadu (pengelolaan air, varietas, fungisida berdasarkan tingkat serangan). |
| 6 | Savary et al. (2012) — EPIRICE | Crop Protection 34:6–17 | **Q1** | [10.1016/j.cropro.2011.11.009](https://doi.org/10.1016/j.cropro.2011.11.009) | [12898](https://www.scopus.com/sourceid/12898) | [SJR](https://www.scimagojr.com/journalsearch.php?q=12898&tip=sid) | Model epidemi penyakit padi; RH ≥ 90 % dipakai sebagai indikator daun basah untuk infeksi blas (parameter RHlim, dari dokumentasi implementasi model). |
| 7 | Mew (1987) | Annual Review of Phytopathology 25:359–382 | **Q1** | [10.1146/annurev.py.25.090187.002043](https://doi.org/10.1146/annurev.py.25.090187.002043) | [16715](https://www.scopus.com/sourceid/16715) | [SJR](https://www.scimagojr.com/journalsearch.php?q=16715&tip=sid) | Hawar daun bakteri: peningkatan pupuk N memperparah penyakit dan kehilangan hasil (dasar aksi *tunda urea*). |
| 8 | Azzam & Chancellor (2002) | Plant Disease 86(2):88–100 | **Q1** | [10.1094/PDIS.2002.86.2.88](https://doi.org/10.1094/PDIS.2002.86.2.88) | [60195](https://www.scopus.com/sourceid/60195) | [SJR](https://www.scimagojr.com/journalsearch.php?q=60195&tip=sid) | Tinjauan biologi, epidemiologi & pengelolaan tungro (penular wereng hijau). Klaim "insektisida sering tidak efektif" bersumber dari IRRI. |
| 9 | Barnwal et al. (2013) | European Journal of Plant Pathology 136:443–457 | **Q2** | [10.1007/s10658-013-0195-6](https://doi.org/10.1007/s10658-013-0195-6) | [18024](https://www.scopus.com/sourceid/18024) | [SJR](https://www.scimagojr.com/journalsearch.php?q=18024&tip=sid) | Bercak coklat mencerminkan tanaman yang mengalami stres (kekeringan, kesuburan tanah rendah) → aksi *perbaikan hara*. |
| 10 | Zeng & Shannon (2000) | Crop Science 40(4):996–1003 | **Q2**¹ | [10.2135/cropsci2000.404996x](https://doi.org/10.2135/cropsci2000.404996x) | [38753](https://www.scopus.com/sourceid/38753) | [SJR](https://www.scimagojr.com/journalsearch.php?q=38753&tip=sid) | Salinitas: pertumbuhan bibit turun pada 1,9 dS/m; daya hidup bibit & indeks panen turun nyata pada ≥ 3,4 dS/m. |
| 11 | Zheng et al. (2023) | European Journal of Agronomy 144:126765 | **Q1** | [10.1016/j.eja.2023.126765](https://doi.org/10.1016/j.eja.2023.126765) | [59988](https://www.scopus.com/sourceid/59988) | [SJR](https://www.scimagojr.com/journalsearch.php?q=59988&tip=sid) | Meta-analisis pengaruh salinitas terhadap hasil & mutu beras (pendukung; abstrak tidak tersedia di OpenAlex). |
| 12 | Dobermann et al. (2002) | Field Crops Research 74:37–66 | **Q1** | [10.1016/S0378-4290(01)00197-6](https://doi.org/10.1016/S0378-4290(01)00197-6) | [78796](https://www.scopus.com/sourceid/78796) | [SJR](https://www.scimagojr.com/journalsearch.php?q=78796&tip=sid) | Pengelolaan hara spesifik lokasi (SSNM) padi Asia — dasar pemupukan P & K berbasis status hara. |
| 13 | Yang et al. (2003) | Agronomy Journal 95:212–217 | **Q2** | [10.2134/agronj2003.2120](https://doi.org/10.2134/agronj2003.2120) | [15639](https://www.scopus.com/sourceid/15639) | [SJR](https://www.scimagojr.com/journalsearch.php?q=15639&tip=sid) | Bagan Warna Daun (LCC) berkorelasi erat dengan SPAD (R² 0,62–0,98) dan layak dipakai petani untuk menentukan waktu pemupukan N. |
| 14 | Islam et al. (2007) | Field Crops Research 103:70–75 | **Q1** | [10.1016/j.fcr.2007.04.012](https://doi.org/10.1016/j.fcr.2007.04.012) | [78796](https://www.scopus.com/sourceid/78796) | [SJR](https://www.scimagojr.com/journalsearch.php?q=78796&tip=sid) | Adopsi BWD/LCC untuk efisiensi N di lahan petani (pendukung; abstrak tidak tersedia di OpenAlex). |
| 15 | Alloway (2009) | Environmental Geochemistry and Health 31:537–548 | **Q1** | [10.1007/s10653-009-9255-4](https://doi.org/10.1007/s10653-009-9255-4) | [20992](https://www.scopus.com/sourceid/20992) | [SJR](https://www.scimagojr.com/journalsearch.php?q=20992&tip=sid) | Faktor tanah penyebab defisiensi Zn (pendukung pH tinggi; klaim kelarutan diverifikasi dari IRRI). |
| 16 | Impa & Johnson-Beebout (2012) | Plant and Soil 361:3–41 | **Q1** | [10.1007/s11104-012-1315-3](https://doi.org/10.1007/s11104-012-1315-3) | [16574](https://www.scopus.com/sourceid/16574) | [SJR](https://www.scimagojr.com/journalsearch.php?q=16574&tip=sid) | Defisiensi Zn pada padi: kimia tanah & fisiologi tanaman (pendukung; abstrak tidak tersedia di OpenAlex). |
| 17 | Berry et al. (2004) | Advances in Agronomy 84:217–271 | **Q1** | [10.1016/S0065-2113(04)84005-7](https://doi.org/10.1016/S0065-2113(04)84005-7) | [14324](https://www.scopus.com/sourceid/14324) | [SJR](https://www.scimagojr.com/journalsearch.php?q=14324&tip=sid) | Mekanisme & pengurangan rebah pada serealia (pendukung; angka 7–8 m/s dari Hitaka 1969). |
| 18 | Grossiord et al. (2020) | New Phytologist 226:1550–1566 | **Q1** | [10.1111/nph.16485](https://doi.org/10.1111/nph.16485) | [19908](https://www.scopus.com/sourceid/19908) | [SJR](https://www.scimagojr.com/journalsearch.php?q=19908&tip=sid) | VPD tinggi menurunkan konduktansi stomata setelah ambang tertentu (umum, bukan khusus padi). Karena itu VPD **hanya ditampilkan**, tidak memicu aksi. |
| 19 | Mahmud et al. (2026) | Sensors 26(9):2692 | **Q1** | [10.3390/s26092692](https://doi.org/10.3390/s26092692) | [130124](https://www.scopus.com/sourceid/130124) | [SJR](https://www.scimagojr.com/journalsearch.php?q=130124&tip=sid) | AWD otomatis berbasis IoT (sensor kelembapan kapasitif) di BRRI: irigasi pada **kadar air volumetrik 35 %** memberi hasil 7,76 t/ha, hemat air 28 % vs genangan terus-menerus → ambang irigasi VWC 35 %. |
| 20 | Shukla et al. (2004) | Agronomy Journal 96:1606–1621 | **Q2** | [10.2134/agronj2004.1606](https://doi.org/10.2134/agronj2004.1606) | [15639](https://www.scopus.com/sourceid/15639) | [SJR](https://www.scimagojr.com/journalsearch.php?q=15639&tip=sid) | Kalibrasi BWD/LCC: ambang **4 untuk padi inbrida** (Saket-4), 3 basmati, 5 hibrida memberi hasil & efisiensi N lebih tinggi daripada pemupukan terjadwal → dasar aksi urea berbasis BWD. |
| 21 | Panda et al. (2026) | Plant Cell Reports 45(6) | **Q1** | [10.1007/s00299-026-03836-3](https://doi.org/10.1007/s00299-026-03836-3) | — | — | Padi kekurangan cahaya bila cahaya fotosintesis < 600 µmol m⁻² s⁻¹; dapat menurunkan hasil 10–20 % di lapangan → status **Cahaya rendah** (tampilan saja). |
| 22 | Thimijan & Heins (1983) | HortScience 18(6):818–822 | **Q2** | [10.21273/HORTSCI.18.6.818](https://doi.org/10.21273/HORTSCI.18.6.818) | — | — | Faktor konversi baku satuan cahaya matahari (µmol m⁻² s⁻¹ ↔ lux) → 600 µmol m⁻² s⁻¹ ≈ **32.400 lux**. |
| 23 | Sato et al. (2023) | Sensors 23(14):6466 | **Q2** | [10.3390/s23146466](https://doi.org/10.3390/s23146466) | — | — | Kadar N tersedia optimum tanah sawah 80–200 mg N/kg; di atasnya pupuk dasar dikurangi karena padi mudah rebah → status N **Rendah / Sedang / Tinggi** (tampilan saja). |
| 24 | Chakraborty et al. (2022) | Journal of the Saudi Society of Agricultural Sciences 21(2):98–107 | **Q1** | [10.1016/j.jssas.2021.07.001](https://doi.org/10.1016/j.jssas.2021.07.001) | — | — | Pertumbuhan akar dan hasil gabah turun bila suhu tanah melewati batas kritis 34 °C → status suhu tanah **Tinggi** (> 34 °C). |
| 25 | Li et al. (2019) | Scientific Reports 9:10618 | **Q1** | [10.1038/s41598-019-47031-9](https://doi.org/10.1038/s41598-019-47031-9) | — | — | Padi pada suhu zona akar 17,5 °C tumbuh lebih buruk dibanding 27,5 °C → status suhu tanah **Rendah** (≤ 17,5 °C). |

¹ *Crop Science* Q1 pada 1999–2022 dan Q2 pada 2023–2024 menurut SCImago.

**Rujukan klasik (tidak diklaim berkuartil):** Maas, E.V. & Hoffman, G.J. (1977). Crop salt tolerance —
current assessment. *Journal of the Irrigation and Drainage Division (ASCE)* 103(2):115–134.
[10.1061/JRCEA4.0001137](https://doi.org/10.1061/JRCEA4.0001137) — konsep ambang salinitas (hasil
turun linear di atas ambang); nilai ambang padi 3 dS/m dan −12 %/dS/m lazim dikutip dari karya ini.

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
| [HITAKA1969](https://www.jircas.go.jp/sites/default/files/publication/jarq/04-3-001-006_0.pdf) | JIRCAS (jurnal JARQ: Scopus **Q4**, bukan Q1/Q2) | Batang padi mulai patah ±7 m/s; membungkuk 45° pada 7–8 m/s saat masak |

## C. Ambang batas → sumber

| Parameter | Nilai di sistem | Sumber | Status |
|---|---|---|---|
| Irigasi (AWD aman) | kadar air volumetrik (VWC) < 35 % | Mahmud 2026 (Sensors, Q1); Carrijo 2017 (Q1): muka air ≤ 15 cm / ≥ −20 kPa | **Bersumber** |
| Genangan saat berbunga | VWC di bawah kondisi tergenang (bawaan 45 %) | Bouman & Tuong 2001 (Q1), IRRI_WATER; Mahmud 2026 (IRRISAT: irigasi saat sedikit di bawah jenuh) | Prinsip bersumber; nilai jenuh = **kalibrasi** (bacaan sensor saat sawah tergenang) |
| Tidak diairi menjelang panen | ≥ 10 hari sebelum panen | IRRI_WATER | Bersumber |
| Fase tumbuh | reproduktif 35 h, pemasakan 30 h, berbunga ±7 h | IRRI_GROWTH | Bersumber |
| Stres panas | ≥ 35 °C; ≥ 33 °C saat berbunga | Jagadish 2007 (Q1) | Bersumber |
| Cuaca pendukung blas | RH ≥ 90 % dan 20–28 °C | Savary 2012 (Q1), UCIPM_BLAST, Asibi 2019 (Q1) | Bersumber |
| Cuaca pendukung hawar bakteri | 25–34 °C, RH > 70 %, hujan ≥ 50 mm/24 jam | IRRI_BLB (suhu/RH, hujan lebat); BMKG_HUJAN (hujan lebat ≥ 50 mm/hari) | Bersumber |
| Salinitas | EC ≥ 3000 µS/cm atau TDS ≥ 1920 ppm | Maas & Hoffman 1977, Zeng & Shannon 2000 (Q2), Zheng 2023 (Q1), FAO29 | Bersumber (EC sensor ≠ ECe lab) |
| pH masam / basa | ≤ 5,5 / > 7,5 | BALITTANAH; Alloway 2009 (Q1), Impa 2012 (Q1), IRRI_ZN | Bersumber |
| P tersedia | < 6 rendah, 6–10 sedang mg/kg | DOBERMANN_FAIRHURST2000, Dobermann 2002 (Q1), PUTS | Bersumber (asumsi sensor ≈ P-Olsen; kalibrasi lab) |
| K dapat ditukar | < 78 mg/kg (0,2 cmol/kg) | DOBERMANN_FAIRHURST2000, Dobermann 2002 (Q1), PUTS | Bersumber (asumsi sensor ≈ K-dd; kalibrasi lab) |
| Nitrogen | **tanpa ambang sensor N**; urea pada fase vegetatif–reproduktif dengan BWD skala 4; tunda urea bila ada hama/penyakit yang diperparah N | Shukla 2004 (Q2), Yang 2003 (Q2), Islam 2007 (Q1), BWD; Mew 1987 (Q1), UCIPM_BLAST | Bersumber |
| Rebah | angin ≥ 8 m/s fase bunting–masak | HITAKA1969 (JIRCAS, Q4); Berry 2004 (Q1, mekanisme umum) | Data lembaga kredibel, **bukan** Q1/Q2 untuk angkanya |
| VPD | ≥ 1,5 kPa ditandai "tinggi" | Grossiord 2020 (Q1, umum) | **Informasi saja** — tidak memicu aksi |

### C.2 Ambang status tampilan (tidak memicu aksi)

Label status pada kartu cuaca/tanah (web, API v1 `/dashboard`, aplikasi mobile) dihitung di server oleh
`backend/laravel/app/Support/SensorStatus.php`. Ambang yang sama dengan basis pengetahuan (suhu, RH, VPD,
angin, hujan, pH, kadar air, EC, TDS, P, K) mengikuti tabel C. Ambang berikut **hanya untuk status tampilan**:
tercatat di `knowledge_base.T` dan `THRESHOLD_SOURCES`, tetapi tidak dipakai label/aksi Random Forest.

| Parameter | Status di sistem | Sumber | Catatan |
|---|---|---|---|
| Intensitas cahaya | Rendah < 32.400 lux; Malam = 0 lux | Panda 2026 (Q1); Thimijan & Heins 1983 (Q2, konversi) | Pagi/sore wajar di bawah batas; paling bermakna bila muncul siang hari. Tidak ada status "Tinggi" (tidak ada ambang bersumber). |
| Tekanan udara | Rendah ≤ 1000 hPa (dikoreksi ke permukaan laut dengan `ELEVASI_STASIUN_M`) | BMKG_SIKLON | Tekanan tidak berpengaruh langsung; menandai potensi cuaca buruk. |
| Suhu tanah | Rendah ≤ 17,5 °C; Tinggi > 34 °C | Li 2019 (Q1); Chakraborty 2022 (Q1) | — |
| Nitrogen tersedia | Rendah < 80; Sedang 80–200; Tinggi > 200 mg/kg | Sato 2023 (Q2) | Dosis urea tetap memakai BWD (tabel C); angka sensor N tidak memicu aksi. |
| Curah hujan 24 jam | Sedang 20–50; Tinggi ≥ 50 mm | BMKG_HUJAN | Sama dengan kategori hujan harian BMKG. |

## D. Aksi per hama/penyakit (CNN)

| Deteksi | Aksi sistem | Sumber |
|---|---|---|
| Blas | Genangi sawah, N dibagi, fungisida triazol/strobilurin (saat bunting–keluar malai) | Asibi 2019 (Q1), UCIPM_BLAST, IRRI_BLAST |
| Hawar/garis daun bakteri | N berimbang (tunda urea), drainase kecuali saat berbunga, sanitasi, varietas tahan — **tanpa bakterisida** | Mew 1987 (Q1), IRRI_BLB |
| Tungro | Eradikasi rumpun bergejala, varietas tahan & tanam serempak — **bukan insektisida** | Azzam & Chancellor 2002 (Q1), IRRI_TUNGRO |
| Hispa | Pengusir ultrasonik + lampu UV (perangkat sistem), potong ujung daun, hindari N berlebih | IRRI_HISPA |
| Pucuk mati (penggerek) | Naikkan genangan, kumpulkan telur, N terbagi; insektisida hanya bila di atas ambang | IRRI_STEMBORER |
| Bercak coklat | Perbaiki kesuburan (K, silika), jaga genangan, perlakuan benih | Barnwal 2013 (Q2), IRRI_BROWNSPOT |
| Embun bulu | Hindari tanah jenuh lama pada tanaman muda, cabut tanaman sakit, perlakuan benih | CABI_DOWNY |

## E. Keterbatasan

1. **Sensor NPK RS485 murah** mengestimasi hara dari konduktivitas, bukan ekstraksi kimia; angka P & K belum
   setara P-Olsen/K-dd laboratorium. Kalibrasi dengan uji lab/PUTS. N sensor tidak dipakai untuk keputusan.
2. **EC sensor in-situ** berbeda dengan ECe (ekstrak pasta jenuh) pada Maas & Hoffman / Zeng & Shannon.
3. **VWC jenuh** berbeda antar tekstur tanah; nilai bawaan 45 % harus diganti dengan bacaan sensor saat
   sawah tergenang (`soil_moisture_saturated`).
4. **Data latih Random Forest sintetis** (label dari aturan di atas); model setara aturan pakar. Untuk
   melampauinya diperlukan data lapangan berlabel (aksi penyuluh/petani dan hasil panennya).
5. Rekomendasi bukan pengganti diagnosis petugas POPT/PPL, terutama untuk keputusan pestisida.
6. Kuartil jurnal dapat berubah setiap tahun; status di atas sesuai SCImago saat diakses (Oktober 2026).

## F. Audit status seluruh data sistem

| Komponen data | Sumber saat ini | Status | Tindak lanjut |
|---|---|---|---|
| Ambang & aksi basis pengetahuan rekomendasi | 20 artikel Scopus Q1/Q2 + IRRI, Balitbangtan, BMKG, Balai Penelitian Tanah, UC IPM, CABI, FAO | **Sesuai** (kecuali angka rebah: JIRCAS/Q4; VWC jenuh: kalibrasi lokal) | — |
| Uji skenario pakar (15 kasus) | Aturan di atas | **Sesuai** — 15/15 lulus pada RF dan aturan | — |
| Data latih Random Forest | Sintetis; label = basis pengetahuan bersumber; sebaran nilai sensor = asumsi rentang lapangan | Label **bersumber**, sebaran nilai **sintetis** | Ganti/tambah dengan data lapangan berlabel |
| Dataset citra CNN (Paddy Doctor, 10.407 citra) | Petchiammal et al. (2023), *Paddy Doctor: A visual image dataset for automated paddy disease classification and benchmarking*, CODS-COMAD (ACM, prosiding), [10.1145/3570991.3570994](https://doi.org/10.1145/3570991.3570994) | Dataset terbit & ditelaah sejawat; **prosiding, bukan jurnal Q1/Q2** | Validasi dengan citra lapangan lokal |
| Model LSTM kesehatan (`plant_health_lstm_model.h5`) | Dataset Kaggle *Plant Health Data* (sintetis) | **Tidak kredibel sebagai data pertanian** → output LSTM hanya ditampilkan, **tidak lagi memicu aksi** | Latih ulang dengan data sensor sawah nyata + label agronom |
| Eksperimen regresi LSTM (Tabel 5 naskah) | `make_synthetic_microclimate_dataset.py` (sintetis) | Sintetis (sudah diungkap di README) | Uji dengan data stasiun lapangan |
| Ambang status tampilan (web, API, mobile) & aturan PHP cadangan | `SensorStatus.php` dan `RecommendationService::THRESHOLDS`: sama dengan basis pengetahuan, ditambah ambang tampilan §C.2 | **Sesuai** — semua ambang bersumber (§C, §C.2) | — |
| Tren & tabel prediksi dashboard (15 sensor) | Ramalan statistik dari riwayat sensor petak sendiri (`rnn/app/services/sensor_forecast.py`), metode bersumber §G | **Sesuai** — tidak ada nilai rekaan; data < 4 hari → persistensi dan dilaporkan | Laporkan MAE/MASE dari deret lapangan nyata sebelum mengklaim akurasi |
| Curah hujan dari firmware | Tipping bucket | **Diperbaiki**: bug `curah_hujan = (curah_hujan, 2)` dihapus; nilai per interval, dijumlah 24 jam di Laravel | Flash ulang firmware ESP32 |

## G. Peramalan 15 masukan sensor (tren & tabel prediksi)

Dipakai oleh `backend/python/rnn/app/services/sensor_forecast.py` (endpoint `GET /sensor-forecast/<titik>?days=N`). Diverifikasi via Crossref (DOI, penulis, volume, halaman) dan SCImago/Scopus (kuartil), Oktober 2026.

| Referensi | Jurnal | Kuartil | DOI | Scopus | Klaim yang dipakai |
|---|---|---|---|---|---|
| Hyndman & Koehler (2006) | Int. J. Forecasting 22(4):679–688 | Q1 | [10.1016/j.ijforecast.2006.03.001](https://doi.org/10.1016/j.ijforecast.2006.03.001) | [22706](https://www.scopus.com/sourceid/22706) | MASE (galat diskalakan dengan galat naif dalam sampel) sebagai ukuran pembanding antar-deret; baseline naif & musiman naif |
| Hyndman, Koehler, Snyder & Grose (2002) | Int. J. Forecasting 18(3):439–454 | Q1 | [10.1016/S0169-2070(01)00110-8](https://doi.org/10.1016/S0169-2070(01)00110-8) | [22706](https://www.scopus.com/sourceid/22706) | Pemilihan otomatis model exponential smoothing per deret |
| Gardner (2006) | Int. J. Forecasting 22(4):637–666 | Q1 | [10.1016/j.ijforecast.2006.03.005](https://doi.org/10.1016/j.ijforecast.2006.03.005) | [22706](https://www.scopus.com/sourceid/22706) | SES dan Holt-Winters dengan tren teredam (damped trend) |
| Tashman (2000) | Int. J. Forecasting 16(4):437–450 | Q1 | [10.1016/S0169-2070(00)00065-0](https://doi.org/10.1016/S0169-2070(00)00065-0) | [22706](https://www.scopus.com/sourceid/22706) | Evaluasi out-of-sample dengan rolling origin |
| Makridakis, Spiliotis & Assimakopoulos (2018) | PLOS ONE 13(3):e0194889 | Q1 | [10.1371/journal.pone.0194889](https://doi.org/10.1371/journal.pone.0194889) | [10600153309](https://www.scopus.com/sourceid/10600153309) | Metode statistik sederhana setara/lebih akurat daripada ML pada deret waktu, dengan biaya komputasi jauh lebih kecil |
| Makridakis, Spiliotis & Assimakopoulos (2020) | Int. J. Forecasting 36(1):54–74 | Q1 | [10.1016/j.ijforecast.2019.04.014](https://doi.org/10.1016/j.ijforecast.2019.04.014) | [22706](https://www.scopus.com/sourceid/22706) | Kompetisi M4: baseline statistik sederhana sulit dikalahkan |
| Murphy (1988) | Monthly Weather Review 116(12):2417–2424 | Q1 | [10.1175/1520-0493(1988)116<2417:SSBOTM>2.0.CO;2](https://doi.org/10.1175/1520-0493(1988)116%3C2417:SSBOTM%3E2.0.CO;2) | [13482](https://www.scopus.com/sourceid/13482) | Klimatologi dan persistensi sebagai ramalan acuan (dipakai untuk curah hujan) |

Pedoman: FAO-56 (Allen et al. 1998, Bab 3 pers. 11–19) untuk e<sub>s</sub> dan VPD; FAO-29 (Ayers & Westcot 1985) untuk hubungan EC–TDS: ramalan TDS = k × EC dengan k = median rasio TDS/EC terukur sensor 8-in-1 petak itu; bila belum ada pasangan terukur, k = 0,64 (FAO-29). Firmware memakai 0,64 yang sama bila sensor tidak punya register TDS. Perangkat lunak: statsmodels 0.14 (`statsmodels.tsa.holtwinters`).

Kolom status pada tabel prediksi **tidak** menambah ambang baru: nilai ramalan dibandingkan dengan ambang di §C (`knowledge_base.T`).

Batasan: ramalan mengekstrapolasi riwayat petak sendiri, bukan prakiraan cuaca numerik; curah hujan tidak dapat meramalkan kejadian hujan tertentu (gunakan prakiraan BMKG). Akurasi lapangan belum diukur; nilai MAE/MASE hasil backtest ditampilkan di grafik.
