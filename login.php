<?php
require_once 'config/database.php';

// JWT Helper Functions
function generateJWT($user_id, $username) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $issuedAt = time();
    $expiration = $issuedAt + 900; // 15 minutes = 900 seconds
    
    $payload = json_encode([
        'user_id' => $user_id,
        'username' => $username,
        'iat' => $issuedAt,
        'exp' => $expiration
    ]);
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $secret = 'your_jwt_secret_key_2026_travelai';
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

function verifyJWT($token) {
    $secret = 'your_jwt_secret_key_2026_travelai';
    $tokenParts = explode('.', $token);
    
    if (count($tokenParts) != 3) {
        return false;
    }
    
    $payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1]));
    $payloadData = json_decode($payload, true);
    
    // Check if token is expired
    if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
        return false;
    }
    
    $expectedSignature = hash_hmac('sha256', $tokenParts[0] . "." . $tokenParts[1], $secret, true);
    $expectedSignatureBase64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expectedSignature));
    
    if ($tokenParts[2] !== $expectedSignatureBase64) {
        return false;
    }
    
    return $payloadData;
}

if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

// Handle Registration - FIXED: using full_name (with underscore)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($full_name) || empty($email) || empty($username) || empty($password)) {
        $error = "Please fill in all fields!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        $check_query = "SELECT * FROM users WHERE username = ? OR email = ?";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$username, $email]);
        
        if ($check_stmt->rowCount() > 0) {
            $error = "Username or email already exists!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            // FIXED: column name is 'full_name' not 'fullname'
            $query = "INSERT INTO users (full_name, email, username, password) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($query);
            
            if ($stmt->execute([$full_name, $email, $username, $hashed_password])) {
                $success = "Registration successful! You can now login.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}

// Handle Login - FIXED: using full_name (with underscore)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $query = "SELECT * FROM users WHERE username = ? OR email = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];  // FIXED: full_name with underscore
        
        // Generate JWT token with 15 minutes expiry
        $_SESSION['jwt_token'] = generateJWT($user['id'], $user['username']);
        
        header("Location: index.php");
        exit();
    } else {
        $error = "Invalid username/email or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TravelAI - Login & Register</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            overflow-x: hidden;
            position: relative;
        }

        /* Animated Background */
        .stars {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }

        .star {
            position: absolute;
            background: white;
            border-radius: 50%;
            animation: twinkle 3s infinite;
        }

        @keyframes twinkle {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.2); }
        }

        /* Flying Airplanes */
        .airplane {
            position: fixed;
            font-size: 2rem;
            color: rgba(255,255,255,0.2);
            pointer-events: none;
            z-index: 1;
            animation: fly 10s linear infinite;
        }

        @keyframes fly {
            0% {
                transform: translateX(-150px) translateY(0px) rotate(0deg);
                opacity: 0;
            }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% {
                transform: translateX(calc(100vw + 150px)) translateY(-80px) rotate(5deg);
                opacity: 0;
            }
        }

        .airplane:nth-child(1) { top: 15%; animation-duration: 12s; animation-delay: 0s; }
        .airplane:nth-child(2) { top: 35%; animation-duration: 15s; animation-delay: 3s; font-size: 1.5rem; }
        .airplane:nth-child(3) { top: 55%; animation-duration: 10s; animation-delay: 6s; }
        .airplane:nth-child(4) { top: 75%; animation-duration: 13s; animation-delay: 2s; font-size: 1.8rem; }
        .airplane:nth-child(5) { top: 85%; animation-duration: 11s; animation-delay: 8s; }

        /* Container */
        .container {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        /* Glass Card */
        .glass-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            padding: 3rem;
            width: 100%;
            max-width: 500px;
            border: 1px solid rgba(255,255,255,0.2);
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            animation: slideUp 0.6s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo i {
            font-size: 3rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .logo h1 {
            font-size: 2rem;
            color: white;
            margin-top: 0.5rem;
        }

        .logo span {
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            background: rgba(255,255,255,0.05);
            border-radius: 50px;
            padding: 0.3rem;
        }

        .tab-btn {
            flex: 1;
            padding: 0.8rem;
            background: transparent;
            border: none;
            color: rgba(255,255,255,0.6);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            border-radius: 50px;
            transition: all 0.3s;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }

        /* Forms */
        .form-panel {
            display: none;
        }

        .form-panel.active {
            display: block;
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .input-group {
            margin-bottom: 1.5rem;
        }

        .input-group label {
            display: block;
            color: rgba(255,255,255,0.8);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .input-group input {
            width: 100%;
            padding: 1rem;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 15px;
            color: white;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .input-group input:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(255,255,255,0.15);
        }

        .input-group input::placeholder {
            color: rgba(255,255,255,0.4);
        }

        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 15px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102,126,234,0.4);
        }

        .message {
            padding: 0.8rem;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .error {
            background: rgba(255,0,0,0.2);
            border: 1px solid rgba(255,0,0,0.3);
            color: #ff6b6b;
        }

        .success {
            background: rgba(0,255,0,0.15);
            border: 1px solid rgba(0,255,0,0.3);
            color: #51cf66;
        }

        .session-note {
            font-size: 0.7rem;
            text-align: center;
            margin-top: 1rem;
            color: rgba(255,255,255,0.5);
        }

        @media (max-width: 768px) {
            .glass-card {
                padding: 2rem;
                margin: 1rem;
            }
        }
    </style>
</head>
<body>

<!-- Stars Background -->
<div class="stars">
    <?php for($i = 0; $i < 100; $i++): ?>
        <div class="star" style="left: <?php echo rand(0,100); ?>%; top: <?php echo rand(0,100); ?>%; width: <?php echo rand(1,3); ?>px; height: <?php echo rand(1,3); ?>px; animation-delay: <?php echo rand(0,3000); ?>ms;"></div>
    <?php endfor; ?>
</div>

<!-- Flying Airplanes -->
<div class="airplane"><i class="fas fa-plane"></i></div>
<div class="airplane"><i class="fas fa-fighter-jet"></i></div>
<div class="airplane"><i class="fas fa-plane"></i></div>
<div class="airplane"><i class="fas fa-rocket"></i></div>
<div class="airplane"><i class="fas fa-plane"></i></div>

<div class="container">
    <div class="glass-card">
        <div class="logo">
            <i class="fas fa-globe-americas"></i>
            <h1>Travel<span>AI</span></h1>
        </div>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('login')">Login</button>
            <button class="tab-btn" onclick="switchTab('register')">Register</button>
        </div>

        <?php if($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Login Form -->
        <div id="loginForm" class="form-panel active">
            <form method="POST" action="">
                <div class="input-group">
                    <label><i class="fas fa-user"></i> Username or Email</label>
                    <input type="text" name="username" placeholder="Enter username or email" required>
                </div>
                <div class="input-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <input type="password" name="password" placeholder="Enter password" required>
                </div>
                <button type="submit" name="login" class="btn-submit">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            <div class="session-note">
                <i class="fas fa-clock"></i> Session expires after 15 minutes for security
            </div>
        </div>

        <!-- Register Form - FIXED: input name is 'full_name' -->
        <div id="registerForm" class="form-panel">
            <form method="POST" action="">
                <div class="input-group">
                    <label><i class="fas fa-user"></i> Full Name</label>
                    <input type="text" name="full_name" placeholder="Enter your full name" required>
                </div>
                <div class="input-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" name="email" placeholder="Enter your email" required>
                </div>
                <div class="input-group">
                    <label><i class="fas fa-user-circle"></i> Username</label>
                    <input type="text" name="username" placeholder="Choose a username" required>
                </div>
                <div class="input-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <input type="password" name="password" placeholder="Create a password" required>
                </div>
                <div class="input-group">
                    <label><i class="fas fa-check-circle"></i> Confirm Password</label>
                    <input type="password" name="confirm_password" placeholder="Confirm your password" required>
                </div>
                <button type="submit" name="register" class="btn-submit">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>
            <div class="session-note">
                <i class="fas fa-shield-alt"></i> Your data is securely stored
            </div>
        </div>
    </div>
</div>

<script>
    function switchTab(tab) {
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        const buttons = document.querySelectorAll('.tab-btn');
        
        if (tab === 'login') {
            loginForm.classList.add('active');
            registerForm.classList.remove('active');
            buttons[0].classList.add('active');
            buttons[1].classList.remove('active');
        } else {
            loginForm.classList.remove('active');
            registerForm.classList.add('active');
            buttons[0].classList.remove('active');
            buttons[1].classList.add('active');
        }
    }
</script>
</body>
</html>