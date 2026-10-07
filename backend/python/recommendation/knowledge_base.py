import math


def _scopus(cite, title, journal, quartile, sid, doi):
    return {'type': 'scopus', 'cite': cite, 'title': title, 'journal': journal, 'quartile': quartile,
            'scopus_sid': sid, 'doi': doi, 'url': f'https://doi.org/{doi}',
            'scopus_url': f'https://www.scopus.com/sourceid/{sid}' if sid else None}


def _pedoman(cite, title, url):
    return {'type': 'pedoman', 'cite': cite, 'title': title, 'url': url}


SOURCES = {
    'CARRIJO2017': _scopus('Carrijo, Lundy & Linquist (2017)',
                           'Rice yields and water use under alternate wetting and drying irrigation: A meta-analysis',
                           'Field Crops Research 203:173–180', 'Q1', 78796, '10.1016/j.fcr.2016.12.002'),
    'LAMPAYAN2015': _scopus('Lampayan et al. (2015)',
                            'Adoption and economics of alternate wetting and drying water management for irrigated lowland rice',
                            'Field Crops Research 170:95–108', 'Q1', 78796, '10.1016/j.fcr.2014.10.013'),
    'BOUMAN2001': _scopus('Bouman & Tuong (2001)',
                          'Field water management to save water and increase its productivity in irrigated lowland rice',
                          'Agricultural Water Management 49:11–30', 'Q1', 35824, '10.1016/S0378-3774(00)00128-1'),
    'CHAKRABORTY2022': _scopus('Chakraborty et al. (2022)',
                               'Assessing congenial soil temperature and its impact on root growth, grain yield of summer rice '
                               'under varying water stress condition in Lower Gangetic Plain of India',
                               'Journal of the Saudi Society of Agricultural Sciences 21(2):98–107', 'Q1', None,
                               '10.1016/j.jssas.2021.07.001'),
    'LI2019_RHIZO': _scopus('Li et al. (2019)',
                            'Responses of plant growth, physiological, gas exchange parameters of super and non-super rice '
                            'to rhizosphere temperature at the tillering stage',
                            'Scientific Reports 9:10618', 'Q1', None, '10.1038/s41598-019-47031-9'),
    'JAGADISH2007': _scopus('Jagadish, Craufurd & Wheeler (2007)',
                            'High temperature stress and spikelet fertility in rice (Oryza sativa L.)',
                            'Journal of Experimental Botany 58(7):1627–1635', 'Q1', 19210, '10.1093/jxb/erm003'),
    'ASIBI2019': _scopus('Asibi, Chai & Coulter (2019)',
                         'Rice Blast: A Disease with Implications for Global Food Security',
                         'Agronomy 9(8):451', 'Q1', 21100447811, '10.3390/agronomy9080451'),
    'SAVARY2012': _scopus('Savary et al. (2012)', 'Modeling and mapping potential epidemics of rice diseases globally (EPIRICE)',
                          'Crop Protection 34:6–17', 'Q1', 12898, '10.1016/j.cropro.2011.11.009'),
    'MEW1987': _scopus('Mew (1987)', 'Current status and future prospects of research on bacterial blight of rice',
                       'Annual Review of Phytopathology 25:359–382', 'Q1', 16715, '10.1146/annurev.py.25.090187.002043'),
    'AZZAM2002': _scopus('Azzam & Chancellor (2002)', 'The biology, epidemiology, and management of rice tungro disease in Asia',
                         'Plant Disease 86(2):88–100', 'Q1', 60195, '10.1094/PDIS.2002.86.2.88'),
    'BARNWAL2013': _scopus('Barnwal et al. (2013)',
                           'A review on crop losses, epidemiology and disease management of rice brown spot',
                           'European Journal of Plant Pathology 136:443–457', 'Q2', 18024, '10.1007/s10658-013-0195-6'),
    'ZENG2000': _scopus('Zeng & Shannon (2000)', 'Salinity effects on seedling growth and yield components of rice',
                        'Crop Science 40(4):996–1003', 'Q2', 38753, '10.2135/cropsci2000.404996x'),
    'ZHENG2023': _scopus('Zheng et al. (2023)', 'Effect of salinity stress on rice yield and grain quality: A meta-analysis',
                         'European Journal of Agronomy 144:126765', 'Q1', 59988, '10.1016/j.eja.2023.126765'),
    'DOBERMANN2002': _scopus('Dobermann et al. (2002)', 'Site-specific nutrient management for intensive rice cropping systems in Asia',
                             'Field Crops Research 74:37–66', 'Q1', 78796, '10.1016/S0378-4290(01)00197-6'),
    'YANG2003': _scopus('Yang et al. (2003)', 'Using leaf color charts to estimate leaf nitrogen status of rice',
                        'Agronomy Journal 95:212–217', 'Q2', 15639, '10.2134/agronj2003.2120'),
    'ISLAM2007': _scopus('Islam et al. (2007)',
                         'Adoption of leaf color chart for nitrogen use efficiency in rice: impact assessment of a farmer-participatory experiment',
                         'Field Crops Research 103:70–75', 'Q1', 78796, '10.1016/j.fcr.2007.04.012'),
    'ALLOWAY2009': _scopus('Alloway (2009)', 'Soil factors associated with zinc deficiency in crops and humans',
                           'Environmental Geochemistry and Health 31:537–548', 'Q1', 20992, '10.1007/s10653-009-9255-4'),
    'IMPA2012': _scopus('Impa & Johnson-Beebout (2012)',
                        'Mitigating zinc deficiency and achieving high grain Zn in rice through integration of soil chemistry and plant physiology research',
                        'Plant and Soil 361:3–41', 'Q1', 16574, '10.1007/s11104-012-1315-3'),
    'BERRY2004': _scopus('Berry et al. (2004)', 'Understanding and reducing lodging in cereals',
                         'Advances in Agronomy 84:217–271', 'Q1', 14324, '10.1016/S0065-2113(04)84005-7'),
    'GROSSIORD2020': _scopus('Grossiord et al. (2020)', 'Plant responses to rising vapor pressure deficit',
                             'New Phytologist 226:1550–1566', 'Q1', 19908, '10.1111/nph.16485'),
    'MAHMUD2026': _scopus('Mahmud et al. (2026)',
                          'IoT-based precision irrigation system featuring multi-sensor monitoring and scheduled automated water-control gates for rice production',
                          'Sensors 26(9):2692', 'Q1', 130124, '10.3390/s26092692'),
    'SHUKLA2004': _scopus('Shukla et al. (2004)',
                          'Calibrating the leaf color chart for nitrogen management in different genotypes of rice and wheat in a systems perspective',
                          'Agronomy Journal 96:1606–1621', 'Q2', 15639, '10.2134/agronj2004.1606'),
    'MAAS1977': {'type': 'klasik', 'cite': 'Maas & Hoffman (1977)', 'title': 'Crop salt tolerance — current assessment',
                 'journal': 'Journal of the Irrigation and Drainage Division (ASCE) 103:115–134',
                 'doi': '10.1061/JRCEA4.0001137', 'url': 'https://doi.org/10.1061/JRCEA4.0001137'},

    'IRRI_AWD': _pedoman('IRRI Rice Knowledge Bank', 'Saving water with Alternate Wetting Drying (AWD)',
                         'http://www.knowledgebank.irri.org/training/fact-sheets/water-management/saving-water-alternate-wetting-drying-awd'),
    'IRRI_WATER': _pedoman('IRRI Rice Knowledge Bank', 'Water management',
                           'http://www.knowledgebank.irri.org/step-by-step-production/growth/water-management'),
    'IRRI_GROWTH': _pedoman('IRRI Rice Knowledge Bank', 'Growth stages of the rice plant',
                            'http://www.knowledgebank.irri.org/ericeproduction/0.2._Growth_stages_of_the_rice_plant.htm'),
    'IRRI_BLAST': _pedoman('IRRI Rice Knowledge Bank', 'Blast (leaf and collar)',
                           'http://www.knowledgebank.irri.org/training/fact-sheets/pest-management/diseases/item/blast-leaf-collar'),
    'UCIPM_BLAST': _pedoman('UC Statewide IPM Program', 'Rice blast — Pest Management Guidelines',
                            'https://ipm.ucanr.edu/agriculture/rice/rice-blast/'),
    'IRRI_BLB': _pedoman('IRRI Rice Knowledge Bank', 'Bacterial blight',
                         'http://www.knowledgebank.irri.org/decision-tools/rice-doctor/rice-doctor-fact-sheets/item/bacterial-blight'),
    'IRRI_BROWNSPOT': _pedoman('IRRI Rice Knowledge Bank', 'Brown spot',
                               'http://www.knowledgebank.irri.org/training/fact-sheets/pest-management/diseases/item/brown-spot'),
    'IRRI_TUNGRO': _pedoman('IRRI Rice Knowledge Bank', 'Tungro',
                            'http://www.knowledgebank.irri.org/training/fact-sheets/pest-management/diseases/item/tungro'),
    'IRRI_HISPA': _pedoman('IRRI Rice Knowledge Bank', 'Rice hispa',
                           'http://www.knowledgebank.irri.org/training/fact-sheets/pest-management/insects/item/rice-hispa'),
    'IRRI_STEMBORER': _pedoman('IRRI Rice Knowledge Bank', 'Stem borer',
                               'http://www.knowledgebank.irri.org/training/fact-sheets/pest-management/insects/item/stem-borer'),
    'IRRI_ZN': _pedoman('IRRI Rice Knowledge Bank', 'Zinc deficiency',
                        'http://www.knowledgebank.irri.org/training/fact-sheets/nutrient-management/deficiencies-and-toxicities-fact-sheet/item/zinc-deficiency'),
    'CABI_DOWNY': _pedoman('CABI Compendium', 'Sclerophthora macrospora (downy mildew)',
                           'https://www.cabidigitallibrary.org/doi/abs/10.1079/cabicompendium.49243'),
    'FAO29': _pedoman('Ayers & Westcot (1985), FAO Irrigation and Drainage Paper 29', 'Water quality for agriculture (TDS ≈ 640 × EC dS/m)',
                      'https://www.fao.org/4/t0234e/t0234e00.htm'),
    'DOBERMANN_FAIRHURST2000': _pedoman('Dobermann & Fairhurst (2000), IRRI/PPI', 'Rice: Nutrient Disorders & Nutrient Management',
                                        'http://books.irri.org/9810427425_content.pdf'),
    'PUTS': _pedoman('Balitbangtan', 'Perangkat Uji Tanah Sawah (PUTS) & rekomendasi SP-36/KCl',
                     'https://pustaka.bppsdmp.pertanian.go.id/info-literasi/info-teknologi-perangkat-uji-tanah-sawah-puts-untuk-efisiensi-pupuk'),
    'BWD': _pedoman('BB Padi Balitbangtan', 'Pemupukan pada tanaman padi (Bagan Warna Daun, skala kritis 4)',
                    'https://bbpadi.litbang.pertanian.go.id/index.php/info-berita/info-teknologi/pemupukan-pada-tanaman-padi'),
    'BALITTANAH': _pedoman('Balai Penelitian Tanah (2009)', 'Petunjuk Teknis Analisis Kimia Tanah — kriteria pH',
                           'https://id.scribd.com/doc/313325075/juknis-tanah'),
    'PANDA2026': _scopus('Panda et al. (2026)',
                         'Rice adaptation to low-light stress: integrating morpho-physiology, biochemical plasticity, '
                         'signalling networks and molecular breeding strategies',
                         'Plant Cell Reports 45(6)', 'Q1', None, '10.1007/s00299-026-03836-3'),
    'THIMIJAN1983': _scopus('Thimijan & Heins (1983)',
                            'Photometric, radiometric, and quantum light units of measure: a review of procedures for interconversion',
                            'HortScience 18(6):818–822', 'Q2', None, '10.21273/HORTSCI.18.6.818'),
    'SATO2023': _scopus('Sato et al. (2023)',
                        'Predicting rice lodging risk from the distribution of available nitrogen in soil using UAS images '
                        'in a paddy field', 'Sensors 23(14):6466', 'Q2', None, '10.3390/s23146466'),
    'BMKG_SIKLON': _pedoman('BMKG (2021)', 'Siklon Tropis Teratai: tekanan udara di pusatnya mencapai 1000 hPa; '
                            'potensi hujan sedang–lebat dan angin kencang',
                            'https://www.bmkg.go.id/siaran-pers/bmkg-siklon-tropis-teratai-terbentuk-di-wilayah-indonesia'),
    'BMKG_HUJAN': _pedoman('BMKG', 'Kategori intensitas hujan harian (sedang 20–50, lebat 50–100, sangat lebat > 100 mm/hari)',
                           'https://ntt.bmkg.go.id/uploads/buletin/2020/06/buletin%20_Juni_2020.pdf'),
    'HITAKA1969': _pedoman('Hitaka (1969), JARQ 4(3) — JIRCAS (jurnal Scopus Q4)', 'Studies on the lodging of rice plants',
                           'https://www.jircas.go.jp/sites/default/files/publication/jarq/04-3-001-006_0.pdf'),
    'OPERASIONAL': {'type': 'operasional', 'cite': 'Ambang operasional sistem',
                    'title': 'Perlu kalibrasi lokal dengan pengukuran lapangan/laboratorium', 'url': None},
}

CNN_CLASSES = [
    'Hawar daun bakteri',
    'Garis daun bakteri',
    'Hawar malai bakteri',
    'Penyakit blas (jamur)',
    'Bercak coklat',
    'Pucuk mati',
    'Embun bulu (jamur bulu halus)',
    'Hama penggerek daun (Hispa)',
    'Normal / Sehat',
    'Penyakit tungro (virus)',
]
CNN_SLUGS = ['hawar_daun_bakteri', 'garis_daun_bakteri', 'hawar_malai_bakteri', 'blas',
             'bercak_coklat', 'pucuk_mati', 'embun_bulu', 'hispa', 'normal', 'tungro']

PEST_GROUPS = {
    'bakteri': [0, 1, 2],
    'blas': [3],
    'bercak': [4],
    'penggerek': [5],
    'embun_bulu': [6],
    'hispa': [7],
    'tungro': [9],
}
N_SENSITIVE_GROUPS = ['blas', 'bakteri', 'hispa', 'penggerek']

DETECTION_THRESHOLD = 0.5

T = {
    'soil_moisture_irrigate': 35,
    'soil_moisture_saturated': 45,
    'vpd_high': 1.5,

    'temp_heat': 35.0,
    'temp_heat_flowering': 33.0,

    'blast_rh': 90, 'blast_t_min': 20, 'blast_t_max': 28,
    'blb_rh': 70, 'blb_t_min': 25, 'blb_t_max': 34,
    'blb_rain_24h': 50,

    'ec_saline': 3000,
    'tds_saline': 1920,

    'ph_acid': 5.5, 'ph_alkaline': 7.5,

    'n_avail_low': 80, 'n_avail_high': 200,
    'light_low_lux': 32400,
    'pressure_low_slp': 1000,
    'p_low': 6, 'p_medium': 10,
    'k_low': 78, 'k_medium': 156,

    'wind_lodging': 8.0,
    'soil_temp_low': 17.5,
    'soil_temp_high': 34.0,

    'reproductive_days': 35, 'ripening_days': 30, 'flowering_window': 7,
    'drain_before_harvest': 10,
}

THRESHOLD_SOURCES = {
    'soil_moisture_irrigate': ['MAHMUD2026', 'CARRIJO2017', 'LAMPAYAN2015', 'IRRI_AWD'],
    'soil_moisture_saturated': ['MAHMUD2026', 'BOUMAN2001', 'IRRI_WATER', 'OPERASIONAL'],
    'vpd_high': ['GROSSIORD2020'], 'temp_heat': ['JAGADISH2007'], 'temp_heat_flowering': ['JAGADISH2007'],
    'blast_rh': ['SAVARY2012', 'UCIPM_BLAST', 'ASIBI2019'], 'blb_rh': ['IRRI_BLB'], 'blb_rain_24h': ['IRRI_BLB', 'BMKG_HUJAN'],
    'ec_saline': ['MAAS1977', 'ZENG2000', 'ZHENG2023'], 'tds_saline': ['MAAS1977', 'FAO29'], 'ph_acid': ['BALITTANAH'],
    'ph_alkaline': ['BALITTANAH', 'ALLOWAY2009', 'IMPA2012', 'IRRI_ZN'], 'urea_timing': ['SHUKLA2004', 'YANG2003', 'BWD'],
    'p_low': ['DOBERMANN_FAIRHURST2000', 'DOBERMANN2002', 'PUTS'], 'k_low': ['DOBERMANN_FAIRHURST2000', 'DOBERMANN2002', 'PUTS'],
    'wind_lodging': ['HITAKA1969', 'BERRY2004'],
    'n_avail_low': ['SATO2023'], 'n_avail_high': ['SATO2023'], 'light_low_lux': ['PANDA2026', 'THIMIJAN1983'],
    'pressure_low_slp': ['BMKG_SIKLON'],
    'soil_temp_low': ['LI2019_RHIZO'], 'soil_temp_high': ['CHAKRABORTY2022', 'LI2019_RHIZO'], 'reproductive_days': ['IRRI_GROWTH'],
}

NEUTRAL = {
    'temperature': 28.0, 'humidity': 80.0, 'windspeed': 1.5, 'rainfall': 0.0,
    'light_intensity': 600.0, 'ph': 6.3, 'soil_moisture': 48.0, 'ec': 800.0,
    'tds': 500.0, 'soil_temp': 29.0, 'nitrogen': 35.0, 'phosphorus': 15.0, 'potassium': 160.0,
}

PHASES = ['vegetatif', 'reproduktif', 'berbunga', 'pemasakan']

ACT_REPELLER = 'activate_ultrasonic_repeller_and_uv_lamp'
ACT_SPRAY = 'recommend_selective_insecticide_spraying'
ACT_IRRIGATION = 'irrigation_notification'
ACT_MONITOR = 'continue_monitoring'

ACTIONS = {
    'pengendalian_hispa': {
        'title': 'Basmi hama putih palsu (hispa)', 'type': 'danger', 'icon': 'bx bx-broadcast',
        'message': 'Nyalakan alat pengusir hama (suara) dan lampu perangkap. Potong ujung daun yang ada telurnya lalu '
                   'benamkan ke lumpur (bisa menekan ulat 75–92%). Jangan memberi pupuk urea berlebihan.',
        'actuation': ACT_REPELLER, 'actuators': ['ultrasonic_repeller', 'uv_lamp'], 'sources': ['IRRI_HISPA'],
    },
    'eradikasi_tungro': {
        'title': 'Cabut tanaman yang kena tungro', 'type': 'danger', 'icon': 'bx bx-trash',
        'message': 'Tungro tidak bisa diobati. Cabut rumpun yang sakit lalu benamkan ke lumpur. Semprotan obat wereng '
                   'hijau sering tidak mempan. Musim depan pakai varietas tahan tungro dan tanam serempak dengan tetangga.',
        'actuation': ACT_MONITOR, 'actuators': [], 'sources': ['AZZAM2002', 'IRRI_TUNGRO'],
    },
    'pengendalian_penggerek': {
        'title': 'Basmi hama penggerek batang', 'type': 'danger', 'icon': 'bx bx-bug',
        'message': 'Tinggikan air di sawah supaya telur hama tenggelam. Kumpulkan kelompok telur lalu musnahkan. Beri '
                   'pupuk urea sesuai dosis dan dibagi beberapa kali. Semprot obat hanya bila serangan sudah banyak '
                   '(tanyakan dulu ke petugas pengamat hama/penyuluh).',
        'actuation': ACT_SPRAY, 'actuators': [], 'sources': ['IRRI_STEMBORER'],
    },
    'pengendalian_bakteri': {
        'title': 'Atasi penyakit kresek (hawar daun bakteri)', 'type': 'danger', 'icon': 'bx bx-shield-plus',
        'message': 'Tunda atau kurangi pupuk urea. Buang kelebihan air dari sawah (kecuali saat padi berbunga). '
                   'Bersihkan rumput, jerami, dan tunas sisa panen yang sakit. Musim depan pakai varietas tahan.',
        'actuation': ACT_MONITOR, 'actuators': [], 'sources': ['MEW1987', 'IRRI_BLB'],
    },
    'fungisida_blas': {
        'title': 'Atasi penyakit blas', 'type': 'danger', 'icon': 'bx bx-shield-quarter',
        'message': 'Usahakan sawah tetap tergenang. Beri pupuk urea sedikit-sedikit beberapa kali. Semprot obat jamur '
                   '(fungisida berbahan triazol atau strobilurin) seperlunya, paling ampuh saat padi bunting sampai keluar malai.',
        'actuation': ACT_SPRAY, 'actuators': [], 'sources': ['ASIBI2019', 'UCIPM_BLAST', 'IRRI_BLAST'],
    },
    'pengendalian_embun_bulu': {
        'title': 'Atasi penyakit embun bulu', 'type': 'danger', 'icon': 'bx bx-shield-quarter',
        'message': 'Jangan biarkan tanah terlalu becek terlalu lama pada tanaman muda; perbaiki saluran pembuangan air '
                   'di persemaian/petak. Cabut tanaman yang sakit. Musim depan obati benih dengan obat jamur '
                   '(metalaksil atau mankozeb) sebelum disemai.',
        'actuation': ACT_MONITOR, 'actuators': [], 'sources': ['CABI_DOWNY'],
    },
    'perbaikan_hara_bercak': {
        'title': 'Bercak coklat: suburkan tanah', 'type': 'warning', 'icon': 'bx bx-leaf',
        'message': 'Bercak coklat sering muncul di tanah yang kurang subur atau kering. Tambahkan pupuk kalium dan '
                   'silika sesuai hasil uji tanah, dan jaga sawah tetap tergenang. Musim depan obati benih dengan obat '
                   'jamur atau rendam dalam air panas 53–54 °C.',
        'actuation': ACT_MONITOR, 'actuators': [], 'sources': ['BARNWAL2013', 'IRRI_BROWNSPOT'],
    },
    'waspada_penyakit': {
        'title': 'Waspada cuaca pemicu penyakit', 'type': 'warning', 'icon': 'bx bx-error',
        'message': 'Cuaca saat ini mudah memicu penyakit blas dan kresek. Periksa tanaman lebih sering, jaga sawah tetap '
                   'tergenang, jangan memberi urea berlebihan, dan bersihkan jerami/rumput yang sakit.',
        'actuation': ACT_MONITOR, 'actuators': [], 'sources': ['SAVARY2012', 'UCIPM_BLAST', 'IRRI_BLB', 'BMKG_HUJAN'],
    },
    'mitigasi_panas': {
        'title': 'Lindungi padi dari udara panas', 'type': 'danger', 'icon': 'bx bx-sun',
        'message': 'Udara terlalu panas bisa membuat gabah hampa, terutama saat padi berbunga. Jaga air di sawah '
                   'setinggi ±5 cm agar tanaman lebih sejuk.',
        'actuation': ACT_IRRIGATION, 'actuators': ['irrigation_valve'], 'sources': ['JAGADISH2007', 'IRRI_WATER'],
    },
    'bilas_garam': {
        'title': 'Tanah terlalu asin: bilas sawah', 'type': 'danger', 'icon': 'bx bx-water',
        'message': 'Kadar garam tanah melewati batas yang bisa ditahan padi (hasil panen turun ±12% setiap kenaikan '
                   'kadar garam 1 dS/m). Alirkan air irigasi tawar ke sawah lalu buang airnya lewat saluran pembuangan.',
        'actuation': ACT_IRRIGATION, 'actuators': ['irrigation_valve'], 'sources': ['MAAS1977', 'ZENG2000', 'ZHENG2023', 'FAO29'],
    },
    'irigasi': {
        'title': 'Airi sawah kembali', 'type': 'warning', 'icon': 'bx bx-droplet',
        'message': 'Genangi sawah lagi setinggi ±5 cm. Cara hemat air yang aman: airi lagi saat kadar air tanah turun ke '
                   '±35% (air sekitar 15 cm di bawah permukaan tanah). Mulai seminggu sebelum sampai sesudah padi '
                   'berbunga, sawah harus selalu tergenang.',
        'actuation': ACT_IRRIGATION, 'actuators': ['irrigation_valve'], 'sources': ['MAHMUD2026', 'CARRIJO2017', 'IRRI_AWD', 'IRRI_WATER'],
    },
    'tunda_urea': {
        'title': 'Tunda atau kurangi pupuk urea', 'type': 'warning', 'icon': 'bx bx-leaf',
        'message': 'Ada hama/penyakit yang makin parah bila pupuk urea berlebihan (blas, kresek, hispa, penggerek '
                   'batang). Tunda pemupukan urea, lalu berikan secukupnya dan dibagi beberapa kali.',
        'actuation': ACT_MONITOR, 'actuators': [], 'sources': ['MEW1987', 'UCIPM_BLAST', 'IRRI_HISPA', 'IRRI_STEMBORER'],
    },
    'urea_bwd': {
        'title': 'Pupuk urea sesuai warna daun', 'type': 'info', 'icon': 'bx bx-leaf',
        'message': 'Ini masa pemupukan urea (sebelum padi berbunga). Cocokkan warna daun dengan bagan warna daun secara '
                   'rutin: bila lebih pucat dari skala 4 (padi hibrida: skala 4–5), beri urea 50–75 kg/ha '
                   '(target panen 5 ton/ha gabah kering giling).',
        'actuation': ACT_MONITOR, 'actuators': [], 'sources': ['SHUKLA2004', 'YANG2003', 'ISLAM2007', 'BWD'],
    },
    'pupuk_p': {
        'title': 'Tambah pupuk fosfat (SP-36)', 'type': 'warning', 'icon': 'bx bx-leaf',
        'message': 'Kandungan fosfat tanah kurang. Berikan SP-36 sebagai pupuk dasar sesuai hasil alat uji tanah sawah (PUTS).',
        'actuation': ACT_MONITOR, 'actuators': [], 'sources': ['DOBERMANN2002', 'DOBERMANN_FAIRHURST2000', 'PUTS'],
    },
    'pupuk_k': {
        'title': 'Tambah pupuk kalium (KCl)', 'type': 'warning', 'icon': 'bx bx-leaf',
        'message': 'Kandungan kalium tanah kurang. Berikan KCl 100 kg/ha (cukup 50 kg/ha bila jerami dikembalikan ke sawah).',
        'actuation': ACT_MONITOR, 'actuators': [], 'sources': ['DOBERMANN2002', 'DOBERMANN_FAIRHURST2000', 'PUTS'],
    },
    'kapur': {
        'title': 'Beri kapur untuk tanah masam', 'type': 'warning', 'icon': 'bx bx-test-tube',
        'message': 'Tanah terlalu masam. Taburkan kapur pertanian (dolomit) saat mengolah tanah dan tambahkan pupuk kandang/kompos.',
        'actuation': ACT_MONITOR, 'actuators': [], 'sources': ['BALITTANAH'],
    },
    'cegah_defisiensi_zn': {
        'title': 'Tanah basa: cegah kekurangan seng (Zn)', 'type': 'warning', 'icon': 'bx bx-test-tube',
        'message': 'Di tanah basa, padi sulit menyerap unsur seng. Perhatikan gejala kekurangan seng lalu beri pupuk '
                   'ZnSO4; pakai pupuk ZA sebagai sumber nitrogen.',
        'actuation': ACT_MONITOR, 'actuators': [], 'sources': ['ALLOWAY2009', 'IMPA2012', 'IRRI_ZN'],
    },
    'proteksi_angin': {
        'title': 'Waspada padi rebah karena angin kencang', 'type': 'warning', 'icon': 'bx bx-wind',
        'message': 'Angin kencang bisa merobohkan padi yang sedang bunting sampai masak. Tunda penyemprotan, jangan '
                   'memberi urea berlebihan, dan periksa pematang serta saluran air.',
        'actuation': ACT_MONITOR, 'actuators': [], 'sources': ['BERRY2004', 'HITAKA1969'],
    },
    'pantau': {
        'title': 'Kondisi aman, lanjutkan pengamatan', 'type': 'success', 'icon': 'bx bx-check-shield',
        'message': 'Tidak ada hama/penyakit dan kondisi sawah aman.',
        'actuation': ACT_MONITOR, 'actuators': [], 'sources': [],
    },
}

ACTION_IDS = list(ACTIONS.keys())

ACTUATION_PRIORITY = ['pengendalian_hispa', 'fungisida_blas', 'pengendalian_penggerek', 'mitigasi_panas',
                      'bilas_garam', 'irigasi', 'eradikasi_tungro', 'pengendalian_bakteri', 'pengendalian_embun_bulu']


def vpd_kpa(temperature, humidity):
    svp = 0.6108 * math.exp((17.27 * temperature) / (temperature + 237.3))
    return svp * (1 - humidity / 100.0)


def crop_phase(hst, umur_varietas=115, umur_bibit=21):

    if hst is None or hst < 0:
        return None
    das = hst + umur_bibit
    flowering_day = umur_varietas - T['ripening_days']
    if abs(das - flowering_day) <= T['flowering_window']:
        phase = 'berbunga'
    elif das < umur_varietas - T['ripening_days'] - T['reproductive_days']:
        phase = 'vegetatif'
    elif das < flowering_day:
        phase = 'reproduktif'
    else:
        phase = 'pemasakan'
    return {
        'fase': phase,
        'hst': hst,
        'menjelang_panen': das >= umur_varietas - T['drain_before_harvest'],
    }


def pest_conf(v, group):
    return max(v.get(f'pest_{CNN_SLUGS[i]}', 0.0) for i in PEST_GROUPS[group])


def detected(v, group):
    return pest_conf(v, group) >= DETECTION_THRESHOLD


def phase_of(v):
    if not v.get('fase_diketahui'):
        return None
    for p in PHASES:
        if v.get(f'fase_{p}'):
            return p
    return None


def blast_weather(v):
    return v['humidity'] >= T['blast_rh'] and T['blast_t_min'] <= v['temperature'] <= T['blast_t_max']


def blb_weather(v):
    return (T['blb_t_min'] <= v['temperature'] <= T['blb_t_max'] and v['humidity'] > T['blb_rh']
            and v['rainfall'] >= T['blb_rain_24h'])


def label_actions(v):
    phase = phase_of(v)
    flowering = phase == 'berbunga'
    near_harvest = bool(v.get('menjelang_panen'))
    n_sensitive = any(detected(v, g) for g in N_SENSITIVE_GROUPS)
    actions = set()

    if detected(v, 'hispa'):
        actions.add('pengendalian_hispa')
    if detected(v, 'tungro'):
        actions.add('eradikasi_tungro')
    if detected(v, 'penggerek'):
        actions.add('pengendalian_penggerek')
    if detected(v, 'bakteri'):
        actions.add('pengendalian_bakteri')
    if detected(v, 'blas') or (blast_weather(v) and flowering):
        actions.add('fungisida_blas')
    if detected(v, 'embun_bulu'):
        actions.add('pengendalian_embun_bulu')
    if detected(v, 'bercak'):
        actions.add('perbaikan_hara_bercak')
    if (blast_weather(v) or blb_weather(v)) and not (detected(v, 'blas') or detected(v, 'bakteri')) \
            and 'fungisida_blas' not in actions:
        actions.add('waspada_penyakit')

    if v['temperature'] >= T['temp_heat'] or (flowering and v['temperature'] >= T['temp_heat_flowering']):
        actions.add('mitigasi_panas')
    if v['ec'] >= T['ec_saline'] or v['tds'] >= T['tds_saline']:
        actions.add('bilas_garam')

    if not near_harvest:
        sm = v['soil_moisture']
        if sm < T['soil_moisture_irrigate'] or (flowering and sm < T['soil_moisture_saturated']):
            actions.add('irigasi')

    if n_sensitive:
        actions.add('tunda_urea')
    elif phase in ('vegetatif', 'reproduktif'):
        actions.add('urea_bwd')

    if phase in (None, 'vegetatif'):
        if v['phosphorus'] < T['p_medium']:
            actions.add('pupuk_p')
        if v['potassium'] < T['k_low']:
            actions.add('pupuk_k')

    if v['ph'] <= T['ph_acid']:
        actions.add('kapur')
    elif v['ph'] > T['ph_alkaline']:
        actions.add('cegah_defisiensi_zn')

    if v['windspeed'] >= T['wind_lodging'] and phase in (None, 'reproduktif', 'berbunga', 'pemasakan'):
        actions.add('proteksi_angin')

    if not actions:
        actions.add('pantau')
    return actions


def explain(action, v):
    r = []
    phase = phase_of(v)

    def pest_reason(group):
        best = max(PEST_GROUPS[group], key=lambda i: v.get(f'pest_{CNN_SLUGS[i]}', 0.0))
        conf = v.get(f'pest_{CNN_SLUGS[best]}', 0.0)
        if conf >= DETECTION_THRESHOLD:
            r.append(f'Kamera mendeteksi {CNN_CLASSES[best]} (yakin {conf * 100:.0f}%)')

    if action == 'pengendalian_hispa':
        pest_reason('hispa')
    elif action == 'eradikasi_tungro':
        pest_reason('tungro')
    elif action == 'pengendalian_penggerek':
        pest_reason('penggerek')
    elif action == 'pengendalian_bakteri':
        pest_reason('bakteri')
        if phase == 'berbunga':
            r.append('padi sedang berbunga: jangan keringkan sawah')
    elif action == 'fungisida_blas':
        pest_reason('blas')
        if blast_weather(v):
            r.append(f"kelembapan udara {v['humidity']:.0f}% dan suhu {v['temperature']:.1f} °C cocok untuk jamur blas")
        if phase == 'berbunga':
            r.append('padi keluar malai/berbunga: rawan blas leher')
    elif action == 'pengendalian_embun_bulu':
        pest_reason('embun_bulu')
    elif action == 'perbaikan_hara_bercak':
        pest_reason('bercak')
        if v['potassium'] < T['k_medium']:
            r.append(f"kalium tanah {v['potassium']:.0f} mg/kg belum cukup")
    elif action == 'waspada_penyakit':
        if blast_weather(v):
            r.append(f"kelembapan udara {v['humidity']:.0f}% dan suhu {v['temperature']:.1f} °C cocok untuk jamur blas")
        if blb_weather(v):
            r.append(f"hujan lebat {v['rainfall']:.0f} mm sehari, suhu {v['temperature']:.0f} °C, kelembapan {v['humidity']:.0f}%: cocok untuk penyakit kresek")
    elif action == 'mitigasi_panas':
        r.append(f"suhu udara {v['temperature']:.1f} °C")
        if phase == 'berbunga':
            r.append(f"padi sedang berbunga (gabah mulai hampa bila ≥{T['temp_heat_flowering']:.0f} °C)")
    elif action == 'bilas_garam':
        if v['ec'] >= T['ec_saline']:
            r.append(f"kadar garam tanah (EC) {v['ec']:.0f} µS/cm, batas {T['ec_saline']}")
        if v['tds'] >= T['tds_saline']:
            r.append(f"zat terlarut tanah (TDS) {v['tds']:.0f} ppm, batas {T['tds_saline']}")
    elif action == 'irigasi':
        if v['soil_moisture'] < T['soil_moisture_irrigate']:
            r.append(f"kadar air tanah {v['soil_moisture']:.0f}%")
        if phase == 'berbunga':
            r.append(f"padi sedang berbunga: kadar air {v['soil_moisture']:.0f}%, belum tergenang (±{T['soil_moisture_saturated']}%)")
    elif action == 'tunda_urea':
        sensitive = [g for g in N_SENSITIVE_GROUPS if detected(v, g)]
        if sensitive:
            nama = {'blas': 'blas', 'bakteri': 'kresek', 'hispa': 'hispa', 'penggerek': 'penggerek batang'}
            r.append('terdeteksi ' + ', '.join(nama.get(g, g) for g in sensitive) + ' (makin parah bila urea berlebihan)')
    elif action == 'urea_bwd':
        r.append(f"padi fase {phase} (umur {int(v.get('hst', 0))} hari setelah tanam): masa pemupukan urea")
        r.append(f"sensor nitrogen {v['nitrogen']:.0f} mg/kg (hanya perkiraan, bukan dasar dosis)")
    elif action == 'pupuk_p':
        p = v['phosphorus']
        dose = '100–125 kg SP-36/ha' if p < T['p_low'] else '75 kg SP-36/ha'
        status = 'rendah' if p < T['p_low'] else 'sedang'
        r.append(f"fosfat tanah {p:.0f} mg/kg ({status}): berikan {dose}")
    elif action == 'pupuk_k':
        r.append(f"kalium tanah {v['potassium']:.0f} mg/kg, kurang dari batas {T['k_low']} mg/kg")
    elif action == 'kapur':
        r.append(f"pH tanah {v['ph']:.1f} (masam, batas {T['ph_acid']})")
    elif action == 'cegah_defisiensi_zn':
        r.append(f"pH tanah {v['ph']:.1f} (basa, di atas {T['ph_alkaline']})")
    elif action == 'proteksi_angin':
        r.append(f"angin {v['windspeed']:.1f} m/s (batas aman {T['wind_lodging']:.0f} m/s)")
    return r


def sources_for(action):
    return [{'key': k, **SOURCES[k]} for k in ACTIONS[action]['sources']]
