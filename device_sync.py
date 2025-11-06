import time
import socket
import sys
import os
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


LOG_LEVEL = 'INFO'


def setup_logging():
    logger = logging.getLogger('device_sync')
    # Map configured LOG_LEVEL string to logging level
    level = getattr(logging, LOG_LEVEL.upper(), logging.INFO)
    logger.setLevel(level)

    if not os.path.exists('logs'):
        os.makedirs('logs')

    file_handler = RotatingFileHandler('logs/device_sync.log', maxBytes=5*1024*1024, backupCount=7)
    file_handler.setLevel(level)

    console_handler = logging.StreamHandler()
    # Keep console less verbose: show INFO+ by default
    console_handler.setLevel(level)

    formatter = logging.Formatter('%(asctime)s - %(levelname)s - %(message)s', datefmt='%Y-%m-%d %H:%M:%S')
    file_handler.setFormatter(formatter)
    console_handler.setFormatter(formatter)

    # Avoid adding duplicate handlers if setup_logging called multiple times
    if not logger.handlers:
        logger.addHandler(file_handler)
        logger.addHandler(console_handler)

    return logger


def test_device_connection(logger):
    """Try combinations to find working device parameters."""
    logger.info(f"🔍 Testing connection to device {DEVICE_IP}:{PORT}")
    for force_udp in (False, True):
        for ommit_ping in (False, True):
            try:
                logger.info(f"Attempt with force_udp={force_udp}, ommit_ping={ommit_ping}")
                zk = ZK(DEVICE_IP, port=PORT, timeout=5, force_udp=force_udp, ommit_ping=ommit_ping)
                conn = zk.connect()
                if conn:
                    try:
                        conn.disable_device()
                    except Exception:
                        pass
                    attendance = conn.get_attendance()
                    count = len(attendance) if attendance else 0
                    logger.info(f"Connected ok (force_udp={force_udp}, ommit_ping={ommit_ping}), attendance_count={count}")
                    conn.disconnect()
                    return {'force_udp': force_udp, 'ommit_ping': ommit_ping}
            except Exception as e:
                logger.debug(f"Attempt failed (force_udp={force_udp}, ommit_ping={ommit_ping}): {e}")
                continue
    return None


def connect_db(logger, max_retries=5, retry_delay=5):
    retry = 0
    while retry < max_retries:
        try:
            conn = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME,
                                   charset='utf8mb4', cursorclass=pymysql.cursors.DictCursor, autocommit=True,
                                   connect_timeout=10)
            logger.info("✅ DB connected")
            return conn
        except Exception as e:
            retry += 1
            logger.warning(f"DB connect attempt {retry} failed: {e}")
            time.sleep(retry_delay)
    logger.error("DB connect failed after retries")
    return None


def connect_device_with_params(logger, params, max_retries=5, retry_delay=5):
    retry = 0
    while retry < max_retries:
        try:
            zk = ZK(DEVICE_IP, port=PORT, timeout=20, force_udp=params.get('force_udp', False), ommit_ping=params.get('ommit_ping', False))
            conn = zk.connect()
            conn.disable_device()
            logger.info("✅ Device connected and disabled")
            return conn
        except Exception as e:
            retry += 1
            logger.warning(f"Device connect attempt {retry} failed: {e}")
            time.sleep(retry_delay)
    logger.error("Device connect failed after retries")
    return None


def get_last_timestamp(conn):
    try:
        with conn.cursor() as cursor:
            cursor.execute(f"SELECT MAX(timestamp) AS last_ts FROM {TABLE_NAME}")
            row = cursor.fetchone()
            return row['last_ts'] if row and row['last_ts'] else None
    except Exception as e:
        logging.getLogger('device_sync').error(f"Error getting last timestamp: {e}")
        return None


def insert_attendance(conn, rec):
    logger = logging.getLogger('device_sync')
    try:
        with conn.cursor() as cursor:
            cursor.execute(f"""
                INSERT IGNORE INTO {TABLE_NAME}
                (user_id, timestamp, status, punch, message, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
            """, (
                rec.user_id,
                rec.timestamp,
                getattr(rec, 'status', ''),
                getattr(rec, 'punch', ''),
                getattr(rec, 'message', ''),
                datetime.now(),
                datetime.now()
            ))
            if cursor.rowcount > 0:
                logger.info(f"Inserted user {rec.user_id} @ {rec.timestamp}")
            else:
                logger.debug(f"Duplicate/ignored user {rec.user_id} @ {rec.timestamp}")
            return True
    except Exception as e:
        logger.error(f"DB insert error: {e}")
        return False


def check_db_alive(conn):
    try:
        conn.ping(reconnect=True)
        return True
    except:
        return False


def main():
    logger = setup_logging()
    logger.info("🚀 Starting device sync (production)")

    # Detect working device params
    params = test_device_connection(logger)
    if not params:
        logger.error("Could not determine working device parameters. Exiting.")
        return
    logger.info(f"Using device params: {params}")

    # Connect DB once
    conn_db = connect_db(logger)
    if not conn_db:
        logger.error("Cannot connect to DB, exiting")
        return

    last_ts = get_last_timestamp(conn_db)
    logger.info(f"Last synced ts: {last_ts}")

    device_backoff = 5
    try:
        while True:
            conn_dev = connect_device_with_params(logger, params)
            if not conn_dev:
                logger.error("Failed to connect device, backing off")
                time.sleep(device_backoff)
                device_backoff = min(device_backoff * 2, 300)
                continue

            device_backoff = 5
            try:
                logger.info("Beginning polling loop")
                while True:
                    if not check_db_alive(conn_db):
                        logger.warning("DB connection lost, reconnecting...")
                        conn_db.close()
                        conn_db = connect_db(logger)
                        if not conn_db:
                            raise Exception("DB reconnect failed")

                    attendance = conn_dev.get_attendance()
                    if not attendance:
                        logger.debug("No new records")
                        time.sleep(POLL_INTERVAL)
                        continue

                    logger.info(f"Found {len(attendance)} records")
                    for rec in attendance:
                        try:
                            if not isinstance(rec.timestamp, datetime):
                                logger.warning(f"Bad ts for user {getattr(rec,'user_id',None)}")
                                continue
                            if last_ts and rec.timestamp <= last_ts:
                                continue
                            success = insert_attendance(conn_db, rec)
                            if success:
                                last_ts = rec.timestamp
                        except Exception as e:
                            logger.error(f"Record processing error: {e}")

                    # periodic cleanup of device buffer to avoid duplicates/memory

                    time.sleep(POLL_INTERVAL)

            except KeyboardInterrupt:
                logger.info("Stopping by user")
                break
            except Exception as e:
                logger.error(f"Polling loop error: {e}")
            finally:
                try:
                    conn_dev.enable_device()
                except Exception:
                    pass
                try:
                    conn_dev.disconnect()
                except Exception:
                    pass

    finally:
        try:
            conn_db.close()
        except Exception:
            pass


if __name__ == "__main__":
    main()