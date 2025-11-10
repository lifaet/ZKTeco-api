<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center" style="height:100vh; background:#f5f7fb;">
<div class="card p-4" style="width:360px">
    <h5 class="mb-3">Dashboard Login</h5>
    @if(session('login_error'))
        <div class="alert alert-danger">{{ session('login_error') }}</div>
    @endif
    <form method="POST" action="/login">
        @csrf
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required autofocus>
        </div>
        <div class="d-flex justify-content-between align-items-center">
            <button class="btn btn-primary">Login</button>
        </div>
    </form>
</div>
</body>
</html>