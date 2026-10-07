#pragma once

#define URL_SERVER "https://petaniasik.my.id"
#define IOT_ID "jTZids5M"
#define IOT_TOKEN "lOsx0KMJSAfJpxEe"

static const char* ROOT_CA = nullptr;

#define AP_PORTAL "TaniVerse-A-Setup"
#define AP_PORTAL_PASS "taniverse123"

static const uint8_t MAC_NODE_B[6] = {0x00, 0x00, 0x00, 0x00, 0x00, 0x00};
#define PAKAI_ENKRIPSI_ESPNOW 1
#define ESPNOW_PMK "GantiPMK16kar!!!"
#define ESPNOW_LMK "GantiLMK16kar!!!"

#define PAKAI_KAMERA_A 1
#define URL_KAMERA_A "http://192.168.1.64/ISAPI/Streaming/channels/101/picture"
#define USER_KAMERA_A "admin"
#define PASS_KAMERA_A ""

#define INTERVAL_SENSOR_MS (15UL * 60UL * 1000UL)
#define INTERVAL_FOTO_MS (10UL * 60UL * 1000UL)
#define JAM_FOTO_MULAI 6
#define JAM_FOTO_SELESAI 18
#define INTERVAL_AKTUASI_MS 15000UL
#define ZONA_WAKTU "WIB-7"
#define UKURAN_GAMBAR_MAKS (200UL * 1024UL)

#define PIN_DHT 15
#define PIN_ANEMOMETER 4
#define PIN_HUJAN 18
#define PIN_SDA 21
#define PIN_SCL 22
#define MM_PER_TIP 0.40f
#define ANGIN_A (-0.0181f)
#define ANGIN_B 1.3859f
#define ANGIN_C 1.4055f
#define V_MIN_ANGIN 1.5f

#define PIN_RS485_RX 32
#define PIN_RS485_TX 33
#define PIN_RS485_DE_RE 25
#define RS485_BAUD 4800
#define ALAMAT_SENSOR_8IN1 1
#define PETA_REGISTER PETA_8IN1_UMUM
#define DUMP_REGISTER 0

#define PAKAI_BATERAI 1
#define PIN_BATERAI 34
#define BATERAI_R1 82000.0f
#define BATERAI_R2 10000.0f
#define BATERAI_V_KOSONG 12.0f
#define BATERAI_V_PENUH 16.8f

#define PAKAI_AKTUATOR 1
#define PIN_RELAY_UV 19
#define PIN_RELAY_ULTRASONIK 23
#define PIN_RELAY_IRIGASI 27
#define LUX_MALAM 100

#define PAKAI_MQTT 0
#define MQTT_HOST "petaniasik.my.id"
#define MQTT_PORT 1883
#define MQTT_TOPIK_PREFIX "taniverse"
