import glob
import os
import sys


def _add_cuda_dll_dirs():
    if os.name != 'nt':
        return
    dirs = glob.glob(os.path.join(sys.prefix, 'Lib', 'site-packages', 'nvidia', '*', 'bin'))
    cuda_path = os.environ.get('CUDA_PATH')
    if cuda_path:
        dirs.append(os.path.join(cuda_path, 'bin'))
    dirs += glob.glob(r'C:\Program Files\NVIDIA GPU Computing Toolkit\CUDA\v11.*\bin')

    for d in dirs:
        if os.path.isdir(d):
            os.environ['PATH'] = d + os.pathsep + os.environ.get('PATH', '')
            try:
                os.add_dll_directory(d)
            except (OSError, AttributeError):
                pass


os.environ.setdefault('TF_CPP_MIN_LOG_LEVEL', '1')
_add_cuda_dll_dirs()


def configure_tensorflow(tf):
    gpus = tf.config.list_physical_devices('GPU')
    for gpu in gpus:
        try:
            tf.config.experimental.set_memory_growth(gpu, True)
        except RuntimeError:
            pass

    if gpus:
        names = [tf.config.experimental.get_device_details(g).get('device_name', g.name) for g in gpus]
        print(f"[gpu_env] TensorFlow {tf.__version__}: GPU aktif -> {', '.join(names)} (+ CPU)")
    else:
        print(f"[gpu_env] TensorFlow {tf.__version__}: GPU tidak terdeteksi, berjalan di CPU")
    return gpus


def keras_model_path(path):

    import tensorflow as tf
    if tf.__version__.startswith('2.') and int(tf.__version__.split('.')[1]) < 16:
        root, ext = os.path.splitext(path)
        converted = f"{root}_tf210{ext}"
        if os.path.exists(converted):
            return converted
    return path
