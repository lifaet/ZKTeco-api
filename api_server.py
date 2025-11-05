from flask import Flask, request, jsonify, Response
from datetime import datetime, timedelta
import pymysql
from pymysql.err import OperationalError, Error as PyMySQLError
import logging
from logging.handlers import RotatingFileHandler
import os
import time
from functools import wraps
from typing import Optional, Dict, Any, Callable
import json
from config import (
    DB_HOST, DB_USER, DB_PASS, DB_NAME, 
    TABLE_NAME, API_TOKEN, API_PORT
)

# Initialize Flask app
app = Flask(__name__)

# Configure logging
def setup_logging():
    """Configure logging for the API server"""
    logger = logging.getLogger('api_server')
    logger.setLevel(logging.INFO)
    
    # Create logs directory if it doesn't exist
    if not os.path.exists('logs'):
        os.makedirs('logs')
    
    # File handler with rotation
    file_handler = RotatingFileHandler(
        'logs/api_server.log',
        maxBytes=5*1024*1024,  # 5MB
        backupCount=5
    )
    file_handler.setLevel(logging.INFO)
    
    # Console handler
    console_handler = logging.StreamHandler()
    console_handler.setLevel(logging.INFO)
    
    # Format for logs
    formatter = logging.Formatter(
        '%(asctime)s - %(levelname)s - [%(ip)s] %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )
    file_handler.setFormatter(formatter)
    console_handler.setFormatter(formatter)
    
    logger.addHandler(file_handler)
    logger.addHandler(console_handler)
    
    return logger

# Database connection pool
class DatabasePool:
    def __init__(self, max_connections=5):
        self.connections = []
        self.max_connections = max_connections
        self.logger = logging.getLogger('api_server')

    def get_connection(self) -> Optional[pymysql.Connection]:
        """Get a database connection from the pool or create a new one"""
        # Remove closed connections
        self.connections = [conn for conn in self.connections if self._is_connection_alive(conn)]

        # Return existing connection if available
        for conn in self.connections:
            if self._is_connection_alive(conn):
                return conn

        # Create new connection if under limit
        if len(self.connections) < self.max_connections:
            new_conn = self._create_connection()
            if new_conn:
                self.connections.append(new_conn)
                return new_conn

        self.logger.error("❌ No available database connections")
        return None

    def _create_connection(self) -> Optional[pymysql.Connection]:
        """Create a new database connection"""
        try:
            conn = pymysql.connect(
                host=DB_HOST,
                user=DB_USER,
                password=DB_PASS,
                database=DB_NAME,
                charset='utf8mb4',
                cursorclass=pymysql.cursors.DictCursor,
                autocommit=True,
                connect_timeout=5
            )
            return conn
        except Exception as e:
            self.logger.error(f"❌ Failed to create database connection: {e}")
            return None

    def _is_connection_alive(self, conn: pymysql.Connection) -> bool:
        """Check if a connection is still alive"""
        try:
            conn.ping(reconnect=True)
            return True
        except:
            return False

# Initialize database pool
db_pool = DatabasePool()

# Custom JSON encoder for datetime objects
class CustomJSONEncoder(json.JSONEncoder):
    def default(self, obj):
        if isinstance(obj, datetime):
            return obj.strftime('%Y-%m-%d %H:%M:%S')
        return super().default(obj)

app.json_encoder = CustomJSONEncoder

# Middleware and decorators
def auth_required(f: Callable) -> Callable:
    """Authentication middleware"""
    @wraps(f)
    def wrapper(*args, **kwargs):
        auth = request.headers.get("Authorization", "")
        if not auth.startswith("Bearer ") or auth.split(" ")[1] != API_TOKEN:
            return jsonify({
                "status": False, 
                "message": "Unauthorized access"
            }), 401
        return f(*args, **kwargs)
    return wrapper

def with_error_handling(f: Callable) -> Callable:
    """Error handling middleware"""
    @wraps(f)
    def wrapper(*args, **kwargs):
        logger = logging.getLogger('api_server')
        try:
            return f(*args, **kwargs)
        except PyMySQLError as e:
            logger.error(f"Database error: {e}", extra={'ip': request.remote_addr})
            return jsonify({
                "status": False,
                "message": "Database error occurred",
                "error": str(e)
            }), 500
        except Exception as e:
            logger.error(f"Unexpected error: {e}", extra={'ip': request.remote_addr})
            return jsonify({
                "status": False,
                "message": "Internal server error",
                "error": str(e)
            }), 500
    return wrapper

# Routes
@app.route("/api/health", methods=["GET"])
@with_error_handling
def health_check() -> Response:
    """API health check endpoint"""
    conn = db_pool.get_connection()
    if not conn:
        return jsonify({
            "status": False,
            "message": "Database connection failed",
            "timestamp": datetime.now()
        }), 500

    return jsonify({
        "status": True,
        "message": "API is healthy",
        "timestamp": datetime.now()
    })

@app.route("/api/attendances", methods=["GET"])
@auth_required
@with_error_handling
def get_attendances() -> Response:
    """Get attendance records with filtering and pagination"""
    logger = logging.getLogger('api_server')
    
    # Parse query parameters
    page = int(request.args.get('page', 1))
    per_page = min(int(request.args.get('per_page', 50)), 100)
    start_date = request.args.get('start_date')
    end_date = request.args.get('end_date')
    user_id = request.args.get('user_id')

    # Build query
    query = f"SELECT * FROM {TABLE_NAME} WHERE 1=1"
    params = []

    if start_date:
        query += " AND timestamp >= %s"
        params.append(start_date)
    if end_date:
        query += " AND timestamp <= %s"
        params.append(end_date)
    if user_id:
        query += " AND user_id = %s"
        params.append(user_id)

    # Add pagination
    offset = (page - 1) * per_page
    query += " ORDER BY timestamp DESC LIMIT %s OFFSET %s"
    params.extend([per_page, offset])

    conn = db_pool.get_connection()
    if not conn:
        return jsonify({
            "status": False,
            "message": "Database connection failed",
            "data": []
        }), 500

    try:
        with conn.cursor() as cursor:
            # Get total count
            count_query = f"SELECT COUNT(*) as total FROM {TABLE_NAME} WHERE 1=1"
            if params[:-2]:  # Exclude pagination params
                count_query += query[query.find("WHERE 1=1") + 8:query.find("ORDER BY")]
                cursor.execute(count_query, params[:-2])
            else:
                cursor.execute(count_query)
            total = cursor.fetchone()['total']

            # Get data
            cursor.execute(query, params)
            rows = cursor.fetchall()

            return jsonify({
                "status": True,
                "message": "Data fetched successfully",
                "data": rows,
                "pagination": {
                    "page": page,
                    "per_page": per_page,
                    "total": total,
                    "total_pages": (total + per_page - 1) // per_page
                }
            })

    except Exception as e:
        logger.error(f"Error fetching attendance records: {e}", 
                    extra={'ip': request.remote_addr})
        raise

@app.route("/api/attendances", methods=["POST"])
@auth_required
@with_error_handling
def add_attendance() -> Response:
    """Add new attendance record"""
    logger = logging.getLogger('api_server')
    payload = request.json or {}

    # Validate required fields
    required_fields = ['user_id', 'timestamp']
    missing_fields = [field for field in required_fields if field not in payload]
    if missing_fields:
        return jsonify({
            "status": False,
            "message": f"Missing required fields: {', '.join(missing_fields)}"
        }), 400

    # Validate timestamp format
    try:
        timestamp = datetime.strptime(payload['timestamp'], "%Y-%m-%d %H:%M:%S")
        if timestamp > datetime.now() + timedelta(minutes=5):
            return jsonify({
                "status": False,
                "message": "Timestamp cannot be in the future"
            }), 400
    except ValueError:
        return jsonify({
            "status": False,
            "message": "Invalid timestamp format. Use YYYY-MM-DD HH:MM:SS"
        }), 400

    conn = db_pool.get_connection()
    if not conn:
        return jsonify({
            "status": False,
            "message": "Database connection failed"
        }), 500

    try:
        with conn.cursor() as cursor:
            cursor.execute(f"""
                INSERT INTO {TABLE_NAME}
                (user_id, timestamp, status, punch, message, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                punch = VALUES(punch),
                message = VALUES(message),
                updated_at = VALUES(updated_at)
            """, (
                payload['user_id'],
                payload['timestamp'],
                payload.get('status', ''),
                payload.get('punch', ''),
                payload.get('message', ''),
                datetime.now(),
                datetime.now()
            ))

            return jsonify({
                "status": True,
                "message": "Attendance record processed successfully",
                "record_id": cursor.lastrowid
            })

    except Exception as e:
        logger.error(f"Error adding attendance record: {e}", 
                    extra={'ip': request.remote_addr})
        raise

@app.route("/api/statistics", methods=["GET"])
@auth_required
@with_error_handling
def get_statistics() -> Response:
    """Get attendance statistics"""
    conn = db_pool.get_connection()
    if not conn:
        return jsonify({
            "status": False,
            "message": "Database connection failed"
        }), 500

    try:
        with conn.cursor() as cursor:
            # Get today's stats
            cursor.execute(f"""
                SELECT 
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(*) as total_records
                FROM {TABLE_NAME}
                WHERE DATE(timestamp) = CURDATE()
            """)
            today_stats = cursor.fetchone()

            # Get last 7 days trend
            cursor.execute(f"""
                SELECT 
                    DATE(timestamp) as date,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(*) as total_records
                FROM {TABLE_NAME}
                WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY DATE(timestamp)
                ORDER BY date DESC
            """)
            weekly_trend = cursor.fetchall()

            return jsonify({
                "status": True,
                "message": "Statistics retrieved successfully",
                "today": today_stats,
                "weekly_trend": weekly_trend
            })

    except Exception as e:
        logging.getLogger('api_server').error(
            f"Error fetching statistics: {e}",
            extra={'ip': request.remote_addr}
        )
        raise

# Error handlers
@app.errorhandler(404)
def not_found_error(error):
    return jsonify({
        "status": False,
        "message": "Endpoint not found"
    }), 404

@app.errorhandler(405)
def method_not_allowed_error(error):
    return jsonify({
        "status": False,
        "message": "Method not allowed"
    }), 405

# Main entry point
if __name__ == "__main__":
    logger = setup_logging()
    logger.info("🚀 Starting API server...", extra={'ip': 'localhost'})
    
    # Add extra security headers
    @app.after_request
    def add_security_headers(response):
        response.headers['X-Content-Type-Options'] = 'nosniff'
        response.headers['X-Frame-Options'] = 'DENY'
        response.headers['X-XSS-Protection'] = '1; mode=block'
        response.headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains'
        return response
    
    app.run(
        host="0.0.0.0",
        port=API_PORT,
        debug=False,
        use_reloader=False
    )