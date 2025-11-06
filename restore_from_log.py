#!/usr/bin/env python3
"""
Restore attendance records from `logs/device_sync.log` into the database.

This script looks for lines like:
  Inserted user 1 @ 2022-11-02 10:40:23

And inserts those records into the configured `TABLE_NAME`.

Usage:
  python3 restore_from_log.py [--log path_to_log]

Be careful: run on a test DB first if you're unsure. The script uses
INSERT IGNORE to avoid creating duplicates (requires a suitable unique
index on (user_id, timestamp) in your table).
"""
import re
import sys
import argparse
from datetime import datetime
import pymysql
from config import DB_HOST, DB_USER, DB_PASS, DB_NAME, TABLE_NAME


LINE_RE = re.compile(r"Inserted user\s+(?P<user>\S+)\s+@\s+(?P<ts>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})")


def parse_log(path):
    records = []
    with open(path, 'r', encoding='utf-8', errors='ignore') as f:
        for line in f:
            m = LINE_RE.search(line)
            if not m:
                continue
            user = m.group('user')
            ts = m.group('ts')
            try:
                ts_obj = datetime.strptime(ts, '%Y-%m-%d %H:%M:%S')
            except Exception:
                continue
            records.append((user, ts_obj))
    return records


def insert_records(records):
    if not records:
        print('No records to insert.')
        return

    conn = None
    try:
        conn = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASS,
                               database=DB_NAME, charset='utf8mb4',
                               cursorclass=pymysql.cursors.DictCursor,
                               autocommit=True)
        with conn.cursor() as cursor:
            sql = f"""
                INSERT IGNORE INTO {TABLE_NAME}
                (user_id, timestamp, status, punch, message, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
            """
            inserted = 0
            for user_id, ts in records:
                try:
                    cursor.execute(sql, (
                        user_id,
                        ts,
                        '',  # status unknown from log
                        '',  # punch
                        '',  # message
                        datetime.now(),
                        datetime.now()
                    ))
                    if cursor.rowcount > 0:
                        inserted += 1
                except Exception as e:
                    print(f'Error inserting {user_id} @ {ts}: {e}')
            print(f'Inserted {inserted} new records (out of {len(records)})')

    except Exception as e:
        print('DB connection failed:', e)
    finally:
        if conn:
            try:
                conn.close()
            except Exception:
                pass


def main():
    p = argparse.ArgumentParser(description='Restore attendance from device_sync log')
    p.add_argument('--log', default='logs/device_sync.log', help='path to log file')
    args = p.parse_args()

    print('Parsing log:', args.log)
    recs = parse_log(args.log)
    print(f'Found {len(recs)} attendance lines in log')

    if not recs:
        return

    confirm = input('Proceed to insert into DB? (y/N): ').strip().lower()
    if confirm != 'y':
        print('Aborted by user')
        return

    insert_records(recs)


if __name__ == '__main__':
    main()
