#include "konfigurasi.h"

#include <WiFi.h>
#include <WiFiManager.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <DHT.h>
#include <BH1750.h>
#include <Adafruit_BMP280.h>
#include <esp_now.h>
#include <esp_wifi.h>
#include <time.h>
#if PAKAI_MQTT
#include <PubSubClient.h>
#endif

#include "taniverse_espnow.h"
#include "taniverse_common.h"
#include "gateway_tipe.h"

#define TITIK_A 1
#define TITIK_B 2

DHT dht(PIN_DHT, DHT22);
BH1750 lightMeter;
Adafruit_BMP280 bmp;
bool bhOk = false, bmpOk = false;

volatile uint32_t pulsaAngin = 0, tipHujan = 0;
volatile uint32_t tPulsaTerakhirUs = 0, tTipTerakhirMs = 0;

void IRAM_ATTR isrAngin() {
  uint32_t t = micros();
  if (t - tPulsaTerakhirUs >= 5000) {
    pulsaAngin = pulsaAngin + 1;
    tPulsaTerakhirUs = t;
  }
}

void IRAM_ATTR isrHujan() {
  uint32_t t = millis();
  if (t - tTipTerakhirMs >= 250) {
    tipHujan = tipHujan + 1;
    tTipTerakhirMs = t;
  }
}

uint32_t tJendelaAngin = 0, pulsaAwalJendela = 0;
double jumlahAngin = 0;
uint32_t nJendelaAngin = 0;
float anginMaks = 0;

uint32_t tipDasar = 0;

QueueHandle_t qMasuk = nullptr;
QueueHandle_t qSensorB = nullptr;
bool espnowSiap = false;
volatile bool adaMacAsing = false;
uint8_t macAsing[6];

enum StatusGambar : uint8_t { GB_KOSONG = 0, GB_MENERIMA = 1, GB_LENGKAP = 2 };
volatile uint8_t statusGambarB = GB_KOSONG;
uint8_t* bufGambarB = nullptr;
size_t kapGambarB = 0;
uint32_t idGambarB = 0, ukuranGambarB = 0, crcGambarB = 0, tMulaiGambarB = 0;
uint16_t jumlahChunkB = 0, chunkDiterimaB = 0;
uint8_t petaChunkB[(UKURAN_GAMBAR_MAKS / TV_CHUNK_DATA) / 8 + 2];
uint32_t sampelTerakhirB = 0xFFFFFFFF;
int8_t rssiGambarB = 0;

uint8_t* bufGambarA = nullptr;
size_t kapGambarA = 0;

volatile uint8_t aktuatorA = 0;
volatile uint8_t aktuatorB = 0;
bool malam = false;
uint32_t tAksiOkA = 0, tAksiOkB = 0;
#define AKSI_KEDALUWARSA_MS (30UL * 60UL * 1000UL)
uint8_t aktuatorBTerkirim = 0xFF;
uint32_t tPerintahB = 0;

#if PAKAI_MQTT
WiFiClient mqttNet;
PubSubClient mqtt(mqttNet);
uint32_t tMqttSambung = 0;
#endif

uint32_t tSensorBerikut = 0, tFotoBerikut = 0, tAktuasiBerikut = 0, tWifiPutus = 0;

Pending pendingA, pendingB;
uint8_t gagalUnggahB = 0;

uint32_t epochSekarang() {
  time_t t = time(nullptr);
  return t > 1700000000 ? (uint32_t)t : 0;
}

bool jamFotoOk() {
  struct tm tm;
  if (!getLocalTime(&tm, 0)) return true;
  return tm.tm_hour >= JAM_FOTO_MULAI && tm.tm_hour < JAM_FOTO_SELESAI;
}

int kualitasSinyal(int rssi) {
  if (rssi == 0) return -1;
  return constrain(2 * (rssi + 100), 0, 100);
}

void isiAtauNull(JsonDocument& d, const char* k, float v, int desimal = 2) {
  if (isnan(v)) d[k] = nullptr;
  else {
    float f = powf(10, desimal);
    d[k] = roundf(v * f) / f;
  }
}

float vpdFao56(float t, float rh) {
  if (isnan(t) || isnan(rh)) return NAN;
  float es = 0.6108f * expf(17.27f * t / (t + 237.3f));
  return es * (1.0f - rh / 100.0f);
}

bool mulaiHttp(HTTPClient& http, WiFiClientSecure& tls, WiFiClient& biasa, const String& url) {
  if (url.startsWith("https://")) {
    if (ROOT_CA) tls.setCACert(ROOT_CA);
    else tls.setInsecure();
    return http.begin(tls, url);
  }
  return http.begin(biasa, url);
}

bool kirimJsonSensor(const String& json) {
  if (WiFi.status() != WL_CONNECTED) return false;
  WiFiClientSecure tls;
  WiFiClient biasa;
  HTTPClient http;
  if (!mulaiHttp(http, tls, biasa, String(URL_SERVER) + "/api/kirim_sensor")) return false;
  http.setTimeout(15000);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  int kode = http.POST(json);
  Serial.printf("[server] kirim_sensor -> %d\n", kode);
  if (kode != 200 && kode != 201) Serial.println(kode > 0 ? http.getString() : HTTPClient::errorToString(kode));
  http.end();
  return kode == 200 || kode == 201;
}

bool kirimGambar(const uint8_t* buf, size_t len, int titik) {
  if (WiFi.status() != WL_CONNECTED || len == 0) return false;
  WiFiClientSecure tls;
  WiFiClient biasa;
  HTTPClient http;
  if (!mulaiHttp(http, tls, biasa, String(URL_SERVER) + "/api/kirim_gambar")) return false;
  http.setTimeout(60000);
  http.addHeader("Content-Type", "image/jpeg");
  http.addHeader("Accept", "application/json");
  http.addHeader("X-IOT-ID", IOT_ID);
  http.addHeader("X-IOT-TOKEN", IOT_TOKEN);
  http.addHeader("X-TITIK", String(titik));
  int kode = http.POST((uint8_t*)buf, len);
  String resp = kode > 0 ? http.getString() : HTTPClient::errorToString(kode);
  Serial.printf("[server] kirim_gambar titik %d (%u B) -> %d %s\n", titik, (unsigned)len, kode, resp.c_str());
  http.end();
  return kode == 200 || kode == 201;
}

void layaniPending(Pending& p, bool titikA) {
  if (!p.ada || millis() - p.tCoba < 60000UL) return;
  p.tCoba = millis();
  if (kirimJsonSensor(p.json)) {
    if (titikA) tipDasar = p.tipSaatBaca;
    p.ada = false;
  }
}

void layaniAngin() {
  uint32_t now = millis();
  if (now - tJendelaAngin < 10000UL) return;
  float dt = (now - tJendelaAngin) / 1000.0f;
  tJendelaAngin = now;
  uint32_t p = pulsaAngin;
  uint32_t n = p - pulsaAwalJendela;
  pulsaAwalJendela = p;
  float r = n / dt;
  float v = n == 0 ? 0.0f : ANGIN_A * r * r + ANGIN_B * r + ANGIN_C;
  if (v < V_MIN_ANGIN) v = 0.0f;
  jumlahAngin += v;
  nJendelaAngin++;
  if (v > anginMaks) anginMaks = v;
}

float bacaLux() {
  if (!bhOk) return NAN;
  float lux = lightMeter.readLightLevel();
  return lux < 0 ? NAN : lux;
}

void bacaDanKirimSensorA() {
  float t = NAN, rh = NAN;
  for (int i = 0; i < 3 && (isnan(t) || isnan(rh)); i++) {
    if (i) delay(2100);
    t = dht.readTemperature();
    rh = dht.readHumidity();
  }
  if (!dalamRentang(t, -20, 60) || !dalamRentang(rh, 0, 100)) t = rh = NAN;

  float lux = bacaLux();
  float tekanan = bmpOk ? bmp.readPressure() : NAN;
  if (!dalamRentang(tekanan, 30000, 110000)) tekanan = NAN;

  float angin = nJendelaAngin ? (float)(jumlahAngin / nJendelaAngin) : NAN;
  float anginHembus = anginMaks;
  jumlahAngin = 0;
  nJendelaAngin = 0;
  anginMaks = 0;

  uint32_t tip = tipHujan;
  float hujan = (tip - tipDasar) * MM_PER_TIP;

  DataTanah tanah = bacaSensorTanah();
  int rssi = WiFi.RSSI();

  Serial.printf("[titik 1] T=%.1fC RH=%.1f%% VPD=%.2fkPa angin=%.2fm/s (maks %.2f) hujan=%.1fmm lux=%.0f P=%.0fPa\n",
                t, rh, vpdFao56(t, rh), angin, anginHembus, hujan, lux, tekanan);
  cetakDataTanah("titik 1", tanah);

  JsonDocument d;
  d["iot_id"] = IOT_ID;
  d["iot_token"] = IOT_TOKEN;
  d["titik"] = TITIK_A;
  isiAtauNull(d, "temperature", t);
  isiAtauNull(d, "humidity", rh);
  isiAtauNull(d, "windspeed", angin);
  isiAtauNull(d, "rainfall", hujan);
  if (isnan(lux)) d["light_intensity"] = nullptr; else d["light_intensity"] = (long)lroundf(lux);
  isiAtauNull(d, "pressure", tekanan);
  isiAtauNull(d, "ph", tanah.ph);
  isiAtauNull(d, "soil_moisture", tanah.vwc);
  isiAtauNull(d, "ec", tanah.ec);
  isiAtauNull(d, "tds", tanah.tds);
  isiAtauNull(d, "soil_temp", tanah.suhu);
  isiAtauNull(d, "Nitrogen_Level", tanah.n);
  isiAtauNull(d, "Phosphorus_Level", tanah.p);
  isiAtauNull(d, "Potassium_Level", tanah.k);
#if PAKAI_BATERAI
  d["battery_level"] = persenBaterai(bacaTeganganBaterai());
#else
  d["battery_level"] = nullptr;
#endif
  int q = kualitasSinyal(rssi);
  if (q < 0) d["signal_strength"] = nullptr; else d["signal_strength"] = q;

  pendingA.json = "";
  serializeJson(d, pendingA.json);
  pendingA.tipSaatBaca = tip;
  pendingA.ada = true;
  pendingA.tCoba = millis() - 60000UL;
  layaniPending(pendingA, true);
}

String jsonDariSensorB(const SensorB& b) {
  const TvSensor& s = b.s;
  JsonDocument d;
  d["iot_id"] = IOT_ID;
  d["iot_token"] = IOT_TOKEN;
  d["titik"] = TITIK_B;
  isiAtauNull(d, "ph", (s.valid & TV_V_PH) ? s.ph : NAN);
  isiAtauNull(d, "soil_moisture", (s.valid & TV_V_VWC) ? s.vwc : NAN);
  isiAtauNull(d, "ec", (s.valid & TV_V_EC) ? s.ec : NAN);
  isiAtauNull(d, "tds", (s.valid & TV_V_TDS) ? s.tds : NAN);
  isiAtauNull(d, "soil_temp", (s.valid & TV_V_SUHU_TANAH) ? s.suhu_tanah : NAN);
  isiAtauNull(d, "Nitrogen_Level", (s.valid & TV_V_N) ? s.n : NAN);
  isiAtauNull(d, "Phosphorus_Level", (s.valid & TV_V_P) ? s.p : NAN);
  isiAtauNull(d, "Potassium_Level", (s.valid & TV_V_K) ? s.k : NAN);
  if (s.valid & TV_V_BATERAI) d["battery_level"] = s.baterai; else d["battery_level"] = nullptr;
  int q = kualitasSinyal(b.rssi);
  if (q < 0) d["signal_strength"] = nullptr; else d["signal_strength"] = q;
  String out;
  serializeJson(d, out);
  return out;
}

void balasKeB(uint8_t untukTipe, uint32_t ref, uint8_t status) {
  TvBalasan b;
  tvIsiHeader(b.h, TV_BALASAN);
  b.untuk_tipe = untukTipe;
  b.ref_id = ref;
  b.status = status;
  b.epoch = epochSekarang();
  b.aktuator = aktuatorB | (malam ? TV_AKT_UV : 0);
  esp_now_send(MAC_NODE_B, (const uint8_t*)&b, sizeof(b));
}

void prosesPaketB(const PaketMasuk& p) {
  if (!tvHeaderValid(p.data, p.len)) return;
  uint8_t tipe = ((const TvHeader*)p.data)->tipe;

  switch (tipe) {
    case TV_HELLO:
      if (p.len >= (int)sizeof(TvHello)) balasKeB(TV_HELLO, ((const TvHello*)p.data)->nonce, TV_ST_OK);
      break;

    case TV_SENSOR: {
      if (p.len < (int)sizeof(TvSensor)) return;
      SensorB b;
      memcpy(&b.s, p.data, sizeof(TvSensor));
      b.rssi = p.rssi;
      if (b.s.sampel_id != sampelTerakhirB) {
        sampelTerakhirB = b.s.sampel_id;
        xQueueOverwrite(qSensorB, &b);
      }
      balasKeB(TV_SENSOR, b.s.sampel_id, TV_ST_OK);
      break;
    }

    case TV_IMG_START: {
      if (p.len < (int)sizeof(TvImgStart)) return;
      const TvImgStart* s = (const TvImgStart*)p.data;
      if (statusGambarB == GB_LENGKAP) { balasKeB(TV_IMG_START, s->img_id, TV_ST_SIBUK); return; }
      if (!bufGambarB || s->ukuran > kapGambarB || s->ukuran == 0 ||
          s->jumlah_chunk != (s->ukuran + TV_CHUNK_DATA - 1) / TV_CHUNK_DATA) {
        balasKeB(TV_IMG_START, s->img_id, TV_ST_MEMORI);
        return;
      }
      idGambarB = s->img_id;
      ukuranGambarB = s->ukuran;
      crcGambarB = s->crc32;
      jumlahChunkB = s->jumlah_chunk;
      chunkDiterimaB = 0;
      memset(petaChunkB, 0, sizeof(petaChunkB));
      tMulaiGambarB = millis();
      rssiGambarB = p.rssi;
      statusGambarB = GB_MENERIMA;
      balasKeB(TV_IMG_START, s->img_id, TV_ST_OK);
      break;
    }

    case TV_IMG_CHUNK: {
      if (statusGambarB != GB_MENERIMA || p.len < (int)(sizeof(TvImgChunk) - TV_CHUNK_DATA)) return;
      const TvImgChunk* c = (const TvImgChunk*)p.data;
      size_t offset = (size_t)c->indeks * TV_CHUNK_DATA;
      if (c->img_id != idGambarB || c->indeks >= jumlahChunkB || c->panjang > TV_CHUNK_DATA ||
          p.len < (int)(sizeof(TvImgChunk) - TV_CHUNK_DATA + c->panjang) || offset + c->panjang > ukuranGambarB)
        return;
      uint8_t bit = 1 << (c->indeks & 7);
      if (!(petaChunkB[c->indeks >> 3] & bit)) {
        memcpy(bufGambarB + offset, c->data, c->panjang);
        petaChunkB[c->indeks >> 3] |= bit;
        chunkDiterimaB++;
      }
      break;
    }

    case TV_IMG_END: {
      if (p.len < (int)sizeof(TvImgEnd)) return;
      const TvImgEnd* e = (const TvImgEnd*)p.data;
      if (statusGambarB != GB_MENERIMA || e->img_id != idGambarB) return;
      if (chunkDiterimaB != jumlahChunkB) {
        statusGambarB = GB_KOSONG;
        balasKeB(TV_IMG_END, e->img_id, TV_ST_KURANG);
      } else if (tvCrc32(bufGambarB, ukuranGambarB) != crcGambarB) {
        statusGambarB = GB_KOSONG;
        balasKeB(TV_IMG_END, e->img_id, TV_ST_CRC);
      } else {
        statusGambarB = GB_LENGKAP;
        balasKeB(TV_IMG_END, e->img_id, TV_ST_OK);
        Serial.printf("[espnow] gambar B diterima %u B (%u chunk)\n", (unsigned)ukuranGambarB, jumlahChunkB);
      }
      break;
    }
  }
}

void tugasRadio(void*) {
  PaketMasuk p;
  for (;;) {
    if (xQueueReceive(qMasuk, &p, pdMS_TO_TICKS(1000)) == pdTRUE) prosesPaketB(p);
    if (statusGambarB == GB_MENERIMA && millis() - tMulaiGambarB > 60000UL) {
      statusGambarB = GB_KOSONG;
      Serial.println("[espnow] transfer gambar B kedaluwarsa");
    }
  }
}

static void simpanPaket(const uint8_t* mac, const uint8_t* data, int len, int8_t rssi) {
  if (memcmp(mac, MAC_NODE_B, 6) != 0) {
    memcpy(macAsing, mac, 6);
    adaMacAsing = true;
    return;
  }
  if (len <= 0 || len > TV_MAKS_PAYLOAD) return;
  PaketMasuk p;
  memcpy(p.data, data, len);
  p.len = len;
  p.rssi = rssi;
  xQueueSend(qMasuk, &p, 0);
}

#if ESP_ARDUINO_VERSION_MAJOR >= 3
void onEspNowTerima(const esp_now_recv_info_t* info, const uint8_t* data, int len) {
  simpanPaket(info->src_addr, data, len, info->rx_ctrl ? (int8_t)info->rx_ctrl->rssi : 0);
}
#else
void onEspNowTerima(const uint8_t* mac, const uint8_t* data, int len) {
  simpanPaket(mac, data, len, 0);
}
#endif

bool macKosong(const uint8_t* m) {
  for (int i = 0; i < 6; i++)
    if (m[i]) return false;
  return true;
}

void mulaiEspNow() {
  WiFi.setSleep(false);
  if (esp_now_init() != ESP_OK) {
    Serial.println("[espnow] init gagal");
    return;
  }
#if PAKAI_ENKRIPSI_ESPNOW
  esp_now_set_pmk((const uint8_t*)ESPNOW_PMK);
#endif
  esp_now_register_recv_cb(onEspNowTerima);
  if (macKosong(MAC_NODE_B)) {
    Serial.println("[espnow] MAC_NODE_B belum diisi di konfigurasi.h -> data ESP32 B belum bisa diterima");
    return;
  }
  esp_now_peer_info_t peer = {};
  memcpy(peer.peer_addr, MAC_NODE_B, 6);
  peer.channel = 0;
  peer.ifidx = WIFI_IF_STA;
#if PAKAI_ENKRIPSI_ESPNOW
  peer.encrypt = true;
  memcpy(peer.lmk, ESPNOW_LMK, 16);
#endif
  if (esp_now_add_peer(&peer) != ESP_OK) {
    Serial.println("[espnow] gagal menambah peer B");
    return;
  }
  espnowSiap = true;
  Serial.printf("[espnow] siap di kanal %d\n", WiFi.channel());
}

void kirimPerintahB() {
  if (!espnowSiap) return;
  uint8_t bit = aktuatorB | (malam ? TV_AKT_UV : 0);
  if (bit == aktuatorBTerkirim && millis() - tPerintahB < 300000UL) return;
  TvPerintah p;
  tvIsiHeader(p.h, TV_PERINTAH);
  p.aktuator = bit;
  p.epoch = epochSekarang();
  if (esp_now_send(MAC_NODE_B, (const uint8_t*)&p, sizeof(p)) == ESP_OK) {
    aktuatorBTerkirim = bit;
    tPerintahB = millis();
  }
}

uint8_t bitDariAksi(const char* aksi) {
  if (strcmp(aksi, "activate_ultrasonic_repeller_and_uv_lamp") == 0) return TV_AKT_UV | TV_AKT_ULTRASONIK;
  if (strcmp(aksi, "irrigation_notification") == 0) return TV_AKT_IRIGASI;
  return 0;
}

bool ambilAksi(int titik, uint8_t& bit) {
  WiFiClientSecure tls;
  WiFiClient biasa;
  HTTPClient http;
  String url = String(URL_SERVER) + "/api/inference/" + IOT_ID + "?titik=" + titik;
  if (!mulaiHttp(http, tls, biasa, url)) return false;
  http.setTimeout(10000);
  http.addHeader("Accept", "application/json");
  int kode = http.GET();
  bool ok = false;
  if (kode == 200) {
    JsonDocument doc;
    if (!deserializeJson(doc, http.getString())) {
      bit = bitDariAksi(doc["data"]["actuation_action"] | "");
      ok = true;
    }
  } else if (kode == 404) {
    bit = 0;
    ok = true;
  }
  http.end();
  return ok;
}

void terapkanRelayA() {
#if PAKAI_AKTUATOR
  uint8_t bit = aktuatorA | (malam ? TV_AKT_UV : 0);
  digitalWrite(PIN_RELAY_UV, (bit & TV_AKT_UV) ? LOW : HIGH);
  digitalWrite(PIN_RELAY_ULTRASONIK, (bit & TV_AKT_ULTRASONIK) ? LOW : HIGH);
  digitalWrite(PIN_RELAY_IRIGASI, (bit & TV_AKT_IRIGASI) ? LOW : HIGH);
#endif
}

void layaniAktuasi() {
  float lux = bacaLux();
  if (!isnan(lux)) malam = lux < LUX_MALAM;
#if PAKAI_AKTUATOR
  if (WiFi.status() == WL_CONNECTED) {
    uint8_t bit;
    if (ambilAksi(TITIK_A, bit)) { aktuatorA = bit; tAksiOkA = millis(); }
    if (ambilAksi(TITIK_B, bit)) { aktuatorB = bit; tAksiOkB = millis(); }
  }
  if (millis() - tAksiOkA > AKSI_KEDALUWARSA_MS) aktuatorA = 0;
  if (millis() - tAksiOkB > AKSI_KEDALUWARSA_MS) aktuatorB = 0;
  terapkanRelayA();
  kirimPerintahB();
#endif
}

#if PAKAI_MQTT
void onMqtt(char*, byte* payload, unsigned int len) {
  JsonDocument doc;
  if (deserializeJson(doc, payload, len)) return;
  int titik = doc["titik"] | 1;
  uint8_t bit = bitDariAksi(doc["actuation"]["action"] | "");
  if (titik == TITIK_A) { aktuatorA = bit; tAksiOkA = millis(); }
  else if (titik == TITIK_B) { aktuatorB = bit; tAksiOkB = millis(); }
  terapkanRelayA();
  kirimPerintahB();
}

void layaniMqtt() {
  if (WiFi.status() != WL_CONNECTED) return;
  if (!mqtt.connected() && millis() - tMqttSambung > 5000UL) {
    tMqttSambung = millis();
    String cid = String("esp32a-") + IOT_ID + "-" + String((uint32_t)ESP.getEfuseMac(), HEX);
    if (mqtt.connect(cid.c_str())) mqtt.subscribe((String(MQTT_TOPIK_PREFIX) + "/" + IOT_ID + "/inference").c_str());
  }
  mqtt.loop();
}
#endif

void setup() {
  Serial.begin(115200);
  delay(300);
  Serial.println("\n=== TaniVerse ESP32 A (gateway) ===");

#if PAKAI_AKTUATOR
  pinMode(PIN_RELAY_UV, OUTPUT);
  pinMode(PIN_RELAY_ULTRASONIK, OUTPUT);
  pinMode(PIN_RELAY_IRIGASI, OUTPUT);
  terapkanRelayA();
#endif

  dht.begin();
  Wire.begin(PIN_SDA, PIN_SCL);
  bhOk = lightMeter.begin(BH1750::CONTINUOUS_HIGH_RES_MODE, 0x23, &Wire);
  if (bhOk) lightMeter.setMTreg(BH1750_MTREG_MIN);
  bmpOk = bmp.begin(0x76) || bmp.begin(0x77);
  if (bmpOk)
    bmp.setSampling(Adafruit_BMP280::MODE_NORMAL, Adafruit_BMP280::SAMPLING_X2, Adafruit_BMP280::SAMPLING_X16,
                    Adafruit_BMP280::FILTER_X16, Adafruit_BMP280::STANDBY_MS_500);
  Serial.printf("BH1750 %s, BMP280 %s\n", bhOk ? "OK" : "TIDAK ADA (null)", bmpOk ? "OK" : "TIDAK ADA (null)");
  pinMode(PIN_ANEMOMETER, INPUT_PULLUP);
  pinMode(PIN_HUJAN, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(PIN_ANEMOMETER), isrAngin, FALLING);
  attachInterrupt(digitalPinToInterrupt(PIN_HUJAN), isrHujan, FALLING);
  analogSetPinAttenuation(PIN_BATERAI, ADC_11db);
  mulaiSensorTanah();
#if DUMP_REGISTER
  dumpRegisterTanah(0x0000, 0x0024);
#endif

  bufGambarB = alokasiBufferGambar(kapGambarB, UKURAN_GAMBAR_MAKS, 40 * 1024);
#if PAKAI_KAMERA_A
  bufGambarA = alokasiBufferGambar(kapGambarA, UKURAN_GAMBAR_MAKS, 40 * 1024);
#endif

  WiFi.mode(WIFI_STA);
  WiFiManager wm;
  wm.setConfigPortalTimeout(300);
  if (!wm.autoConnect(AP_PORTAL, AP_PORTAL_PASS)) {
    Serial.println("WiFi tidak tersambung, restart");
    ESP.restart();
  }
  WiFi.setAutoReconnect(true);
  Serial.printf("WiFi OK: %s, IP %s, kanal %d\n", WiFi.SSID().c_str(), WiFi.localIP().toString().c_str(),
                WiFi.channel());
  Serial.printf(">>> MAC ESP-NOW node A (isi ke MAC_NODE_A di ESP32 B): %s\n", WiFi.macAddress().c_str());
  configTzTime(ZONA_WAKTU, "pool.ntp.org", "time.google.com");

  qMasuk = xQueueCreate(48, sizeof(PaketMasuk));
  qSensorB = xQueueCreate(1, sizeof(SensorB));
  mulaiEspNow();
  xTaskCreatePinnedToCore(tugasRadio, "radio", 6144, nullptr, 3, nullptr, 0);

#if PAKAI_MQTT
  mqtt.setServer(MQTT_HOST, MQTT_PORT);
  mqtt.setCallback(onMqtt);
  aturBufferMqtt(mqtt, 0);
#endif

  uint32_t now = millis();
  tJendelaAngin = now;
  tSensorBerikut = now + 60000UL;
  tFotoBerikut = now + 30000UL;
  tAktuasiBerikut = now + 5000UL;
}

void loop() {
  uint32_t now = millis();
  layaniAngin();

  if (WiFi.status() != WL_CONNECTED) {
    if (!tWifiPutus) tWifiPutus = now;
    if (now - tWifiPutus > 600000UL) ESP.restart();
  } else {
    tWifiPutus = 0;
  }

  if ((int32_t)(now - tSensorBerikut) >= 0) {
    tSensorBerikut = now + INTERVAL_SENSOR_MS;
    bacaDanKirimSensorA();
  }
  layaniPending(pendingA, true);

  SensorB b;
  if (xQueueReceive(qSensorB, &b, 0) == pdTRUE) {
    pendingB.json = jsonDariSensorB(b);
    pendingB.ada = true;
    pendingB.tCoba = millis() - 60000UL;
    Serial.printf("[titik 2] sampel %lu dari B (RSSI %d dBm)\n", (unsigned long)b.s.sampel_id, b.rssi);
  }
  layaniPending(pendingB, false);

  if (statusGambarB == GB_LENGKAP) {
    if (kirimGambar(bufGambarB, ukuranGambarB, TITIK_B) || ++gagalUnggahB >= 3) {
      gagalUnggahB = 0;
      statusGambarB = GB_KOSONG;
    }
  }

#if PAKAI_KAMERA_A
  if ((int32_t)(now - tFotoBerikut) >= 0) {
    tFotoBerikut = now + INTERVAL_FOTO_MS;
    if (jamFotoOk() && bufGambarA) {
      size_t n = ambilSnapshot(URL_KAMERA_A, USER_KAMERA_A, PASS_KAMERA_A, bufGambarA, kapGambarA);
      if (n) kirimGambar(bufGambarA, n, TITIK_A);
    }
  }
#endif

  if ((int32_t)(now - tAktuasiBerikut) >= 0) {
    tAktuasiBerikut = now + INTERVAL_AKTUASI_MS;
    layaniAktuasi();
  }
#if PAKAI_MQTT
  layaniMqtt();
#endif

  if (adaMacAsing) {
    adaMacAsing = false;
    Serial.printf("[espnow] paket dari MAC tak dikenal %02X:%02X:%02X:%02X:%02X:%02X (cek MAC_NODE_B)\n",
                  macAsing[0], macAsing[1], macAsing[2], macAsing[3], macAsing[4], macAsing[5]);
  }
  delay(10);
}
