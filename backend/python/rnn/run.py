import os
import sys
import threading
import time

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.dirname(BASE_DIR))
import gpu_env

os.chdir(BASE_DIR)
from app import create_app

app = create_app()

def periodic_task():
    while True:
        print("Task dijalankan setiap 5 menit.")
        time.sleep(300)

threading.Thread(target=periodic_task, daemon=True).start()

if __name__ == '__main__':
    app.socketio.run(app.flask_app, debug=False, host=os.environ.get('RNN_HOST', '0.0.0.0'),
                     port=int(os.environ.get('RNN_PORT', 5000)), allow_unsafe_werkzeug=True)
