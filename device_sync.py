import time
from datetime import datetime
from zk import ZK
import pymysql
from config import DEVICE_IP, PORT, POLL_INTERVAL, DB_HOST, DB_USER, DB_PASS, DB_NAME, TABLE_NAME

def connect_db():
    """Connect to MariaDB with error handling."""
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
    """Insert one attendance record safely."""
    try:
        with conn.cursor() as cursor:
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
            print(f"✅ New punch: User {record['user_id']} at {record['timestamp']}")
    except Exception as e:
        print(f"❌ Error inserting record: {e}")

def get_last_timestamp(conn):
    """Get the last synced timestamp from the database."""
    try:
        with conn.cursor() as cursor:
            cursor.execute(f"SELECT MAX(timestamp) AS last_ts FROM {TABLE_NAME}")
            row = cursor.fetchone()
            return row['last_ts'] if row and row['last_ts'] else None
    except Exception as e:
        print(f"❌ Error fetching last timestamp: {e}")
        return None

def main():
    # Connect to the device
    print("🔗 Connecting to device...")
    zk = ZK(DEVICE_IP, port=PORT, timeout=20)
    try:
        conn_dev = zk.connect()
        conn_dev.disable_device()
        print("✅ Connected to device.")
    except Exception as e:
        print(f"❌ Cannot connect to device: {e}")
        return

    # Connect to database
    conn_db = connect_db()
    if not conn_db:
        print("❌ Could not connect to MariaDB. Exiting.")
        try:
            conn_dev.enable_device()
            conn_dev.disconnect()
        except:
            pass
        return

    # Get last synced timestamp
    last_ts = get_last_timestamp(conn_db)
    print(f"🕒 Last synced timestamp: {last_ts}")

    # Start polling
    try:
        while True:
            try:
                attendance = conn_dev.get_attendance()
                if not attendance:
                    time.sleep(POLL_INTERVAL)
                    continue

                for rec in attendance:
                    ts = rec.timestamp.strftime("%Y-%m-%d %H:%M:%S")
                    if last_ts and ts <= str(last_ts):
                        continue
                    record = {
                        'user_id': rec.user_id,
                        'timestamp': ts,
                        'status': getattr(rec, 'status', ''),
                        'punch': getattr(rec, 'punch', ''),
                        'message': getattr(rec, 'message', '')
                    }
                    insert_attendance(conn_db, record)
                    last_ts = ts

                time.sleep(POLL_INTERVAL)

            except KeyboardInterrupt:
                print("\n🛑 Stopped by user.")
                break
            except Exception as e:
                print(f"❌ Error fetching attendance: {e}")
                time.sleep(5)

    finally:
        try:
            conn_dev.enable_device()
            conn_dev.disconnect()
        except Exception as e:
            print(f"❌ Error disconnecting device: {e}")
        try:
            if conn_db:
                conn_db.close()
        except Exception as e:
            print(f"❌ Error closing DB: {e}")

if __name__ == "__main__":
    main()
