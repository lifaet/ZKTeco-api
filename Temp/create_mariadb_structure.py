import pymysql

# ---------------- CONFIG ----------------
DB_HOST = '127.0.0.1'
DB_USER = 'root'
DB_PASS = '123456'
DB_NAME = 'attendance_db'
TABLE_NAME = 'attendances'
# ---------------------------------------

def create_database_and_table():
    try:
        conn = pymysql.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASS,
            charset='utf8mb4',
            cursorclass=pymysql.cursors.DictCursor,
            autocommit=True
        )
        cursor = conn.cursor()

        # Create database
        cursor.execute(f"CREATE DATABASE IF NOT EXISTS {DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")
        print(f"✅ Database '{DB_NAME}' created or already exists.")

        # Use the database
        cursor.execute(f"USE {DB_NAME};")

        # Create table
        cursor.execute(f"""
            CREATE TABLE IF NOT EXISTS {TABLE_NAME} (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(50),
                timestamp DATETIME,
                status VARCHAR(50),
                punch VARCHAR(50),
                message VARCHAR(255),
                created_at DATETIME,
                updated_at DATETIME,
                UNIQUE KEY uq_user_time (user_id, timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        """)
        print(f"✅ Table '{TABLE_NAME}' created or already exists.")

    except Exception as e:
        print(f"❌ Error creating database or table: {e}")
    finally:
        conn.close()

if __name__ == "__main__":
    create_database_and_table()
