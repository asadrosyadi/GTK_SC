import sys

from .engine import RecommendationEngine
from .features import build_features
from .knowledge_base import label_actions

SAWAH_NORMAL = {'ph': 6.3, 'soil_moisture': 50, 'ec': 800, 'tds': 500, 'soil_temp': 29,
                'Nitrogen_Level': 35, 'Phosphorus_Level': 15, 'Potassium_Level': 160}
CUACA_NORMAL = {'temperature': 29, 'humidity': 78, 'windspeed': 1.5, 'rainfall': 0, 'light_intensity': 600}
SEHAT = {'penyakit': 'Normal / Sehat', 'probabilitas': 92}


def tanam(hst, umur=115):
    return {'hst': hst, 'umur_varietas': umur, 'umur_bibit': 21}


SCENARIOS = [
    ('Sawah tergenang normal, vegetatif -> hanya pantau BWD', {}, {}, SEHAT, tanam(20),
     {'urea_bwd'}, {'irigasi', 'pupuk_k', 'pupuk_p', 'tunda_urea'}, 'MAHMUD2026, BOUMAN2001, SHUKLA2004, BWD'),
    ('Blas terdeteksi -> genangi & fungisida, tunda urea', {'humidity': 92, 'temperature': 26}, {},
     {'penyakit': 'Penyakit blas (jamur)', 'probabilitas': 85}, tanam(25),
     {'fungisida_blas', 'tunda_urea'}, {'urea_bwd'}, 'ASIBI2019, UCIPM_BLAST, MEW1987'),
    ('Hawar bakteri saat berbunga (VWC 40%) -> tanpa bakterisida, tetap tergenang', {}, {'soil_moisture': 40},
     {'penyakit': 'Hawar daun bakteri', 'probabilitas': 80}, tanam(64),
     {'pengendalian_bakteri', 'irigasi', 'tunda_urea'}, {'urea_bwd'}, 'MEW1987, IRRI_BLB, IRRI_WATER'),
    ('Tungro -> eradikasi, bukan insektisida', {}, {}, {'penyakit': 'Penyakit tungro (virus)', 'probabilitas': 77},
     tanam(30), {'eradikasi_tungro'}, {'pengendalian_penggerek'}, 'AZZAM2002, IRRI_TUNGRO'),
    ('Suhu 34 C saat berbunga -> mitigasi panas', {'temperature': 34, 'humidity': 65}, {}, SEHAT, tanam(64),
     {'mitigasi_panas'}, set(), 'JAGADISH2007'),
    ('Suhu 34 C fase vegetatif -> belum kritis', {'temperature': 34, 'humidity': 65}, {}, SEHAT, tanam(15),
     set(), {'mitigasi_panas'}, 'JAGADISH2007'),
    ('EC 2000 uS/cm -> masih di bawah ambang 3 dS/m', {}, {'ec': 2000, 'tds': 1200}, SEHAT, tanam(20),
     set(), {'bilas_garam'}, 'MAAS1977, ZENG2000'),
    ('EC 3500 uS/cm -> bilas garam', {}, {'ec': 3500, 'tds': 2240}, SEHAT, tanam(20),
     {'bilas_garam'}, set(), 'MAAS1977, ZENG2000, ZHENG2023'),
    ('pH 7,8 -> risiko Zn', {}, {'ph': 7.8}, SEHAT, tanam(20),
     {'cegah_defisiensi_zn'}, {'kapur'}, 'BALITTANAH, ALLOWAY2009, IMPA2012'),
    ('Fase pemasakan -> tidak menambah urea (meski N sensor rendah)', {}, {'Nitrogen_Level': 12}, SEHAT, tanam(80),
     set(), {'urea_bwd'}, 'SHUKLA2004, BWD (N sebelum berbunga)'),
    ('Menjelang panen, VWC 25% -> tidak diairi', {}, {'soil_moisture': 25}, SEHAT, tanam(88),
     set(), {'irigasi'}, 'IRRI_WATER (kering 7-10 hari sebelum panen)'),
    ('VWC 30% fase vegetatif -> irigasi AWD', {}, {'soil_moisture': 30}, SEHAT, tanam(20),
     {'irigasi'}, set(), 'MAHMUD2026, CARRIJO2017'),
    ('VWC 40% vegetatif -> belum perlu irigasi (AWD aman)', {}, {'soil_moisture': 40}, SEHAT, tanam(20),
     set(), {'irigasi'}, 'MAHMUD2026, CARRIJO2017'),
    ('Hujan lebat 60 mm/24 jam, 28 C, RH 88% -> waspada hawar bakteri', {'rainfall': 60, 'temperature': 28, 'humidity': 88},
     {}, SEHAT, tanam(30), {'waspada_penyakit'}, set(), 'IRRI_BLB, BMKG_HUJAN'),
    ('K 60 mg/kg vegetatif -> KCl; angin 9 m/s vegetatif -> belum rawan rebah', {'windspeed': 9},
     {'Potassium_Level': 60}, SEHAT, tanam(15), {'pupuk_k'}, {'proteksi_angin'}, 'DOBERMANN_FAIRHURST2000, HITAKA1969'),
]


def main():
    engine = RecommendationEngine()
    failures = 0
    for name, w, s, cam, fase, must, forbid, src in SCENARIOS:
        weather = {**CUACA_NORMAL, **w}
        soil = {**SAWAH_NORMAL, **s}
        out = engine.recommend_petak(weather, soil, cam, None, fase)
        rf = {r['action'] for r in out['recommendations']}
        rules = label_actions(build_features(weather, soil, cam, None, fase)[0])
        ok_rf = must <= rf and not (forbid & rf)
        ok_rules = must <= rules and not (forbid & rules)
        failures += (not ok_rf) + (not ok_rules)
        print(f"[{'OK ' if ok_rf and ok_rules else 'GAGAL'}] {name}  ({src})")
        print(f"        fase={out['fase']} | RF: {sorted(rf)}")
        if not ok_rules:
            print(f"        aturan: {sorted(rules)}")
    print(f"\n{len(SCENARIOS)}/{len(SCENARIOS)} skenario sesuai pedoman" if failures == 0 else f"\n{failures} kegagalan")
    return 1 if failures else 0


if __name__ == '__main__':
    sys.exit(main())
