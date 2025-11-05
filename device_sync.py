import time
import socket
import sys
from datetime import datetime, timedelta
from zk import ZK
import pymysql
from pymysql.err import OperationalError, Error as PyMySQLError
import logging
from logging.handlers import RotatingFileHandler
from config import (
    DEVICE_IP, PORT, POLL_INTERVAL, DB_HOST, 
    DB_USER, DB_PASS, DB_NAME, TABLE_NAME
)

# Configure logging
def setup_logging():
    logger = logging.getLogger('device_sync')
    logger.setLevel(logging.INFO)
    
    # Create logs directory if it doesn't exist
    import os
    if not os.path.exists('logs'):
        os.makedirs('logs')
    
    # File handler with rotation
    file_handler = RotatingFileHandler(
        'logs/device_sync.log',
        maxBytes=5*1024*1024,  # 5MB
        backupCount=5
    )
    file_handler.setLevel(logging.INFO)
    
    # Console handler
    console_handler = logging.StreamHandler()
    console_handler.setLevel(logging.INFO)
    
    # Format for logs
    formatter = logging.Formatter(
        '%(asctime)s - %(levelname)s - %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )
    file_handler.setFormatter(formatter)
    console_handler.setFormatter(formatter)
    
    logger.addHandler(file_handler)
    logger.addHandler(console_handler)
    
    return logger

# Database connection with retry mechanism
def connect_db(max_retries=5, retry_delay=5):
    """Connect to MariaDB with retry mechanism."""
    logger = logging.getLogger('device_sync')
    retry_count = 0
    
    while retry_count < max_retries:
        try:
            conn = pymysql.connect(
                host=DB_HOST,
                user=DB_USER,
                password=DB_PASS,
                database=DB_NAME,
                charset='utf8mb4',
                cursorclass=pymysql.cursors.DictCursor,
                autocommit=True,
                connect_timeout=10
            )
            logger.info("✅ Successfully connected to database")
            return conn
        except OperationalError as e:
            retry_count += 1
            logger.warning(f"❌ Database connection attempt {retry_count} failed: {e}")
            if retry_count < max_retries:
                logger.info(f"⏳ Retrying in {retry_delay} seconds...")
                time.sleep(retry_delay)
            else:
                logger.error("❌ Maximum database connection retries reached")
                return None
        except Exception as e:
            logger.error(f"❌ Unexpected database error: {e}")
            return None

def connect_device(max_retries=5, retry_delay=5):
    """Connect to ZKTeco device with retry mechanism."""
    logger = logging.getLogger('device_sync')
    retry_count = 0
    
    while retry_count < max_retries:
        try:
            zk = ZK(DEVICE_IP, port=PORT, timeout=20)
            conn = zk.connect()
            conn.disable_device()
            logger.info("✅ Successfully connected to device")
            return conn
        except socket.error as e:
            retry_count += 1
            logger.warning(f"❌ Device connection attempt {retry_count} failed: Network error - {e}")
            if retry_count < max_retries:
                logger.info(f"⏳ Retrying in {retry_delay} seconds...")
                time.sleep(retry_delay)
            else:
                logger.error("❌ Maximum device connection retries reached")
                return None
        except Exception as e:
            logger.error(f"❌ Unexpected device error: {e}")
            return None

def insert_attendance(conn, record):
    """Insert one attendance record safely."""
    logger = logging.getLogger('device_sync')
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
            logger.info(f"✅ New punch: User {record['user_id']} at {record['timestamp']}")
            return True
    except PyMySQLError as e:
        logger.error(f"❌ Database error while inserting record: {e}")
        return False
    except Exception as e:
        logger.error(f"❌ Unexpected error while inserting record: {e}")
        return False

def get_last_timestamp(conn):
    """Get the last synced timestamp from the database."""
    logger = logging.getLogger('device_sync')
    try:
        with conn.cursor() as cursor:
            cursor.execute(f"SELECT MAX(timestamp) AS last_ts FROM {TABLE_NAME}")
            row = cursor.fetchone()
            return row['last_ts'] if row and row['last_ts'] else None
    except Exception as e:
        logger.error(f"❌ Error fetching last timestamp: {e}")
        return None

def check_connection(conn):
    """Check if database connection is still alive."""
    try:
        conn.ping(reconnect=True)
        return True
    except:
        return False

def main():
    logger = setup_logging()
    reconnect_delay = 5  # seconds
    
    while True:
        try:
            # Connect to device
            logger.info("🔗 Connecting to device...")
            conn_dev = connect_device()
            if not conn_dev:
                logger.error("❌ Could not connect to device. Retrying in 30 seconds...")
                time.sleep(30)
                continue

            # Connect to database
            conn_db = connect_db()
            if not conn_db:
                logger.error("❌ Could not connect to MariaDB. Retrying in 30 seconds...")
                try:
                    conn_dev.enable_device()
                    conn_dev.disconnect()
                except:
                    pass
                time.sleep(30)
                continue

            # Get last synced timestamp
            last_ts = get_last_timestamp(conn_db)
            logger.info(f"🕒 Last synced timestamp: {last_ts}")

            # Main polling loop
            while True:
                try:
                    # Check database connection
                    if not check_connection(conn_db):
                        logger.warning("⚠️ Database connection lost. Reconnecting...")
                        raise ConnectionError("Database connection lost")

                    # Get attendance data
                    attendance = conn_dev.get_attendance()
                    if not attendance:
                        logger.debug("⏳ No new attendance records")
                        time.sleep(POLL_INTERVAL)
                        continue

                    for rec in attendance:
                        try:
                            ts = rec.timestamp
                            # Validate timestamp
                            if not isinstance(ts, datetime):
                                logger.warning(f"⚠️ Invalid timestamp format for user {rec.user_id}")
                                continue
                            
                            ts_str = ts.strftime("%Y-%m-%d %H:%M:%S")
                            if last_ts and ts <= last_ts:
                                continue

                            record = {
                                'user_id': rec.user_id,
                                'timestamp': ts_str,
                                'status': getattr(rec, 'status', ''),
                                'punch': getattr(rec, 'punch', ''),
                                'message': getattr(rec, 'message', '')
                            }

                            if insert_attendance(conn_db, record):
                                last_ts = ts

                        except AttributeError as e:
                            logger.error(f"❌ Invalid record format: {e}")
                            continue

                    time.sleep(POLL_INTERVAL)

                except (socket.error, ConnectionError) as e:
                    logger.error(f"❌ Connection error: {e}")
                    raise  # Re-raise to trigger reconnection

                except Exception as e:
                    logger.error(f"❌ Unexpected error in polling loop: {e}")
                    raise  # Re-raise to trigger reconnection

        except Exception as e:
            logger.error(f"❌ Critical error: {e}")
            # Clean up connections
            try:
                conn_db.close()
            except:
                pass
            try:
                conn_dev.enable_device()
                conn_dev.disconnect()
            except:
                pass
            
            logger.info(f"⏳ Restarting in {reconnect_delay} seconds...")
            time.sleep(reconnect_delay)
            # Increase reconnect delay (max 5 minutes)
            reconnect_delay = min(reconnect_delay * 2, 300)
            continue

if __name__ == "__main__":
    main()