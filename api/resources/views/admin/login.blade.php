<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — JSON2Video Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: #0a0e1a;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: radial-gradient(ellipse at 30% 20%, rgba(100, 255, 218, 0.05) 0%, transparent 50%),
                radial-gradient(ellipse at 70% 80%, rgba(99, 102, 241, 0.05) 0%, transparent 50%);
        }

        .login-box {
            width: 100%;
            max-width: 400px;
            padding: 40px;
            background: #111827;
            border: 1px solid #1e293b;
            border-radius: 16px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        }

        .login-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
            justify-content: center;
        }

        .login-logo .icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #000;
            font-size: 15px;
        }

        .login-logo h1 {
            font-size: 20px;
            font-weight: 600;
        }

        .login-logo span {
            font-size: 12px;
            color: #64748b;
            display: block;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #94a3b8;
            margin-bottom: 6px;
        }

        input {
            width: 100%;
            padding: 12px 16px;
            background: #0a0e1a;
            border: 1px solid #1e293b;
            border-radius: 10px;
            color: #e2e8f0;
            font-size: 14px;
            font-family: inherit;
        }

        input:focus {
            outline: none;
            border-color: #64ffda;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .form-check input {
            width: 16px;
            height: 16px;
            accent-color: #64ffda;
        }

        .form-check label {
            font-size: 13px;
            color: #94a3b8;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: #64ffda;
            color: #000;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            font-family: inherit;
        }

        .btn:hover {
            background: #5ae6c2;
        }

        .error {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.2);
            color: #f87171;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }
    </style>
</head>

<body>
    <div class="login-box">
        <div class="login-logo">
            <div class="icon">J2V</div>
            <div>
                <h1>JSON2Video</h1>
                <span>Admin Panel</span>
            </div>
        </div>

        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/admin/login">
            @csrf
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="admin@json2video.local"
                    required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <div class="form-check">
                <input type="checkbox" name="remember" id="remember">
                <label for="remember">Remember me</label>
            </div>
            <button type="submit" class="btn">Sign In</button>
        </form>
    </div>
</body>

</html>