<?php
// Force destroy session to fix persistent login issue
session_start();
session_destroy();
session_start();

// Add a logout parameter check
if (isset($_GET['logout'])) {
    header('Location: index.php');
    exit;
}

require 'db.php';
// Only redirect to chat if explicitly logging in
// This removes automatic redirect
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $pwd  = trim($_POST['password']);
    if (isset($_POST['signup'])) {
        // Create user
        $hash = password_hash($pwd, PASSWORD_BCRYPT);
        $ins  = $conn->prepare("INSERT INTO users (name, password) VALUES (?, ?)");
        $ins->bind_param("ss", $name, $hash);
        if ($ins->execute()) {
            // Assign unique number
            $newId = $conn->insert_id;
            $unum  = "+786-" . (1000 + $newId);
            $upd   = $conn->prepare("UPDATE users SET unique_number = ? WHERE id = ?");
            $upd->bind_param("si", $unum, $newId);
            $upd->execute();
            $msg = "✅ Signed up! Your number is <strong>$unum</strong>";
        } else {
            $msg = "❌ Signup failed: " . htmlspecialchars($ins->error);
        }
    } elseif (isset($_POST['login'])) {
        // Login
        $stmt = $conn->prepare("SELECT id, password, unique_number FROM users WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($u = $res->fetch_assoc()) {
            if (password_verify($pwd, $u['password'])) {
                $_SESSION['user_id']       = $u['id'];
                $_SESSION['unique_number'] = $u['unique_number'];
                header('Location: chat.php');
                exit;
            } else {
                $msg = "❌ Wrong password.";
            }
        } else {
            $msg = "❌ User not found.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Clone - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background-color: white;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 480px;
            padding: 35px 45px;
            position: relative;
            overflow: hidden;
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            background-color: rgba(37, 211, 102, 0.1);
            border-radius: 50%;
            z-index: 0;
        }
        
        .container::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: -50px;
            width: 120px;
            height: 120px;
            background-color: rgba(7, 94, 84, 0.1);
            border-radius: 50%;
            z-index: 0;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 35px;
            position: relative;
            z-index: 1;
        }
        
        .logo-icon {
            font-size: 58px;
            color: #25D366;
            margin-bottom: 15px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        h2 {
            color: #075E54;
            font-size: 28px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
        }
        
        h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background-color: #25D366;
            border-radius: 3px;
        }
        
        .form-group {
            margin-bottom: 24px;
            position: relative;
            z-index: 1;
        }
        
        .form-control {
            width: 100%;
            padding: 16px;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
            outline: none;
            background-color: #f9f9f9;
        }
        
        .form-control:focus {
            border-color: #25D366;
            box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.2);
            background-color: #fff;
        }
        
        .btn-group {
            display: flex;
            gap: 14px;
            margin-top: 35px;
            position: relative;
            z-index: 1;
        }
        
        .btn {
            flex: 1;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 0%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.2);
            transition: width 0.3s ease;
        }
        
        .btn:hover::before {
            width: 100%;
        }
        
        .btn-primary {
            background-color: #25D366;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #1fac53;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
        }
        
        .btn-secondary {
            background-color: #075E54;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #064740;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(7, 94, 84, 0.3);
        }
        
        .message {
            margin-top: 25px;
            padding: 14px;
            border-radius: 12px;
            font-size: 15px;
            text-align: center;
            position: relative;
            z-index: 1;
            animation: fadeIn 0.5s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message.success {
            background-color: #D4EDDA;
            color: #155724;
            border: 1px solid #C3E6CB;
        }
        
        .message.error {
            background-color: #F8D7DA;
            color: #721C24;
            border: 1px solid #F5C6CB;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            color: #555;
            font-size: 14px;
            position: relative;
            z-index: 1;
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 25px;
                border-radius: 12px;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <div class="logo-icon">💬</div>
            <h2>WhatsApp Clone</h2>
        </div>
        
        <form method="post">
            <div class="form-group">
                <input class="form-control" name="name" placeholder="Username" required>
            </div>
            
            <div class="form-group">
                <input class="form-control" name="password" type="password" placeholder="Password" required>
            </div>
            
            <div class="btn-group">
                <button class="btn btn-secondary" type="submit" name="login">Login</button>
                <button class="btn btn-primary" type="submit" name="signup">Sign Up</button>
            </div>
        </form>
        
        <?php if (!empty($msg)): ?>
            <?php 
                $msgClass = strpos($msg, '✅') !== false ? 'success' : 'error';
            ?>
            <div class="message <?= $msgClass ?>">
                <?= $msg ?>
            </div>
        <?php endif; ?>
        
        <div class="footer">
            Connect with friends and family securely
        </div>
    </div>
</body>
</html>
