@echo off
setlocal
cd /d "%~dp0"

if not exist ".venv-gpu\Scripts\python.exe" (
    echo [ERROR] Environment belum dibuat. Jalankan setup_gpu_env.bat terlebih dahulu.
    exit /b 1
)

echo Kamera stream: %CAMERAS% (kosong = hanya unggahan ESP32)

cd detect_penyakit
"..\.venv-gpu\Scripts\python.exe" app.py
endlocal
