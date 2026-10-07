@echo off
setlocal
cd /d "%~dp0"

py -3.10 --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Python 3.10 tidak ditemukan. TensorFlow GPU di Windows butuh Python 3.10.
    exit /b 1
)

if not exist ".venv-gpu\Scripts\python.exe" (
    echo [1/4] Membuat virtual environment .venv-gpu ...
    py -3.10 -m venv .venv-gpu || exit /b 1
)

echo [2/4] Memasang paket ...
".venv-gpu\Scripts\python.exe" -m pip install --upgrade pip || exit /b 1
".venv-gpu\Scripts\python.exe" -m pip install -r requirements-gpu.txt || exit /b 1

echo [3/4] Konversi model Keras 3 ke format TF 2.10 ...
".venv-gpu\Scripts\python.exe" convert_keras3_to_tf210.py || exit /b 1

if not exist "recommendation\models\recommendation_rf.joblib" (
    echo [3b] Melatih model rekomendasi Random Forest ...
    ".venv-gpu\Scripts\python.exe" -m recommendation.train || exit /b 1
)

echo [4/4] Cek GPU ...
".venv-gpu\Scripts\python.exe" -c "import gpu_env, tensorflow as tf; gpu_env.configure_tensorflow(tf)"

echo.
echo Selesai. Jalankan server dengan run_rnn.bat dan run_detect.bat
endlocal
