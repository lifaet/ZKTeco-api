from flask import Flask, request, jsonify
from datetime import datetime
import pymysql
from config import DB_HOST, DB_USER, DB_PASS, DB_NAME, TABLE_NAME, API_TOKEN, API_PORT

app = Flask(__name__)

# ---------------- Database Connection ----------------
def get_db():
    try:
        return pymysql.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASS,
            database=DB_NAME,
            charset='utf8mb4',
            cursorclass=pymysql.cursors.DictCursor,
            autocommit=True
        )
    except Exception as e:
        print(f"❌ DB connect failed: {e}")
        return None

# ---------------- Auth Middleware ----------------
def auth_required(f):
    def wrapper(*args, **kwargs):
        auth = request.headers.get("Authorization", "")
        if not auth.startswith("Bearer ") or auth.split(" ")[1] != API_TOKEN:
            return jsonify({"status": False, "message": "Unauthorized"}), 401
        return f(*args, **kwargs)
    wrapper.__name__ = f.__name__
    return wrapper

# ---------------- Routes ----------------
@app.route("/api", methods=["GET"])
def api_root():
    return jsonify({
        "status": True,
        "message": "API ready",
        "time": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    })

@app.route("/api/attendances", methods=["GET"])
@auth_required
def get_attendances():
    try:
        conn = get_db()
        if not conn:
            return jsonify({"status": False, "message": "DB connection failed", "data": []}), 500

        with conn.cursor() as cursor:
            cursor.execute(f"SELECT * FROM {TABLE_NAME} ORDER BY id DESC")
            rows = cursor.fetchall()
        conn.close()

        return jsonify({"status": True, "message": "Data fetched", "data": rows})
    except Exception as e:
        return jsonify({"status": False, "message": f"Error: {e}", "data": []}), 500

@app.route("/api/attendances", methods=["POST"])
@auth_required
def add_attendance():
    try:
        payload = request.json or {}
        required = ['user_id', 'timestamp']
        for key in required:
            if key not in payload:
                return jsonify({"status": False, "message": f"Missing field: {key}"}), 400

        # Validate timestamp format (optional)
        try:
            datetime.strptime(payload['timestamp'], "%Y-%m-%d %H:%M:%S")
        except ValueError:
            return jsonify({"status": False, "message": "Invalid timestamp format"}), 400

        conn = get_db()
        if not conn:
            return jsonify({"status": False, "message": "DB connection failed"}), 500

        with conn.cursor() as cursor:
            cursor.execute(f"""
                INSERT IGNORE INTO {TABLE_NAME}
                (user_id, timestamp, status, punch, message, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
            """, (
                payload['user_id'],
                payload['timestamp'],
                payload.get('status', ''),
                payload.get('punch', ''),
                payload.get('message', ''),
                datetime.now(),
                datetime.now()
            ))
        conn.close()

        return jsonify({"status": True, "message": "Record inserted successfully"})
    except Exception as e:
        return jsonify({"status": False, "message": f"Insert failed: {e}"}), 500

# ---------------- Run Server ----------------
if __name__ == "__main__":
    app.run(host="0.0.0.0", port=API_PORT)
