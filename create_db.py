import pymysql
from config import DB_HOST, DB_USER, DB_PASS, DB_NAME, TABLE_NAME

def create_database_and_table():
    conn = None
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
        cursor.execute(f"CREATE DATABASE IF NOT EXISTS {DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")
        cursor.execute(f"USE {DB_NAME};")
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
        print(f"✅ Database and table created or already exist.")
    except Exception as e:
        print(f"❌ Error creating database or table: {e}")
    finally:
        if conn:
            conn.close()

if __name__ == "__main__":
    create_database_and_table()
