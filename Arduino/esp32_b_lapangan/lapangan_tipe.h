#pragma once
#include <Arduino.h>
#include "taniverse_espnow.h"

struct PaketMasuk {
  uint8_t data[TV_MAKS_PAYLOAD];
  int len;
};
