# TaniVerse: CNN–LSTM Edge-AIoT for Rice Monitoring

TaniVerse contains software associated with the manuscript **Hybrid CNN–LSTM Edge-AIoT Framework for Rice Disease and Pest Classification with Microclimate Forecasting**, prepared for the *Bulletin of Electrical Engineering and Informatics*. The project integrates artificial intelligence of things (AIoT), image classification, environmental time-series modeling, monitoring, and control interfaces.

The principal components are:

- An EfficientNet-B0 convolutional neural network (CNN) for ten rice image classes covering disease, pest-related damage, and normal conditions.
- A long short-term memory (LSTM) regression experiment predicting air temperature, relative humidity, soil pH, light intensity, and vapor pressure deficit (VPD).
- A weighted-sum CNN–LSTM fusion implementation with image-class, health-status, and microclimate outputs.
- ESP32 sensing/relay firmware, Python inference services, and a Laravel application programming interface (API) and dashboard.
- **Multi-point monitoring:** point 1 is a full station (weather + soil + camera for plot 1); point 2 measures soil only (pH, soil moisture, EC, TDS, soil temperature, N, P, K) and has its own camera for plot 2. Plot 2 uses the area weather from point 1.
- **Offline recommendation model:** a per-action Random Forest that fuses area weather, per-plot soil, per-plot CNN detections, per-plot LSTM health, and crop growth phase into prioritized actions. It runs locally on the server PC without any external AI API.

The manuscript describes a Raspberry Pi 4 deployment. A component's presence in the repository does not establish that it was deployed or evaluated in the field. The standalone regression experiment, online health-status service, and fusion model are distinct implementations.

**Documentation scope.** This revision uses the uploaded README and selected source/artifact checks against repository commit `89151d592b388795345e9bac0dd4aa5d57058f5f`. Evidence statements below refer to that reviewed snapshot; later runs require their own records. No training, field trial, or device benchmark was executed as part of this documentation revision.

**October 2026 revision.** Added multi-point (two-plot) monitoring, a two-camera CNN service, per-plot LSTM forecasting, the offline Random Forest recommendation model with a referenced knowledge base, and a Windows GPU environment (TensorFlow 2.10 + CUDA 11.x). These additions were verified by Laravel feature tests (SQLite), Python expert-scenario tests, and end-to-end runs with simulated sensor data. They were **not** evaluated in a field trial.

**Later October 2026 revision (application layer).** The web application and the new Flutter app are branded **AgroGuard** (`APP_NAME`); file, firmware and service names keep the earlier `taniverse` prefix. This revision added:
- a REST API v1 that returns screen-ready data, and a Flutter app that mirrors every web page;
- server-side sensor status labels (`app/Support/SensorStatus.php`) shared by the web, API and app;
- a combined "Gabungan Petak 1 & 2" view and LSTM health columns in the prediction and history tables;
- the CNN detection history page (`/cnn`) and Excel exports with charts;
- an AI assistant (Google Gemini through an OpenAI-compatible API) that summarises the field state and answers farmer questions.

It also removed unused code: the LiDAR surface, legacy firmware, Vite scaffolding, Docker files and scaffolded module routes. It restricted the admin Data pages to users with the `view_user` permission, and fixed the public path so uploaded profile photos are stored in the served directory `backend/html`. These changes were checked with 66 Laravel feature tests (SQLite in memory), 15/15 Python expert scenarios, 8 Flutter tests, and a headless-browser pass over every web page. They were **not** evaluated in a field trial.

**Evidence policy.** Describe supported code paths separately from completed experiments. Preserve results even when they differ from manuscript values. A reproducible result requires a defined data partition, configuration, checkpoint, and evaluation output. A matching number in a figure or script comment is insufficient.

For additional architecture details, see [ARSITEKTUR_SISTEM.md](ARSITEKTUR_SISTEM.md). That companion document requires separate reconciliation where its claims conflict with source code or evaluation artifacts; it is not a substitute for those records.

## Contents

1. [Repository components](#1-repository-components)
2. [Data and evaluation partitions](#2-data-and-evaluation-partitions)
3. [Environment setup](#3-environment-setup)
4. [Running experiments and services](#4-running-experiments-and-services)
5. [Manuscript-to-artifact map](#5-manuscript-to-artifact-map)
6. [Recorded results and unresolved discrepancies](#6-recorded-results-and-unresolved-discrepancies)
7. [Methodological and operational limitations](#7-methodological-and-operational-limitations)
8. [Recording a new evaluation](#8-recording-a-new-evaluation)
9. [Recommendation knowledge base](#9-recommendation-knowledge-base)
10. [Sensor forecasting: trend chart and prediction table](#10-sensor-forecasting-trend-chart-and-prediction-table)
11. [Field hardware (ESP32 A/B) and VPS deployment](#11-field-hardware-esp32-ab-and-vps-deployment)
12. [REST API v1](#12-rest-api-v1)
13. [Mobile app (Flutter)](#13-mobile-app-flutter)
14. [Running without ESP32 (dummy data)](#14-running-without-esp32-dummy-data)
15. [Web application, access control and AI assistant](#15-web-application-access-control-and-ai-assistant)

## 1. Repository components

Paths are relative to the repository root. Dataset files, pretrained weights, and generated outputs may need to be obtained or produced separately.

| Path | Purpose |
|---|---|
| `Arduino/esp32_a_gateway/` | ESP32 A firmware: weather station (DHT22, anemometer, tipping bucket, BH1750, BMP280) and RS485 8-in-1 soil sensor for plot 1, IP-camera snapshot A, ESP-NOW receiver for ESP32 B, uploads to the VPS, relay control. See [`Arduino/README.md`](Arduino/README.md) (§11). |
| `Arduino/esp32_b_lapangan/` | ESP32 B firmware: RS485 8-in-1 soil sensor and IP-camera snapshot B for plot 2, sent to ESP32 A over ESP-NOW (no internet at B). |
| `Training_Penyakit Padi/train.py` | Keras EfficientNet-B0 training: fixed train/validation, repartitioned holdout, or cross-validation modes. |
| `Training_Penyakit Padi/compare_cnn_backbones.py` | Keras backbone comparison workflow associated with manuscript Table 4. |
| `Training_Penyakit Padi/compute_real_50perclass_matrix.py` | Evaluation of a selected checkpoint on 50 validation images per class. |
| `Training_Penyakit Padi/plot_real_50perclass_confusion_matrix.py` | Plotting of the corresponding confusion-matrix data. |
| `Training_Penyakit Padi/make_figure4_combined.py` | Image-augmentation panel and system JSON reporting example. |
| `backend/python/detect_penyakit/app.py` | CNN inference service. Main mode: `POST /detect?titik=N` receives JPEG snapshots forwarded by Laravel from ESP32 A. Optional stream mode reads cameras directly (`CAMERAS=0:1,1:2`). Each detection is stored with its `titik` (point/plot number). |
| `backend/python/detect_penyakit/benchmark_modern_models.py` | PyTorch/timm and Ultralytics classification benchmark. |
| `backend/python/rnn/` | Offline synthetic regression experiments and a separate online forecasting/health-status service. The online service groups readings per point, fills point 2 with point-1 weather (nearest timestamp), forecasts per plot (`/forecast/1?titik=N`), and serves `POST /recommendation`. |
| `backend/python/recommendation/` | Offline recommendation model: knowledge base (`knowledge_base.py`), features, synthetic dataset generator, per-action Random Forest training, inference engine, expert-scenario tests, and the reference list `SUMBER_REFERENSI.md`. |
| `backend/python/gpu_env.py`, `convert_keras3_to_tf210.py`, `requirements-gpu.txt`, `*.bat` | Windows GPU environment (TensorFlow 2.10, CUDA 11.x, cuDNN 8.9 from pip), conversion of Keras 3 `.h5` models to TF 2.10 format, and service launchers. |
| `backend/python/hybrid_cnn_lstm/` | Synthetic pairing, fusion model, weight selection, training, ablation evaluation, and inference/control logic. |
| `backend/laravel/` | Laravel 12 API, persistence, web pages (dashboard, history, CNN detections, admin data, users/roles, profile), and recommendation services. Key files:<br>• `config/monitoring.php`: multi-point settings.<br>• `app/Services/MultiTitikService.php`: snapshot assembly, model call, combined view, prediction/history tables.<br>• `app/Http/Controllers/RecommendationService.php`: PHP rule fallback.<br>• `app/Support/SensorStatus.php`: sensor status labels.<br>• `app/Services/DetectionService.php`: CNN history and advice.<br>• `app/Services/AiAssistantService.php`: AI assistant.<br>• `app/Http/Controllers/Api/V1/`: REST API v1 (§12). |
| `backend/html/` | Public web root (`index.php`, CSS/JS assets, profile photos in `img/profile`, detection images in `img/hama_padi`). Laravel's `public_path()` points here (`bootstrap/app.php`); serve this directory, not `backend/laravel/public`. |
| `mobile/` | Flutter (Android/iOS) client of the REST API v1 that mirrors the web pages (§13). |
| `deploy/vps/`, `backend/python/requirements-vps.txt` | Linux VPS deployment: systemd units for the two Python services, nginx site, environment template, CPU requirements (§11.3). |
| `ARSITEKTUR_SISTEM.md` | Companion technical description, subject to source/artifact reconciliation. |

The historical servo–LiDAR scanning node is not part of the system. Its remaining Laravel surface was removed from the code in October 2026: the `Lidar` model, `GET /api/bacajson/{iot_id}`, the dashboard and monitoring polls, and the monitoring "pest / ultrasonic" boxes that displayed LiDAR values. The legacy firmware `Arduino/padi_sawah` and the test sender `Arduino/kirim_laravel` were removed at the same time. Existing databases may still contain three empty, unused tables (`lidars`, `historyiots`, `history_yolos`). Their create migrations are still in the repository, so drop them manually only after deciding to delete those migrations as well. Do not infer depth-map input to the CNN from historical LiDAR references.

## 2. Data and evaluation partitions

### 2.1 Data sources

| Dataset | Source | Role |
|---|---|---|
| Rice images | [Paddy Doctor image distribution on Kaggle](https://www.kaggle.com/datasets/imbikramsaha/paddy-doctor) | 10,407 labeled images across ten classes. The separate 3,469-image competition test directory lacks public ground-truth labels. |
| Synthetic microclimate series | `backend/python/rnn/make_synthetic_microclimate_dataset.py` | 10,000 records at four-hour intervals, with five variables including VPD derived from temperature and humidity. This is synthetic data, not a field sensor record. |
| Plant Health Data | [Kaggle development reference](https://www.kaggle.com/datasets/ziya07/plant-health-data) | Development/field-mapping reference described in the original documentation. Its role must be distinguished from the synthetic regression data and the training provenance of the separate online model. |
| Classification notebook | [Kaggle implementation reference](https://www.kaggle.com/code/mhassaan1122/rice-disease-classification-99-accuracy) | Development reference; its title or reported accuracy does not establish this repository's performance. |

Class counts reported for the labeled rice dataset:

| Class | Images |
|---|---:|
| Bacterial leaf blight | 479 |
| Bacterial leaf streak | 380 |
| Bacterial panicle blight | 337 |
| Blast | 1,738 |
| Brown spot | 965 |
| Dead heart | 1,442 |
| Downy mildew | 620 |
| Hispa | 1,594 |
| Normal | 1,764 |
| Tungro | 1,088 |
| **Total** | **10,407** |

The labeled layout is `Training_Penyakit Padi/train/<class>/` and `Training_Penyakit Padi/val/<class>/`, with 8,323 and 2,084 images, respectively. The competition `test/` directory is flat and unlabeled; it cannot supply a supervised confusion matrix. Save the actual class-index mapping and partition indices for each run.

### 2.2 Separate evaluation configurations

| Experiment | Partition and interpretation |
|---|---|
| Table 7 classification benchmark | Fixed 8,323-image training and 2,084-image validation partitions. Validation also guides checkpoint selection; reported scores are not independent-test performance. |
| Existing Figure 8 evaluation | Keras checkpoint evaluated on the first 50 images per class in sorted filename order from `val/`, totaling 500 images. This is a descriptive validation subset. It uses a different checkpoint/implementation from Table 7's PyTorch/timm EfficientNet-B0 row. |
| CNN repartitioned holdout | `train.py --cv-folds 0` pools labeled images and creates a new stratified 70/15/15 partition. It is not the fixed-split Figure 8 protocol. |
| CNN cross-validation | Five outer folds with approximately 20% test data each. In the reviewed `train.py`, an inner validation fraction of `0.15 / 0.8` leaves approximately 65% training, 15% validation, and 20% test overall. Preserve exact indices and verify the backbone-comparison script's corresponding split. |
| Fusion ablation | In the reviewed `evaluate_hybrid.py`, each outer training pool reserves `val_frac=0.2` for validation: approximately 64% training, 16% validation, and 20% test overall. This differs from the CNN split. Synthetic label conditioning remains a limitation even with disjoint indices. |
| Standalone LSTM regression | 9,994 six-step windows from 10,000 records; chronological split of 6,396 training, 1,599 validation, and 1,999 test windows. The recorded full-series scaling introduces preprocessing leakage. |
| Final fusion training | A separate 85/15 train/validation configuration is described in project documentation. Deployment identity and execution must be supported by the corresponding run records; this is not an ablation test result. |

Six observations spaced four hours apart span **20 hours between the first and last input observations**. The next-step target is four hours after the last observation. Do not label this as a 24-hour input timestamp span.

## 3. Environment setup

Use separate Python virtual environments for TensorFlow/Keras and PyTorch/Ultralytics services where their dependencies differ. Dependency installation is not a record of the versions used by historical checkpoints; retain an environment export for each new run.

From the repository root, install the requirements for the component being used:

```bash
# Standalone Keras training dependencies; pin compatible versions for a recorded run.
python -m pip install tensorflow keras numpy pandas scikit-learn matplotlib seaborn opencv-python

# CNN benchmark and evaluation environments, as appropriate.
python -m pip install -r backend/python/detect_penyakit/requirements_experiment.txt
python -m pip install -r backend/python/detect_penyakit/requirements_evaluation.txt

# Fusion experiments.
python -m pip install -r backend/python/hybrid_cnn_lstm/requirements.txt
```

The two live services (CNN detection and forecasting/recommendation) share one environment: `backend/python/requirements-gpu.txt` on Windows with an NVIDIA GPU (below), or `backend/python/requirements-vps.txt` on a Linux VPS (§11.3). The former per-service `requirements.txt` files were removed because they pinned older, incompatible versions.

**Windows GPU environment for the live services (recommended).** Native Windows GPU support ends at TensorFlow 2.10 (Python 3.10, CUDA 11.x). The stored `.h5` models were saved with Keras 3, so they are converted once to TF 2.10 format (`*_tf210.h5`, same weights). In a check with identical inputs, the converted models' outputs differed from the originals by at most 8 × 10⁻⁷, and every predicted class matched.

```bat
cd backend\python
setup_gpu_env.bat      :: creates .venv-gpu, installs requirements-gpu.txt, converts models, trains the recommendation model if missing
```

The services print `[gpu_env] TensorFlow 2.10.1: GPU aktif -> <GPU name> (+ CPU)` at startup and fall back to CPU when no GPU is present.

Laravel setup, from the repository root:

```bash
cd backend/laravel
composer install
cp .env.example .env
php artisan key:generate
# Set database and service configuration in .env before migrating.
php artisan migrate
php artisan db:seed      # roles, permissions and the main SuperAdmin account (ADMIN_* in .env)
```

No front-end build step is needed: the pages load their CSS/JS directly from `backend/html/assets`. Under XAMPP the application is served at `http://localhost/gemastik/backend/html`.

Configure device identifiers, credentials, service addresses, and MQTT settings for the target installation. The AI assistant needs `OPENAI_BASE_URL`, `OPENAI_API_KEY` and `OPENAI_MODEL`. The current provider is Google Gemini through its OpenAI-compatible endpoint (`https://generativelanguage.googleapis.com/v1beta/openai`, model `gemini-3.1-flash-lite`). Without a key the assistant panel reports that it is not active; the rest of the system is unaffected. Keep secrets outside version control. Compile the ESP32 sketch using the board configuration and libraries required by its source, including WiFiManager, PubSubClient, ArduinoJson, DHT, BH1750, and Adafruit_BMP280.

## 4. Running experiments and services

Start **each command block from the repository root** unless otherwise stated. Commands below describe supported workflows, not guaranteed reproduction of historical scores. Check `--help` before a new run. Record the resolved configuration, including framework-specific defaults; a top-level seed does not establish the effective seed of every training backend.

Some scripts write fixed filenames. Use an isolated working copy or archive existing checkpoints and outputs before retraining or regenerating results. Existing evaluation artifacts must not be overwritten to make them agree with manuscript values.

### 4.1 CNN training and backbone comparison

```bash
cd "Training_Penyakit Padi"
python compare_cnn_backbones.py --cv-folds 5
```

The comparison workflow is intended to produce `table4_cnn_comparison.{md,csv,json}` and per-backbone `table4_fold_results_<Model>.json`. The existence of the script does not establish a completed comparison for all backbones. Section 6 identifies the recorded EfficientNet-B0 result.

To train a new Keras model on the fixed directories:

```bash
cd "Training_Penyakit Padi"
python train.py --fixed-split --cv-folds 0 --seed 42 --output efficientnetb0_fixed_split_new.h5
```

The reviewed trainer supports `--fixed-split` and writes `fixed_split_results.json`. Validation is used for both checkpoint selection and reported evaluation in this mode. A newly trained checkpoint is not automatically the checkpoint used by an earlier Figure 8.

For a new stratified 70/15/15 holdout experiment:

```bash
cd "Training_Penyakit Padi"
python train.py --cv-folds 0 --seed 42 --output efficientnetb0_holdout_new.h5
```

Never evaluate an existing checkpoint on a newly repartitioned test set without establishing that those images were excluded from its training and selection history.

### 4.2 Classification benchmark associated with Table 7

Eight model families are represented: YOLOv8n-cls, YOLO11n-cls (called YOLOv11n-cls in the manuscript), EfficientNetV2, EfficientNet-B0, ViT, ResNet50, MobileNetV2, and VGG16. Their training protocols are not uniform.

Example fresh YOLO-family benchmark:

```bash
cd backend/python/detect_penyakit
python benchmark_modern_models.py --dataset-root "/absolute/path/to/Training_Penyakit Padi" \
  --output-dir "/absolute/path/to/new_yolo_run" \
  --epochs 5 --batch-size 8 --img-size 224 --device cpu --num-workers 0 --seed 42 \
  --no-efficientnetv2 --no-efficientnetb0 --no-vit --no-resnet50 --no-mobilenetv2 --no-vgg16
```

Use actual absolute paths. The historical YOLO runs are documented as using five epochs, batch size eight, 224-pixel inputs, and CPU execution. Capture Ultralytics' resolved `args.yaml`, selected optimizer, augmentations, seed, version, and evaluated checkpoint; identical headline settings do not prove every effective setting is identical.

Example fresh torchvision/timm benchmark:

```bash
cd backend/python/detect_penyakit
python benchmark_modern_models.py --dataset-root "/absolute/path/to/Training_Penyakit Padi" \
  --output-dir "/absolute/path/to/new_timm_run" \
  --epochs 30 --img-size 224 --batch-size 16 --seed 42 --no-yolo --no-yolo11
```

The actual historical epoch counts for several stored non-YOLO checkpoints were not recorded in the supplied documentation. These commands therefore initiate new comparisons rather than guarantee reproduction of existing numbers. Keep YOLO and timm preprocessing descriptions separate.

Historical outputs are under `experiments/scopus_q1_comparison/` and `experiments/yolov11_addition/`. See their per-model metrics and provenance documents. A function selecting the newest checkpoint is not sufficient evidence that the same checkpoint produced an older metrics file.

### 4.3 Figure 8: descriptive validation-subset evaluation

```bash
cd "Training_Penyakit Padi"
python compute_real_50perclass_matrix.py
python plot_real_50perclass_confusion_matrix.py
```

Before running, inspect the checkpoint path, class mapping, and output paths in these scripts. The recorded historical result is 217 correct predictions out of 500 images (43.40%). Regenerate the JSON and plot from the same predictions when evaluating a different checkpoint. Do not assume a fresh run will reproduce either 43.40% or 89.20%.

### 4.4 Synthetic LSTM regression and Figure 9

```bash
cd backend/python/rnn
# Only regenerate the dataset in a separate run when needed.
python make_synthetic_microclimate_dataset.py
python generate_real_figure9_2025dates.py
python rebuild_figure9_labeled.py
python run_real_vpd_example.py
```

Archive the source dataset and existing prediction CSV first. `rebuild_figure9_labeled.py` plots the stored `figure9_2025dates_predictions.csv`; plotting is distinct from retraining. The figure compares synthetic reference values with predictions, not measured field values.

The historical regression results use full-series scaling before partitioning. Correcting this leakage requires retraining and reevaluation; changing this README cannot make existing metrics leakage-free. Any 14-day rolling evaluation must have its own predictions and metrics and must not reuse the full-holdout metrics as period-specific results.

### 4.5 Fusion experiments and decision-logic checks

```bash
cd backend/python/hybrid_cnn_lstm
python make_paired_dataset.py
python tune_fusion_weights.py
python train_hybrid.py --fusion adaptive
python evaluate_hybrid.py
```

The CLI value `adaptive` and class name `AdaptiveFeatureFusion` are implementation identifiers. The described fusion operation uses **fixed weighted-sum coefficients during inference**. Record the coefficients selected by the actual tuning run; do not require the search to return 0.6/0.4 merely because those values appear in the manuscript. Concatenation is an alternative fusion baseline, not a no-fusion model.

The paired windows are generated conditionally on image labels. Their assigned timestamps do not establish real field synchronization or independent environmental evidence.

To inspect inference/decision logic without a field trial:

```bash
cd backend/python/hybrid_cnn_lstm
python algorithm1.py --dry-run --limit 10
python actuation_eval.py --run-log algorithm1_run_log.json --out decision_rule_agreement.json
```

The reviewed `actuation_eval.py` compares logged action names with rule-derived expected actions using information in the same log. It measures **decision-rule agreement**, despite its output key `success_rate_percent`. It does not verify relay switching, water delivery, pest suppression, uptime, or the manuscript's 30-day field-trial success rate. Physical actuation success requires independent observations linked to individual field events.

### 4.6 Figure 4: augmentation and reporting example

```bash
cd backend/python/rnn
python make_figure4b_json_output.py
cd "../../../Training_Penyakit Padi"
python make_figure4_combined.py
```

Use one original training image and transformations produced by the actual augmentation pipeline. Keep the image identifier, transform parameters, and random seeds. The JSON panel is a system reporting example; its values are not normalized model-input tensors, and additional fields are not necessarily regression outputs. Preserve the provenance of the JSON example separately from field measurements and model evaluation.

### 4.7 Live services

Run each service in a separate terminal from the repository root:

```bash
# Terminal 1: CNN service, documented port 7000. Receives ESP32 images via Laravel
# (POST /api/kirim_gambar -> POST /detect). Optional direct streams: CAMERAS=0:1,1:2
cd backend/python/detect_penyakit
python app.py
```

```bash
# Terminal 2: online forecasting service, documented port 5000.
cd backend/python/rnn
python run.py
```

```bash
# Terminal 3: Laravel local development server.
cd backend/laravel
php artisan serve
```

On Windows with the GPU environment, use the launchers instead:

```bat
cd backend\python
run_rnn.bat           :: forecasting + recommendation service, port 5000 (HISTORY_URL defaults to the local Laravel /api/sensor)
run_detect.bat        :: CNN service, port 7000; images from ESP32 A arrive through Laravel /api/kirim_gambar
:: optional local test with direct streams: set CAMERAS=0:1,1:2 before run_detect.bat
```

### 4.8 Multi-point configuration

- **Field nodes:** ESP32 A (point 1, weather + soil + camera A) is the only device with internet access. ESP32 B (point 2, soil + camera B) sends to A over ESP-NOW, and A posts both points to Laravel with `titik` 1 or 2. Laravel discards weather fields received for point 2. Wiring, settings, and setup steps are in [`Arduino/README.md`](Arduino/README.md) and §11.
- **Database:** `php artisan migrate` adds the `titik` column to `sensor_iots`, `sensor_kameras`, and `hybrid_inferences`. It also makes the sensor columns nullable `decimal(10,2)`, matching the production schema. Existing rows become point 1.
- **Growth phase:** set the transplanting date and variety duration per plot in `backend/laravel/.env`:

  ```dotenv
  TANGGAL_TANAM_TITIK_1=2026-08-10
  TANGGAL_TANAM_TITIK_2=2026-08-10
  UMUR_VARIETAS_TITIK_1=115
  UMUR_VARIETAS_TITIK_2=115
  API_RECOMMENDATION=http://127.0.0.1:5000/recommendation
  API_SENSOR_FORECAST=http://127.0.0.1:5000/sensor-forecast/
  API_DETECT=http://127.0.0.1:7000/detect
  ```

  Without a planting date, phase-dependent rules cannot be applied: ponding during flowering, no urea after flowering, no irrigation before harvest, and P/K as basal fertilizer only.

These are service startup instructions, not a complete production deployment. Verify configured endpoints, hardware connections, and model files. Identify which inference services actually execute on the Raspberry Pi and which execute on a server before describing a deployment as entirely edge-based.

## 5. Manuscript-to-artifact map

Table and figure numbers refer to the manuscript version discussed during this review. Expected filenames indicate workflow outputs, not a certification that all files exist or reproduce the manuscript.

| Manuscript item | Workflow or artifact | Evidence boundary |
|---|---|---|
| Table 1 | Labeled dataset counts | Exclude the unlabeled competition directory. |
| Table 2 / Algorithm 1 | `backend/python/hybrid_cnn_lstm/algorithm1.py` | Source and execution logs establish implemented logic; dry-runs do not establish field outcomes. |
| Table 3 | Run configurations and environment exports | Distinguish defaults, resolved run settings, and deployment settings. |
| Table 4 | `table4_fold_results_<Model>.json`; optional combined comparison outputs | Reviewed EfficientNet-B0 result differs from manuscript values; other rows require their own fold records. |
| Table 5 / Figure 9 | `backend/python/rnn/figure9_2025dates_predictions.csv` | Recalculate metrics from the same stored targets/predictions; retain synthetic-data and scaling limitations. |
| Table 6 | `backend/python/hybrid_cnn_lstm/evaluate_hybrid.py`; expected `table6_hybrid_comparison.{md,json}` | Script availability alone does not verify reported ablation scores or latency. |
| Table 7 | Per-model metrics under `experiments/scopus_q1_comparison/` and `experiments/yolov11_addition/` | Link each metric to the exact evaluated checkpoint and full validation partition. |
| Figure 4 | `make_figure4_combined.py` and `figure4b_real_forecast_entry.json` | Experimental augmentation and system reporting example; no generative reconstruction of data. |
| Figure 8 | `real_50perclass_confusion_matrix.json` and its plotted figure | Historical 500-image validation result is 43.40%; supplied 89.20% figure requires reconciliation. |
| Section 3.8 field actuation | Independent event-level field records | `actuation_eval.py` alone does not substantiate 116 successful physical events out of 120. |

Checkpoint names referenced by the project include `Training_Penyakit Padi/efficientnetb0_model.h5`, `backend/python/rnn/config/plant_health_lstm_model.h5`, and the fusion training output `hybrid_cnn_lstm_model.h5`. File naming alone does not establish deployment or linkage to published metrics.

## 6. Recorded results and unresolved discrepancies

### 6.1 CNN evidence available in the reviewed snapshot

| Evaluation | Recorded or displayed value | Status |
|---|---|---|
| Historical Figure 8 validation subset | 43.40% accuracy: 217/500 | Recorded in `Training_Penyakit Padi/real_50perclass_confusion_matrix.json`. |
| EfficientNet-B0 five-fold run | Mean accuracy 38.73%; stored SD 1.63 percentage points | Recorded in `Training_Penyakit Padi/table4_fold_results_EfficientNet-B0.json`. |
| Separately supplied 500-image figure | 89.20% accuracy: 446/500 | Arithmetic derived from the displayed matrix; no matching evaluated-checkpoint/prediction linkage was established in the reviewed artifacts. |
| Manuscript Table 4 EfficientNet-B0 claim | 89.2 ± 0.6% | Not supported by the reviewed five-fold file. |

The five recorded fold accuracies are 39.11%, 37.72%, 41.21%, 36.35%, and 39.29%. The reviewed training summarizer uses `ddof=0` for SD. Specify this convention; a population SD across the five observed scores differs from the sample SD obtained with `ddof=1`. Approximate confidence intervals generated from fold scores should not be described as independent-test guarantees because training sets overlap across folds.

No complete four-backbone result set was established in the prior artifact review. Absence of a file in that snapshot does not prove an experiment never ran elsewhere. Supply the missing run records or conduct a new documented comparison before retaining unsupported manuscript numbers.

The uploaded documentation attributes the 89.20% figure to an earlier manually specified matrix and mentions another 33.60% run. Those provenance claims were not independently established in this revision. Neither claim is treated here as a verified new evaluation. Resolve figure provenance through source history and prediction records, without constructing predictions to match a desired score.

### 6.2 Fusion and regression results

The manuscript's reported fusion accuracy of 93.6%, F1-score of 0.93, and associated improvements remain unverified against complete ablation run artifacts in this review. Label-conditioned pairing disclosure does not replace evidence that a reported experiment produced those values.

The documented standalone regression values are synthetic chronological-holdout results. Their interpretation remains limited by preprocessing leakage. Neither those metrics nor the image-subset metrics establish field predictive accuracy.

### 6.3 Benchmark and latency interpretation

Table 7 compares validation checkpoints, including a PyTorch/timm EfficientNet-B0 distinct from Figure 8's Keras checkpoint. Its scores must not replace cross-validation or fusion-baseline results. Low checkpoint scores alone do not establish training divergence or an architectural performance limit; inspect predictions and training records before assigning causes.

The supplied benchmark values give YOLO11n-cls 90.93% accuracy and 89.90% macro F1, versus EfficientNetV2's 86.90% and 84.91%. The differences are **4.03 and 4.99 percentage points**, respectively. These validation metrics alone do not establish deployment efficiency.

The manuscript's 92 ms CNN and 110 ms fusion figures concern different model configurations. Retain them as measured device results only with hardware, runtime, precision, batch size, warm-up, repetitions, and timing boundaries recorded. Do not infer Raspberry Pi latency from a laptop run or infer TensorFlow Lite latency from Keras `model.predict()` timing.

In the reviewed fusion evaluator, latency is measured within each fold and the summarizer aggregates fold-level latency values with a mean and SD. Identify both aggregation levels before comparing that output with a manuscript table reporting a single median. Prediction-call timing excludes acquisition, preprocessing, communication, and physical actuation unless explicitly included.

## 7. Methodological and operational limitations

- **Regression preprocessing leakage:** historical input/target scalers were fitted on the entire synthetic series before partitioning. A corrected experiment must fit them on training data only, then reevaluate.
- **Label-conditioned pairing:** synthetic environmental windows encode information derived from the image target labels. This limits claims about independently measured environmental benefits. Health-category thresholds must also be learned from training data only in a corrected evaluation.
- **Validation reuse:** Table 7 and historical Figure 8 use validation data involved in model selection. Distinct outer test folds are a different protocol, but their independence still depends on actual training, tuning, preprocessing, and duplicate/group handling.
- **Tuning versus evaluation:** fusion-weight selection must not use outer test scores. Save tuning/evaluation indices and use an inner selection procedure when reporting outer-fold results.
- **Online versus offline models:** the five-target regression experiment and the online health classifier are different. Since October 2026 the online forecast projects drivers only from measured data: the plot's hour-of-day means for weather channels and persistence for soil channels. The earlier rule-generated trajectories and NPK clipping were removed. These projections are baselines, not weather forecasts.
- **Sensor forecasts (§10):** the prediction table and trend chart show statistical forecasts of the 15 sensor inputs. The LSTM health probabilities are shown next to them, in separate columns and as the chart's default series, but they are an output of the synthetic-data model (§9.2) and do not drive the forecasts. They extrapolate the plot's own history and are not numerical weather prediction. Rainfall is forecast from its own history (climatology or persistence, whichever backtests better), so individual storms cannot be anticipated; use BMKG forecasts for that. Accuracy depends on the length and quality of the stored series; with less than 4 days of 4-hour blocks the service falls back to persistence and says so in the output.
- **Live CNN colour order (fixed October 2026):** the live service passed OpenCV BGR images to a model trained on RGB images (`tf.io.decode_image` in `train.py`). On 150 validation images (15 per class) full-image accuracy was 0.373 with BGR input and 0.973 with RGB input. The validation set was used for model selection, so 0.973 is optimistic. The service now converts to RGB. Offline evaluation scripts read images with TensorFlow and were not affected.
- **Leaf region proposals:** the Haar cascade (`cascade3.xml`) found no region in 120/120 Paddy Doctor images tested. For ESP32 snapshots the service therefore classifies the whole image when no leaf region is found (`FULL_FRAME_FALLBACK=1`, reported as `mode: gambar_penuh`), which matches how the CNN was trained. Field-camera scenes can differ from the close-up dataset photos, so field accuracy must be measured separately.
- **Camera snapshots, not video:** ESP32 boards cannot decode RTSP video. They fetch a JPEG snapshot over HTTP every 10 minutes (06:00–18:00 WIB). Detection is therefore periodic, not continuous.
- **Fallback values:** when the forecasting service is unavailable, the dashboard now shows an explicit "no forecast" message instead of the former zero-filled rows. Failed sensors are sent as `null` by the firmware and are never stored as 0.
- **Operational claims:** local code, MQTT/REST messages, and rule agreement do not demonstrate continuous operation or successful physical intervention. Field event logs and downtime records are separate evidence.
- **Recommendation model training data:** the Random Forest is trained on synthetic samples labeled by the referenced knowledge base, with sensor noise, missing sensors, and missing camera/LSTM outputs. It therefore reproduces the expert rules (F1-micro 0.968 vs 0.968 for the rules on noisy readings). It cannot outperform them until it is retrained on field-labeled actions and outcomes.
- **Sensor calibration:** low-cost RS485 NPK sensors estimate nutrients from conductivity. Their P and K readings in mg/kg are not equivalent to laboratory Olsen-P or exchangeable K. Sensor N is not used for decisions; urea follows the growth phase and the leaf color chart. In-situ EC differs from ECe. The soil probe reports volumetric water content (VWC). Irrigation uses VWC 35 % (Mahmud et al. 2026), and the saturated VWC used during flowering (default 45 %) must be set to the sensor reading of a ponded field.
- **Historical components:** the LiDAR node, its Laravel routes and the legacy firmware were removed in October 2026 (§1). Removed scripts must be documented through version history, not represented as active or removed merely because their scores differ from manuscript values.
- **Unauthenticated read endpoints:** `GET /api/sensor?iot_id=…` (used by the forecasting service) and `GET /api/inference/{iot_id}` (polled by ESP32 A) need only the device identifier. Protecting them requires changing the Python service and the firmware together.

## 8. Recording a new evaluation

For each run, preserve:

1. Git commit, run identifier, date, hardware, and exported software environment.
2. Dataset source/version or checksum, class-index mapping, and exact partition/image lists.
3. Training and preprocessing configuration, seeds, augmentation settings, and checkpoint-selection criterion.
4. Checkpoint path and SHA-256 hash, including any conversion or optimization history.
5. Ground-truth and predicted labels, or timestamped regression targets and predictions.
6. Metrics calculated from those records, with averaging, units, and SD conventions stated.
7. Figures generated from the same records and the command used to produce them.
8. For physical actuation, independent observations of the actual response and the event success criteria.

Use separate run directories, for example:

```text
# Proposed organization for future runs; not a claim that these folders already exist.
evaluation_results/
    historical_validation_500/
    efficientnet_b0_cv_<run_id>/
    updated_validation_500_<run_id>/
    fusion_ablation_<run_id>/
```

Update manuscript tables, narrative claims, figure captions, and this README from the same verified records. An updated Figure 8 does not automatically update Tables 4, 6, or 7. Keep historical results traceable and document corrections explicitly.

## 9. Recommendation knowledge base

The offline recommendation model (`backend/python/recommendation/`) turns a per-plot snapshot into prioritized actions. The snapshot contains area weather from point 1, plot soil, the plot's CNN detection, the plot's LSTM health probabilities, and the growth phase. The model is one Random Forest per action: 19 actions, 44 features, and 100 trees per action. Every recommendation carries its reasons and its references (`explain`, `sources_for`). The web dashboard and the mobile app show the reasons in farmer wording. The reference list is in [`backend/python/recommendation/SUMBER_REFERENSI.md`](backend/python/recommendation/SUMBER_REFERENSI.md).

### 9.1 Model evaluation and expert-scenario checks

| Metric (8,000 synthetic test samples) | Random Forest | Rules applied to noisy readings |
|---|---|---|
| F1-micro | 0.968 | 0.968 |
| F1-macro | 0.958 | 0.961 |
| Subset accuracy | 0.865 | 0.869 |
| Hamming loss | 0.0087 | 0.0087 |

`python -m recommendation.test_expert_scenarios` checks 15 guideline cases on both the model and the rules, and all 15 pass. For example: irrigation at VWC 30 % but not at 40 %; a bacterial-blight alert after 60 mm of rain in 24 h; no drainage of a normally flooded field; flooding plus fungicide, not drainage, for blast; roguing instead of insecticide for tungro; no salinity alarm at EC 2,000 µS/cm; no urea after flowering (BWD); and no irrigation before harvest. These figures describe agreement with the referenced rules on synthetic data, **not** agronomic performance in the field. Field validation by local extension officers (PPL/POPT) and calibration against laboratory soil tests remain required.

### 9.2 Data-status audit

| Data | Status |
|---|---|
| Recommendation thresholds and actions | Referenced (see `SUMBER_REFERENSI.md`). The saturated VWC is a local calibration. |
| Recommendation training data | Labels come from the referenced rules; sensor-value distributions are synthetic. |
| CNN image dataset | Paddy Doctor. |
| LSTM health model | Trained on the synthetic Kaggle *Plant Health Data*, which is not credible agricultural data. Its output is displayed only and no longer triggers actions or irrigation. It must be retrained on real paddy data. |
| Dashboard trend chart and prediction table | Statistical forecasts of the 15 sensors from the plot's own measured history (§10) No invented values: with under 4 days of data the output is persistence and labelled as such. Field accuracy not yet measured. |
| Firmware rainfall | Fixed: the comma-operator bug `curah_hujan = (curah_hujan, 2)` was removed, and rainfall is now sent per interval and summed over 24 h by Laravel. The ESP32 nodes must be re-flashed. |

The full audit is in `SUMBER_REFERENSI.md` §F.


## 10. Sensor forecasting: trend chart and prediction table

The **Prediksi** table and the **Tren Prediksi** chart forecast all 15 sensor inputs of a plot:

temperature, humidity, VPD, wind speed, rainfall, light intensity, pressure, pH, soil moisture, EC, TDS, soil temperature, N, P, K.

They appear in three places:
- the farmer dashboard: `/dashboard?titik=0|1|2&days=1..30`;
- the admin monitoring page: `/data/monitoring/{iot_id}`;
- the mobile app's Prediksi tab, through `GET /api/v1/forecast`.

`titik=0` is the default **Gabungan Petak 1 & 2** view: values are the mean of the plots, statuses and causes are listed per plot, and the conclusion is the worst plot's. `days` is any whole number from 1 to 30 (default 3), chosen with buttons or typed in.

**Table layout.** The 5 latest measured readings come first (green rows), then the forecast rows in 4-hour blocks. Each row shows:

| Column | Content |
|---|---|
| Sensors | the 15 sensor values |
| P. Sehat, P. Sedang, P. Tinggi | the LSTM health probabilities (%) |
| P. Kondisi (Kelas LSTM) | the most likely LSTM class |
| Status | farmer-worded warnings from the knowledge-base thresholds |
| Penyebab | the measured value that triggered each status |
| Kesimpulan | the row's conclusion; tapping it opens the plot's "Aksi Rekomendasi · Model Random Forest" list |

The LSTM columns of measured rows are filled by `GET /health-history/<titik>?n=N`, a batched LSTM run on the stored readings. The forecast rows use the recursive LSTM forecast. The chart's default series is the three LSTM probabilities with a "start of forecast" marker; any sensor can be selected instead.

**History page.** `/history` (and `GET /api/v1/history`) shows the same columns for measured readings only (1–30 days, newest first), with a "Tren Riwayat" chart and an Excel export that contains the data sheet and native Excel charts.

The forecasts are served by `GET /sensor-forecast/<titik>?days=N` on the Flask service (`backend/python/rnn/app/services/sensor_forecast.py`). Laravel (`MultiTitikService::sensorForecast`) discards responses that belong to another device. When the service is unreachable, the page shows "Belum ada ramalan sensor" instead of placeholder values. Point 2 has no weather station, so its weather columns use point-1 data, aligned by time (`merge_asof`).

### 10.1 Method

| Step | Implementation |
|---|---|
| Aggregation | Readings are averaged into 4-hour blocks (6 blocks = 1 day); rainfall is summed per block. Gaps of up to 2 blocks inside the series are interpolated for continuous sensors; rainfall is never interpolated. |
| Candidate models per sensor | Naive (last value); seasonal naive (same block 24 h earlier); simple exponential smoothing; additive Holt-Winters with damped trend (season = 6 blocks); historical block-of-day climatology for rainfall. |
| Model selection | Rolling-origin backtest: up to 4 forecast origins, 1-day horizon each. The candidate with the lowest backtest error (MAE; identical ranking to MASE within a series) is used for that sensor. MAE and MASE of the chosen model are shown in the chart. |
| Why statistical models | Simple statistical methods were as accurate as or more accurate than ML methods on large benchmark collections, at a fraction of the cost, and need no external training data. |
| Derived variables | VPD = e<sub>s</sub>(T) × (1 − RH/100), with e<sub>s</sub> from the FAO-56 Tetens equation, computed from the forecast temperature and RH. TDS = k × EC, where k is the median measured TDS/EC ratio of the plot (the 8-in-1 sensor's own conversion); without measured pairs k = 0.64 (FAO-29). The value of k and its origin are shown in the chart. |
| Physical bounds | Forecasts are clipped to the sensor's physical range (for example RH 0–100 %, pH 0–14, non-negative rainfall and nutrients). |
| Short history | With fewer than 24 blocks (4 days) no backtest is possible, so persistence is used and a note in the output says so. |

**Status column.** Each row (measured or forecast) is compared with the thresholds of the recommendation knowledge base (§9, `knowledge_base.py`, mirrored in `RecommendationService::THRESHOLDS`). No new thresholds are introduced. Laravel (`MultiTitikService::STATUS_LABELS`) shows the statuses in farmer wording:

| Status shown | Threshold |
|---|---|
| Udara terlalu panas | Temperature ≥ 35 °C |
| Rawan penyakit blas | RH ≥ 90 % at 20–28 °C |
| Rawan penyakit kresek (bacterial blight) | Rainfall ≥ 50 mm/24 h with RH > 70 % at 25–34 °C |
| Tanah kering, perlu diairi | Soil moisture < 35 % |
| Tanah/air terlalu asin | EC ≥ 3,000 µS/cm or TDS ≥ 1,920 ppm |
| Tanah terlalu masam / basa | pH ≤ 5.5 / pH > 7.5 |
| Angin kencang, padi rawan rebah | ≥ 8 m/s |

**Kesimpulan column.** It combines the status column with the LSTM class (`MultiTitikService::conclusion`):

| Status warning | LSTM class "High Stress" | Conclusion |
|---|---|---|
| yes | yes | **Bahaya: tindak lanjuti sekarang** |
| yes | no | **Waspada: ikuti rekomendasi Status** |
| no | yes | **Cek lapangan: LSTM mendeteksi stres** |
| no | no | **Aman** |

The status rules are referenced; the LSTM model was trained on synthetic data (§9.2). A "Cek lapangan" conclusion is therefore a prompt to inspect the field, not a diagnosis.

**Sensor status labels on the cards.** The dashboard's weather and soil cards, `GET /api/v1/dashboard`, and the mobile app use one server-side function, `app/Support/SensorStatus.php`. Besides the thresholds above, it adds display-only labels for:
- light: low below 32,400 lux; "Malam" at 0 lux;
- sea-level pressure: low at ≤ 1000 hPa (station height `ELEVASI_STASIUN_M`);
- soil temperature: low at ≤ 17.5 °C, high above 34 °C;
- available N: low, medium or high at 80 and 200 mg/kg;
- 24-hour rainfall: medium at 20–50 mm, high at ≥ 50 mm.

These labels never trigger actions.

### 10.2 Checks

- `php artisan test` includes `test_dashboard_shows_15_sensor_forecast`, which checks that all 15 columns, a farmer-worded status ("Rawan penyakit blas"), and the requested horizon reach the page. `test_sensor_forecast_for_other_device_is_ignored` checks that a response for another device is not shown. `test_data_pages_require_view_user_permission` checks that the admin monitoring, history and export pages reject users without the `view_user` permission (403).
- On a simulated 5-day series the backtest chose Holt-Winters for temperature, RH, wind, VWC, EC, soil temperature and P; seasonal naive for light and pressure; SES for pH, N and K; and climatology for rainfall. This shows that the selection works; it is **not** a field accuracy result. Report MAE/MASE from the real plot series (shown in the chart) before claiming forecast accuracy.


## 11. Field hardware (ESP32 A/B) and VPS deployment

### 11.1 Topology

```
Camera B ──WiFi hotspot──► ESP32 B (RS485 8-in-1 soil, plot 2) ──ESP-NOW──┐
Camera A ──router LAN────► ESP32 A (DHT22, anemometer, rain gauge, BH1750,  │
                                     BMP280, RS485 8-in-1 soil, plot 1) ◄───┘
ESP32 A ──router WiFi, HTTPS──► VPS: nginx + Laravel ──► Flask detect (127.0.0.1:7000)
                                                     ──► Flask rnn    (127.0.0.1:5000)
```

- **ESP32 B** has no internet connection. It creates a WiFi hotspot for camera B, reads the soil sensor, and sends data and JPEG images to ESP32 A over ESP-NOW. Images are split into 239-byte chunks, reassembled at A, and checked with CRC32.
- **ESP32 A** posts point-1 and point-2 data to `/api/kirim_sensor` and both cameras' images to `/api/kirim_gambar`. It polls `/api/inference/{iot_id}?titik=N` for relay commands and forwards plot-2 commands to B.
- **Laravel** is the only public endpoint. The Python services listen on 127.0.0.1 only.

Firmware, wiring tables, setup steps, and troubleshooting: [`Arduino/README.md`](Arduino/README.md).

### 11.2 Image path

1. ESP32 A sends `POST /api/kirim_gambar` with the raw JPEG body and headers `X-IOT-ID`, `X-IOT-TOKEN`, and `X-TITIK`.
2. `CameraUploadController` checks the device token, the plot number, the JPEG signature, and the 2 MB size limit.
3. It forwards the image to `POST {API_DETECT}?titik=N&iot_id=...`.
4. The detection service classifies leaf regions, or the whole image when no region is found. It stores the result in `sensor_kameras` and the image in `backend/html/img/hama_padi`.
5. Laravel replies to ESP32 A:
   - **201:** a detection was stored.
   - **200:** the image was received but nothing was stored (outside `DETECT_HOURS`).
   - **401 / 422:** the request was rejected.
   - **502 / 503:** the detection service failed or is unreachable.

The route is limited to 30 requests per minute.

### 11.3 VPS deployment (Ubuntu 22.04 or later)

1. **Packages:** nginx, PHP 8.3-FPM with the extensions Laravel needs, MySQL/MariaDB, Composer, and Python 3.10 with `venv`. Ubuntu 24.04 ships Python 3.12, so install 3.10 separately; TensorFlow 2.10 needs Python ≤ 3.10.
2. **Code:** copy the repository to `/var/www/taniverse`, then:
   ```bash
   cd /var/www/taniverse/backend/laravel
   composer install --no-dev
   cp .env.example .env && php artisan key:generate
   # set DB_*, APP_URL, APP_TIMEZONE=Asia/Jakarta, API_* (defaults point to 127.0.0.1) in .env
   php artisan migrate --force && php artisan optimize
   php artisan db:seed --force      # roles, permissions, main SuperAdmin account (ADMIN_* in .env)
   sudo chown -R www-data:www-data storage bootstrap/cache ../html/img/hama_padi ../html/img/profile
   ```
3. **Python:**
   ```bash
   cd /var/www/taniverse/backend/python
   python3.10 -m venv .venv
   .venv/bin/pip install -r requirements-vps.txt
   ```
   All pinned packages have Linux wheels for Python 3.10; this was checked with `pip download --platform manylinux…`. The `.h5` and `.joblib` models are the same files used on Windows (TF 2.10, scikit-learn 1.6.1).
4. **Services:**
   ```bash
   sudo install -D -m 640 deploy/vps/taniverse.env.example /etc/taniverse/taniverse.env   # edit DB password, domain
   sudo cp deploy/vps/taniverse-rnn.service deploy/vps/taniverse-detect.service /etc/systemd/system/
   sudo systemctl daemon-reload && sudo systemctl enable --now taniverse-rnn taniverse-detect
   ```
   `TZ=Asia/Jakarta` in the environment file keeps the detection hours and the stored timestamps in WIB, consistent with Laravel.
5. **nginx and HTTPS:** copy `deploy/vps/nginx-taniverse.conf`, set the domain and the PHP-FPM socket, enable the site, and run `certbot --nginx -d DOMAIN`. Open only ports 80/443 in the firewall; ports 5000 and 7000 stay local.
6. **Firmware:** set `URL_SERVER` in `Arduino/esp32_a_gateway/konfigurasi.h` to `https://DOMAIN`.

### 11.4 Verification status

| Item | How it was checked | Result |
|---|---|---|
| ESP32 A and B firmware | `arduino-cli` compile, esp32 core 3.3.11, ESP32 Wrover, Huge APP; also optional variants (MQTT, JXCT map, register dump, fixed channel, no encryption, no relays) | Compiles without errors or warnings. **Not yet tested on physical hardware.** |
| `/api/kirim_gambar` | `tests/Feature/CameraUploadTest.php`: forwarding, token, plot, JPEG checks, offline service, plot-2 gateway payload | Passing (66 Laravel tests in total, October 2026; `phpunit.xml` runs them on SQLite in memory, so the MySQL database is not touched) |
| `POST /detect` | Flask test client with the real EfficientNet-B0 model on validation images; database deliberately unreachable | Correct classes for blast, normal, brown spot, and tungro; no orphan image files when the database insert fails |
| VPS requirements | `pip download` for manylinux / Python 3.10 | All wheels available |
| systemd / nginx files | Reviewed only | Not executed on a VPS |

The RS485 register maps (`PETA_8IN1_UMUM`, `PETA_JXCT`) are common vendor layouts, not a guarantee for a particular sensor. Check them against the datasheet with `DUMP_REGISTER 1` before trusting the readings.

## 12. REST API v1

A versioned REST API for mobile apps and integrations is served under `/api/v1`. The full reference with real example responses is in [`backend/laravel/API.md`](backend/laravel/API.md).

- **Authentication:** Laravel Sanctum Bearer tokens from `POST /api/v1/auth/login`, valid for `API_TOKEN_DAYS` days (default 30). Data is limited to the token owner's device (`iot_id`); SuperAdmin users may pass `?iot_id=`.
- **Resources:**

  | Resource | Endpoints |
  |---|---|
  | Account | `POST /auth/login`, `GET /auth/me`, `POST /auth/logout`, `POST/PATCH /auth/profile` (incl. photo upload), `PUT /auth/password`, `POST /auth/forgot-password` |
  | App screens (ready to display, same as the web pages) | `GET /dashboard`, `GET /recommendations`, `GET /forecast?titik=0..&days=1..30`, `GET /history`, `GET /history/export`, `GET /detections/summary`, `GET /detections/export`, `GET /ai/summary`, `POST /ai/chat` |
  | Devices (SuperAdmin) | `GET /devices` |
  | Plots | `GET /plots`, `GET /plots/{titik}`, `GET /plots/{titik}/forecast` |
  | Snapshot | `GET /snapshot` |
  | Sensor readings | `GET /sensor-readings`, `GET /sensor-readings/latest`, `GET /sensor-readings/{id}` |
  | Camera detections | `GET /detections`, `GET /detections/latest`, `GET /detections/{id}` |
  | Fused-model inferences | `GET /inferences`, `GET /inferences/latest`, `GET /inferences/{id}` |

- **Server-side presentation:** status objects `{code, label, level}`, LSTM classes, conclusions and recommendation texts are computed by Laravel, so the app and the web always show the same result.
- **Rate limits** (per user unless noted):

  | Scope | Limit |
  |---|---|
  | General | 120/min |
  | Login | 10/min per IP and email |
  | Forgot password | 5/min per IP |
  | Password change | 10/min |
  | Excel export | 10/min |
  | AI chat | 20/min |
  | Device images | 30/min per device |

- **Language:** validation and error messages follow the account's `locale`, otherwise the `Accept-Language` header (`ApiLocale` middleware), defaulting to Indonesian.

- **List endpoints:** paginated, with `titik`, `from`, `to`, and `per_page` (≤ 100) filters.
- **Device endpoints:** `POST /device/sensor-readings` and `POST /device/images` are aliases of the firmware routes `/api/kirim_sensor` and `/api/kirim_gambar`, which stay active.
- **Errors:** always JSON (401, 403, 404, 422, 429, 502, 503). Failed sensors are `null`; the forecast endpoint returns 503 rather than substitute values; 502 means the AI provider failed.
- **Tests:** `tests/Feature/ApiV1Test.php` (13 tests) covers login and expiry, logout revocation, device scoping and SuperAdmin access, filters and validation (`days` 1–30), plot-2 area weather, forecast proxy/503, the recommendation fallback format, and the device alias. All 22 v1 endpoints except the AI pair were also called against the local MySQL data and returned 200.
- **Legacy routes:** `/api/kirim_sensor`, `/api/kirim_gambar`, `POST /api/inference`, `GET /api/inference/{iot_id}` and `GET /api/sensor` stay active for the firmware and the Python service. `GET /api/bacajson/{iot_id}` and `GET /api/user` were removed.
- **Removed scaffolding:** the empty `apiResource` routes generated by the nwidart modules were removed, because their controllers return HTML views and have empty write methods:
  - API: `/api/v1/data`, `/dashboards`, `/histories`, `/yolos`;
  - web: `create`, `show` and `edit` for `/dashboard`, `/data`, `/history` and `/cnn`, which returned HTTP 500.

## 13. Mobile app (Flutter)

[`mobile/`](mobile/) is a Flutter (Android/iOS) client of the REST API v1, displayed as **AgroGuard**. It contains the same pages and content as the web application, laid out for phones. A bottom navigation bar replaces the sidebar, and the account page opens from the profile photo:

| Tab | Web page | Content |
|---|---|---|
| **Beranda** | Dashboard (upper part) | VPD card, weather, soil table per plot and device status; CNN card per plot (photo, pest-detection score, LSTM health score); Aksi Rekomendasi; Aksi Rekomendasi Keseluruhan |
| **Prediksi** | Dashboard prediction table + trend chart | Combined/plot selector, 1–30 days, the table and chart described in §10 |
| **Riwayat** | `/history` | Measured readings with the same columns, trend chart, Excel export |
| **Deteksi CNN** | `/cnn` | Summary cards, photo gallery with advice, distribution per disease, Excel export |
| **Asisten AI** | Dashboard AI panel | Field summary and chat |
| **Akun** (profile photo) | Account → Profile | Photo upload, name, email, language, password, device selection (SuperAdmin), server address, logout |

The login page has a "Lupa password?" link. Status labels, LSTM classes and conclusions come from the server (§12), so the app holds no thresholds of its own.

- **Verification:** `flutter analyze` reports no issues. `flutter test` passes 8 tests; the fixtures are real API v1 responses captured from the local server, and the test that opens every tab fails on any layout error. `flutter build apk --debug` succeeds.
- **Not yet tested:** a physical device against the production server, and an iOS build.

Setup and server addresses: [`mobile/README.md`](mobile/README.md).

## 14. Running without ESP32 (dummy data)

`php artisan taniverse:dummy` fills the database with clearly flagged dummy data, so the dashboard, forecasts, recommendations, and mobile app can be tried before the ESP32 nodes are installed.

```bash
cd backend/laravel
php artisan migrate                              # adds the is_dummy column (once)
php artisan taniverse:dummy                      # 5 days of readings every 15 min, plots 1 and 2
php artisan taniverse:dummy --kamera             # + photos classified by the real CNN (detect service must run)
php artisan taniverse:dummy --live --kamera      # keep adding data like the ESP32 (Ctrl+C to stop)
php artisan taniverse:dummy --hapus              # delete ALL dummy rows and their images
```

- **Flagging:** every row gets `is_dummy = 1`. The API exposes it as `is_dummy` on detections and `dummy` on `GET /api/v1/dashboard` and the snapshot. The web dashboard and the mobile app do not show a dummy badge, because it was removed from the screens on request. Check the database or the API to see whether the displayed data are dummy data.
- **Removal:** `--hapus` deletes only flagged rows (and the uuid-named images the detection service created for them); real ESP32 data is never touched.
- **Sensor values:** generated from a typical tropical-paddy daily pattern (temperature, RH, light, afternoon rain on some days, an AWD-like soil-moisture cycle). They are not measurements and must not be used for analysis.
- **Camera photos:** `--kamera` sends random Paddy Doctor validation images (`--gambar=` to choose a folder) to the running detection service, so the stored labels are real CNN outputs on dataset images.
- **Tests:** `tests/Feature/DummyDataTest.php`.

## 15. Web application, access control and AI assistant

### 15.1 Pages

The web root is `backend/html` (`http://localhost/gemastik/backend/html` under XAMPP).

| Page | Path | Content |
|---|---|---|
| Dashboard | `/dashboard?titik=0..&days=1..30` | Panels in this order:<br>1. Pemantauan Lingkungan Waktu Nyata: VPD, weather, soil per plot, device status.<br>2. Deteksi Hama CNN per Petak.<br>3. Aksi Rekomendasi and Aksi Rekomendasi Keseluruhan, side by side.<br>4. Asisten AI.<br>5. Prediksi table.<br>6. Tren Prediksi chart.<br>Sensors refresh every 15 s, cameras every 60 s, recommendations every 30 s. |
| Riwayat → Sensor IoT | `/history?titik=&days=` | Measured history table, trend chart, Excel export with charts |
| Riwayat → Deteksi CNN | `/cnn?titik=&days=` | Summary cards, photo gallery with advice and a recommendation pop-up, distribution chart, Excel export. `/yolo` redirects here. |
| Manajemen Pengguna → Data | `/data`, `/data/monitoring/{iot_id}`, `/data/historyiot/{iot_id}`, `/data/historyyolo/{iot_id}` | Admin view of every user's device, monitoring, history and exports |
| Manajemen Pengguna → User / Role | `/user`, `/role` | User and role management (spatie/laravel-permission) |
| Akun | `/account/profile`, `/account/activity-log` | Profile, photo, language, password; activity log |

### 15.2 Access control

Every page requires a logged-in, email-verified user. Ordinary pages only show the logged-in user's own device (`iot_id`); the page parameters cannot select another device.

The admin Data pages, their AJAX endpoints (`/monitor_iot`, `/monitor_kamera`) and the per-user exports require the `view_user` permission, the same permission that shows the menu. Before October 2026 these routes only required a login, so any registered user could open other users' data by URL. User and role pages check their own permissions (`view_user`, `create_user`, `view_role`, …).

In the API, the SuperAdmin role may select another device with `?iot_id=`; other users receive 403.

### 15.3 AI assistant

The dashboard panel "Asisten AI" and `GET/POST /api/v1/ai/*` are served by `app/Services/AiAssistantService.php`. They call an OpenAI-compatible chat API configured by `OPENAI_BASE_URL`, `OPENAI_API_KEY` and `OPENAI_MODEL`; the current provider is Google Gemini, `gemini-3.1-flash-lite`.

The prompt context is built from the system's own data:
- area weather;
- soil, camera detection, LSTM class and growth phase per plot;
- the current recommendation list.

The assistant is instructed to answer from that context. The field summary is cached for 6 hours unless the data change. A chat uses the last 10 messages of the conversation and is limited to 20 questions per minute.

The assistant does not trigger actions, and its answers are not part of the evaluated recommendation model. Treat them as an explanation layer over the referenced recommendations, not as an independent agronomic source.
