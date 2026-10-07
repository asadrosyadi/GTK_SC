#pragma once
#include <stdint.h>
#include <stddef.h>

#define TV_MAGIC 0x54
#define TV_VERSI 1
#define TV_MAKS_PAYLOAD 250

enum TvTipe : uint8_t {
  TV_HELLO = 1,
  TV_SENSOR = 2,
  TV_IMG_START = 3,
  TV_IMG_CHUNK = 4,
  TV_IMG_END = 5,
  TV_BALASAN = 6,
  TV_PERINTAH = 7,
};

struct __attribute__((packed)) TvHeader {
  uint8_t magic;
  uint8_t versi;
  uint8_t tipe;
  uint8_t cadangan;
};

enum : uint16_t {
  TV_V_PH = 1 << 0,
  TV_V_VWC = 1 << 1,
  TV_V_EC = 1 << 2,
  TV_V_TDS = 1 << 3,
  TV_V_SUHU_TANAH = 1 << 4,
  TV_V_N = 1 << 5,
  TV_V_P = 1 << 6,
  TV_V_K = 1 << 7,
  TV_V_BATERAI = 1 << 8,
};

struct __attribute__((packed)) TvHello {
  TvHeader h;
  uint32_t nonce;
};

struct __attribute__((packed)) TvSensor {
  TvHeader h;
  uint32_t sampel_id;
  uint16_t valid;
  float ph, vwc, ec, tds, suhu_tanah, n, p, k;
  int16_t baterai;
};

struct __attribute__((packed)) TvImgStart {
  TvHeader h;
  uint32_t img_id;
  uint32_t ukuran;
  uint16_t jumlah_chunk;
  uint32_t crc32;
};

#define TV_CHUNK_DATA (TV_MAKS_PAYLOAD - sizeof(TvHeader) - 4 - 2 - 1)

struct __attribute__((packed)) TvImgChunk {
  TvHeader h;
  uint32_t img_id;
  uint16_t indeks;
  uint8_t panjang;
  uint8_t data[TV_CHUNK_DATA];
};

struct __attribute__((packed)) TvImgEnd {
  TvHeader h;
  uint32_t img_id;
};

enum TvStatus : uint8_t {
  TV_ST_OK = 0,
  TV_ST_SIBUK = 1,
  TV_ST_CRC = 2,
  TV_ST_KURANG = 3,
  TV_ST_MEMORI = 4,
};

enum : uint8_t {
  TV_AKT_UV = 1 << 0,
  TV_AKT_ULTRASONIK = 1 << 1,
  TV_AKT_IRIGASI = 1 << 2,
};

struct __attribute__((packed)) TvBalasan {
  TvHeader h;
  uint8_t untuk_tipe;
  uint32_t ref_id;
  uint8_t status;
  uint32_t epoch;
  uint8_t aktuator;
};

struct __attribute__((packed)) TvPerintah {
  TvHeader h;
  uint8_t aktuator;
  uint32_t epoch;
};

static_assert(sizeof(TvImgChunk) <= TV_MAKS_PAYLOAD, "chunk melebihi payload ESP-NOW");
static_assert(sizeof(TvSensor) <= TV_MAKS_PAYLOAD, "paket sensor melebihi payload ESP-NOW");

inline void tvIsiHeader(TvHeader& h, uint8_t tipe) {
  h.magic = TV_MAGIC;
  h.versi = TV_VERSI;
  h.tipe = tipe;
  h.cadangan = 0;
}

inline bool tvHeaderValid(const uint8_t* data, int len) {
  if (len < (int)sizeof(TvHeader)) return false;
  const TvHeader* h = (const TvHeader*)data;
  return h->magic == TV_MAGIC && h->versi == TV_VERSI;
}

inline uint32_t tvCrc32(const uint8_t* data, size_t len, uint32_t crc = 0) {
  crc = ~crc;
  for (size_t i = 0; i < len; i++) {
    crc ^= data[i];
    for (int b = 0; b < 8; b++) crc = (crc >> 1) ^ (0xEDB88320UL & (0 - (crc & 1)));
  }
  return ~crc;
}
