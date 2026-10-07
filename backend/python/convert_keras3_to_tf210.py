import inspect
import json
import os
import sys

import h5py
import numpy as np

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import gpu_env
import tensorflow as tf

HERE = os.path.dirname(os.path.abspath(__file__))
MODELS = [
    os.path.join(HERE, 'rnn', 'config', 'plant_health_lstm_model.h5'),
    os.path.join(HERE, 'detect_penyakit', 'static', 'src', 'efficientnetb0_model.h5'),
]

BASE_LAYER_KWARGS = {'name', 'trainable', 'dtype', 'dynamic', 'input_dim', 'input_shape',
                     'batch_input_shape', 'batch_size', 'weights', 'activity_regularizer',
                     'autocast'}


def convert_value(value):
    if isinstance(value, dict):
        if value.get('class_name') == 'DTypePolicy':
            return value['config']['name']
        if 'class_name' in value and 'config' in value and 'module' in value:
            return {'class_name': value['class_name'], 'config': convert_value(value['config'])}
        return {k: convert_value(v) for k, v in value.items()}
    if isinstance(value, list):
        return [convert_value(v) for v in value]
    return value


def convert_inbound_nodes(nodes):
    converted = []
    for node in nodes:
        history = []

        def walk(x):
            if isinstance(x, dict) and x.get('class_name') == '__keras_tensor__':
                history.append(x['config']['keras_history'])
            elif isinstance(x, (list, tuple)):
                for item in x:
                    walk(item)

        walk(node.get('args', []))
        kwargs = {k: v for k, v in node.get('kwargs', {}).items()
                  if v is not None
                  and not (isinstance(v, dict) and v.get('class_name') == '__keras_tensor__')}
        converted.append([[h[0], h[1], h[2], kwargs] for h in history])
    return converted


def convert_layer(layer):
    class_name = layer['class_name']
    cls = getattr(tf.keras.layers, class_name)
    config = convert_value(layer['config'])

    if class_name == 'InputLayer' and 'batch_shape' in config:
        config['batch_input_shape'] = config.pop('batch_shape')

    allowed = set(inspect.signature(cls.__init__).parameters) | BASE_LAYER_KWARGS
    dropped = sorted(k for k in config if k not in allowed)
    config = {k: v for k, v in config.items() if k in allowed}
    if dropped:
        print(f"    {config.get('name')}: abaikan {dropped}")

    out = {'class_name': class_name, 'config': config}
    if 'name' in layer:
        out['name'] = layer['name']
    if 'inbound_nodes' in layer:
        out['inbound_nodes'] = convert_inbound_nodes(layer['inbound_nodes'])
    return out


def as_tensor_list(refs):
    if refs and isinstance(refs[0], str):
        return [refs]
    return refs


def build_model(model_config):
    config = model_config['config']
    layers = [convert_layer(layer) for layer in config['layers']]

    if model_config['class_name'] == 'Sequential':
        return tf.keras.Sequential.from_config({'name': config.get('name'), 'layers': layers})

    return tf.keras.Model.from_config({
        'name': config.get('name'),
        'layers': layers,
        'input_layers': as_tensor_list(config['input_layers']),
        'output_layers': as_tensor_list(config['output_layers']),
    })


def load_weights(model, h5file):
    group = h5file['model_weights']
    for layer in model.layers:
        if layer.name not in group or not layer.weights:
            continue
        names = [n.decode() if isinstance(n, bytes) else n
                 for n in group[layer.name].attrs['weight_names']]
        arrays = [np.asarray(group[layer.name][n]) for n in names]
        if len(arrays) != len(layer.weights):
            raise ValueError(f"Jumlah bobot layer {layer.name} tidak cocok: "
                             f"{len(arrays)} di file vs {len(layer.weights)} di model")
        layer.set_weights(arrays)


def convert(path):
    out_path = os.path.splitext(path)[0] + '_tf210.h5'
    print(f"[convert] {os.path.relpath(path, HERE)}")
    with h5py.File(path, 'r') as f:
        model_config = json.loads(f.attrs['model_config'])
        model = build_model(model_config)
        load_weights(model, f)
    model.save(out_path, include_optimizer=False)
    print(f"    -> {os.path.relpath(out_path, HERE)}  input={model.input_shape} output={model.output_shape}")
    return out_path


if __name__ == '__main__':
    for model_path in (sys.argv[1:] or MODELS):
        convert(model_path)
