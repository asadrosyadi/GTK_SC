@echo off
setlocal
cd /d "%~dp0"

if not exist ".venv-gpu\Scripts\python.exe" (
    echo [ERROR] Environment belum dibuat. Jalankan setup_gpu_env.bat terlebih dahulu.
    exit /b 1
)

if "%HISTORY_URL%"=="" set "HISTORY_URL=http://localhost/gemastik/backend/html/api/sensor?iot_id=jTZids5M"

".venv-gpu\Scripts\python.exe" rnn\run.py
endlocal
