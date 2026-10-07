MICROCLIMATE_TARGETS = ["temperature", "humidity", "ph", "light_intensity", "vpd"]

SENSOR_FEATURES = MICROCLIMATE_TARGETS + ["hour_sin", "hour_cos", "temp_humidity"]

PEST_CLASSES = [
    "bacterial_leaf_blight", "bacterial_leaf_streak", "bacterial_panicle_blight",
    "blast", "brown_spot", "dead_heart", "downy_mildew", "hispa", "normal", "tungro",
]
HEALTH_CLASSES = ["Healthy", "Moderate Stress", "High Stress"]

SEQUENCE_LENGTH = 6
FUSION_DIM = 128
DEFAULT_W1 = 0.6
DEFAULT_W2 = 0.4
TAU_PEST = 0.5
TAU_HEALTH = 0.5
