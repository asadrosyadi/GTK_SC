#include "konfigurasi.h"

#include <WiFi.h>
#include <esp_now.h>
#include <esp_wifi.h>
#include <Preferences.h>

#include "taniverse_espnow.h"
#include "taniverse_common.h"
#include "lapangan_tipe.h"

QueueHandle_t qMasuk = nullptr;
SemaphoreHandle_t semKirim = nullptr;
volatile bool kirimSukses = false;
Preferences pref;

uint8_t kanal = 1;
uint32_t sampelId = 0;
uint8_t* bufGambar = nullptr;
size_t kapGambar = 0;

uint32_t epochA = 0, tEpochA = 0;
uint8_t aktuator = 0;
uint32_t tKabarA = 0;
uint32_t tAckTerakhir = 0;

uint32_t tSensorBerikut = 0, tFotoBerikut = 0;
bool sensorTertunda = false;
TvSensor paketTertunda;
uint32_t tCobaSensor = 0;
uint8_t cobaFoto = 0;

bool jamFotoOk() {
  if (!epochA) return true;
  uint32_t sekarang = epochA + (millis() - tEpochA) / 1000;
  int jam = ((sekarang + OFFSET_ZONA_DETIK) / 3600) % 24;
  return jam >= JAM_FOTO_MULAI && jam < JAM_FOTO_SELESAI;
}

void terapkanRelay() {
#if PAKAI_AKTUATOR
  uint8_t bit = (millis() - tKabarA > AKTUATOR_MATI_JIKA_PUTUS_MS) ? 0 : aktuator;
  digitalWrite(PIN_RELAY_UV, (bit & TV_AKT_UV) ? LOW : HIGH);
  digitalWrite(PIN_RELAY_ULTRASONIK, (bit & TV_AKT_ULTRASONIK) ? LOW : HIGH);
  digitalWrite(PIN_RELAY_IRIGASI, (bit & TV_AKT_IRIGASI) ? LOW : HIGH);
#endif
}

void catatKabarA(uint32_t epoch, uint8_t akt) {
  if (epoch) {
    epochA = epoch;
    tEpochA = millis();
  }
  if (akt != aktuator) Serial.printf("[aktuator] perintah petak 2: 0x%02X\n", akt);
  aktuator = akt;
  tKabarA = millis();
  terapkanRelay();
}

#if ESP_IDF_VERSION >= ESP_IDF_VERSION_VAL(5, 5, 0)
void onKirim(const esp_now_send_info_t*, esp_now_send_status_t status) {
#else
void onKirim(const uint8_t*, esp_now_send_status_t status) {
#endif
  kirimSukses = status == ESP_NOW_SEND_SUCCESS;
  xSemaphoreGive(semKirim);
}

static void simpanPaket(const uint8_t* mac, const uint8_t* data, int len) {
  if (memcmp(mac, MAC_NODE_A, 6) != 0 || len <= 0 || len > TV_MAKS_PAYLOAD) return;
  PaketMasuk p;
  memcpy(p.data, data, len);
  p.len = len;
  xQueueSend(qMasuk, &p, 0);
}

#if ESP_ARDUINO_VERSION_MAJOR >= 3
void onTerima(const esp_now_recv_info_t* info, const uint8_t* data, int len) { simpanPaket(info->src_addr, data, len); }
#else
void onTerima(const uint8_t* mac, const uint8_t* data, int len) { simpanPaket(mac, data, len); }
#endif

bool kirimKeA(const void* data, size_t len, int coba = 5) {
  for (int i = 0; i < coba; i++) {
    xSemaphoreTake(semKirim, 0);
    if (esp_now_send(MAC_NODE_A, (const uint8_t*)data, len) == ESP_OK &&
        xSemaphoreTake(semKirim, pdMS_TO_TICKS(150)) == pdTRUE && kirimSukses) {
      tAckTerakhir = millis();
      return true;
    }
    delay(5 * (i + 1));
  }
  return false;
}

bool prosesPaketA(const PaketMasuk& p, uint8_t untukTipe, uint32_t ref, TvBalasan* hasil) {
  if (!tvHeaderValid(p.data, p.len)) return false;
  uint8_t tipe = ((const TvHeader*)p.data)->tipe;
  if (tipe == TV_PERINTAH && p.len >= (int)sizeof(TvPerintah)) {
    const TvPerintah* c = (const TvPerintah*)p.data;
    catatKabarA(c->epoch, c->aktuator);
  } else if (tipe == TV_BALASAN && p.len >= (int)sizeof(TvBalasan)) {
    const TvBalasan* b = (const TvBalasan*)p.data;
    catatKabarA(b->epoch, b->aktuator);
    if (hasil && b->untuk_tipe == untukTipe && b->ref_id == ref) {
      *hasil = *b;
      return true;
    }
  }
  return false;
}

bool tungguBalasan(uint8_t untukTipe, uint32_t ref, TvBalasan& hasil, uint32_t batasMs) {
  uint32_t mulai = millis();
  PaketMasuk p;
  while (millis() - mulai < batasMs) {
    uint32_t sisa = batasMs - (millis() - mulai);
    if (xQueueReceive(qMasuk, &p, pdMS_TO_TICKS(sisa)) == pdTRUE && prosesPaketA(p, untukTipe, ref, &hasil))
      return true;
  }
  return false;
}

void layaniPaketMasuk() {
  PaketMasuk p;
  while (xQueueReceive(qMasuk, &p, 0) == pdTRUE) prosesPaketA(p, 0, 0, nullptr);
}

void aturKanal(uint8_t ch) {
  WiFi.softAP(AP_KAMERA_SSID, AP_KAMERA_PASS, ch);
  kanal = ch;
}

bool sapaA() {
  TvHello h;
  tvIsiHeader(h.h, TV_HELLO);
  h.nonce = esp_random();
  if (!kirimKeA(&h, sizeof(h), 2)) return false;
  TvBalasan b;
  tungguBalasan(TV_HELLO, h.nonce, b, 300);
  return true;
}

uint32_t tCariTerakhir = 0;
bool sudahPernahCari = false;

bool cariKanal() {
  if (sudahPernahCari && millis() - tCariTerakhir < 300000UL) return false;
  sudahPernahCari = true;
  tCariTerakhir = millis();
  if (KANAL_TETAP) {
    if (kanal != KANAL_TETAP) aturKanal(KANAL_TETAP);
    return sapaA();
  }
  uint8_t simpan = pref.getUChar("kanal", 1);
  uint8_t urutan[14];
  int n = 0;
  urutan[n++] = simpan;
  for (uint8_t ch = 1; ch <= 13; ch++)
    if (ch != simpan) urutan[n++] = ch;
  for (int i = 0; i < n; i++) {
    aturKanal(urutan[i]);
    delay(50);
    if (sapaA()) {
      if (urutan[i] != simpan) pref.putUChar("kanal", urutan[i]);
      Serial.printf("[espnow] ESP32 A ditemukan di kanal %d\n", urutan[i]);
      return true;
    }
  }
  Serial.println("[espnow] ESP32 A tidak ditemukan di kanal 1-13 (cek MAC, kunci, jarak)");
  aturKanal(simpan);
  return false;
}

void mulaiEspNow() {
  if (esp_now_init() != ESP_OK) {
    Serial.println("[espnow] init gagal, restart");
    delay(1000);
    ESP.restart();
  }
#if PAKAI_ENKRIPSI_ESPNOW
  esp_now_set_pmk((const uint8_t*)ESPNOW_PMK);
#endif
  esp_now_register_send_cb(onKirim);
  esp_now_register_recv_cb(onTerima);
  esp_now_peer_info_t peer = {};
  memcpy(peer.peer_addr, MAC_NODE_A, 6);
  peer.channel = 0;
  peer.ifidx = WIFI_IF_AP;
#if PAKAI_ENKRIPSI_ESPNOW
  peer.encrypt = true;
  memcpy(peer.lmk, ESPNOW_LMK, 16);
#endif
  if (esp_now_add_peer(&peer) != ESP_OK) Serial.println("[espnow] gagal menambah peer A (cek MAC_NODE_A)");
}

TvSensor bacaPaketSensor() {
  DataTanah d = bacaSensorTanah();
  cetakDataTanah("titik 2", d);
  TvSensor s = {};
  tvIsiHeader(s.h, TV_SENSOR);
  s.sampel_id = ++sampelId;
  s.valid = 0;
  auto nilai = [&](float v, uint16_t bit) -> float {
    if (isnan(v)) return 0;
    s.valid |= bit;
    return v;
  };
  s.ph = nilai(d.ph, TV_V_PH);
  s.vwc = nilai(d.vwc, TV_V_VWC);
  s.ec = nilai(d.ec, TV_V_EC);
  s.tds = nilai(d.tds, TV_V_TDS);
  s.suhu_tanah = nilai(d.suhu, TV_V_SUHU_TANAH);
  s.n = nilai(d.n, TV_V_N);
  s.p = nilai(d.p, TV_V_P);
  s.k = nilai(d.k, TV_V_K);
#if PAKAI_BATERAI
  s.baterai = persenBaterai(bacaTeganganBaterai());
  s.valid |= TV_V_BATERAI;
#endif
  return s;
}

bool kirimSensor(TvSensor& s) {
  bool ok = kirimKeA(&s, sizeof(s));
  if (!ok && cariKanal()) ok = kirimKeA(&s, sizeof(s));
  if (!ok) return false;
  TvBalasan b;
  tungguBalasan(TV_SENSOR, s.sampel_id, b, 1000);
  Serial.printf("[espnow] sensor sampel %lu terkirim ke A (kanal %d)\n", (unsigned long)s.sampel_id, kanal);
  return true;
}

uint8_t kirimGambarKeA(const uint8_t* buf, size_t len) {
  TvImgStart st;
  tvIsiHeader(st.h, TV_IMG_START);
  st.img_id = esp_random();
  st.ukuran = len;
  st.jumlah_chunk = (len + TV_CHUNK_DATA - 1) / TV_CHUNK_DATA;
  st.crc32 = tvCrc32(buf, len);

  TvBalasan b;
  if (!kirimKeA(&st, sizeof(st)) && !(cariKanal() && kirimKeA(&st, sizeof(st)))) return 0xFF;
  if (!tungguBalasan(TV_IMG_START, st.img_id, b, 1000)) return 0xFF;
  if (b.status != TV_ST_OK) return b.status;

  TvImgChunk c;
  tvIsiHeader(c.h, TV_IMG_CHUNK);
  c.img_id = st.img_id;
  for (uint16_t i = 0; i < st.jumlah_chunk; i++) {
    size_t offset = (size_t)i * TV_CHUNK_DATA;
    c.indeks = i;
    c.panjang = (uint8_t)min((size_t)TV_CHUNK_DATA, len - offset);
    memcpy(c.data, buf + offset, c.panjang);
    if (!kirimKeA(&c, sizeof(TvImgChunk) - TV_CHUNK_DATA + c.panjang)) return 0xFF;
    if ((i & 15) == 15) delay(2);
  }

  TvImgEnd e;
  tvIsiHeader(e.h, TV_IMG_END);
  e.img_id = st.img_id;
  if (!kirimKeA(&e, sizeof(e))) return 0xFF;
  if (!tungguBalasan(TV_IMG_END, st.img_id, b, 3000)) return 0xFF;
  return b.status;
}

void ambilDanKirimFoto() {
  size_t n = ambilSnapshot(URL_KAMERA_B, USER_KAMERA_B, PASS_KAMERA_B, bufGambar, kapGambar);
  if (!n) return;
  uint8_t st = kirimGambarKeA(bufGambar, n);
  if (st == TV_ST_KURANG || st == TV_ST_CRC) st = kirimGambarKeA(bufGambar, n);
  Serial.printf("[espnow] gambar %u B -> status %u\n", (unsigned)n, st);
  if (st == TV_ST_SIBUK && ++cobaFoto <= 3) {
    tFotoBerikut = millis() + 30000UL;
  } else {
    cobaFoto = 0;
    if (st == TV_ST_MEMORI) Serial.println("[kamera] gambar terlalu besar untuk buffer A; kecilkan resolusi");
  }
}

void setup() {
  Serial.begin(115200);
  delay(300);
  Serial.println("\n=== TaniVerse ESP32 B (node lapangan petak 2) ===");

#if PAKAI_AKTUATOR
  pinMode(PIN_RELAY_UV, OUTPUT);
  pinMode(PIN_RELAY_ULTRASONIK, OUTPUT);
  pinMode(PIN_RELAY_IRIGASI, OUTPUT);
  tKabarA = millis() - AKTUATOR_MATI_JIKA_PUTUS_MS - 1;
  terapkanRelay();
#endif
  analogSetPinAttenuation(PIN_BATERAI, ADC_11db);
  mulaiSensorTanah();
#if DUMP_REGISTER
  dumpRegisterTanah(0x0000, 0x0024);
#endif
  bufGambar = alokasiBufferGambar(kapGambar, UKURAN_GAMBAR_MAKS, 40 * 1024);

  pref.begin("taniverse", false);
  qMasuk = xQueueCreate(8, sizeof(PaketMasuk));
  semKirim = xSemaphoreCreateBinary();

  WiFi.mode(WIFI_AP);
  kanal = KANAL_TETAP ? KANAL_TETAP : pref.getUChar("kanal", 1);
  aturKanal(kanal);
  WiFi.setSleep(false);
  Serial.printf(">>> MAC ESP-NOW node B (isi ke MAC_NODE_B di ESP32 A): %s\n", WiFi.softAPmacAddress().c_str());
  Serial.printf("Hotspot kamera: %s, IP %s\n", AP_KAMERA_SSID, WiFi.softAPIP().toString().c_str());

  mulaiEspNow();
  cariKanal();

  tSensorBerikut = millis() + 5000UL;
  tFotoBerikut = millis() + 60000UL;
}

void loop() {
  uint32_t now = millis();
  layaniPaketMasuk();
  terapkanRelay();

  if ((int32_t)(now - tSensorBerikut) >= 0) {
    tSensorBerikut = now + INTERVAL_SENSOR_MS;
    paketTertunda = bacaPaketSensor();
    sensorTertunda = true;
    tCobaSensor = now - 60000UL;
  }
  if (sensorTertunda && now - tCobaSensor >= 60000UL) {
    tCobaSensor = now;
    if (kirimSensor(paketTertunda)) sensorTertunda = false;
  }

#if PAKAI_KAMERA_B
  if ((int32_t)(now - tFotoBerikut) >= 0) {
    tFotoBerikut = now + INTERVAL_FOTO_MS;
    if (jamFotoOk() && bufGambar) ambilDanKirimFoto();
  }
#endif

  if (now - tAckTerakhir > 20UL * 60UL * 1000UL) {
    tAckTerakhir = now;
    cariKanal();
  }
  delay(20);
}
