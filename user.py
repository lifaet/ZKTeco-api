from zk import ZK
from config import DEVICE_IP, PORT

zk = ZK(DEVICE_IP, port=PORT, timeout=10)

try:
    conn = zk.connect()

    device_time = conn.get_time()

    print("Device time:", device_time)
    print("ISO:", device_time.isoformat())

    conn.disconnect()

except Exception as e:
    print("Error:", e)