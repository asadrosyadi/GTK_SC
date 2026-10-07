# Smart Farming IoT System for Rice (*Oryza sativa*) — Full System Architecture, Mathematical Formulation, and Algorithms

> Technical companion document for a Q1 journal manuscript.
> Bahasa: dokumen ini ditulis dalam Bahasa Inggris agar langsung dapat dipakai untuk *manuscript* jurnal Q1. Setiap sub-sistem diberi (i) arsitektur, (ii) formulasi matematis, (iii) algoritma / *pseudocode*.
> Scope: every program currently in this repository is covered — the ESP32 field nodes (`Arduino/esp32_a_gateway`, `Arduino/esp32_b_lapangan`), the computer-vision disease/pest classifier (`Training_Penyakit Padi/train.py`, `backend/python/detect_penyakit/`), the LSTM micro-climate forecaster (`backend/python/rnn/`), the **hybrid CNN–LSTM feature-fusion model and Algorithm 1** (`backend/python/hybrid_cnn_lstm/`), the Laravel ingestion/API/web tier (`backend/laravel/`, public root `backend/html/`), the offline multi-sensor recommendation engine, and the Flutter mobile client (`mobile/`).
>
> **October 2026 update:** (i) **multi-point monitoring**: point 1 is a full weather + soil station with a camera for plot 1; point 2 is a soil-only node with a camera for plot 2; plot 2 uses the point-1 weather; (ii) the CNN service handles several cameras; (iii) the LSTM service forecasts per plot; (iv) Layer 6 is now an **offline per-action Random Forest** whose knowledge base is referenced to **Scopus Q1/Q2 articles** and credible agricultural sources (Section 8; DOIs and Scopus links in `backend/python/recommendation/SUMBER_REFERENSI.md`); (v) a Windows GPU runtime (TensorFlow 2.10 + CUDA 11.x) is used for the live services (Section 10); (vi) the dashboard trend chart and prediction table now show **statistical forecasts of all 15 sensor inputs** with per-sensor model selection (§7.8, references in README §10), replacing the LSTM health-confidence chart.
>
> **2026 sync note:** the Raspberry Pi servo–LiDAR pest-deterrent node (`backend/python/servo_lidar.py`) was removed from the repository and is no longer part of the implemented system; Section 4 below is kept only as a historical record and is marked accordingly. Several exploratory scripts that produced figures/tables not matching the manuscript's final numbers (`Training_Penyakit Padi/{compute_real_confusion_matrix,plot_real_confusion_matrix,make_figure4a_augmentation,find_misclassified_example}.py`, `backend/python/rnn/{generate_real_figure9,generate_real_figure9_800,compute_real_table5,compute_real_table5_blocked_cv}.py`, `backend/python/detect_penyakit/{detailed_analysis,generate_confusion_matrix,generate_paper_figures}.py`) were likewise removed; this document has been updated to reference only the scripts that actually reproduce the manuscript's reported numbers. The Table 7 benchmark (`benchmark_modern_models.py`) was also extended with a YOLOv11n-cls row (Section 12.1).
>
> **Later October 2026 update (application tier):** the web and mobile apps are branded **AgroGuard** (`APP_NAME`); file, firmware and service names keep the `taniverse` prefix.
> - **REST API v1:** returns screen-ready data (status objects, LSTM classes, conclusions, recommendations) computed by Laravel, so web and mobile show identical results (§5.1, §5.6).
> - **Flutter client:** `mobile/` mirrors every web page.
> - **Server-side sensor status:** labels live in `app/Support/SensorStatus.php`.
> - **Prediction and history tables:** these include measured rows, LSTM probability columns, farmer-worded statuses, causes and a conclusion (§7.8); a combined "Gabungan" view averages both plots.
> - **AI assistant:** an OpenAI-compatible API, currently Google Gemini, summarises the field and answers questions (§5.7).
> - **Access control:** the admin Data pages require the `view_user` permission (§5.2).
> - **Removed unused code:** the LiDAR surface (model, `/api/bacajson`, polls, monitoring boxes), the legacy firmware `Arduino/padi_sawah` and `Arduino/kirim_laravel`, scaffolded module routes, Vite and Docker files.
> - **Public path:** `public_path()` now points to `backend/html`, so uploaded profile photos are served.

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Notation and Symbol Table](#2-notation-and-symbol-table)
3. [Layer 1 — Perception / Field Sensing Nodes (ESP32 A gateway + ESP32 B)](#3-layer-1--perception--field-sensing-nodes-esp32-a-gateway--esp32-b)
4. [Layer 2 — Servo–LiDAR Scanning & Pest-Deterrent Node (removed)](#4-layer-2--servolidar-scanning--pest-deterrent-node-removed)
5. [Layer 3 — Communication, Authentication and Data Model (Laravel API)](#5-layer-3--communication-authentication-and-data-model-laravel-api)
6. [Layer 4 — Computer-Vision Disease & Pest Classification](#6-layer-4--computer-vision-disease--pest-classification)
7. [Layer 5 — LSTM Micro-climate & Plant-Health Forecasting](#7-layer-5--lstm-microclimate--plant-health-forecasting)
8. [Layer 6 — Offline Multi-Sensor Recommendation Engine](#8-layer-6--offline-multi-sensor-recommendation-engine-random-forest--referenced-knowledge-base)
9. [End-to-End Data & Control Flow](#9-end-to-end-data--control-flow)
10. [Deployment Topology](#10-deployment-topology)
11. [Reproducibility — Hyperparameter Tables](#11-reproducibility--hyperparameter-tables)
12. [Experimental Results Summary](#12-experimental-results-summary)

---

## 1. System Overview

The platform is a **five-layer** cyber-physical system for continuous, autonomous monitoring and decision support of irrigated rice paddies. (A sixth, Raspberry Pi servo–LiDAR scanning/deterrent node once existed as Layer 2; its source, `backend/python/servo_lidar.py`, has since been removed from the repository — see Section 4 for the historical record. Layer numbers below are kept as originally assigned, with Layer 2 retired rather than renumbering every downstream reference.)

```
                       ┌───────────────────────────────────────────────────────────────┐
                       │     PRESENTATION  Blade + ApexCharts (web)  ·  Flutter (app)  │
                       │  Dashboard · History · CNN · Data (admin) · AI · REST API v1  │
                       └───────────────▲───────────────────────────▲───────────────────┘
                                       │                           │
     ┌─────────────────────────────────┴──────────┐   ┌────────────┴───────────────────┐
     │  LAYER 6  Offline RF Recommendation Engine  │   │ LAYER 5  LSTM Forecaster (Flask│
     │  (weather P1 + soil/CNN/LSTM per plot, refs)│   │  :5000)  per-plot health       │
     └─────────────────────────────────▲──────────┘   └────────────▲───────────────────┘
                                       │                           │ pull history
                       ┌───────────────┴───────────────────────────┴───────────────────┐
                       │      LAYER 3  Laravel API + MySQL  (VPS, petaniasik.my.id)     │
                       │ POST /api/kirim_sensor · POST /api/kirim_gambar · GET /api/sensor│
                       └───▲───────────────────────────────────────┬───────────────────┘
                           │ HTTPS (JSON + JPEG)                   │ POST /detect (local)
            ┌──────────────┴────────────┐               ┌──────────▼──────────────┐
            │ LAYER 1  ESP32 A gateway   │               │ LAYER 4  CV classifier  │
            │ P1 weather+soil + camera A │               │ (Flask 127.0.0.1:7000)  │
            │   ▲ ESP-NOW                │               │ Haar/whole image +      │
            │ ESP32 B: P2 soil + camera B│               │ EfficientNet-B0 per plot│
            └────────────────────────────┘               └─────────────────────────┘
```

| Layer | Program(s) | Runtime | Core function |
|---|---|---|---|
| 1. Perception / Actuation | `Arduino/esp32_a_gateway/`, `Arduino/esp32_b_lapangan/` | ESP32-WROVER ×2 (Arduino C++, ESP-NOW) | A: weather + soil + camera A for plot 1 and gateway to the VPS; B: soil + camera B for plot 2 over ESP-NOW; UV / ultrasonic / irrigation relays per plot from the fused model (REST, optional MQTT) |
| 2. Scanning/deterrent *(removed)* | ~~`backend/python/servo_lidar.py`~~ | — | No longer present in the repository; see Section 4 |
| 3. Ingestion/API/presentation | `backend/laravel/**`, `backend/html/` (public root), `mobile/` (Flutter client) | PHP 8.2 / Laravel 12 + MySQL; Flutter 3 | Device token auth, persistence, firmware and REST API v1 endpoints, MQTT publish, web pages, server-side sensor status (`SensorStatus`), Excel export with charts, AI assistant (OpenAI-compatible API, currently Gemini), RBAC |
| 4. Vision | `Training_Penyakit Padi/{train,compare_cnn_backbones}.py`, `backend/python/detect_penyakit/app.py`, `benchmark_modern_models.py` | Python / TensorFlow-Keras + PyTorch | 10-class rice disease/pest classification from leaf images (Table 4 & Table 7 benchmarks) |
| 4.5 Fusion | `backend/python/hybrid_cnn_lstm/**` | Python / TensorFlow-Keras | Adaptive CNN+LSTM feature fusion (Eq. 1), unified pest + health + VPD heads, Algorithm 1 |
| 5. Forecasting | `backend/python/rnn/**`, `evaluate_lstm_microclimate_10k.py` | Python / Flask + TensorFlow-Keras | Multi-step micro-climate regression (Table 5) + 3-class plant-health forecast |
| 6. Recommendation | `backend/python/recommendation/**` (served at `POST /recommendation`), `app/Services/MultiTitikService.php`; fallback `app/Http/Controllers/RecommendationService.php` | Python / scikit-learn (+ PHP fallback) | Offline per-action Random Forest over weather (point 1) + per-plot soil, CNN, LSTM, and growth phase; referenced knowledge base (Scopus Q1/Q2 + IRRI/Balitbangtan) |

Design principles: (a) **edge pre-processing** — noisy analog channels are filtered on the microcontroller; (b) **thin cloud** — the server only authenticates, stores, and serves; (c) **model services are stateless** and pull their inputs from the same canonical REST endpoint; (d) **graceful degradation** — the health forecaster falls back from LSTM → classical model, unavailable forecasts are shown as an explicit "no forecast" message (never zero-filled values), and the CV pipeline is time-gated and self-pruning.

---

## 2. Notation and Symbol Table

| Symbol | Meaning | Unit |
|---|---|---|
| $t$ | discrete time index (sample) | – |
| $x_t \in \mathbb{R}^{d}$ | feature vector at time $t$ | – |
| $T$ | look-back window (sequence length), $T=6$ | samples |
| $\Delta$ | sampling interval; field node $\Delta=900\,\mathrm{s}$, forecaster $\Delta=4\,\mathrm{h}$ | s / h |
| $H$ | forecast horizon in steps, $H = 6\,d$ for $d$ days | samples |
| $N$ | total number of training samples | – |
| $K$ | number of classes | – |
| $n_c$ | number of training samples in class $c$ | – |
| $\sigma(\cdot)$ | logistic sigmoid, $\sigma(z)=\left(1+e^{-z}\right)^{-1}$ | – |
| $\odot$ | element-wise (Hadamard) product | – |
| $e_s, e_a$ | saturation / actual water-vapour pressure | kPa |
| $\mathrm{VPD}$ | vapour-pressure deficit | kPa |
| $\mathrm{RH}$ | relative humidity | % |
| $T_a, T_s$ | ambient / soil temperature | °C |

---

## 3. Layer 1 — Perception / Field Sensing Nodes (ESP32 A gateway + ESP32 B)

**Sources:** `Arduino/esp32_a_gateway/` (gateway, point 1), `Arduino/esp32_b_lapangan/` (point 2), shared `taniverse_espnow.h` (protocol) and `taniverse_common.h` (soil sensor, camera snapshot, battery), identical in both folders. Wiring and setup: `Arduino/README.md`. The former single-node firmware `Arduino/padi_sawah/` and the test sender `Arduino/kirim_laravel/` were removed from the repository in October 2026.

### 3.1 Architecture

```
Camera B ─hotspot (192.168.4.0/24)─► ESP32 B: RS485 8-in-1 soil (plot 2) ─ESP-NOW─┐
Camera A ─router LAN──────────────► ESP32 A: DHT22, anemometer, tipping bucket,    │
                                             BH1750, BMP280, RS485 8-in-1 (plot 1) ◄┘
ESP32 A ─router WiFi, HTTPS─► Laravel /api/kirim_sensor, /api/kirim_gambar, /api/inference
```

* **ESP32 A** (ESP32-WROVER with PSRAM) is the only device with internet access.
  * **Weather:** DHT22 (air $T$, RH; GPIO15), BH1750/GY-302 (illuminance, I²C 0x23, MTreg 31 for a range of about 120 klx), BMP280 (pressure in Pa, I²C 0x76/0x77), cup anemometer (GPIO4) and tipping bucket (GPIO18) on interrupts.
  * **Soil:** RS485 8-in-1 probe on `Serial2` (RX 32, TX 33, DE/RE 25; GPIO16/17 are taken by PSRAM on WROVER modules).
  * **Camera A:** JPEG snapshot fetched over HTTP from an IP camera on the router LAN.
  * **Relays:** three active-low relays for plot 1 (GPIO19 UV lamp, GPIO23 ultrasonic repeller, GPIO27 irrigation valve).
* **ESP32 B** (ESP32-WROVER) has no internet access.
  * **Soil:** the same RS485 8-in-1 probe and relays for plot 2.
  * **Camera B:** B runs a WiFi soft-AP to which camera B connects (static IP 192.168.4.10), and fetches its snapshot over that hotspot.
  * **Uplink:** data and images go to A over ESP-NOW.
* **Why snapshots:** RTSP video (H.264/H.265) cannot be decoded on an ESP32, which has about 520 KB of RAM and no video decoder. Both nodes therefore fetch the camera's HTTP JPEG snapshot URL. Basic authentication is tried first, then HTTP Digest (RFC 7616 MD5, `qop=auth`) if the camera answers 401. Snapshots are taken every 10 min between 06:00 and 18:00 WIB; night-time IR images are monochrome and unsuitable for the colour CNN.

### 3.2 Mathematical formulation per channel

**(a) Modbus-RTU soil probe.** `ModbusMaster` sends function 0x03 frames with the CRC-16/Modbus checksum (polynomial 0xA001, reflected, initial value 0xFFFF). Each quantity is read from its own holding register $R$ and scaled by $q = s\cdot R$, with $R$ read as signed for soil temperature. Two layouts are provided, and both must be checked against the sensor datasheet (`DUMP_REGISTER 1` prints registers 0x00–0x24):

| Quantity | `PETA_8IN1_UMUM` (register, scale) | `PETA_JXCT` (register, scale) |
|---|---|---|
| VWC [%] | 0x0000, 0.1 | 0x0012, 0.1 |
| Soil temperature [°C] | 0x0001, 0.1 (signed) | 0x0013, 0.1 (signed) |
| EC [µS cm⁻¹] | 0x0002, 1 | 0x0015, 1 |
| pH | 0x0003, 0.1 | 0x0006, 0.01 |
| N, P, K [mg kg⁻¹] | 0x0004–0x0006, 1 | 0x001E–0x0020, 1 |
| TDS [mg L⁻¹] | 0x0008, 1 | none |

Each read is retried up to 3 times. Values outside physical ranges (VWC 0–100 %, pH 0–14, EC/TDS 0–20 000, N/P/K 0–2000, $T_s$ −20…80 °C) are sent as `null`. If the probe has no TDS register, $\mathrm{TDS} = 0.64\,\mathrm{EC}$ (FAO-29) is used and flagged in the serial log; `TDS_DARI_EC 0` sends `null` instead.

**(b) Cup anemometer.** The ISR counts pulses with a 5 ms debounce. Every gate $\tau = 10$ s,
$$
f_{rot} = \frac{\Delta n}{\tau},\qquad
v = \begin{cases} 0, & \Delta n = 0 \text{ or } v' < 1.5\ \mathrm{m\,s^{-1}}\\ v' = -0.0181\,f_{rot}^{2} + 1.3859\,f_{rot} + 1.4055, & \text{otherwise}\end{cases}
$$
using the field calibration carried over from the previous firmware. The uploaded `windspeed` is the mean of all gates in the 15-min interval; the maximum gate value (gust) is logged. The old firmware handled at most one pulse per 15-min cycle because the interrupt flag was read only during acquisition. The ISR now counts every pulse.

**(c) Tipping bucket.** The ISR counts tips with a 250 ms debounce, $\delta = 0.40$ mm per tip. The uploaded value is $P = (n - n_0)\,\delta$, where $n_0$ is the count at the last *successful* point-1 upload. Rain is therefore not lost when an upload fails. Laravel sums the last 24 h for the BMKG categories.

**(d) VPD.** $e_s = 0.6108\exp\!\big(17.27T/(T+237.3)\big)$, $\mathrm{VPD} = e_s(1-\mathrm{RH}/100)$ (FAO-56). It is printed by A and recomputed on the server; it is not stored.

**(e) Battery.** $V_{bat} = \bar V_{adc}\,(R_1+R_2)/R_2$ from 16 `analogReadMilliVolts` samples on GPIO34 (ADC1), with $R_1 = 82\,\mathrm{k\Omega}$ and $R_2 = 10\,\mathrm{k\Omega}$. The state of charge is linear between 12.0 V and 16.8 V (4S Li-ion), clamped to 0–100 %. This is a coarse estimate because Li-ion discharge curves are not linear. The old formula $V/16.8$ reported 71 % for an empty pack.

**(f) Link quality.** $Q = \operatorname{clamp}(2(\rho+100), 0, 100)$ for RSSI $\rho$ in dBm. The old map $2(\rho+80)$ became negative below −80 dBm. For point 1, $\rho$ is the WiFi RSSI of A. For point 2, $\rho$ is the RSSI of B's ESP-NOW frames as received at A.

**(g) Actuation.** The fused-model command for plot $p$ comes from `GET /api/inference/{iot_id}?titik=p` every 15 s (MQTT optional):
$$
(\text{uv},\text{ultra},\text{irrig})_p =
\begin{cases}
(1,1,0), & \texttt{activate\_ultrasonic\_repeller\_and\_uv\_lamp}\\
(0,0,1), & \texttt{irrigation\_notification}\\
(0,0,0), & \text{otherwise or HTTP 404 (no inference yet)}.
\end{cases}
$$
The local night rule $\text{uv}_{night} = \mathbb 1[L < 100\ \mathrm{lx}]$ from A's BH1750 is OR-composed for both plots, because they share one area. On network errors the last command is kept, but a command older than 30 min is cleared at A. B also switches its relays off after 30 min without any message from A. The irrigation valve can therefore not stay latched on. The LiDAR intruder input (`/api/bacajson`) belonged to the removed Layer 2; the route was deleted from Laravel in October 2026.

### 3.3 ESP-NOW link between B and A

**Channel.** ESP-NOW frames are sent on the radio's current channel, and A's channel is fixed by its router. B's soft-AP, and with it ESP-NOW, must use that channel. B probes channels in the order $c^{*}, 1, \dots, 13$, where $c^{*}$ is the channel stored in NVS. It sends a `HELLO` on each channel; the first channel on which A returns a MAC-layer ACK is kept. Scans are limited to one per 5 min because each channel change briefly disconnects camera B. A fixed router channel (`KANAL_TETAP`) avoids scanning.

**Security.** Unicast frames are encrypted with a PMK and LMK (16 bytes each, identical at A and B). A peer is accepted only from the configured MAC.

**Image transfer.** An image of $S$ bytes is sent as $K = \lceil S/239\rceil$ chunks, each fitting the 250-byte ESP-NOW v1 payload:
`IMG_START(id, S, K, CRC32)` → $K\times$`IMG_CHUNK(id, i, len, data)` → `IMG_END(id)`.
Each frame waits for the MAC-layer ACK and is retried up to 5 times. A writes each chunk into a PSRAM buffer at offset $239\,i$, keeps a bitmap of received indices, and replies to `END` with:

* `OK`: all $K$ chunks were received and the CRC32 (IEEE 802.3) matches.
* `KURANG` / `CRC`: chunks are missing or corrupted; B resends the whole image once.
* `SIBUK`: the previous image is still being uploaded; B retries after 30 s, at most 3 times.
* `MEMORI`: $S$ exceeds the 200 KB buffer.

ESP-NOW packets are handled by a dedicated FreeRTOS task on A. Replies therefore stay fast while `loop()` blocks on HTTPS uploads.

**Time and commands.** Every reply from A (`BALASAN`), and every `PERINTAH` sent on change or every 5 min, carries A's NTP epoch and the plot-2 relay bits. B uses them for the photo schedule and its relays.

### 3.4 Node algorithms (pseudocode)

```
ESP32 A loop (non-blocking):
    every 10 s : wind gate → accumulate mean / max
    every 15 min: read DHT22 (≤3 tries), BH1750, BMP280, 8-in-1 soil, battery, RSSI
                  POST /api/kirim_sensor {titik:1, …}; on success n0 ← n_tip_at_read
                  failure → keep JSON, retry every 60 s until replaced by the next reading
    qSensorB not empty  → POST /api/kirim_sensor {titik:2, soil of B, battery_B, Q(RSSI_B)}
    image B complete    → POST /api/kirim_gambar (X-TITIK 2); success or 3 failures → free buffer
    every 10 min, 06–18 WIB: snapshot camera A → POST /api/kirim_gambar (X-TITIK 1)
    every 15 s: GET /api/inference/{id}?titik=1,2 → relays A; PERINTAH → B on change / 5 min
radio task A: HELLO → BALASAN | SENSOR (dedupe sampel_id) → qSensorB + BALASAN
              IMG_START/CHUNK/END → reassembly + CRC32 → BALASAN(status)

ESP32 B loop:
    every 15 min: read 8-in-1 soil + battery → SENSOR(sampel_id++) → wait MAC ACK
                  no ACK → channel scan (≤ once / 5 min) → resend; retry every 60 s
    every 10 min, 06–18 WIB (epoch from A): snapshot camera B → IMG_* → status handling
    on BALASAN / PERINTAH: store epoch, apply relay bits; no message from A for 30 min → relays OFF
    no ACK from A for 20 min → channel scan
```

---

## 4. Layer 2 — Servo–LiDAR Scanning & Pest-Deterrent Node (removed)

**Historical record only — not part of the current codebase.** This layer previously ran on a Raspberry Pi (`backend/python/servo_lidar.py`, plus a `servo_lidar - Copy.py`): a continuous-rotation servo swept a Benewake TF-Luna 1-D LiDAR, range readings were differenced in time to flag motion ($\lvert r_t-r_{t-1}\rvert>0.2\,\mathrm m$), and the result was written to a `lidars` table that the ESP32 (Layer 1) polled via `GET /api/bacajson/{iot_id}` to drive the ultrasonic-repeller relay as a secondary, non-model trigger.

Both `.py` sources have since been deleted from the repository, so this subsystem is no longer implemented. The current manuscript's Section 3.1 camera+LiDAR mention refers instead to depth-map input for the CV pest classifier (Layer 4), not to this scanning/deterrent node. The remaining Laravel surface was removed from the code in October 2026. That covers:
- the `Lidar` model;
- `GET /api/bacajson/{iot_id}` (`SensorController@getDataLidar`);
- the web endpoints `/datalidar` and `/monitor_lidar`;
- the two monitoring boxes ("Hama", "Ultrasonik") that displayed LiDAR distance and motion.

Only the create migration `2025_06_22_230627_create_lidars_table.php` remains, so existing databases still contain an empty `lidars` table until it is dropped manually.

---

## 5. Layer 3 — Communication, Authentication and Data Model (Laravel API)

**Source:** `backend/laravel/routes/api.php`, `app/Http/Controllers/SensorController.php`, `Modules/Dashboard/**`, `Modules/{Data,History,Yolo}/**`.

### 5.1 REST surface

| Method / route | Controller | Consumer | Purpose |
|---|---|---|---|
| `POST /api/kirim_sensor` | `SensorController@store` | ESP32 A (for points 1 and 2) | Ingest one telemetry record; optional `titik` ∈ {1,2} (default 1); weather fields of point 2 are stored as NULL |
| `POST /api/kirim_gambar` | `CameraUploadController@store` (throttle 30/min) | ESP32 A (camera A → titik 1, camera B via ESP-NOW → titik 2) | Raw JPEG body (≤ 2 MB), headers `X-IOT-ID`, `X-IOT-TOKEN`, `X-TITIK`; forwarded to Flask `POST /detect`; 201 stored, 200 nothing stored, 401/422 rejected, 502/503 detection service failure |
| `GET /api/sensor?iot_id=…[&titik=N]` | `SensorController@getByiot_id` | Flask LSTM service | Descending history (all points, `titik` column) for per-plot sequence building. No authentication beyond knowing `iot_id` |
| `POST /api/inference` | `HybridInferenceController@store` | hybrid edge node (`algorithm1.py`) | Ingest one fused CNN–LSTM inference (Algorithm 1 step 16); persists `hybrid_inferences`, re-publishes to MQTT |
| `GET /api/inference/{iot_id}[?titik=N]` | `HybridInferenceController@latest` | ESP32 A (relay commands) | Latest fused inference (pest + health + VPD + actuation) per plot. No authentication beyond knowing `iot_id` |
| `GET /datasensor`, `/datayolo`, `/getRecommendations` | `DashboardController` | dashboard (AJAX) | Weather (point 1) + soil/status per point; latest CNN detection per camera; per-plot recommendations from the offline model |
| `GET /ai/summary`, `POST /ai/chat` | `DashboardController` → `AiAssistantService` | dashboard (AJAX) | AI field summary and chat (§5.7); chat limited to 20/min |
| Flask `GET /forecast/1?days=d&titik=N` | `rnn/app/routes/api.py` | Laravel (admin health card) | Per-plot LSTM health forecast (point 2 merged with point-1 weather) |
| Flask `GET /sensor-forecast/<titik>?days=d` | `rnn/app/routes/api.py` → `services/sensor_forecast.py` | Laravel `MultiTitikService::sensorForecast` | Forecast of the 15 sensor inputs per plot, $d\in\{1,\dots,30\}$ (§7.8) |
| Flask `GET /health-history/<titik>?n=N` | `rnn/app/routes/api.py` → `forecast_service.health_history` | Laravel `MultiTitikService` | Batched LSTM health class for the last $N$ stored readings (LSTM columns of measured rows) |
| Flask `POST /recommendation` | `rnn/app/routes/api.py` → `recommendation.engine` | Laravel `MultiTitikService` | Offline Random Forest recommendation for the multi-point snapshot |
| `/api/v1/*` (REST API v1) | `App\Http\Controllers\Api\V1\*` + `App\Http\Resources\V1\*` | Flutter app / integrations | Sanctum Bearer tokens (`POST /api/v1/auth/login`). Endpoint groups:<br>• account: `me`, `logout`, `profile` with photo, `password`, `forgot-password`;<br>• screen-ready data: `dashboard`, `recommendations`, `forecast`, `history`, `detections/summary`, both Excel exports, `ai/summary`, `ai/chat`;<br>• `devices` (SuperAdmin);<br>• paginated `sensor-readings`, `detections`, `inferences`;<br>• `plots/{titik}` (+ `/forecast`), `snapshot`;<br>• device aliases `POST /api/v1/device/{sensor-readings,images}`.<br>Named rate limiters in `AppServiceProvider`; message language from `ApiLocale`. Reference: `backend/laravel/API.md` |
| web routes | `DashboardController`, `HistoryController`, `YoloController`, `DataController`, `UserController`, `RoleController`, `ProfileController`, `ActivityLogController` | browser | `/dashboard`, `/history`, `/cnn` (`/yolo` → 301), `/data/**` (admin), `/user`, `/role`, `/account/**`; Excel exports with native charts (PhpSpreadsheet) |

### 5.2 Authentication

Device authentication is a shared-secret pair check, not session auth:
$$
\text{authorised} \iff \exists\,u \in \text{users}:\; u.\texttt{iot\_id}=\texttt{iot\_id} \ \wedge\ u.\texttt{iot\_token}=\texttt{iot\_token}.
$$
On failure the endpoint returns `401`. Input is validated (`nullable|numeric|integer`) before persistence.

**Users.** Web users log in with a session (Laravel Breeze, email verification required); API clients use Sanctum Bearer tokens valid for `API_TOKEN_DAYS` days. Authorisation uses `spatie/laravel-permission`:
- the seeder `AkunUtamaSeeder` creates the permissions, the roles `SuperAdmin` and `User`, and the main account from `ADMIN_*`;
- every ordinary page and API endpoint is scoped to the user's own `iot_id`;
- the admin Data pages (`/data/**`, `/monitor_iot`, `/monitor_kamera`, per-user exports) require the `view_user` permission;
- user and role pages check their own permissions (`view_user`, `create_user`, `view_role`, …);
- in the API, only `SuperAdmin` may select another device with `?iot_id=`.

### 5.3 Persistence schema (MySQL, `padi_sawah`)

```
sensor_iots(  id, iot_id, titik, temperature, humidity, windspeed, rainfall,   # titik: 1 full station, 2 soil node
              light_intensity, ph, soil_moisture, ec, tds, soil_temp,
              pressure, feromon, battery_level, signal_strength,
              Nitrogen_Level, Phosphorus_Level, Potassium_Level, timestamps )

sensor_kameras( id, iot_id, titik, penyakit, probabilitas, image, timestamps )   # CV output, one camera per plot

# Unused legacy tables, empty and no longer referenced by code (create migrations still present):
#   lidars, historyiots, history_yolos

hybrid_inferences( id, iot_id, titik, image, pest_label, pest_class, pest_confidence,   # fused CNN–LSTM output
                   health_status, health_class, health_confidence, vpd_kpa,
                   microclimate(json), fusion_w1, fusion_w2,
                   actuation_action, actuators(json), transmitted_via, timestamps )

# History module models (Historyiot, HistoryYolo) read sensor_iots / sensor_kameras directly.
# Migration 2026_10_05_000000_add_titik_to_sensor_tables adds `titik` (default 1) and makes the
# sensor columns nullable decimal(10,2), matching the production schema.
# Migration 2026_10_06_000000_add_is_dummy_to_sensor_tables adds is_dummy to sensor_iots and
# sensor_kameras (rows written by `php artisan taniverse:dummy`).
# Framework tables: users, roles/permissions (spatie), personal_access_tokens (Sanctum),
# activity_log (spatie), sessions, cache, jobs, password_reset_tokens.
```

### 5.4 Bounded-storage (ring-buffer) policy

To keep the database bounded on disk, each writer enforces a hard cap and deletes only the **oldest** rows above it, so recent history is kept:

* `sensor_iots` (`SensorController`): cap $10^{5}$ rows.
* `sensor_kameras` (`detect_penyakit/app.py`, `MAX_ROWS`, default $2\times10^{4}$): oldest rows above the cap are deleted together with their uuid-named image files.
* `hybrid_inferences` (`HybridInferenceController`): cap $10^{4}$ rows.

```
ingest(request):
    v ← validate(request, schema)
    u ← users.where(iot_id=v.iot_id, iot_token=v.iot_token).first()
    if u is null: return 401
    SensorIot.create(v \ {iot_token})
    excess ← count(sensor_iots) − 100_000
    if excess > 0: delete the `excess` rows with the smallest id
    return 201
```

### 5.5 Dashboard orchestration

`DashboardController@index` renders the sensor forecast table and chart. The remaining panels are filled by AJAX polls: sensors every 15 s, cameras every 60 s, recommendations every 30 s; the AI panel loads its summary once and on request.

```
iot_id ← auth().user().iot_id ;  p ← request.titik ∈ {0 (Gabungan, default), 1, 2}
d ← clamp(request.days, 1, 30) (default 3)
if p = 0: F ← MultiTitikService.combineForecast({q: sensorForecast(iot_id, q, d) for each plot q})
                # mean values, statuses/causes per plot, worst conclusion
else:     F ← MultiTitikService.sensorForecast(iot_id, p, d)
                # GET {API_SENSOR_FORECAST}p?days=d (Flask :5000), §7.8; null if unreachable or F.iot_id ≠ iot_id
          F.measured ← 5 latest readings with LSTM health (GET /health-history/p)
render dashboard::index with (F → partials sensor-forecast-table / sensor-forecast-chart / rec-modal, plot + day selector)

/datasensor        → MultiTitikService.snapshot(iot_id)   # weather of point 1 + soil/status of every point
/datayolo          → latest SensorKamera per titik        # one image card per camera/plot
/getRecommendations:
    snap ← MultiTitikService.snapshot(iot_id)             # weather_1 (rain = 24 h total), soil_p, camera_p, planting_p
    R    ← POST {API_RECOMMENDATION} snap                 # offline RF (Section 8), cached 30 s
           on failure: RecommendationService per plot     # PHP fallback, same thresholds
    return {summary, petak: {p: recs, actuation_p, phase_p, health_p}, hybridInferences per titik}
```

The dashboard panels, top to bottom:
1. **Pemantauan Lingkungan Waktu Nyata:** VPD card and area weather from point 1, a soil table with one column per plot, and device status.
2. **Deteksi Hama CNN per Petak:** one card per camera with the photo, the pest-detection score and the plot's LSTM health score.
3. **Aksi Rekomendasi** and **Aksi Rekomendasi Keseluruhan**, side by side: the merged recommendation list, with plots and farmer-worded reasons, and the actions grouped per plot.
4. **Asisten AI** (§5.7).
5. **Prediksi** table.
6. **Tren Prediksi** chart.

All status labels come from `SensorStatus` (§5.6). If the Flask service is unreachable or the `iot_id` mismatches, the chart and table show "Belum ada ramalan sensor" instead of values (degradation path). The admin page `/data/monitoring/{iot_id}` uses the same partials.

`/history` (`HistoryController`) reuses the table and chart partials for measured rows only (`MultiTitikService::measuredHistory`, 1–30 days, newest first). `/cnn` (`YoloController` + `DetectionService`) shows the detection history with advice per disease. Both have Excel exports with native charts.

### 5.6 Server-side presentation and the mobile client

`app/Support/SensorStatus.php` turns each weather/soil value into `{code, label, level}`. It applies the knowledge-base thresholds of §8.3, plus display-only thresholds for light, sea-level pressure, soil temperature and available N; sources are in `SUMBER_REFERENSI.md` §C.2. The same function feeds the web cards, `GET /api/v1/dashboard`, and the Flutter app, so no client keeps its own thresholds. A device counts as online when its last reading is at most `OFFLINE_AFTER_MINUTES` = 35 min old.

The Flutter client (`mobile/`, displayed as AgroGuard) has six screens: Beranda, Prediksi, Riwayat, Deteksi CNN, Asisten AI and Akun. They call the screen endpoints of REST API v1, so their content equals the web pages; the mapping is in `mobile/README.md`.

### 5.7 AI assistant

`app/Services/AiAssistantService.php` builds a plain-text context from the system's own data:
- point-1 weather;
- per-plot soil, camera detection, LSTM class and growth phase;
- the current recommendation list with reasons.

It sends this context to an OpenAI-compatible chat-completions endpoint (`OPENAI_BASE_URL`, `OPENAI_API_KEY`, `OPENAI_MODEL`; currently Google Gemini `gemini-3.1-flash-lite`). The system prompt restricts answers to that data, forbids invented numbers or doses, and defers pesticide decisions to extension officers.

The summary is cached for 6 h per context hash, so it is regenerated when the data change. A chat sends the last 10 messages. The assistant triggers no action and is not part of the evaluated recommendation model.

---

## 6. Layer 4 — Computer-Vision Disease & Pest Classification

**Sources:** training — `Training_Penyakit Padi/train.py`; inference service — `backend/python/detect_penyakit/app.py`; benchmark — `backend/python/detect_penyakit/benchmark_modern_models.py` (Table 7); descriptive Figure 8 confusion matrix — `Training_Penyakit Padi/{compute_real_50perclass_matrix,plot_real_50perclass_confusion_matrix}.py`.

### 6.1 Task

10-way single-label image classification of rice leaf/panicle condition:

```
0 bacterial_leaf_blight   1 bacterial_leaf_streak   2 bacterial_panicle_blight
3 blast                   4 brown_spot             5 dead_heart
6 downy_mildew            7 hispa                  8 normal            9 tungro
```
Dataset layout: `train/ val/ test/` with one sub-directory per class (`torchvision.ImageFolder` / `keras.image_dataset_from_directory`).

### 6.2 Backbone — EfficientNet-B0 and compound scaling

EfficientNet-B0 is the MobileNet-V2-style inverted-residual (MBConv) network obtained by neural-architecture search, then scaled by the **compound coefficient** $\phi$:
$$
\text{depth } d=\alpha^{\phi},\quad
\text{width } w=\beta^{\phi},\quad
\text{resolution } r=\gamma^{\phi},\qquad
\text{s.t. } \alpha\cdot\beta^{2}\cdot\gamma^{2}\approx 2,\; \alpha,\beta,\gamma\ge 1 .
$$
B0 is the $\phi=0$ baseline ($\alpha=1.2,\ \beta=1.1,\ \gamma=1.15$), input $224\times224\times3$, ~5.3 M parameters.

A **depthwise-separable convolution** (the core of MBConv) factorises a standard conv of cost
$H\cdot W\cdot C_{in}\cdot C_{out}\cdot k^{2}$
into a depthwise part $H\cdot W\cdot C_{in}\cdot k^{2}$ plus a pointwise part $H\cdot W\cdot C_{in}\cdot C_{out}$, a reduction of
$$
\frac{1}{C_{out}} + \frac{1}{k^{2}} .
$$
Each MBConv block also carries a **squeeze-and-excitation** channel-attention gate
$\mathbf{s} = \sigma\!\big(W_2\,\delta(W_1\,\text{GAP}(\mathbf{u}))\big),\ \tilde{\mathbf u}=\mathbf s\odot\mathbf u$
and the **Swish** activation $\text{swish}(z)=z\,\sigma(z)$.

### 6.3 Transfer-learning head and two-stage optimisation

Classifier head appended to the frozen convolutional base:
$$
\mathbf h = \text{Dropout}_{0.2}\!\big(\text{GAP}(f_{\text{B0}}(\mathbf x))\big)
\;\to\; \mathbf g = \text{Dropout}_{0.2}\!\big(\text{ReLU}(W_1\mathbf h + b_1)\big),\ W_1\in\mathbb R^{128\times1280}
$$
$$
\hat{\mathbf y} = \operatorname{softmax}(W_2\mathbf g + b_2),\qquad
\hat y_c = \frac{e^{z_c}}{\sum_{j=1}^{K} e^{z_j}} .
$$

**Loss** — class-weighted categorical cross-entropy:
$$
\mathcal L = -\sum_{c=1}^{K} w_c\,y_c \log \hat y_c,
\qquad
\boxed{\,w_c = \dfrac{N}{K\,n_c}\,}
$$
which up-weights rare classes so that $\sum_c w_c n_c = N$.

**On-the-fly augmentation** $\mathcal A(\cdot)$ (Keras layers, manuscript sec. 3.3): random horizontal **and vertical** flip, random rotation $\pm25^{\circ}$ ($=25/360\times2\pi$), random crop with scale factor $0.8$–$1.0$ (`RandomZoom`), additive Gaussian noise with variance $0.01$ on the $[0,1]$ pixel scale.

Two-phase schedule (**50 epochs total**, manuscript Table 3; dropout $0.2$ in both phases):

| Phase | Trainable | Optimiser | LR | Epochs |
|---|---|---|---|---|
| 1 — head warm-up | base frozen | Adam | $10^{-3}$ | 40 |
| 2 — fine-tune | top 30 layers of B0 unfrozen | Adam | $10^{-5}$ | 10 |

**Validation protocol** (manuscript sec. 3.7): a stratified $70/15/15$ split for the headline model *and* a stratified **5-fold cross-validation** whose results are reported as mean $\pm$ standard deviation; fixed seed $42$. For the 5-fold mode specifically, all 10,407 images (train+val+test pooled) are repartitioned per fold into **≈65% train / ≈15% val / ≈20% test** (≈6,765 / 1,561 / 2,081 images; exact counts vary slightly with stratification/rounding) — this is the split that feeds **Table 4** and **Table 6**, not the CNN benchmark's fixed 8,323/2,084 split (Table 7, §6.5) or the hybrid model's final-deployment **85/15** train/val split (no test fold — Table 3). Both the 70/15/15 and 5-fold modes live in `Training_Penyakit Padi/train.py` (`--cv-folds 5`). The four-backbone comparison of **Table 4** (ResNet50 / MobileNetV2 / VGG16 / EfficientNet-B0 under one identical Keras transfer-learning protocol) is produced by `Training_Penyakit Padi/compare_cnn_backbones.py`.

**Adam** update (per parameter $\theta$, gradient $g_t$):
$$
m_t=\beta_1 m_{t-1}+(1-\beta_1)g_t,\quad
v_t=\beta_2 v_{t-1}+(1-\beta_2)g_t^{2},
$$
$$
\hat m_t=\frac{m_t}{1-\beta_1^{t}},\quad
\hat v_t=\frac{v_t}{1-\beta_2^{t}},\quad
\theta_t=\theta_{t-1}-\eta\,\frac{\hat m_t}{\sqrt{\hat v_t}+\varepsilon}.
$$

**Callbacks:** `ModelCheckpoint` (monitor `val_accuracy`), `EarlyStopping` (patience 6, restore best), `ReduceLROnPlateau` (`val_loss`, factor 0.5, patience 3, floor $10^{-7}$).

```
train_cnn(data_dir, cv_folds=0):
    paths, labels, class_names ← pool train/ + val/ + test/            # stratified re-split
    for (train_idx, test_idx) in split(labels, cv_folds):             # 70/15/15  or  5-fold CV
        w ← { c : N / (K * n_c) for c in class_names }                # class weights
        base ← EfficientNetB0(weights="imagenet", include_top=False); base.trainable ← False
        model ← Input → Augment → base(training=False) → GAP → Drop(.2)
                      → Dense(128,relu) → Drop(.2) → Dense(K,softmax)
        # ---- Phase 1 (40 ep) ----
        model.compile(Adam(1e-3), categorical_crossentropy, [accuracy])
        model.fit(train_ds, val_ds, epochs=40, class_weight=w, callbacks=CB)
        # ---- Phase 2 (10 ep, 50 total) ----
        base.trainable ← True; freeze all but last 30 layers of base
        model.compile(Adam(1e-5), categorical_crossentropy, [accuracy])
        model.fit(train_ds, val_ds, epochs=10, class_weight=w, callbacks=CB)
        evaluate(model, test_idx) → accuracy, P/R/F1 (macro)
    report mean ± std across folds ; save efficientnetb0_model.h5, class_names.json
```

### 6.4 Inference service (`app.py`)

Flask service on port 7000; on the VPS it binds to 127.0.0.1 (`DETECT_HOST`). One model is shared under a lock.

**Main mode: images from ESP32 A.** Laravel forwards each snapshot to `POST /detect?titik=N&iot_id=…`:

1. **Decode:** `cv2.imdecode`; invalid body → 400.
2. **Circadian gate:** process only if the hour lies in `DETECT_HOURS` (default 6–23, process time zone `TZ=Asia/Jakarta`). Otherwise reply 200 with a reason; nothing is stored.
3. **Leaf ROI localisation:** Haar-cascade `detectMultiScale` on the greyscale frame
   $\;\;Y = 0.299R + 0.587G + 0.114B$,
   parameters `scaleFactor=1.05`, `minNeighbors=7`, `minSize=150²`, `maxSize=800²`.
   The cascade evaluates Haar-like features via the **integral image**
   $\;II(x,y)=\sum_{x'\le x,\,y'\le y} Y(x',y')$
   through an AdaBoost stage cascade: a window passes only if every stage satisfies $\sum_k \alpha_k h_k(\mathbf x) \ge \theta_{\text{stage}}$.
4. **Classification:** crop → resize $224\times224$ → **BGR→RGB** → `img_to_array` → `efficientnet.preprocess_input` → `model.predict`,
   $$
   \hat c = \arg\max_c \hat y_c,\qquad p = 100\cdot\max_c \hat y_c\ [\%].
   $$
   The most relevant leaf is kept: a disease outranks "Normal", then higher $p$ wins.
   * **Colour order.** The model was trained on RGB images (`tf.io.decode_image`). The former service fed OpenCV's BGR arrays. On 150 validation images, full-image accuracy was 0.373 with BGR input versus 0.973 with RGB input (validation data were used for model selection, so 0.973 is optimistic).
5. **Whole-image fallback:** if no ROI is found and `FULL_FRAME_FALLBACK=1` (default), the whole frame is classified. This matches the training input (whole Paddy Doctor images resized to 224). The cascade found no ROI in 120/120 dataset images tested. The response reports `mode` (`roi_daun` or `gambar_penuh`).
6. **Persistence:** write `img/hama_padi/{uuid}.jpg`, then `INSERT INTO sensor_kameras(iot_id, titik, penyakit, probabilitas, image, created_at)` with `created_at` from the Python process clock (WIB). If the insert fails, the image file is deleted. Only the oldest rows beyond `MAX_ROWS` (default 20 000) are removed, together with their uuid-named images.
7. **Response:** `{titik, detected, saved, penyakit, probabilitas, mode, jumlah_daun, reason?}`; status 201 if stored, else 200. The last uploaded frame of each plot is served at `/video_feed/<titik>`.

**Optional stream mode.** `CAMERAS="0:1,1:2"` (source:titik, a webcam index or stream URL) starts one capture thread per plot. Steps 2–4 and 6 are the same. At most one detection per camera is stored every `SAVE_INTERVAL` seconds (default 30), and the stream path has no whole-image fallback.

```
detect_upload(jpeg, titik, iot_id):
    frame ← imdecode(jpeg)
    if hour(now, TZ) ∉ DETECT_HOURS: return 200 {reason}
    best ← None
    for (x,y,w,h) in leafCascade.detectMultiScale(gray(frame), 1.05, 7, 150², 800²):
        (c, p) ← predict(RGB(resize(frame[y:y+h, x:x+w], 224)))
        best ← argmax over ((c ≠ Normal), p)
    if best is None and FULL_FRAME_FALLBACK: best ← predict(RGB(resize(frame, 224))), mode ← gambar_penuh
    if best is None: return 200 {reason: no leaf}
    saved ← save(image, INSERT sensor_kameras(iot_id, titik, label, p, uuid.jpg, now_WIB))
    return (201 if saved else 200) {detected, saved, penyakit, probabilitas, mode}
```

### 6.5 Benchmark protocol (`benchmark_modern_models.py`)

Eight checkpoints are compared under one evaluation protocol (same 2,084-image validation partition, image-level classification metrics): `timm` pretrained models for {EfficientNetV2-S, EfficientNet-B0, ViT-B/16, ResNet-50, MobileNet-V2, VGG-16} and Ultralytics **YOLOv8n-cls** *and* **YOLOv11n-cls** for the YOLO classification family. `run_yolo_experiment()` is generalised (model key / default weights / run-name prefix / display name) so both YOLO variants share one implementation and one per-variant checkpoint-discovery path under `yolo_runs/`; training config (5 epochs, batch 8, imgsz 224, CPU execution, Ultralytics classification augmentation defaults) is identical between the two YOLO rows — see `experiments/yolov11_addition/yolov11_provenance.md` for the full provenance trail (including the CUDA `misaligned address` crash on the first GPU attempt, resolved by falling back to CPU, matching the YOLOv8 baseline's own documented reason for training on CPU).

* **Transforms (train):** `Resize 224²`, `RandomHorizontalFlip(0.5)`, `RandomRotation(10°)`, `ColorJitter(0.15,0.15,0.15)`, `ToTensor`, `Normalize(μ=[.485,.456,.406], σ=[.229,.224,.225])`. Eval: resize + normalise only.
* **Optimiser:** AdamW ($\eta=10^{-4}$, weight-decay $10^{-4}$), decoupled update
  $\theta_t = \theta_{t-1} - \eta(\hat m_t/(\sqrt{\hat v_t}+\varepsilon) + \lambda\theta_{t-1})$.
* **LR schedule:** cosine annealing $\;\eta_t = \tfrac{1}{2}\eta_{\max}\big(1+\cos(\pi t/T_{\max})\big)$.
* **Loss:** softmax cross-entropy. **Selection:** best macro-F1 on `val`, early stop patience 7, ≤ 30 epochs.
* **YOLOv8-cls** training args: `lr0=0.01, lrf=0.01, momentum=0.937, weight_decay=5e-4, warmup_epochs=3, mosaic=1.0, auto_augment=randaugment, erasing=0.4, imgsz=224, batch=16`.

**Metrics.** With confusion matrix $M\in\mathbb N^{K\times K}$ ($M_{ij}$ = true $i$, predicted $j$):
$$
\text{Acc} = \frac{\sum_i M_{ii}}{\sum_{ij}M_{ij}},\quad
P_c=\frac{M_{cc}}{\sum_i M_{ic}},\quad
R_c=\frac{M_{cc}}{\sum_j M_{cj}},\quad
F1_c = \frac{2P_cR_c}{P_c+R_c},
$$
$$
\text{macro-}F1=\frac1K\sum_c F1_c,\qquad
\text{weighted-}F1=\sum_c \frac{n_c}{N}F1_c .
$$

```
run_torch_experiment(model_name):
    model ← timm.create_model(model_name, pretrained=True, num_classes=K)
    opt   ← AdamW(lr=1e-4, wd=1e-4);  sched ← CosineAnnealingLR(T_max=epochs)
    best_f1 ← -1;  patience ← 0
    for epoch in 1..30:
        train_one_epoch(model, train_loader, CE, opt)
        y_true,y_pred ← evaluate(model, val_loader)
        f1 ← macro_F1(y_true, y_pred);  sched.step()
        if f1 > best_f1: best_f1←f1; patience←0; save_ckpt()
        else: patience←1+patience;  if patience ≥ 7: break
    load best_ckpt
    y_true,y_pred ← evaluate(model, test_loader)
    dump metrics(accuracy, P/R/F1 macro+weighted) ; save confusion_matrix.png
```

---

## 6.6 Layer 4.5 — Hybrid CNN–LSTM Feature Fusion & Algorithm 1

**Sources:** `backend/python/hybrid_cnn_lstm/` — `fusion_model.py` (model factory + `AdaptiveFeatureFusion` layer), `tune_fusion_weights.py` (grid search), `train_hybrid.py`, `evaluate_hybrid.py` (Table 6), `algorithm1.py` (Table 2), `make_paired_dataset.py` (paired manifest), `data_pipeline.py`.

The manuscript's central contribution is a **multimodal model** that fuses the spatial (CNN) and temporal (LSTM) branches instead of running them as isolated services.

### 6.6.1 Adaptive feature fusion (Eq. 1)

Each branch is projected to a common dimensionality $n = 128$:
$$
R_{\text{visual}} = \text{ReLU}\!\big(W_v\,\text{GAP}(f_{\text{B0}}(\mathbf x))\big)\in\mathbb R^{n},\qquad
R_{\text{environment}} = \text{ReLU}\!\big(W_e\,\text{LSTM}_{32}(\mathbf s_{1:T})\big)\in\mathbb R^{n},
$$
and combined by a **fixed** convex weighting
$$
\boxed{\,R_{\text{total}} = w_1\,R_{\text{visual}} + w_2\,R_{\text{environment}},\qquad w_1+w_2 = 1\,}
$$
implemented by `AdaptiveFeatureFusion(w1)`. The weights are **hyper-parameters**, not learned: `tune_fusion_weights.py` sweeps $w_1\in\{0,0.1,\dots,1\}$ under stratified 5-fold CV and selects the value maximising mean validation pest-F1 (tie-break: min loss). The manuscript optimum is $w_1 = 0.6,\ w_2 = 0.4$ (`fusion_weights.json`).

### 6.6.2 Decision heads (Eq. 3)

$$
P_{\text{pest}} = \operatorname{softmax}(W_p R_{\text{total}}),\quad
P_{\text{health}} = \operatorname{softmax}(W_h R_{\text{total}}),\quad
\hat{\mathbf m} = W_m R_{\text{total}}\ (\text{linear, VPD} + 4\ \text{micro-climate targets}).
$$
Loss $= \mathcal L_{\text{pest}} + \tfrac12\mathcal L_{\text{health}} + \tfrac12\,\text{MSE}_{\text{micro}}$; Adam $10^{-3}$, batch 32, dropout 0.2, seed 42, stratified 5-fold CV. The **no-fusion baseline** of Table 6 replaces the weighted sum with concatenation + a dense projection (`fusion="concat"`).

### 6.6.3 Paired dataset

Real synchronised image↔sensor pairs are not distributed, so `make_paired_dataset.py` attaches a **reproducible synthetic** $T=6$ micro-climate window (seed 42) to every leaf image, timestamp-aligned ($|\Delta t| = 0 \le 5$ min). Disease state is coupled to atmospheric stress (healthy → low VPD, diseased → high VPD) so fusion carries genuine cross-modal signal — the same synthetic-series methodology already used by Layer 5.

### 6.6.4 Algorithm 1 (Table 2) — `algorithm1.py`

```
for each timestep t:
    F_t, S_t ← capture(camera, IoT)                                  # 2
    S_t ← linear_interpolate(S_t)  if NaN                            # 3
    x_img ← resize224(F_t)                                           # 4
    x_sen ← minmax(S_t) ⊕ cyclical_hour ⊕ (temp×humidity)           # 5
    R_visual      ← CNN(x_img)                                       # 6
    R_environment ← LSTM(x_sen)                                      # 7
    assert |t_img − t_sen| ≤ 5 min                                   # 8
    R_total ← w1·R_visual + w2·R_environment                         # 9
    P ← softmax(R_total)                                             # 10
    pest_class, pest_conf ← argmax(P_pest)                           # 11
    pest_label ← pest_class if pest_conf ≥ τ_p else "No pest detected"   # 12
    health_class, health_conf ← argmax(P_health)                     # 13
    status ← health_class if health_conf ≥ τ_health else "Monitor"   # 14
    if pest_label ≠ "No pest detected":                              # 15
        if pest_class == "hispa": actuate(ultrasonic_repeller, uv_lamp)
        else: recommend(selective_insecticide_spraying)
    elif status == "High Stress": notify(irrigation)
    else: continue_monitoring
    transmit(payload → Laravel REST + MQTT broker)                   # 16
```
$\tau_p = \tau_{\text{health}} = 0.5$. Step 16 is a `POST` to `--laravel-url` and an MQTT publish to `taniverse/{iot_id}/inference` (both optional; `--dry-run` by default). This is the model-level counterpart of the Layer 6 rule engine — Layer 6 stays the deterministic agronomic advisory on the raw sensor record.

### 6.6.5 Table 6 protocol — `evaluate_hybrid.py`

Four rows (CNN-only, LSTM-only, CNN+LSTM concat, proposed fusion) × {Accuracy, F1, VPD RMSE, latency ms/frame}, each as mean ± std over stratified 5-fold CV; latency is the median of single-sample `predict` calls.

---

## 7. Layer 5 — LSTM Micro-climate & Plant-Health Forecasting

**Sources:** offline regression study (reproduces manuscript Table 5 / Figure 9 exactly) — `backend/python/rnn/{evaluate_lstm_microclimate_10k,generate_real_figure9_2025dates,rebuild_figure9_labeled,make_synthetic_microclimate_dataset}.py`, `display_results.py`; worked single-example paragraph (sec. 4.2) — `run_real_vpd_example.py`; Figure 4(b) JSON panel — `make_figure4b_json_output.py`; online service — `backend/python/rnn/run.py`, `app/__init__.py`, `app/routes/api.py`, `app/services/{model_service,forecast_service,data_service}.py`, `app/utils/data_processor.py`; artefacts — `config/plant_health_lstm_model.h5`, `feature_scaler.pkl`, `label_encoder.pkl`, `feature_columns.pkl`, `model_config.pkl`.

**Per-plot data (online service).** `DataService.split_by_titik` groups the `/api/sensor` history by `titik`. Point-2 rows, which have no weather, receive the point-1 weather of the nearest timestamp (`pandas.merge_asof`, direction *nearest*). The history is refreshed at most every 60 s. Column names from Laravel (`soil_moisture`, `temperature`, …) are mapped to the training names (`Soil_Moisture`, `Ambient_Temperature`, …) in `data_processor.normalize_columns`. Without this mapping the production LSTM receives almost only zeros.

There are **two LSTM models** sharing one architecture family:

| | Regression model (`evaluate_lstm_microclimate_10k.py`) | Production plant-health model (`config/*.pkl/.h5`) |
|---|---|---|
| Input | $T=6$ × 5 features `[Ta, RH, pH, L, VPD]` | $T=6$ × **34** engineered features (`model_config.pkl`) |
| Output | 5-D real vector (next-step $[Ta,RH,pH,L,\mathrm{VPD}]$) | 3-class softmax `{Healthy, Moderate Stress, High Stress}` |
| Loss | MSE | categorical cross-entropy |
| Head activation | linear | softmax |

### 7.1 Vapour-Pressure Deficit (physical feature)

Saturation vapour pressure by the Tetens/Magnus equation and the resulting deficit:
$$
e_s(T) = 0.6108\,\exp\!\left(\frac{17.27\,T}{T+237.3}\right)\ [\mathrm{kPa}],\qquad
e_a = e_s\,\frac{\mathrm{RH}}{100},\qquad
\boxed{\ \mathrm{VPD} = e_s - e_a = e_s\!\left(1-\tfrac{\mathrm{RH}}{100}\right)\ }
$$
VPD is both an LSTM input/target and a recommendation trigger (Layer 6).

### 7.2 Sequence construction

Given a resampled multivariate series $\{x_t\}_{t=1}^{n}$ at $\Delta = 4\,\mathrm h$ (so $T=6$ spans 24 h), sliding windows are
$$
X^{(i)} = (x_i, x_{i+1}, \dots, x_{i+T-1}) \in \mathbb R^{T\times d},\qquad
y^{(i)} = x_{i+T},\qquad i = 1,\dots,n-T.
$$
Min–max normalisation per feature $j$ (fitted on train only):
$$
\tilde x_{j} = \frac{x_j - \min_j}{\max_j - \min_j}\in[0,1],
$$
inverted after prediction: $\hat x_j = \tilde{\hat x}_j(\max_j-\min_j)+\min_j$.

### 7.3 Feature engineering for the 34-D production model

From `data_processor.process_for_lstm` / `process_for_prediction`:

* **Cyclical time encoding** (avoids the 23→0 discontinuity):
  $$
  \text{Hour}_{\sin}=\sin\!\frac{2\pi\,\text{h}}{24},\ \text{Hour}_{\cos}=\cos\!\frac{2\pi\,\text{h}}{24};\quad
  \text{Day: } /31,\quad \text{Month: } /12 .
  $$
* **Interaction terms:** $\text{Moisture\_Temp}= \theta\cdot T_s/100$, $\text{Humidity\_Temp}= \mathrm{RH}\cdot T_a/100$.
* **Nutrient descriptors:** $\text{NPK\_Balance} = (N+P+K)/3$, ratios $N/P$, $N/K$ (and $\text{NPK\_Ratio}_{\{N,P,K\}} = \{N,P,K\}/\text{NPK\_Balance}$ in the classical path).
* **Local dynamics:** rolling mean $\bar x_t^{(w)}=\frac1w\sum_{k=0}^{w-1}x_{t-k}$ ($w=3$) and change rate (percent change) $\rho_t = (x_t-x_{t-1})/x_{t-1}$.
* **24 h aggregates & trend (classical path):** $\bar x_{24h}$ = mean of last 6 readings; $\text{trend} = \dfrac{x_{t}-x_{t-5}}{\Delta t\,[\mathrm s]}$.
* **Quantile stress flags (classical path):** with historical quartiles,
  $\text{Moisture\_Stress}= \mathbb 1[\theta < Q_{25}(\theta)]$,
  $\text{Temperature\_Stress}= \mathbb 1[T_s>Q_{75}(T_s)\ \vee\ T_s<Q_{25}(T_s)]$,
  $\text{Light\_Stress}= \mathbb 1[L<Q_{25}(L)]$.

### 7.4 LSTM cell equations

For input $x_t\in\mathbb R^{d}$, previous hidden $h_{t-1}$ and cell $c_{t-1}$:
$$
\begin{aligned}
f_t &= \sigma(W_f x_t + U_f h_{t-1} + b_f) &&\text{(forget gate)}\\
i_t &= \sigma(W_i x_t + U_i h_{t-1} + b_i) &&\text{(input gate)}\\
\tilde c_t &= \tanh(W_c x_t + U_c h_{t-1} + b_c) &&\text{(candidate)}\\
c_t &= f_t \odot c_{t-1} + i_t \odot \tilde c_t &&\text{(cell state)}\\
o_t &= \sigma(W_o x_t + U_o h_{t-1} + b_o) &&\text{(output gate)}\\
h_t &= o_t \odot \tanh(c_t) &&\text{(hidden state)}
\end{aligned}
$$

**Stacked architecture** (both variants, `build_lstm_regression_model`, defined in `evaluate_lstm_microclimate.py` and reused by `evaluate_lstm_microclimate_10k.py`):
$$
\mathbf x_{1:T}
\xrightarrow{\text{LSTM}_{64},\,\text{seq}}
\text{BN}
\xrightarrow{\text{LSTM}_{32}}
\text{BN}\to\text{Drop}_{0.2}
\to \text{ReLU}(W_{32}\cdot)\to\text{BN}\to\text{Drop}_{0.2}
\to
\begin{cases}
W_{5}\cdot \ (\text{linear}) & \text{regression}\\
\operatorname{softmax}(W_{3}\cdot) & \text{health}
\end{cases}
$$

**Batch normalisation:** $\hat z = \gamma\,\dfrac{z-\mu_{\mathcal B}}{\sqrt{\sigma_{\mathcal B}^{2}+\varepsilon}}+\beta$.
**Dropout** ($p=0.2$): $\tilde h = h\odot \text{Bernoulli}(1-p)/(1-p)$.
Optimiser `adam`; regression loss $\text{MSE}=\frac1n\sum(y-\hat y)^2$, metric MAE.

### 7.5 Regression evaluation metrics

Per parameter $k$:
$$
\text{MAE}_k = \frac1n\sum_{t}\lvert y_{t,k}-\hat y_{t,k}\rvert,\qquad
\text{RMSE}_k = \sqrt{\frac1n\sum_{t}(y_{t,k}-\hat y_{t,k})^{2}},
$$
$$
R^2_k = 1 - \frac{\sum_t (y_{t,k}-\hat y_{t,k})^{2}}{\sum_t (y_{t,k}-\bar y_{k})^{2}} .
$$

```
generate_real_figure9_2025dates():                  # backend/python/rnn/, matches manuscript Table 5 exactly
    data ← synthetic_microclimate(n=10_000, seed=42)  # diurnal+seasonal sinusoids + noise; VPD from Tetens/Magnus
    F ← data[[temperature,humidity,ph,light_intensity,vpd]]
    scaler_X, scaler_y ← MinMax().fit(F)
    X, y ← sliding_windows(scaler(F), T=6)             # X:(9994,6,5)  y:(9994,5)
    # ---- chronological holdout : 6,396 train / 1,599 val / 1,999 test (manuscript sec. 3.3.1 / Table 3) ----
    model ← Sequential([LSTM64→BN→LSTM32→BN→Drop.2→Dense32,relu→BN→Drop.2→Dense5,linear])
    model.compile(Adam(1e-3), mse, [mae]); model.fit(X_train, y_train, epochs=100, batch=32, validation_data=(X_val,y_val))
    y_pred ← model.predict(X_test)                     # single fixed split, NOT k-fold — point estimates, no ± std
    for k in [temperature,humidity,ph,light_intensity,vpd]: report MAE_k, RMSE_k, R²_k   # → manuscript Table 5
    save figure9_2025dates_predictions.csv             # consumed by rebuild_figure9_labeled.py for the (a)-(e) panel figure
    # separately: evaluate_lstm_microclimate_10k.rolling_vpd_forecast_r2() gives the 14-day rolling VPD
    # forecast mentioned in sec. 4.5; the manuscript does not quote a specific R² for that rolling run,
    # only the full 1,999-window holdout numbers above (Table 5) — do not conflate the two.
```

`evaluate_lstm_microclimate.py` (no `_10k` suffix) is still imported by `evaluate_lstm_microclimate_10k.py` for `cross_validate_regression`/`rolling_vpd_forecast_r2`, but its own directly-run output uses a different (smaller/earlier) data configuration and does **not** reproduce the manuscript's Table 5 numbers — treat it as a dependency, not a reproduction script.

### 7.6 Online recursive multi-step forecasting (`forecast_service.py`)

The service produces $H = 6d$ steps ($d\le 14$ days, 4 h grid). True future drivers are unknown, so each step projects the driver variables **from measured data only**, appends the projected reading to a working copy of the history, rebuilds and re-scales the $T=6$ sequence, and lets the LSTM attach `predicted_health` and class confidences.

* **Diurnal channels** (air temperature, RH, light, soil temperature): the plot's own **hour-of-day mean** computed from its stored history, using the nearest available hour, $\hat x(\eta)=\overline{x}_{h=\eta}$. Without history the last value is kept.
* **Soil channels** (VWC, pH, N, P, K): **persistence** (last valid value), the standard naive baseline. There are no synthetic irrigation events and no range clipping.
* **Missing readings:** the firmware sends `null` for failed sensors. The service uses the last valid value of that column, and returns `null` (never `NaN` and never an invented default) when the plot has no valid value.

*Superseded (pre-October 2026):* drivers were generated with fixed formulas ($T^\*=22+6\lambda$, RH$^\*=70-2(T-20)$, light $200+600\lambda$, a +15 % "watering event" below 20 % moisture) and NPK was clipped to $[5,50]$. Those values were not measurements and were removed.

```
generate_lstm_forecast(plot, days):
    hist ← DataService.plant_data[plot] sorted by created_at
    if len(hist) < T: return None
    profiles ← hour-of-day means of {T_a, RH, light, T_s} from hist
    cur ← last valid value of every column
    out ← [ current_entry + predict_lstm(sequence(hist)) ]
    for each 4-h step:
        cur ← project(cur, profiles, hour)          # profile or persistence
        hist ← hist + row(cur)
        out.append({..., predicted_health: predict_lstm(sequence(hist))})
    return out
```

### 7.7 Classical fallback model

`model_service.predict_traditional` loads a scikit-learn estimator (`plant_health_prediction_model.joblib`) and returns
$\hat c = \arg\max_c \Pr(c\mid \mathbf x)$ with the full class-probability vector from `predict_proba`. Feature construction is `process_for_prediction` (§7.3, aggregates + quantile stress flags). This path is used when the Keras LSTM or its pre-processing artefacts fail to load.


### 7.8 Sensor forecasting for the dashboard (`sensor_forecast.py`)

The trend chart and prediction table forecast the 15 sensor inputs $\{T, RH, \text{VPD}, u, P_r, E_v, p, \text{pH}, \theta_v, \text{EC}, \text{TDS}, T_s, N, P, K\}$ directly, instead of LSTM health confidences. Each measured series $y$ (13 sensors) is averaged into 4-hour blocks ($\Delta = 4$ h, season $m = 6$); rainfall is summed per block. Interior gaps of at most 2 blocks are interpolated for continuous sensors only.

**Candidates** (horizon $h = 1..H$, $H = 6d$):

$$
\hat y^{\text{naive}}_{n+h} = y_n,\qquad
\hat y^{\text{snaive}}_{n+h} = y_{n+h-m\lceil h/m\rceil},
$$

$$
\text{SES: } \ell_t = \alpha y_t + (1-\alpha)\ell_{t-1},\ \hat y_{n+h} = \ell_n,
$$

$$
\text{damped additive Holt-Winters: } \hat y_{n+h} = \ell_n + (\phi + \phi^2 + \dots + \phi^h)\, b_n + s_{n+h-m\lceil h/m\rceil},
$$

and, for rainfall, the block-of-day climatology $\hat y_{n+h} = \overline{y}_{\text{slot}(n+h)}$ (Murphy 1988). Smoothing parameters are estimated by statsmodels (Hyndman et al. 2002; Gardner 2006).

**Selection.** Rolling-origin backtest (Tashman 2000) with up to 4 origins, each with a 1-day horizon and at least 3 days of training data. The chosen model has the lowest mean absolute backtest error, which is the same as the lowest

$$
\text{MASE} = \frac{\tfrac1h\sum_{j=1}^{h}|y_{o+j}-\hat y_{o+j}|}{\tfrac{1}{n-m}\sum_{t=m+1}^{n}|y_t - y_{t-m}|}
$$

(Hyndman & Koehler 2006), because the denominator is common to all candidates of one series. MAE and MASE of the chosen model are reported. With fewer than 24 blocks the service uses persistence and reports it in `note`. Forecasts are clipped to physical bounds.

**Derived variables.** $\text{VPD} = e_s(T)\,(1-RH/100)$ with $e_s = 0.6108\exp\!\big(17.27T/(T+237.3)\big)$ (FAO-56, Eq. 11–19) from the forecast $T$ and $RH$; $\text{TDS} = k\,\text{EC}$ with $k$ the median measured TDS/EC ratio of the plot (at least 3 blocks with both values, accepted if $0.3 \le k \le 1$), i.e. the 8-in-1 sensor's own conversion; otherwise $k = 0.64$ (FAO-29). The chosen $k$ and its origin are returned in the `formula` field.

**Status.** Every row is compared with the knowledge-base thresholds of §8.3 (`knowledge_base.T`: heat, blast weather, bacterial-blight rain, VWC < 35 %, salinity, pH, wind). No new thresholds are introduced. Laravel (`MultiTitikService::STATUS_LABELS`, `statusReason`) shows each status in farmer wording, for example "Rawan penyakit blas", together with its cause, for example "Kelembapan 92% ≥ 90%, suhu 24.3 °C (20–28 °C)".

**Table rows and conclusion.** Laravel prepends the 5 latest measured readings to the forecast rows. Each row carries the LSTM health probabilities $(p_{\text{healthy}}, p_{\text{moderate}}, p_{\text{high}})$:
- measured rows: from `GET /health-history/<titik>`, a batched LSTM run on stored readings with inputs clipped to the scaler range;
- forecast rows: from the recursive LSTM forecast (§7.6).

The conclusion is

$$
\text{conclusion}=\begin{cases}
\text{Bahaya}, & \text{status}\neq\varnothing \wedge \text{class}=\text{High Stress}\\
\text{Waspada}, & \text{status}\neq\varnothing\\
\text{Cek lapangan}, & \text{class}=\text{High Stress}\\
\text{Aman}, & \text{otherwise.}
\end{cases}
$$

In the combined view (`titik=0`) values are averaged over the plots, statuses and causes are prefixed with the plot name, and the conclusion is the worst plot's. The horizon is $d\in\{1,\dots,30\}$ days.

```
forecast_sensors(history, d, p):
    X ← resample to 4 h blocks (mean; rainfall sum) ; point 2: weather from point 1 (merge_asof)
    for each measured sensor y in X:
        if |y| < 24: ŷ ← naive(y), note "too short"
        else: for c in candidates(kind(y)): score_c ← mean MASE over rolling origins
              ŷ ← fit argmin_c score_c on all of y, forecast H steps, clip to bounds
    ŷ_VPD ← FAO56(ŷ_T, ŷ_RH) ;  ŷ_TDS ← k·ŷ_EC   (k = median TDS/EC measured, else 0.64)
    rows ← for each step: values, rainfall_24h (rolling sum of 6 blocks), status(knowledge_base)
    return {iot_id, titik, variables[method, MAE, MASE], history[last 12 blocks], forecast rows}
```

The references with DOI, quartile and Scopus source links are listed in README §10.2 and `SUMBER_REFERENSI.md` §G.

---

## 8. Layer 6 — Offline Multi-Sensor Recommendation Engine (Random Forest + Referenced Knowledge Base)

**Sources:** `backend/python/recommendation/{knowledge_base,features,generate_dataset,train,engine,test_expert_scenarios}.py`, served by the Flask service as `POST /recommendation` (`backend/python/rnn/app/routes/api.py`). On the Laravel side, `app/Services/MultiTitikService.php` builds the snapshot and calls the model; `app/Http/Controllers/RecommendationService.php` is the PHP rule fallback, with the same thresholds. All inference runs locally on the server PC, and no external AI API is called.

### 8.1 Multi-point snapshot

The field has two monitoring points. **Point 1** is a full station with weather, soil, and a camera for plot 1. **Point 2** has soil sensors and a camera for plot 2. Weather is shared across the area, so plot $p$ is described by

$$
\mathbf s_p = \big(\underbrace{\mathbf w^{(1)}}_{\text{weather of point 1}},\ \underbrace{\mathbf g^{(p)}}_{\text{soil of point }p},\ \underbrace{(c_p,\pi_p)}_{\text{CNN class, conf.}},\ \underbrace{\mathbf h_p}_{\text{LSTM }\Pr(\text{health})},\ \underbrace{\phi_p}_{\text{growth phase}}\big).
$$

The growth phase $\phi_p$ comes from the transplanting date (`TANGGAL_TANAM_TITIK_p`), the variety duration $D$ (days after sowing), and the seedling age $b$. Following IRRI, the reproductive phase lasts about 35 days and ripening about 30 days. With $\mathrm{DAS}=\mathrm{HST}+b$:

$$
\phi_p=\begin{cases}
\text{flowering}, & |\mathrm{DAS}-(D-30)|\le 7\\
\text{vegetative}, & \mathrm{DAS} < D-65\\
\text{reproductive}, & D-65 \le \mathrm{DAS} < D-30-7\\
\text{ripening}, & \text{otherwise}
\end{cases}
\qquad \text{near-harvest} \iff \mathrm{DAS}\ge D-10 .
$$

### 8.2 Feature vector (44-D) and actions (19 labels)

The feature vector $\mathbf x_p\in\mathbb R^{44}$ contains:
- 6 weather features ($T_a$, RH, VPD from §7.1, wind, rain, light);
- 8 soil features (pH, $\theta$, EC, TDS, $T_s$, N, P, K);
- 10 CNN class-confidence features (one-hot × $\pi_p$);
- 8 group maxima (bacterial, blast, brown spot, borer, downy mildew, hispa, tungro, and N-sensitive);
- 3 LSTM probabilities;
- 7 phase features;
- 2 availability flags.

Missing sensors are imputed with neutral (optimal) values. The label space is the 19 actions in `knowledge_base.ACTIONS`. These include plot-specific pest/disease control, heat mitigation, salinity flushing, irrigation, delaying urea, leaf-color-chart (BWD) urea, SP-36, KCl, liming, Zn prevention, lodging alert, and continue monitoring.

### 8.3 Knowledge base (labelling function) and its references

The labelling function $g(\mathbf x)\subseteq\mathcal A$ encodes guideline rules. Every threshold is tied to a Scopus-indexed Q1/Q2 article or a credible agricultural source. The DOI, Scopus Source ID, and quartile of each reference are listed in `backend/python/recommendation/SUMBER_REFERENSI.md` and README §9.

| Rule | Condition | Reference |
|---|---|---|
| Irrigate (safe AWD) | volumetric water content $\theta_v < 35\%$; at flowering $\theta_v$ below the ponded reading (default 45 %, calibrated); never when near-harvest | Mahmud et al. 2026 (Sensors, Q1); Carrijo et al. 2017 (FCR, Q1); Bouman & Tuong 2001 (AWM, Q1); IRRI |
| Heat mitigation | $T_a \ge 35$ °C, or $T_a\ge 33$ °C at flowering | Jagadish et al. 2007 (J. Exp. Bot., Q1) |
| Blast-favourable weather | RH ≥ 90 % and 20 ≤ $T_a$ ≤ 28 °C | Savary et al. 2012 (Crop Prot., Q1); UC IPM |
| Blast control | CNN blast, or blast weather at flowering → flood, split N, triazole/strobilurin | Asibi et al. 2019 (Agronomy, Q1); IRRI; UC IPM |
| Bacterial blight control | CNN bacterial classes → balanced N, drainage except at flowering, sanitation (no bactericide); weather alert at 25–34 °C, RH > 70 %, rain ≥ 50 mm/24 h | Mew 1987 (Annu. Rev. Phytopathol., Q1); IRRI; BMKG (heavy rain) |
| Tungro | CNN tungro → roguing, resistant varieties, synchronous planting (insecticide often ineffective) | Azzam & Chancellor 2002 (Plant Dis., Q1); IRRI |
| Brown spot | CNN brown spot → improve soil fertility (K, Si), keep flooded | Barnwal et al. 2013 (EJPP, Q2); IRRI |
| Salinity | EC ≥ 3,000 µS cm⁻¹ or TDS ≥ 1,920 ppm | Maas & Hoffman 1977; Zeng & Shannon 2000 (Crop Sci., Q2); Zheng et al. 2023 (EJA, Q1); FAO 29 |
| Soil pH | pH ≤ 5.5 → lime; pH > 7.5 → Zn-deficiency prevention | Balai Penelitian Tanah; Alloway 2009 (EGH, Q1); Impa & Johnson-Beebout 2012 (Plant Soil, Q1) |
| P, K (basal, vegetative/unknown phase) | P < 10 mg kg⁻¹ (< 6 low) → SP-36 per PUTS; K < 78 mg kg⁻¹ (0.2 cmol kg⁻¹) → KCl | Dobermann & Fairhurst 2000; Dobermann et al. 2002 (FCR, Q1); PUTS |
| Nitrogen | no sensor-N threshold; vegetative–reproductive phase and no N-sensitive pest → urea by leaf color chart (scale 4 inbred, 5 hybrid); N-sensitive pest detected → delay urea | Shukla et al. 2004 (Agron. J., Q2); Yang et al. 2003 (Q2); Islam et al. 2007 (FCR, Q1); BB Padi; Mew 1987 (Q1) |
| Lodging | wind ≥ 8 m s⁻¹ in reproductive–ripening phase | Hitaka 1969 (JIRCAS; JARQ is Scopus Q4 → institutional data, not Q1/Q2); Berry et al. 2004 (Adv. Agron., Q1) |

Three choices keep the rules evidence-based. VPD and the LSTM health probabilities are **displayed but not used as triggers**, because no rice-specific VPD threshold exists in Q1/Q2 journals and the LSTM health model was trained on synthetic data. Sensor N is not used, because no literature maps low-cost RS485 N readings to fertilizer decisions. The saturated VWC used during flowering is a field calibration. P/K readings still require laboratory/PUTS calibration. Rainfall is the 24-hour total computed by Laravel; the firmware sends per-interval values after a comma-operator bug fix.

### 8.4 Random Forest model

The model is one binary Random Forest per action (`MultiOutputClassifier(RandomForestClassifier)`). A single multi-output forest optimises all labels jointly, which lowered F1 for rare actions. For action $a$:

$$
\hat p_a(\mathbf x)=\frac1B\sum_{b=1}^{B}\Pr_b(y_a=1\mid\mathbf x),\qquad
\hat{\mathcal A}(\mathbf x)=\{a:\hat p_a(\mathbf x)\ge 0.5\},
$$

with $B=100$ trees, `max_features=0.5`, `max_depth=18`, and `min_samples_leaf=4`. If $\hat{\mathcal A}=\varnothing$, the output is *continue monitoring*.

**Training data** come from `generate_dataset.py`, with 40,000 synthetic plot-states. True field conditions are sampled from a mixture of a tropical-paddy prior and a uniform range covering the threshold extremes; for example, EC goes up to 6 dS m⁻¹. Labels are $g(\mathbf x_{\text{true}})$. The model sees $\mathbf x_{\text{obs}}=\mathbf x_{\text{true}}+\boldsymbol\varepsilon$, with sensor noise, 3 % missing soil sensors, 10 % missing camera, 15 % missing LSTM output, and 20 % unknown planting date. The model therefore learns decisions from imperfect readings and does not merely copy the rules. The split is 80/20.

### 8.5 Per-plot output, aggregation, and actuation

Recommendations are sorted by severity (danger > warning > success) and then by $\hat p_a$. Each one carries its reasons (`knowledge_base.explain`) and references (`sources_for`, with DOI and Scopus quartile). Actions shared by both plots are merged in a summary list that keeps a per-plot reason list. The actuation for plot $p$ is the first active action in the priority list (hispa → blast → borer → heat → salinity → irrigation → …). It is mapped to the four ESP32 codes (`activate_ultrasonic_repeller_and_uv_lamp`, `recommend_selective_insecticide_spraying`, `irrigation_notification`, `continue_monitoring`). Each node executes only the actuation for its own `titik`.

```
recommend(snapshot):
    for p in snapshot.titik:
        h_p  ← LSTM health of plot p (cached 60 s)            # /forecast per titik
        x_p  ← build_features(weather_1, soil_p, camera_p, h_p, planting_p)
        A_p  ← {a : RF_a(x_p) ≥ 0.5}  or {continue_monitoring}
        recs ← sort(A_p by severity, p̂) with explain(a, x_p), sources(a)
        act_p ← first(ACTUATION_PRIORITY ∩ A_p) → ESP32 code
    summary ← merge recs over plots
    return {model, petak: {p: recs, act_p, phase, health}, summary}
```

**Fallback.** If the Flask service is unreachable, `MultiTitikService` runs the PHP `RecommendationService` per plot, combining point-1 weather with the plot's soil and camera data. It uses the same referenced thresholds (`RecommendationService::THRESHOLDS`) without the learned model or the growth phase. The response is marked `model.source = php_rules`.

### 8.6 Evaluation

On 8,000 held-out synthetic samples the Random Forest reaches F1-micro 0.968, F1-macro 0.958, subset accuracy 0.865, and Hamming loss 0.0087. The rules applied directly to the noisy readings reach 0.968, 0.961, 0.869, and 0.0087. These results are expected: the labels derive from the same rules, so the model reproduces the expert knowledge. To exceed it, the model must be retrained on field-labelled actions and outcomes. `test_expert_scenarios.py` checks 15 guideline cases on both the model and the rules, and all 15 pass, including irrigation at VWC 30 % but not 40 %, and a bacterial-blight alert after 60 mm of rain in 24 h. Examples: a normally flooded field is not drained; blast gets flooding plus fungicide; tungro gets roguing instead of insecticide; EC 2,000 µS cm⁻¹ raises no salinity alarm; no urea is given after flowering; no irrigation happens before harvest. These metrics measure agreement with the referenced rules on synthetic data, **not** field agronomic performance.

---

## 9. End-to-End Data & Control Flow

```
   ┌─ every 900 s (sensing) + 15 s (actuation) ────────────────────────────────────────┐
   │ ESP32 B: soil (8-in-1) ─ESP-NOW─► ESP32 A                                          │
   │ ESP32 A: weather + soil ─► POST /api/kirim_sensor {titik:1} ─► sensor_iots          │
   │          B's soil       ─► POST /api/kirim_sensor {titik:2} ─► sensor_iots          │
   │ ESP32 A: GET /api/inference/{id}?titik=1,2 (∨ MQTT) ─► relays A; PERINTAH ─► B     │
   │        ─► action ─► {UV, ultrasonic, irrigation} relays (GPIO19/23/27)             │
   └───────────────────────────────────────────────────────────────────────────────────┘
   ┌─ every 10 min, 06–18 WIB (snapshot) ─────────────────────────────────────────────┐
   │ camera B ─HTTP─► ESP32 B ─ESP-NOW chunks + CRC32─► ESP32 A                         │
   │ camera A ─HTTP─► ESP32 A ─► POST /api/kirim_gambar {X-TITIK} ─► Flask POST /detect │
   │   ─► Haar ROI (or whole image) ─► EfficientNet-B0 (RGB) ─► sensor_kameras{titik}  │
   └───────────────────────────────────────────────────────────────────────────────────┘
   ┌─ on dashboard request / poll ─────────────────────────────────────────────────────┐
   │ Flask LSTM: GET /api/sensor?iot_id ─► build T=6 window ─► LSTM forecast (H=6d)     │
   │            served at GET /forecast/{iot_id}?days=…                                 │
   │ Flask sensor forecast: 4 h blocks ─► rolling-origin MASE selection per sensor     │
   │            ─► 15-sensor forecast + status, GET /sensor-forecast/{titik}?days=…   │
   │ Laravel MultiTitikService: snapshot {weather P1, soil P1/P2, camera 1/2, planting} │
   │   ─► POST /recommendation (RF + per-plot LSTM health) ─► per-plot actions + refs   │
   │   (fallback: PHP RecommendationService)  ─► Blade dashboard + REST API v1 ─► app   │
   └───────────────────────────────────────────────────────────────────────────────────┘
```

**Closed control loops**

1. *Illumination loop (local):* BH1750 lux $< 100$ ⇒ ESP32 raises the UV-lamp relay (no network).
2. ~~*Intruder loop (distributed):* RPi LiDAR motion ⇒ `lidars` row ⇒ Laravel `/api/bacajson` ⇒ ESP32 ultrasonic relay~~ — removed. The LiDAR producer (Layer 2, Section 4), the legacy firmware that polled it, and the `/api/bacajson` route are no longer in the repository.
3. *Advisory loop (human-in-the-loop):* weather (point 1) + soil, CNN, LSTM, and growth phase per plot ⇒ offline Random Forest (Section 8) ⇒ prioritized, referenced actions per plot ⇒ farmer action (irrigation, fungicide, fertiliser, roguing).
4. *Fused actuation loop (model-in-the-loop):* hybrid CNN–LSTM (`algorithm1.py`) ⇒ `getActuation` decision ⇒ `POST /api/inference` ⇒ `hybrid_inferences` + MQTT publish `taniverse/{iot_id}/inference` ⇒ **ESP32** subscriber drives the UV-lamp / ultrasonic-repeller / irrigation-valve relays (GPIO19/23/27), REST-polled fallback every 15 s.

---

## 10. Deployment Topology

| Tier | Hardware | Software stack | Notes |
|---|---|---|---|
| Field gateway (ESP32 A) | ESP32-WROVER (PSRAM), DHT22, BH1750, BMP280, cup anemometer, tipping bucket, RS485 8-in-1 soil probe, IP camera A (router LAN), 3× relay, 4S Li-ion | Arduino C++ (core 3.x; `WiFiManager`, `ArduinoJson` 7, `HTTPClient`/TLS, `ModbusMaster`, `Adafruit_BMP280`, `BH1750`, `DHT`, ESP-NOW; `PubSubClient` optional) | Only internet-connected field device; 15-min sensing, 10-min snapshots, 15-s actuation polling; relays plot-2 commands to B |
| Field node (ESP32 B) | ESP32-WROVER(-IE), RS485 8-in-1 soil probe, IP camera B on B's soft-AP, 3× relay, battery | Arduino C++ (`ModbusMaster`, ESP-NOW, soft-AP) | No internet; ESP-NOW to A on the router's channel (auto scan or fixed) |
| Application server (VPS) | `petaniasik.my.id`, Ubuntu 22.04+ | nginx + PHP-FPM, Laravel 12 + MySQL; Flask services as systemd units (`deploy/vps/`), Python 3.10 `.venv` from `requirements-vps.txt` (TensorFlow-CPU 2.10.1), bound to 127.0.0.1 | The only public endpoint; `TZ=Asia/Jakarta` for the Python services |
| Application server (Laravel details) | same | Laravel 12 (PHP 8.2) + MySQL + `nwidart/laravel-modules` + `spatie/laravel-permission` + `spatie/activitylog`; Flask LSTM service (port 5000, `flask-socketio`); optional MQTT broker | REST + Blade dashboard; RBAC; Excel/ZIP export; `App\Services\MqttPublisher` (php-mqtt client if installed, else built-in QoS-0 socket publisher; `config/mqtt.php`, `MQTT_*` env) |
| Server PC (live AI services) | Windows PC with NVIDIA GPU (tested: GTX 1650 Ti, 4 GB) | Python 3.10 `.venv-gpu`: TensorFlow 2.10.1 + cuDNN 8.9 (pip) + CUDA 11.x, scikit-learn 1.6.1; `run_rnn.bat` (forecast + recommendation, :5000), `run_detect.bat` (cameras, :7000) | Keras 3 `.h5` models converted once to TF 2.10 format (`convert_keras3_to_tf210.py`; max output difference 8 × 10⁻⁷); GPU memory growth so both services share VRAM; CPU fallback without GPU |
| Client (web) | Browser | Blade + Bootstrap (Sneat template) + ApexCharts + jQuery; assets served from `backend/html/assets` (no build step) | dashboard, history, CNN detections, admin data, users/roles, profile, Excel export |
| Client (mobile) | Android / iOS phone | Flutter 3 (`mobile/`): provider, http, fl_chart, image_picker, share_plus | Same pages as the web through REST API v1; built and tested as an Android debug APK, iOS not yet built |

---

## 11. Reproducibility — Hyperparameter Tables

### 11.1 CNN transfer-learning (`train.py`)

| Item | Value |
|---|---|
| Backbone | EfficientNet-B0, ImageNet weights, `include_top=False` |
| Input | 224 × 224 × 3, `efficientnet.preprocess_input` (0–255) |
| Head | GAP → Drop 0.2 → Dense 128 ReLU → Drop 0.2 → Dense 10 softmax |
| Augmentation (sec. 3.3) | flip H+V, rotation ±25° (25/360·2π), random crop scale 0.8–1.0 (`RandomZoom`), Gaussian noise variance 0.01 on [0,1] |
| Class weights | $w_c = N/(K n_c)$ |
| Phase 1 | base frozen, Adam 1e-3, 40 epochs |
| Phase 2 | top 30 layers unfrozen, Adam 1e-5, 10 epochs (**50 total**, manuscript Table 3) |
| Loss / metric | categorical cross-entropy / accuracy |
| Callbacks | early-stop(val_acc, patience 6, restore best), ReduceLROnPlateau(val_loss, 0.5, patience 3, min 1e-7); ckpt(val_acc) in single-split mode |
| Validation | stratified 70/15/15 split **and** stratified 5-fold CV (`--cv-folds 5`) — the 5-fold mode repartitions all 10,407 images per fold as ≈65% train / ≈15% val / ≈20% test (≈6,765/1,561/2,081); mean ± std + 95% CI ($\mu\pm1.96\sigma/\sqrt{5}$, Eq. 9) — this is **Table 4** |
| Batch / seed | 32 / 42 (`keras.utils.set_random_seed`) |

### 11.2 Benchmark (`benchmark_modern_models.py`)

| Item | Value |
|---|---|
| Models | yolov8n-cls, yolo11n-cls, efficientnetv2_rw_s, efficientnet_b0, vit_base_patch16_224, resnet50, mobilenetv2_100, vgg16 |
| Optimiser (torchvision/timm branches) | AdamW, lr 1e-4, weight-decay 1e-4 |
| Scheduler | CosineAnnealingLR, $T_{\max}$ = epochs |
| Epochs / patience (torchvision/timm branches) | ≤ 30 / 7 (monitor val macro-F1) |
| Batch / img size / seed (torchvision/timm branches) | 16 / 224 / 42 |
| Normalisation | ImageNet mean/std |
| YOLOv8n-cls / YOLOv11n-cls (identical protocol) | epochs 5, batch 8, imgsz 224, device cpu, workers 0, optimizer auto (lr0 0.01, lrf 0.01, momentum 0.937, wd 5e-4, warmup 3), mosaic 1.0, randaugment, erasing 0.4, ultralytics 8.4.24 |

### 11.3 LSTM regression — manuscript Table 5 (`evaluate_lstm_microclimate_10k.py` + `generate_real_figure9_2025dates.py`)

| Item | Value |
|---|---|
| Window $T$ / step | 6 / 4 h (24 h look-back), 4 h forecast horizon |
| Features / targets | `[temperature, humidity, ph, light_intensity, vpd]` (5) → next-step (5) |
| Architecture | LSTM 64 (seq) → BN → LSTM 32 → BN → Drop 0.2 → Dense 32 ReLU → BN → Drop 0.2 → Dense 5 linear |
| Optimiser / loss / metric | Adam (lr $10^{-3}$) / MSE / MAE |
| Epochs / batch | 100 / 32 (manuscript Table 3) |
| Scaling | MinMax on X and y, **fitted on the entire 10,000-record series before windowing/partitioning** (manuscript sec. 3.3.1 states this as a documented preprocessing-leakage limitation, not a strength) |
| Data split | chronological holdout: 6,396 train / 1,599 val / 1,999 test windows — a **single fixed split**, not k-fold; Table 5 values are point estimates, not mean ± std |
| Long-horizon eval | 14-day rolling recursive VPD forecast (sec. 4.5) — the manuscript states this is evaluated but does not quote a specific R² for it; do not substitute the Table 5 holdout R² (0.949) for it |

### 11.4 Production plant-health LSTM (`config/model_config.pkl`)

| Item | Value |
|---|---|
| Window $T$ | 6 |
| Features | 34 engineered (raw agro-vars + cyclical time + interactions + NPK descriptors + rolling mean/change rate) |
| Classes | `{0: Healthy, 1: High Stress, 2: Moderate Stress}` |
| Artefacts | `plant_health_lstm_model.h5`, `feature_scaler.pkl`, `label_encoder.pkl`, `feature_columns.pkl` |
| Forecast horizon | up to 14 days (H = 84 steps of 4 h) |

### 11.5 Hybrid CNN–LSTM feature fusion (`hybrid_cnn_lstm/`)

| Item | Value |
|---|---|
| Branches | EfficientNet-B0 → Dense($n$, ReLU) = $R_{\text{visual}}$ ; LSTM 64→BN→LSTM 32→BN→Drop 0.2→Dense($n$, ReLU) = $R_{\text{environment}}$ |
| LSTM input | $T=6 \times 8$ features: `[temperature, humidity, pH, light, VPD]` + `hour_sin` + `hour_cos` + `temp×humidity` (Algorithm 1 step 5, `data_processor`/`engineer_sensor_window`) |
| Fusion dim $n$ | 128 |
| Fusion (Eq. 1) | $R_{\text{total}} = w_1 R_{\text{visual}} + w_2 R_{\text{environment}}$, $w_1+w_2=1$, fixed |
| Weight selection | grid search $w_1\in\{0,0.1,\dots,1\}$ × stratified 5-fold CV → max val pest-F1; manuscript optimum $w_1=0.6$, $w_2=0.4$ |
| Heads | Dense 10 softmax (pest) · Dense 3 softmax (health) · Dense 5 linear (micro-climate/VPD) |
| Loss | $\mathcal L_{\text{pest}} + 0.5\,\mathcal L_{\text{health}} + 0.5\,\text{MSE}_{\text{micro}}$ |
| Optimiser / batch / dropout / seed | Adam $10^{-3}$ / 32 / 0.2 / 42 |
| Validation (ablation) | stratified 5-fold CV on all 10,407 images, ≈65/15/20 per fold; every metric reported as mean ± std **and** 95% CI ($\mu \pm 1.96\sigma/\sqrt{5}$, Eq. 9) — **Table 6** |
| Final deployed model | separate from the ablation above: 85/15 train/val split, no held-out test fold (manuscript Table 3, "Final Fusion Model") |
| No-fusion baseline | concatenation + Dense projection (`fusion="concat"`) |
| Decision thresholds | $\tau_p = \tau_{\text{health}} = 0.5$ (Algorithm 1) |
| Paired data | synthetic timestamp-aligned image↔sensor windows, seed 42, disease-coupled stress |

### 11.6 Offline recommendation Random Forest (`recommendation/train.py`)

| Item | Value |
|---|---|
| Model | `MultiOutputClassifier(RandomForestClassifier)`: one forest per action |
| Labels / features | 19 actions (multi-label) / 44 features (Section 8.2) |
| Trees / depth / leaf / max_features | 100 / 18 / 4 / 0.5 |
| Decision threshold | $\hat p_a \ge 0.5$ |
| Training data | 40,000 synthetic plot-states (`generate_dataset.py`, seed 42); labels from the referenced knowledge base; sensor noise, missing sensors/camera/LSTM, 20 % unknown planting date |
| Split | 80 / 20 (32,000 / 8,000) |
| Library | scikit-learn 1.6.1 (same version as in the GPU and default environments) |

---

## 12. Experimental Results Summary

### 12.1 Validation comparison of available classification checkpoints — Table 7 (`benchmark_modern_models.py`, `experiments/scopus_q1_comparison/` + `experiments/yolov11_addition/`)

Eight checkpoints evaluated on the same 2,084-image validation partition (image-level metrics; validation also guided checkpoint selection, so these are **not** independent-test numbers) — manuscript **Table 7** / Section 4.4 ("Comparative Evaluation of Available Classification Checkpoints"), *not* the Table 4 numbers (Table 4 is the separate deployment-oriented Keras backbone comparison, §12.3).

| Model | Accuracy | Precision (macro) | Recall (macro) | F1 (macro) | F1 (weighted) |
|---|---|---|---|---|---|
| **YOLOv11n-cls** | **0.9093** | **0.9014** | **0.9003** | **0.8990** | **0.9089** |
| EfficientNetV2-S | 0.8690 | 0.8542 | 0.8510 | 0.8491 | 0.8682 |
| YOLOv8n-cls | 0.8652 | 0.8533 | 0.8452 | 0.8482 | 0.8641 |
| EfficientNet-B0 (deployed) | 0.8589 | 0.8479 | 0.8477 | 0.8474 | 0.8586 |
| ResNet-50 | 0.4290 | 0.2910 | 0.2886 | 0.2560 | 0.3676 |
| ViT-B/16 | 0.1387 | 0.0139 | 0.1000 | 0.0244 | 0.0338 |
| VGG-16 | 0.1108 | 0.0688 | 0.1236 | 0.0642 | 0.0745 |
| MobileNet-V2 | 0.0461 | 0.0046 | 0.1000 | 0.0088 | 0.0041 |

YOLOv11n-cls (5 epochs, batch 8, imgsz 224, CPU — identical protocol to the YOLOv8n-cls baseline; see `experiments/yolov11_addition/yolov11_provenance.md`) now tops the table, ahead of EfficientNetV2-S by +2.03 pp accuracy / +0.99 pp macro-F1, added in response to a reviewer request for a newer benchmark model. The two EfficientNet/YOLOv8 checkpoints below it converge to ≈ 0.86–0.87 accuracy and ≈ 0.85 macro-F1; the remaining backbones did not converge under the shared short-schedule / cuDNN-stability protocol (documented failure mode: MobileNet-V2 and ViT each collapsed to predicting a single class for every validation image, `test_loss` NaN for MobileNet-V2). EfficientNet-B0 remains the deployed backbone for its accuracy/FLOP trade-off on the Raspberry Pi, not because it tops this table.

**Note:** this table is unrelated to Figure 8 (the EfficientNet-B0 confusion matrix on a 500-image class-balanced *subset*, 43.40% accuracy, §12.2 of the manuscript / `Training_Penyakit Padi/{compute_real_50perclass_matrix,plot_real_50perclass_confusion_matrix}.py`). An earlier version of this document conflated the two and cited a "before vs. after augmentation" ablation (`generate_confusion_matrix.py`, `detailed_analysis.py`) as reproducing Figure 8; those scripts produced a different, unreferenced analysis and have since been removed from the repository along with their output figures.

### 12.2 LSTM micro-climate regression — manuscript Table 5 (`generate_real_figure9_2025dates.py` → `figure9_2025dates_predictions.csv`, re-plotted with panel labels by `rebuild_figure9_labeled.py`; $T=6$ = 24 h look-back, chronological holdout, $n_{\text{test}}=1{,}999$)

| Parameter | MAE | RMSE | $R^2$ |
|---|---|---|---|
| Air Temperature (°C) | 0.451 | 0.565 | 0.969 |
| Relative Humidity (%) | 1.759 | 2.185 | 0.937 |
| Soil pH | 0.050 | 0.062 | 0.924 |
| Light Intensity (lux) | 27.186 | 32.396 | 0.993 |
| VPD (kPa) | 0.077 | 0.099 | 0.949 |

These are point estimates from a single chronological holdout split (not k-fold, no ± std — see §11.3). Light intensity has the highest $R^2$ (0.993); soil pH the lowest (0.924). VPD RMSE (0.099 kPa) and MAE (0.077 kPa) are the headline numbers quoted in the manuscript's Discussion and Conclusion. The manuscript separately notes a 14-day rolling recursive VPD forecast (sec. 4.5) but does not quote a specific R² for that rolling evaluation — it is a distinct evaluation from the 1,999-window holdout above and the two should not be conflated. The manuscript also explicitly flags that the MinMax scalers were fitted on the entire 10,000-record series before windowing/partitioning, which is a preprocessing-leakage limitation on interpreting these numbers as unseen-data performance (sec. 3.3.1).

### 12.3 CNN backbone comparison — Table 4 (`compare_cnn_backbones.py`)

One identical **Keras two-stage transfer-learning** protocol (frozen EfficientNet-style head, augmentation of sec. 3.3, Adam $10^{-3}\!\to\!10^{-5}$, 40 + 10 epochs, class weights) evaluated by stratified 5-fold CV; mean ± std. This is the deployment-oriented comparison the manuscript reports as Table 4 (distinct from §12.1 / Table 7).

| Model | Accuracy (%) | Precision | Recall | F1-Score |
|---|---|---|---|---|
| ResNet50 | 85.0 ± 0.8 | 0.83 ± 0.01 | 0.82 ± 0.01 | 0.82 ± 0.01 |
| MobileNetV2 | 83.5 ± 0.9 | 0.81 ± 0.01 | 0.80 ± 0.01 | 0.80 ± 0.01 |
| VGG16 | 84.2 ± 0.7 | 0.82 ± 0.01 | 0.81 ± 0.01 | 0.81 ± 0.01 |
| **EfficientNet-B0 (proposed)** | **89.2 ± 0.6** | **0.89 ± 0.01** | **0.89 ± 0.01** | **0.89 ± 0.01** |

Augmentation lifts EfficientNet-B0 from ≈ 85.0 % to ≈ 89.2 % accuracy (blast ↔ brown_spot confusion reduced).

> **Evidence status:** these are the manuscript's Table 4 values. They are **not supported** by the stored run record: `Training_Penyakit Padi/table4_fold_results_EfficientNet-B0.json` shows a five-fold mean accuracy of **38.73 %** (SD 1.63 pp), and no fold records exist for the other backbones (README §6.1). Do not cite this table as a verified result until matching fold records are produced.

### 12.4 Hybrid CNN–LSTM feature fusion — Table 6 (`evaluate_hybrid.py`)

Stratified 5-fold CV, mean ± std; latency = median single-sample `predict`.

| Model | Accuracy (%) | F1-Score | RMSE (VPD) | Latency (ms/frame) |
|---|---|---|---|---|
| CNN only | 89.2 ± 0.6 | 0.89 ± 0.01 | — | 92 ± 3 |
| LSTM only | — | — | 0.075 ± 0.006 | 84 ± 2 |
| CNN + LSTM (without fusion) | 90.3 ± 0.7 | 0.91 ± 0.01 | 0.069 ± 0.005 | 104 ± 4 |
| **Proposed CNN–LSTM (feature fusion, $w_1{=}0.6$)** | **93.6 ± 0.8** | **0.93 ± 0.01** | **0.061 ± 0.004** | 110 ± 4 |

Adaptive fusion adds ≈ 4.4 % pest accuracy and cuts VPD error ≈ 18.6 % vs. the unimodal baselines while staying under the 150 ms real-time budget.

> **Evidence status:** manuscript Table 6 values. They have **not been verified** against complete ablation run artifacts (no `table6_hybrid_comparison` output in the repository), and the paired image–sensor data are synthetic and label-conditioned (README §6.2 and §7). The latency figures need a recorded device benchmark.

### 12.5 Offline recommendation model (`recommendation/models/recommendation_rf_report.md`)

| Metric (8,000 synthetic test samples) | Random Forest | Rules on noisy readings |
|---|---|---|
| F1-micro | 0.968 | 0.968 |
| F1-macro | 0.958 | 0.961 |
| Subset accuracy | 0.865 | 0.869 |
| Hamming loss | 0.0087 | 0.0087 |

Expert-scenario checks (`test_expert_scenarios.py`): 15/15 guideline cases pass for both the model and the rules. These numbers measure agreement with the referenced rules on synthetic data, not field performance (Section 8.6).

---

### Appendix A — File → responsibility map

| Path | Role in the architecture |
|---|---|
| ~~`backend/python/servo_lidar.py`~~ | *Removed from the repository* — formerly Layer 2: servo sweep + TF-Luna + motion → `lidars` (see Section 4) |
| `backend/laravel/routes/api.php` | Layer 3: REST route table |
| `backend/laravel/app/Http/Controllers/SensorController.php` | Layer 3: ingest, auth, ring-buffer, history reads (`/api/sensor`) |
| `backend/laravel/Modules/Dashboard/app/Http/Controllers/DashboardController.php` | Layer 3/6: dashboard page (combined/per-plot forecast), AJAX sensor/camera/recommendation data, AI summary/chat routes |
| `backend/laravel/Modules/History/app/Http/Controllers/HistoryController.php`, `Modules/Yolo/app/Http/Controllers/YoloController.php` | Layer 3: `/history` and `/cnn` pages and their Excel exports with native charts |
| `backend/laravel/Modules/Data/app/Http/Controllers/DataController.php` | Layer 3: admin Data pages (`view_user` permission) |
| `backend/laravel/app/Support/SensorStatus.php` | Server-side status labels for weather/soil values (web, API, app) |
| `backend/laravel/app/Services/DetectionService.php` | CNN detection history, healthy/disease classification, advice per disease |
| `backend/laravel/app/Services/AiAssistantService.php`, `config/services.php` (`openai`) | AI assistant: context, system prompt, cached summary, chat (§5.7) |
| `backend/laravel/app/Http/Controllers/Api/V1/*`, `app/Http/Resources/V1/*`, `app/Http/Middleware/ApiLocale.php`, `app/Providers/AppServiceProvider.php` | REST API v1 controllers, resources, message language, named rate limiters |
| `backend/laravel/Modules/Dashboard/resources/views/partials/rec-modal.blade.php` | "Aksi Rekomendasi · Model Random Forest" pop-up opened from a conclusion |
| `backend/laravel/bootstrap/app.php` | Application bootstrap; sets `public_path()` to `backend/html` |
| `mobile/` | Flutter client (AgroGuard) of REST API v1; see `mobile/README.md` |
| `backend/laravel/app/Http/Controllers/RecommendationService.php` | Layer 6 fallback: PHP rules with the same referenced thresholds (`THRESHOLDS`); `getActuation` = Algorithm 1 step 15 (spraying only for blast/stem borer) |
| `backend/laravel/app/Services/MultiTitikService.php`, `config/monitoring.php` | Layer 3/6: multi-point configuration (labels, planting dates, weather point), snapshot assembly, call to `POST /recommendation`, PHP fallback |
| `backend/laravel/Modules/Dashboard/database/migrations/2026_10_05_000000_add_titik_to_sensor_tables.php` | Layer 3: `titik` column + nullable decimal(10,2) sensor columns |
| `backend/python/recommendation/knowledge_base.py` | Layer 6: referenced thresholds, 19 actions, labelling rules, explanations, `SOURCES` (DOI, Scopus Source ID, quartile) |
| `backend/python/recommendation/{features,generate_dataset,train,engine}.py` | Layer 6: 44-D features, synthetic training data, per-action Random Forest training, inference + per-plot aggregation |
| `backend/python/recommendation/test_expert_scenarios.py`, `SUMBER_REFERENSI.md` | Layer 6: 15 guideline scenario checks; full reference list with verified claims and Scopus links |
| `backend/python/gpu_env.py`, `convert_keras3_to_tf210.py`, `setup_gpu_env.bat`, `run_rnn.bat`, `run_detect.bat` | Deployment: Windows GPU runtime, Keras 3 → TF 2.10 model conversion, service launchers |
| `backend/laravel/Modules/Dashboard/app/Http/Controllers/HybridInferenceController.php` | Layer 3: `POST/GET /api/inference` — ingest & serve fused CNN–LSTM output |
| `backend/laravel/Modules/Dashboard/{database/migrations/*_create_hybrid_inferences_table,app/Models/HybridInference}.php` | Layer 3: `hybrid_inferences` schema + model |
| `backend/laravel/app/Services/MqttPublisher.php`, `config/mqtt.php` | Layer 3: MQTT publish (Algorithm 1 step 16); php-mqtt client or built-in QoS-0 fallback |
| `Training_Penyakit Padi/train.py` | Layer 4: EfficientNet-B0 two-stage transfer learning (50 ep, dropout 0.2, stratified 70/15/15 + 5-fold CV) |
| `Training_Penyakit Padi/compare_cnn_backbones.py` | Layer 4: **Table 4** — ResNet50 / MobileNetV2 / VGG16 / EfficientNet-B0 under one Keras TL protocol + 5-fold CV |
| `Training_Penyakit Padi/make_figure4_combined.py` | Layer 4: **Figure 4** — (a) real augmentation panel via `train.build_augmentation` + (b) reads `figure4b_real_forecast_entry.json` from Layer 5 |
| `backend/python/detect_penyakit/app.py` | Layer 4: real-time Haar + CNN inference service (Flask :7000), several cameras (one per plot) |
| `backend/python/detect_penyakit/benchmark_modern_models.py` | Layer 4: **Table 7** — 8-checkpoint timm/YOLO comparison protocol (incl. YOLOv11n-cls) |
| `Training_Penyakit Padi/{compute_real_50perclass_matrix,plot_real_50perclass_confusion_matrix}.py` | Layer 4: **Figure 8** — EfficientNet-B0 confusion matrix on the 500-image class-balanced validation subset |
| `backend/python/hybrid_cnn_lstm/fusion_model.py` | Layer 4.5: adaptive feature-fusion model factory + `AdaptiveFeatureFusion` (Eq. 1) |
| `backend/python/hybrid_cnn_lstm/tune_fusion_weights.py` | Layer 4.5: grid search $w_1/w_2$ with 5-fold CV (→ `fusion_weights.json`) |
| `backend/python/hybrid_cnn_lstm/{train_hybrid,evaluate_hybrid}.py` | Layer 4.5: train fused model; reproduce **Table 6** |
| `backend/python/hybrid_cnn_lstm/algorithm1.py` | Layer 4.5: runnable **Algorithm 1** (Table 2) — fuse → threshold → actuate → transmit |
| `backend/python/hybrid_cnn_lstm/actuation_eval.py` | Layer 4.5/6: actuation success rate SR (sec. 3.8, Eq. 13) from the Algorithm 1 run log |
| `backend/python/hybrid_cnn_lstm/{make_paired_dataset,data_pipeline}.py` | Layer 4.5: synthetic timestamp-aligned image↔sensor manifest + tf.data pipeline |
| `backend/python/rnn/evaluate_lstm_microclimate_10k.py` | Layer 5: LSTM multi-output regression study, N=10,000 (matches manuscript sec. 3.2.2); also hosts `rolling_vpd_forecast_r2` used for the 14-day rolling forecast (sec. 4.5) |
| `backend/python/rnn/evaluate_lstm_microclimate.py` | Layer 5: dependency of the above (`cross_validate_regression`, `rolling_vpd_forecast_r2`); its own direct output does not reproduce Table 5 |
| `backend/python/rnn/generate_real_figure9_2025dates.py` | Layer 5: reproduces manuscript **Table 5** / Figure 9 data exactly (chronological holdout, n_test=1,999); writes `figure9_2025dates_predictions.csv` |
| `backend/python/rnn/rebuild_figure9_labeled.py` | Layer 5: re-renders Figure 9 from that CSV with (a)-(e) subplot labels matching the manuscript caption — the authoritative Figure 9 image |
| `backend/python/rnn/run_real_vpd_example.py` | Layer 5: worked single-VPD-example paragraph (manuscript sec. 4.2) |
| `backend/python/rnn/make_figure4b_json_output.py` | Layer 5: Figure 4(b) JSON panel, read by `Training_Penyakit Padi/make_figure4_combined.py` |
| `backend/python/rnn/app/services/forecast_service.py` | Layer 5: recursive multi-step LSTM health forecasting from measured drivers |
| `Arduino/esp32_a_gateway/`, `Arduino/esp32_b_lapangan/` | Layer 1: gateway (weather + soil + camera A + ESP-NOW receiver + uploads) and field node B (soil + camera B over ESP-NOW) |
| `backend/laravel/app/Http/Controllers/CameraUploadController.php` | Layer 3: `POST /api/kirim_gambar` → Flask `POST /detect` |
| `deploy/vps/`, `backend/python/requirements-vps.txt` | VPS deployment: systemd units, nginx site, environment template, CPU requirements |
| `backend/python/rnn/app/services/sensor_forecast.py` | Layer 5: 15-sensor statistical forecasting with rolling-origin model selection (§7.8) |
| `backend/laravel/Modules/Dashboard/resources/views/partials/sensor-forecast-{chart,table}.blade.php` | Trend chart and prediction table shared by the dashboard and admin monitoring page |
| `backend/python/rnn/app/services/model_service.py` | Layer 5: model/scaler/encoder loading, LSTM & classical inference |
| `backend/python/rnn/app/utils/data_processor.py` | Layer 5: feature engineering (cyclical, interactions, rolling, stress flags) |
| `backend/python/rnn/app/__init__.py`, `run.py` | Layer 5: Flask app factory + `/forecast/{iot_id}?titik=N` and `POST /recommendation` routes |
| `backend/python/rnn/app/services/data_service.py` | Layer 5: history from `/api/sensor`, grouping per `titik`, point-1 weather merged into point 2 |

