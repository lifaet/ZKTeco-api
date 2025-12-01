<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Attendance Dashboard</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #d9e8f5 50%, #f0f4f8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1e293b;
        }
        .login-container {
            width: 100%;
            max-width: 380px;
            padding: 1rem;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(100, 116, 139, 0.1);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .login-card h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            color: #1e293b;
        }
        .login-card h1 i {
            color: #0284c7;
            font-size: 2rem;
        }
        .login-subtitle {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            font-weight: 500;
            color: #1e293b;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(100, 116, 139, 0.2);
            border-radius: 6px;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
            color: #1e293b;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.9);
            border-color: #0284c7;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
            outline: none;
        }
        .form-control::placeholder {
            color: rgba(100, 116, 139, 0.5);
        }
        .btn-login {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            border: none;
            border-radius: 6px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            font-size: 0.95rem;
            color: #fff;
            width: 100%;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #0369a1 0%, #035280 100%);
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
            transform: translateY(-2px);
        }
        .btn-login:active {
            transform: translateY(0);
        }
        .alert {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #b91c1c;
            border-radius: 6px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.8rem;
            color: #94a3b8;
        }
        @media (max-width: 480px) {
            .login-card {
                padding: 1.5rem;
            }
            .login-card h1 {
                font-size: 1.5rem;
            }
            .login-card h1 i {
                font-size: 1.7rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h1><i class="bi bi-clock-history"></i> Dashboard</h1>
            <p class="login-subtitle">Attendance Management System</p>
            
            @if(session('login_error'))
                <div class="alert">
                    <strong>Error:</strong> {{ session('login_error') }}
                </div>
            @endif
            
            <form method="POST" action="/login">
                @csrf
                <div class="form-group">
                    <label class="form-label"><i class="bi bi-key"></i> Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Enter your password" required autofocus>
                </div>
                <button type="submit" class="btn-login">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </button>
            </form>
            
            <div class="login-footer">
                <p>&copy; 2025 Attendance Management System</p>
            </div>
        </div>
    </div>
</body>
</html>