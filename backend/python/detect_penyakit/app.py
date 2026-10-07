from flask import Flask, render_template, Response, jsonify, abort, request
import numpy as np
import os
import sys

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.dirname(BASE_DIR))
import gpu_env

import tensorflow as tf
from tensorflow.keras.layers import DepthwiseConv2D
import cv2
import uuid
import pymysql.cursors
from datetime import datetime
from tensorflow.keras.models import load_model
from tensorflow.keras.applications.efficientnet import preprocess_input
import threading
import time
import re

gpu_env.configure_tensorflow(tf)

app = Flask(__name__)
app.config['MAX_CONTENT_LENGTH'] = int(os.environ.get('MAX_UPLOAD_BYTES', 4 * 1024 * 1024))

CAMERAS = os.environ.get('CAMERAS', '')
DETECT_HOST = os.environ.get('DETECT_HOST', '0.0.0.0')
FULL_FRAME_FALLBACK = os.environ.get('FULL_FRAME_FALLBACK', '1') == '1'
IOT_ID = os.environ.get('IOT_ID', 'jTZids5M')
DETECT_HOURS = os.environ.get('DETECT_HOURS', '6-23')
SAVE_INTERVAL = float(os.environ.get('SAVE_INTERVAL', 30))
MAX_ROWS = int(os.environ.get('MAX_ROWS', 20000))

app.config['MYSQL_HOST'] = os.environ.get('MYSQL_HOST', 'localhost')
app.config['MYSQL_USER'] = os.environ.get('MYSQL_USER', 'root')
app.config['MYSQL_PASSWORD'] = os.environ.get('MYSQL_PASSWORD', '')
app.config['MYSQL_DB'] = os.environ.get('MYSQL_DB', 'padi_sawah')

SAVE_DIR = os.environ.get(
    'HAMA_PADI_DIR',
    os.path.normpath(os.path.join(BASE_DIR, '..', '..', 'html', 'img', 'hama_padi'))
)
os.makedirs(SAVE_DIR, exist_ok=True)

leafCascade = cv2.CascadeClassifier(os.path.join(BASE_DIR, "static", "src", "cascade3.xml"))

class CustomDepthwiseConv2D(DepthwiseConv2D):
    def __init__(self, *args, **kwargs):
        kwargs.pop('groups', None)
        super().__init__(*args, **kwargs)

model = load_model(
    gpu_env.keras_model_path(os.path.join(BASE_DIR, 'static', 'src', 'efficientnetb0_model.h5')),
    custom_objects={'DepthwiseConv2D': CustomDepthwiseConv2D}
)
model_lock = threading.Lock()
db_lock = threading.Lock()

label = [
    'Hawar daun bakteri',
    'Garis daun bakteri',
    'Hawar malai bakteri',
    'Penyakit blas (jamur)',
    'Bercak coklat',
    'Pucuk mati',
    'Embun bulu (jamur bulu halus)',
    'Hama penggerek daun (Hispa)',
    'Normal / Sehat',
    'Penyakit tungro (virus)'
]


def parse_cameras(spec):
    cameras = {}
    for item in [s.strip() for s in spec.split(',') if s.strip()]:
        source, _, titik = item.rpartition(':')
        if not source or not titik.isdigit():
            source, titik = item, str(len(cameras) + 1)
        cameras[int(titik)] = int(source) if source.isdigit() else source
    return cameras


class Camera:
    def __init__(self, titik, source):
        self.titik = titik
        self.source = source
        self.lock = threading.Lock()
        self.latest_frame = None
        self.last_saved = 0.0
        self.last_detection = None
        self.connected = False


cameras = {titik: Camera(titik, source) for titik, source in parse_cameras(CAMERAS).items()}


def detection_active():
    start, _, end = DETECT_HOURS.partition('-')
    return int(start) <= datetime.now().hour < int(end or 24)


UUID_IMAGE = re.compile(r'[0-9a-f]{32}\.jpg')


def save_detection(titik, disease, prob, image, iot_id=None, dummy=False):
    filename = f"{uuid.uuid4().hex}.jpg"
    connection = None
    saved = False
    with db_lock:
        try:
            cv2.imwrite(os.path.join(SAVE_DIR, filename), image)
            connection = pymysql.connect(
                host=app.config['MYSQL_HOST'],
                user=app.config['MYSQL_USER'],
                password=app.config['MYSQL_PASSWORD'],
                database=app.config['MYSQL_DB'],
                cursorclass=pymysql.cursors.DictCursor
            )
            with connection.cursor() as cursor:
                now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
                values = [iot_id or IOT_ID, titik, disease, float(prob), filename, now]
                columns = 'iot_id, titik, penyakit, probabilitas, image, created_at'
                if dummy:
                    columns += ', is_dummy'
                    values.append(1)
                sql = f"INSERT INTO sensor_kameras ({columns}) VALUES ({', '.join(['%s'] * len(values))})"
                cursor.execute(sql, values)
                connection.commit()
                saved = True

                cursor.execute("SELECT COUNT(*) AS total FROM sensor_kameras")
                excess = cursor.fetchone()['total'] - MAX_ROWS
                if excess > 0:
                    cursor.execute("SELECT id, image FROM sensor_kameras ORDER BY id LIMIT %s", (excess,))
                    old_rows = cursor.fetchall()
                    cursor.execute("DELETE FROM sensor_kameras WHERE id <= %s", (old_rows[-1]['id'],))
                    connection.commit()
                    for row in old_rows:
                        fname = row.get('image') or ''
                        if UUID_IMAGE.fullmatch(fname):
                            fpath = os.path.join(SAVE_DIR, fname)
                            try:
                                if os.path.isfile(fpath):
                                    os.unlink(fpath)
                            except Exception as e:
                                print(f"Gagal menghapus {fpath}: {e}")
        except Exception as e:
            print(f"[titik {titik}] Error menyimpan deteksi: {e}")
            if not saved:
                try:
                    os.unlink(os.path.join(SAVE_DIR, filename))
                except OSError:
                    pass
            if connection is not None:
                try:
                    connection.rollback()
                except Exception:
                    pass
        finally:
            if connection is not None:
                try:
                    connection.close()
                except Exception:
                    pass
    return saved


def predict_bgr(image_bgr):
    rgb = cv2.cvtColor(cv2.resize(image_bgr, (224, 224)), cv2.COLOR_BGR2RGB)
    z = preprocess_input(np.expand_dims(tf.keras.utils.img_to_array(rgb), axis=0))
    with model_lock:
        classes = model.predict(z, verbose=0)
    return label[int(np.argmax(classes))], float(np.max(classes) * 100)


def classify_frame(frame, titik):
    gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
    leaf = leafCascade.detectMultiScale(
        gray,
        scaleFactor=1.05,
        minNeighbors=7,
        minSize=(150, 150),
        maxSize=(800, 800)
    )

    best = None
    for (x, y, w, h) in leaf:
        try:
            load = frame[y:y+h, x:x+w]
            resized_load = cv2.resize(load, (224, 224))
            disease, prob = predict_bgr(load)

            score = (disease != 'Normal / Sehat', prob)
            if best is None or score > best[0]:
                best = (score, disease, prob, resized_load)

            cv2.putText(frame, f"{disease} ({prob:.2f}%)", (x, y-10),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.9, (0, 0, 255), 2)
            cv2.rectangle(frame, (x, y), (x+w, y+h), (0, 0, 255), 2)
        except Exception as e:
            print(f"[titik {titik}] Error: {e}")
    return len(leaf), best


def process_frame(cam, frame):
    if not detection_active():
        return frame
    _, best = classify_frame(frame, cam.titik)

    if best is not None and time.time() - cam.last_saved >= SAVE_INTERVAL:
        _, disease, prob, image = best
        cam.last_saved = time.time()
        cam.last_detection = {'penyakit': disease, 'probabilitas': round(prob, 2),
                              'waktu': datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
        save_detection(cam.titik, disease, prob, image)

    cv2.putText(frame, f"Petak {cam.titik}", (10, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.9, (0, 255, 0), 2)
    return frame

def camera_thread(cam):
    capture = None
    failures = 0
    while True:
        if capture is None or not capture.isOpened():
            capture = cv2.VideoCapture(cam.source)
            cam.connected = capture.isOpened()
            if not cam.connected:
                print(f"[titik {cam.titik}] kamera {cam.source!r} tidak bisa dibuka, coba lagi 5 detik")
                time.sleep(5)
                continue
            print(f"[titik {cam.titik}] kamera {cam.source!r} tersambung")

        success, frame = capture.read()
        if not success:
            failures += 1
            if failures >= 30:
                cam.connected = False
                capture.release()
                capture = None
                failures = 0
            time.sleep(0.1)
            continue
        failures = 0

        try:
            processed_frame = process_frame(cam, frame)
        except Exception as e:
            print(f"[titik {cam.titik}] Error memproses frame: {e}")
            processed_frame = frame
        with cam.lock:
            cam.latest_frame = processed_frame.copy()
        time.sleep(0.1)

def gen_frames(cam):
    while True:
        with cam.lock:
            frame_copy = None if cam.latest_frame is None else cam.latest_frame.copy()

        if frame_copy is None:
            time.sleep(0.1)
            continue

        ret, buffer = cv2.imencode('.jpg', frame_copy)
        if not ret:
            time.sleep(0.1)
            continue
        frame = buffer.tobytes()

        yield (b'--frame\r\n'
               b'Content-Type: image/jpeg\r\n\r\n' + frame + b'\r\n')
        time.sleep(0.1)

@app.route('/video_feed')
def video_feed():
    if not cameras:
        abort(404)
    return video_feed_titik(min(cameras))

@app.route('/video_feed/<int:titik>')
def video_feed_titik(titik):
    if titik not in cameras:
        abort(404)
    return Response(gen_frames(cameras[titik]),
                    mimetype='multipart/x-mixed-replace; boundary=frame')

@app.route('/detect', methods=['POST'])
def detect_upload():
    titik = request.args.get('titik', type=int)
    iot_id = request.args.get('iot_id') or IOT_ID
    dummy = request.args.get('dummy') == '1'
    data = request.get_data(cache=False)
    if not titik or titik < 1 or not data:
        return jsonify({'error': 'parameter titik dan body JPEG wajib'}), 400
    frame = cv2.imdecode(np.frombuffer(data, np.uint8), cv2.IMREAD_COLOR)
    if frame is None:
        return jsonify({'error': 'body bukan gambar yang valid'}), 400

    cam = cameras.setdefault(titik, Camera(titik, 'unggahan ESP32'))
    cam.connected = True
    result = {'titik': titik, 'iot_id': iot_id, 'detected': False, 'saved': False,
              'jumlah_daun': 0, 'penyakit': None, 'probabilitas': None, 'mode': None}

    if not detection_active():
        result['reason'] = f'di luar jam deteksi ({DETECT_HOURS})'
    else:
        result['jumlah_daun'], best = classify_frame(frame, titik)
        result['mode'] = 'roi_daun'
        if best is None and FULL_FRAME_FALLBACK:
            disease, prob = predict_bgr(frame)
            h, w = frame.shape[:2]
            scale = min(1.0, 640.0 / max(h, w))
            preview = cv2.resize(frame, (int(w * scale), int(h * scale))) if scale < 1 else frame.copy()
            best = (None, disease, prob, preview)
            result['mode'] = 'gambar_penuh'
        if best is None:
            result['reason'] = 'tidak ada daun terdeteksi pada gambar'
        else:
            _, disease, prob, crop = best
            result.update({'detected': True, 'penyakit': disease, 'probabilitas': round(prob, 2)})
            result['saved'] = save_detection(titik, disease, prob, crop, iot_id, dummy)
            cam.last_saved = time.time()
            cam.last_detection = {'penyakit': disease, 'probabilitas': round(prob, 2),
                                  'waktu': datetime.now().strftime('%Y-%m-%d %H:%M:%S')}

    cv2.putText(frame, f"Petak {titik}", (10, 30), cv2.FONT_HERSHEY_SIMPLEX, 0.9, (0, 255, 0), 2)
    with cam.lock:
        cam.latest_frame = frame
    print(f"[titik {titik}] unggahan {len(data)} B: {result}")
    return jsonify(result), (201 if result['saved'] else 200)


@app.route('/status')
def status():
    return jsonify({
        'iot_id': IOT_ID,
        'detection_active': detection_active(),
        'cameras': {str(t): {'source': str(c.source), 'connected': c.connected,
                             'last_detection': c.last_detection} for t, c in cameras.items()},
    })

@app.route('/')
def index():
    return render_template('detect.html', titik_list=sorted(cameras))

if __name__ == '__main__':
    for cam in cameras.values():
        threading.Thread(target=camera_thread, args=(cam,), daemon=True).start()
    if cameras:
        print("[detect] kamera stream: " + ', '.join(f"petak {t} <- {c.source!r}" for t, c in sorted(cameras.items())))
    print("[detect] menerima unggahan ESP32 di POST /detect?titik=N")
    app.run(host=DETECT_HOST, port=int(os.environ.get('DETECT_PORT', 7000)), debug=False,
            use_reloader=False, threaded=True)
