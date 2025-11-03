from flask import Flask, request, jsonify
import pymysql
from datetime import datetime

# ---------------- CONFIG ----------------
DB_HOST = "182.160.120.92"
DB_USER = "root"
DB_PASS = "123456"
DB_NAME = "attendance_db"
API_TOKEN = "securetoken123"  # Laravel must use this token
# ----------------------------------------

app = Flask(__name__)

def get_db():
    return pymysql.connect(
        host=DB_HOST,
        user=DB_USER,
        password=DB_PASS,
        database=DB_NAME,
        cursorclass=pymysql.cursors.DictCursor
    )

# -------- AUTH DECORATOR --------
def auth_required(f):
    def wrapper(*args, **kwargs):
        auth = request.headers.get("Authorization", "")
        if not auth.startswith("Bearer ") or auth.split(" ")[1] != API_TOKEN:
            return jsonify({
                "status": False,
                "message": "Unauthorized"
            }), 401
        return f(*args, **kwargs)
    wrapper.__name__ = f.__name__
    return wrapper

# -------- ENDPOINTS --------

@app.route("/api", methods=["GET"])
def api_root():
    return jsonify({
        "status": True,
        "message": "Attendance API ready",
        "time": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    })

@app.route("/api/attendances", methods=["GET"])
@auth_required
def list_attendances():
    try:
        conn = get_db()
        with conn.cursor() as cursor:
            cursor.execute("SELECT * FROM attendances ORDER BY id DESC")
            rows = cursor.fetchall()
        conn.close()
        return jsonify({
            "status": True,
            "message": "Data fetched successfully",
            "data": rows
        })
    except Exception as e:
        return jsonify({
            "status": False,
            "message": f"Database error: {e}",
            "data": []
        }), 500

@app.route("/api/attendances", methods=["POST"])
@auth_required
def add_attendance():
    try:
        payload = request.json
        conn = get_db()
        with conn.cursor() as cursor:
            sql = """
                INSERT INTO attendances (user_id, timestamp, status, punch, message, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, NOW(), NOW())
            """
            cursor.execute(sql, (
                payload.get("user_id"),
                payload.get("timestamp"),
                payload.get("status"),
                payload.get("punch"),
                payload.get("message")
            ))
        conn.commit()
        conn.close()
        return jsonify({
            "status": True,
            "message": "Attendance record added successfully"
        })
    except Exception as e:
        return jsonify({
            "status": False,
            "message": f"Insert failed: {e}"
        }), 500

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=8005, debug=True)
