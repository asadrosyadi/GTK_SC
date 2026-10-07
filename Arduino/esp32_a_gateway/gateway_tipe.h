#pragma once
#include <Arduino.h>
#include "taniverse_espnow.h"

struct PaketMasuk {
  uint8_t data[TV_MAKS_PAYLOAD];
  int len;
  int8_t rssi;
};

struct SensorB {
  TvSensor s;
  int8_t rssi;
};

struct Pending {
  bool ada = false;
  String json;
  uint32_t tipSaatBaca = 0;
  uint32_t tCoba = 0;
};

template <typename T>
auto aturBufferMqtt(T& c, int) -> decltype(c.setBufferSize(768, 256), void()) { c.setBufferSize(768, 256); }
template <typename T>
auto aturBufferMqtt(T& c, long) -> decltype(c.setBufferSize(768), void()) { c.setBufferSize(768); }
