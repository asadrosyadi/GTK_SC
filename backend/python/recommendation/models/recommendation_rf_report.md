# Evaluasi Model Rekomendasi Random Forest

Dilatih: 2026-10-05T11:43:39 · sampel latih 32000, uji 8000 · 100 pohon · scikit-learn 1.6.1

| Metrik | Random Forest | Aturan pada sensor berderau |
|---|---|---|
| subset_accuracy | 0.8652 | 0.8692 |
| hamming_loss | 0.0087 | 0.0087 |
| f1_micro | 0.9676 | 0.9677 |
| f1_macro | 0.9583 | 0.9612 |
| precision_micro | 0.9680 | 0.9664 |
| recall_micro | 0.9672 | 0.9690 |

| Aksi | F1 | Support |
|---|---|---|
| pengendalian_hispa — Kendalikan hama putih palsu/hispa | 0.9832 | 325 |
| eradikasi_tungro — Eradikasi tanaman bergejala tungro | 0.9677 | 308 |
| pengendalian_penggerek — Kendalikan penggerek batang | 0.9844 | 350 |
| pengendalian_bakteri — Kendalikan hawar daun bakteri (kresek) | 0.9745 | 966 |
| fungisida_blas — Kendalikan penyakit blas | 0.9518 | 389 |
| pengendalian_embun_bulu — Kendalikan embun bulu (downy mildew) | 0.9654 | 347 |
| perbaikan_hara_bercak — Bercak coklat: perbaiki kesuburan tanah | 0.9623 | 314 |
| waspada_penyakit — Waspada cuaca pemicu penyakit | 0.8985 | 693 |
| mitigasi_panas — Mitigasi stres panas | 0.9433 | 1024 |
| bilas_garam — Salinitas tinggi: bilas tanah | 0.9946 | 2892 |
| irigasi — Irigasi / genangi kembali | 0.9696 | 1938 |
| tunda_urea — Tunda / kurangi urea | 0.9772 | 1959 |
| urea_bwd — Pemupukan urea berdasarkan BWD | 0.9915 | 2779 |
| pupuk_p — Tambah pupuk fosfor (SP-36) | 0.9303 | 1051 |
| pupuk_k — Tambah pupuk kalium (KCl) | 0.9614 | 514 |
| kapur — Pengapuran tanah masam | 0.9577 | 1846 |
| cegah_defisiensi_zn — Tanah basa: cegah defisiensi seng (Zn) | 0.9547 | 1173 |
| proteksi_angin — Waspada rebah akibat angin kencang | 0.9789 | 1154 |
| pantau — Kondisi aman – lanjutkan pemantauan | 0.8604 | 402 |

## Fitur paling berpengaruh

1. ph (0.1105)
2. temperature (0.0691)
3. grp_n_sensitive (0.0616)
4. grp_bakteri (0.0553)
5. windspeed (0.0492)
6. soil_moisture (0.0390)
7. hst (0.0350)
8. potassium (0.0326)
9. ec (0.0325)
10. tds (0.0317)
11. pest_pucuk_mati (0.0295)
12. phosphorus (0.0292)
13. fase_vegetatif (0.0280)
14. grp_hispa (0.0276)
15. pest_embun_bulu (0.0272)
