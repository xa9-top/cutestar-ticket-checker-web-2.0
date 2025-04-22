<?php
session_start();
require '../conf.php'; // 根据实际路径调整

if (empty($admin_password)){
    // 安装重定向及登录
    $_SESSION['logged_in'] = true;
    $_SESSION['expire_time'] = time() + (43200);
    $_SESSION['isadmin'] = true;
    session_regenerate_id(true); // 防止会话固定
    
    // 设置session cookie
    setcookie(
        session_name(),
        session_id(),
        $_SESSION['expire_time'],
        '/',
        '',
        false,  // 允许HTTP
        true    // HttpOnly
    );
    header('Location: ../install/');
    exit;
}

// 已登录用户重定向
if (isset($_SESSION['logged_in']) && $_SESSION['isadmin']) {
    header('Location: ../');
    exit;
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_password = $_POST['password'] ?? '';
    $salted_input = $input_password . $salt;
    $hashed_password = hash('sha256', $salted_input);
    
    if ($hashed_password === $admin_password) {
        // 登录成功处理
        $_SESSION['logged_in'] = true;
        $_SESSION['expire_time'] = time() + ($cookie_expire * 60);
        $_SESSION['isadmin'] = true;
        $return_url = $_GET['from'] ?? '/';
        
        session_regenerate_id(true); // 防止会话固定
        
        // 设置session cookie
        setcookie(
            session_name(),
            session_id(),
            $_SESSION['expire_time'],
            '/',
            '',
            false,  // 允许HTTP
            true    // HttpOnly
        );
        
        header("Location: $return_url");
        exit;
    } else if ($hashed_password === $user_password) {
        // 登录成功处理
        $_SESSION['logged_in'] = true;
        $_SESSION['expire_time'] = time() + ($cookie_expire * 60);
        $_SESSION['isadmin'] = false;
        $return_url = $_GET['from'] ?? '/';
        
        session_regenerate_id(true); // 防止会话固定
        
        // 设置session cookie
        setcookie(
            session_name(),
            session_id(),
            $_SESSION['expire_time'],
            '/',
            '',
            false,  // 允许HTTP
            true    // HttpOnly
        );
        
        header("Location: $return_url");
        exit;
    }
    
    // 密码错误处理
    $error = true;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>用户登录</title>
    <style>
        body {
            font-family: sans-serif;
            background: #f4f4f4 url('../image/bg.jpg') center/cover no-repeat;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            background-color: rgba(255, 255, 255, 0.5);
            padding: 30px 40px;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            width: 400px;
            text-align: center;
            backdrop-filter: blur(8px);
            transition: transform 0.3s ease;
        }
        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        .login-container h2 {
            margin-bottom: 30px;
            color: #2c3e50;
            font-size: 24px;
            font-weight: 600;
        }
        .form-group {
            margin: 25px 0;
        }
        input[type="password"] {
            padding: 14px 25px;
            border: none;
            border-radius: 30px;
            background: rgba(219, 236, 255, 0.85);
            width: 80%;
            font-size: 16px;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        input[type="password"]:focus {
            outline: none;
            background: rgba(219, 236, 255, 0.95);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            width: 85%;
        }
        button[type="submit"] {
            padding: 14px 30px;
            background: #3B99FC;
            color: white;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            width: 60%;
            margin-top: 15px;
        }
        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
            background: #2E82E0;
        }
        .error-message {
            color: #e74c3c;
            margin: 15px 0;
            font-weight: 500;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.3);
        }
        .message {
            margin: 15px 0;
            font-weight: 500;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>用户登录</h2>
        <?php if (isset($error)): ?>
            <p class="error-message">密码错误，请重试</p>
        <?php endif; ?>
        <?php if ($_SESSION['logged_in']): ?>
            <p class="message">已登录：用户权限</p>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <input type="password" id="password" name="password" placeholder="请输入登录密码" required>
            </div>
            <div class="form-group">
                <button type="submit">立即登录</button>
            </div>
        </form>
    </div>
    <script src="../js/crypto-js.min.js"></script> <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            // 阻止表单默认提交行为
            e.preventDefault();
        
            // 获取用户输入的明文密码
            const plaintextPassword = document.getElementById('password').value;
        
            // 使用 SHA256 对明文密码进行哈希
            const hashedPassword = CryptoJS.SHA256(plaintextPassword).toString();
        
            // 将哈希后的密码放入隐藏字段
            document.getElementById('password').value = hashedPassword;
        
            // 提交表单
            this.submit();
        });
    </script>
</body>
</html>