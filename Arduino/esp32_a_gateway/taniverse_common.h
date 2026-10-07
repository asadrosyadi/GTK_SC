#pragma once
#include <Arduino.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <MD5Builder.h>
#include <base64.h>
#include <ModbusMaster.h>

struct RegSensor {
  uint16_t alamat;
  float skala;
  bool bertanda;
};

#define PETA_8IN1_UMUM 1
#define PETA_JXCT 2

#ifndef PETA_REGISTER
#define PETA_REGISTER PETA_8IN1_UMUM
#endif

#if PETA_REGISTER == PETA_8IN1_UMUM
static const RegSensor REG_VWC = {0x0000, 0.1f, false};
static const RegSensor REG_SUHU = {0x0001, 0.1f, true};
static const RegSensor REG_EC = {0x0002, 1.0f, false};
static const RegSensor REG_PH = {0x0003, 0.1f, false};
static const RegSensor REG_N = {0x0004, 1.0f, false};
static const RegSensor REG_P = {0x0005, 1.0f, false};
static const RegSensor REG_K = {0x0006, 1.0f, false};
static const RegSensor REG_TDS = {0x0008, 1.0f, false};
#elif PETA_REGISTER == PETA_JXCT
static const RegSensor REG_PH = {0x0006, 0.01f, false};
static const RegSensor REG_VWC = {0x0012, 0.1f, false};
static const RegSensor REG_SUHU = {0x0013, 0.1f, true};
static const RegSensor REG_EC = {0x0015, 1.0f, false};
static const RegSensor REG_N = {0x001E, 1.0f, false};
static const RegSensor REG_P = {0x001F, 1.0f, false};
static const RegSensor REG_K = {0x0020, 1.0f, false};
static const RegSensor REG_TDS = {0xFFFF, 1.0f, false};
#else
#error "PETA_REGISTER tidak dikenal"
#endif

#ifndef TDS_DARI_EC
#define TDS_DARI_EC 1
#endif
#define FAKTOR_TDS_PER_EC 0.64f

struct DataTanah {
  float ph = NAN, vwc = NAN, ec = NAN, tds = NAN, suhu = NAN, n = NAN, p = NAN, k = NAN;
  bool tdsTurunan = false;
};

static ModbusMaster tvModbus;

static void tvRs485Kirim() { digitalWrite(PIN_RS485_DE_RE, HIGH); }
static void tvRs485Terima() { digitalWrite(PIN_RS485_DE_RE, LOW); }

inline void mulaiSensorTanah() {
  pinMode(PIN_RS485_DE_RE, OUTPUT);
  tvRs485Terima();
  Serial2.begin(RS485_BAUD, SERIAL_8N1, PIN_RS485_RX, PIN_RS485_TX);
  tvModbus.begin(ALAMAT_SENSOR_8IN1, Serial2);
  tvModbus.preTransmission(tvRs485Kirim);
  tvModbus.postTransmission(tvRs485Terima);
}

inline float bacaRegister(const RegSensor& r) {
  if (r.alamat == 0xFFFF) return NAN;
  for (int coba = 0; coba < 3; coba++) {
    if (tvModbus.readHoldingRegisters(r.alamat, 1) == tvModbus.ku8MBSuccess) {
      uint16_t mentah = tvModbus.getResponseBuffer(0);
      return (r.bertanda ? (float)(int16_t)mentah : (float)mentah) * r.skala;
    }
    delay(120);
  }
  return NAN;
}

inline bool dalamRentang(float v, float lo, float hi) { return !isnan(v) && v >= lo && v <= hi; }

inline DataTanah bacaSensorTanah() {
  DataTanah d;
  d.vwc = bacaRegister(REG_VWC);
  d.suhu = bacaRegister(REG_SUHU);
  d.ec = bacaRegister(REG_EC);
  d.ph = bacaRegister(REG_PH);
  d.n = bacaRegister(REG_N);
  d.p = bacaRegister(REG_P);
  d.k = bacaRegister(REG_K);
  d.tds = bacaRegister(REG_TDS);

  if (!dalamRentang(d.vwc, 0, 100)) d.vwc = NAN;
  if (!dalamRentang(d.suhu, -20, 80)) d.suhu = NAN;
  if (!dalamRentang(d.ec, 0, 20000)) d.ec = NAN;
  if (!dalamRentang(d.ph, 0, 14)) d.ph = NAN;
  if (!dalamRentang(d.n, 0, 2000)) d.n = NAN;
  if (!dalamRentang(d.p, 0, 2000)) d.p = NAN;
  if (!dalamRentang(d.k, 0, 2000)) d.k = NAN;
  if (!dalamRentang(d.tds, 0, 20000)) d.tds = NAN;

#if TDS_DARI_EC
  if (isnan(d.tds) && !isnan(d.ec)) {
    d.tds = d.ec * FAKTOR_TDS_PER_EC;
    d.tdsTurunan = true;
  }
#endif
  return d;
}

inline void cetakDataTanah(const char* label, const DataTanah& d) {
  Serial.printf("[%s] pH=%.2f VWC=%.1f%% EC=%.0fuS/cm TDS=%.0fppm%s Tsoil=%.1fC N=%.0f P=%.0f K=%.0f\n",
                label, d.ph, d.vwc, d.ec, d.tds, d.tdsTurunan ? "(dari EC)" : "", d.suhu, d.n, d.p, d.k);
}

inline void dumpRegisterTanah(uint16_t dari, uint16_t sampai) {
  Serial.println("=== Dump register sensor tanah (mentah) ===");
  for (uint16_t a = dari; a <= sampai; a++) {
    uint8_t r = tvModbus.readHoldingRegisters(a, 1);
    if (r == tvModbus.ku8MBSuccess) Serial.printf("  reg 0x%04X = %u\n", a, tvModbus.getResponseBuffer(0));
    else Serial.printf("  reg 0x%04X : gagal (kode 0x%02X)\n", a, r);
    delay(60);
  }
}

class BufferStream : public Stream {
 public:
  BufferStream(uint8_t* buf, size_t kapasitas) : _buf(buf), _kap(kapasitas) {}
  size_t write(uint8_t c) override { return write(&c, 1); }
  size_t write(const uint8_t* d, size_t n) override {
    size_t m = (_len + n <= _kap) ? n : _kap - _len;
    memcpy(_buf + _len, d, m);
    _len += m;
    if (m < n) _penuh = true;
    return m;
  }
  int available() override { return 0; }
  int read() override { return -1; }
  int peek() override { return -1; }
  void flush() override {}
  size_t panjang() const { return _len; }
  bool penuh() const { return _penuh; }

 private:
  uint8_t* _buf;
  size_t _kap;
  size_t _len = 0;
  bool _penuh = false;
};

inline String tvMd5(const String& s) {
  MD5Builder m;
  m.begin();
  m.add(s);
  m.calculate();
  return m.toString();
}

inline String tvParamDigest(const String& h, const String& kunci) {
  int dari = 0;
  while (true) {
    int i = h.indexOf(kunci + "=", dari);
    if (i < 0) return "";
    char sebelum = i == 0 ? ' ' : h.charAt(i - 1);
    if (sebelum == ' ' || sebelum == ',') {
      int v = i + kunci.length() + 1;
      if (h.charAt(v) == '"') {
        int akhir = h.indexOf('"', v + 1);
        return akhir < 0 ? "" : h.substring(v + 1, akhir);
      }
      int akhir = h.indexOf(',', v);
      String hasil = akhir < 0 ? h.substring(v) : h.substring(v, akhir);
      hasil.trim();
      return hasil;
    }
    dari = i + 1;
  }
}

inline String tvPathDariUrl(const String& url) {
  int skema = url.indexOf("://");
  int slash = url.indexOf('/', skema < 0 ? 0 : skema + 3);
  return slash < 0 ? "/" : url.substring(slash);
}

inline String tvHeaderDigest(const String& www, const String& user, const String& pass, const String& uri) {
  String realm = tvParamDigest(www, "realm");
  String nonce = tvParamDigest(www, "nonce");
  String opaque = tvParamDigest(www, "opaque");
  String qop = tvParamDigest(www, "qop");
  String ha1 = tvMd5(user + ":" + realm + ":" + pass);
  String ha2 = tvMd5("GET:" + uri);
  String hdr = "Digest username=\"" + user + "\", realm=\"" + realm + "\", nonce=\"" + nonce +
               "\", uri=\"" + uri + "\", algorithm=MD5";
  if (qop.indexOf("auth") >= 0) {
    char cnonce[17];
    snprintf(cnonce, sizeof(cnonce), "%08lx%08lx", (unsigned long)esp_random(), (unsigned long)esp_random());
    const char* nc = "00000001";
    String resp = tvMd5(ha1 + ":" + nonce + ":" + nc + ":" + cnonce + ":auth:" + ha2);
    hdr += ", qop=auth, nc=" + String(nc) + ", cnonce=\"" + cnonce + "\", response=\"" + resp + "\"";
  } else {
    hdr += ", response=\"" + tvMd5(ha1 + ":" + nonce + ":" + ha2) + "\"";
  }
  if (opaque.length()) hdr += ", opaque=\"" + opaque + "\"";
  return hdr;
}

inline int tvUnduhKeBuffer(const String& url, const String& authHeader, uint8_t* buf, size_t kap,
                           size_t& panjang, String* wwwAuth) {
  WiFiClient klien;
  HTTPClient http;
  const char* kumpulkan[] = {"WWW-Authenticate"};
  if (!http.begin(klien, url)) return HTTPC_ERROR_CONNECTION_REFUSED;
  http.setTimeout(10000);
  http.setConnectTimeout(5000);
  http.collectHeaders(kumpulkan, 1);
  if (authHeader.length()) http.addHeader("Authorization", authHeader);
  int kode = http.GET();
  if (kode == 401 && wwwAuth) *wwwAuth = http.header("WWW-Authenticate");
  if (kode == 200) {
    BufferStream bs(buf, kap);
    int n = http.writeToStream(&bs);
    panjang = bs.panjang();
    if (bs.penuh()) kode = -100;
    else if (n < 0 && panjang == 0) kode = n;
  }
  http.end();
  return kode;
}

inline size_t ambilSnapshot(const char* url, const char* user, const char* pass, uint8_t* buf, size_t kap) {
  size_t panjang = 0;
  String www;
  String basic;
  if (user && user[0]) {
    String kredensial = String(user) + ":" + pass;
    basic = "Basic " + base64::encode(kredensial);
  }
  int kode = tvUnduhKeBuffer(url, basic, buf, kap, panjang, &www);
  if (kode == 401 && www.startsWith("Digest")) {
    String hdr = tvHeaderDigest(www, user, pass, tvPathDariUrl(url));
    kode = tvUnduhKeBuffer(url, hdr, buf, kap, panjang, nullptr);
  }
  if (kode == -100) {
    Serial.printf("[kamera] gambar > %u byte; kecilkan resolusi snapshot di pengaturan kamera\n", (unsigned)kap);
    return 0;
  }
  if (kode != 200) {
    Serial.printf("[kamera] snapshot gagal: %d %s\n", kode, kode < 0 ? HTTPClient::errorToString(kode).c_str() : "");
    return 0;
  }
  if (panjang < 4 || buf[0] != 0xFF || buf[1] != 0xD8) {
    Serial.println("[kamera] respons bukan JPEG (periksa URL snapshot)");
    return 0;
  }
  return panjang;
}

inline uint8_t* alokasiBufferGambar(size_t& kap, size_t inginPsram, size_t tanpaPsram) {
  uint8_t* p = nullptr;
  if (psramFound()) {
    p = (uint8_t*)ps_malloc(inginPsram);
    if (p) { kap = inginPsram; return p; }
  }
  Serial.println("[memori] PSRAM tidak ada/aktif -> buffer gambar kecil; aktifkan Tools > PSRAM");
  p = (uint8_t*)malloc(tanpaPsram);
  kap = p ? tanpaPsram : 0;
  return p;
}

inline float bacaTeganganBaterai() {
  uint32_t mv = 0;
  for (int i = 0; i < 16; i++) mv += analogReadMilliVolts(PIN_BATERAI);
  float vAdc = (mv / 16.0f) / 1000.0f;
  return vAdc * (BATERAI_R1 + BATERAI_R2) / BATERAI_R2;
}

inline int persenBaterai(float v) {
  float p = (v - BATERAI_V_KOSONG) / (BATERAI_V_PENUH - BATERAI_V_KOSONG) * 100.0f;
  return (int)constrain(p, 0.0f, 100.0f);
}
