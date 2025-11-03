import pymysql
import time
from zk import ZK
from datetime import datetime

# ---------------- CONFIG ----------------
DEVICE_IP = '182.160.120.92'
PORT = 4370
DB_HOST = '127.0.0.1'
DB_USER = 'root'
DB_PASS = '123456'
DB_NAME = 'attendance_db'
TABLE_NAME = 'attendances'
POLL_INTERVAL = 1  # seconds
# ---------------------------------------

def connect_db():
    try:
        conn = pymysql.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASS,
            database=DB_NAME,
            charset='utf8mb4',
            cursorclass=pymysql.cursors.DictCursor,
            autocommit=True
        )
        return conn
    except Exception as e:
        print(f"❌ Database connection failed: {e}")
        return None

def insert_attendance(conn, record):
    try:
        cursor = conn.cursor()
        cursor.execute(f"""
            INSERT IGNORE INTO {TABLE_NAME}
            (user_id, timestamp, status, punch, message, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
        """, (
            record['user_id'],
            record['timestamp'],
            record.get('status', ''),
            record.get('punch', ''),
            record.get('message', ''),
            datetime.now(),
            datetime.now()
        ))
        print(f"✅ New punch inserted: User {record['user_id']} at {record['timestamp']}")
    except Exception as e:
        print(f"❌ Error inserting record: {e}")

def get_last_timestamp(conn):
    cursor = conn.cursor()
    cursor.execute(f"SELECT MAX(timestamp) AS last_ts FROM {TABLE_NAME}")
    row = cursor.fetchone()
    return row['last_ts'] if row and row['last_ts'] else None

def main():
    print("🔗 Connecting to device...")
    zk = ZK(DEVICE_IP, port=PORT, timeout=20)
    try:
        conn_dev = zk.connect()
        conn_dev.disable_device()
        print("✅ Connected to device.")
    except Exception as e:
        print(f"❌ Cannot connect to device: {e}")
        return

    conn_db = connect_db()
    if not conn_db:
        print("❌ Could not connect to MariaDB.")
        return

    last_ts_db = get_last_timestamp(conn_db)
    print(f"🕒 Last synced timestamp: {last_ts_db}")

    try:
        while True:
            try:
                attendance = conn_dev.get_attendance()
                if not attendance:
                    print("⏳ No new punch detected.")
                else:
                    for rec in attendance:
                        ts = rec.timestamp.strftime("%Y-%m-%d %H:%M:%S")
                        if last_ts_db and ts <= str(last_ts_db):
                            continue
                        record = {
                            'user_id': rec.user_id,
                            'timestamp': ts,
                            'status': getattr(rec, 'status', ''),
                            'punch': getattr(rec, 'punch', ''),
                            'message': getattr(rec, 'message', '')
                        }
                        insert_attendance(conn_db, record)
                        last_ts_db = ts
                time.sleep(POLL_INTERVAL)
            except KeyboardInterrupt:
                print("\n🛑 Stopped by user.")
                break
            except Exception as e:
                print(f"❌ Error fetching attendance: {e}")
                time.sleep(5)
    finally:
        conn_dev.enable_device()
        conn_dev.disconnect()
        conn_db.close()

if __name__ == "__main__":
    main()
