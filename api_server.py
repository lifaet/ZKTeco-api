from flask import Flask, jsonify, request
import pymysql
from datetime import datetime
import sys
from config import (
    DB_HOST, DB_USER, DB_PASS, DB_NAME, 
    TABLE_NAME, API_PORT, API_TOKEN
)

app = Flask(__name__)

def require_token(f):
    def decorated(*args, **kwargs):
        token = request.headers.get('Authorization')
        if not token or token != f"Bearer {API_TOKEN}":
            return jsonify({"status": False, "message": "Invalid or missing token"}), 401
        return f(*args, **kwargs)
    return decorated

def format_attendance(rows):
    # Group by user_id and date
    attendance_by_user = {}
    for row in rows:
        user_id = row['user_id']
        timestamp = datetime.strptime(row['timestamp'], '%Y-%m-%d %H:%M:%S')
        date = timestamp.strftime('%Y-%m-%d')
        
        if (user_id, date) not in attendance_by_user:
            # store count to detect single-punch days
            attendance_by_user[(user_id, date)] = {
                'user_id': user_id,
                'date': date,
                'in': timestamp.strftime('%H:%M:%S'),
                'out': timestamp.strftime('%H:%M:%S'),
                'status': row.get('status', ''),
                'punch': row.get('punch', ''),
                'message': row.get('message', ''),
                'count': 1
            }
        else:
            current_time = timestamp.strftime('%H:%M:%S')
            # update in/out
            if current_time < attendance_by_user[(user_id, date)]['in']:
                attendance_by_user[(user_id, date)]['in'] = current_time
            if current_time > attendance_by_user[(user_id, date)]['out']:
                attendance_by_user[(user_id, date)]['out'] = current_time
            attendance_by_user[(user_id, date)]['count'] += 1
    
    # Sort results (newest dates first, then user_id)
    results = sorted(list(attendance_by_user.values()),
                     key=lambda x: (x['date'], x['user_id']),
                     reverse=True)

    # Return list of ordered objects (user_id, date, in, out, status, message)
    ordered_results = []
    for r in results:
        out_val = r.get('out', '')
        # if only one punch on that date, treat it as IN only
        if r.get('count', 0) <= 1:
            out_val = ''

        ordered_results.append({
            'user_id': r.get('user_id', ''),
            'date': r.get('date', ''),
            'in': r.get('in', ''),
            'out': out_val,
            'status': r.get('status', ''),
            'punch': r.get('punch', ''),
            'message': r.get('message', '')
        })

    return ordered_results


def fetch_all_attendances():
    conn = None
    try:
        conn = pymysql.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASS,
            database=DB_NAME,
            charset='utf8mb4',
            cursorclass=pymysql.cursors.DictCursor,
            autocommit=True,
            connect_timeout=5,
        )
        with conn.cursor() as cursor:
            cursor.execute(
                f"SELECT user_id, timestamp, status, punch, message FROM {TABLE_NAME} "
                "ORDER BY timestamp DESC"
            )
            rows = cursor.fetchall()
            # Convert datetimes to strings
            for r in rows:
                if hasattr(r['timestamp'], 'strftime'):
                    r['timestamp'] = r['timestamp'].strftime('%Y-%m-%d %H:%M:%S')
            return rows, None
    except pymysql.Error as e:
        return None, f"Database error: {str(e)}"
    except Exception as e:
        return None, f"Unexpected error: {str(e)}"
    finally:
        if conn:
            try:
                conn.close()
            except Exception:
                pass


@app.route('/', methods=['GET'])
@require_token
def root():
    try:
        rows, err = fetch_all_attendances()
        if err:
            return jsonify({"error": err}), 500
        
        # Format the attendance data
        formatted_data = format_attendance(rows)
        return jsonify(formatted_data), 200
        
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.errorhandler(404)
def not_found(e):
    return jsonify({
        "status": False,
        "message": "Endpoint not found"
    }), 404

@app.errorhandler(405)
def method_not_allowed(e):
    return jsonify({
        "status": False,
        "message": "Method not allowed"
    }), 405

if __name__ == '__main__':
    try:
        app.run(host='0.0.0.0', port=API_PORT)
    except Exception as e:
        print(f"Failed to start server: {e}", file=sys.stderr)
        sys.exit(1)