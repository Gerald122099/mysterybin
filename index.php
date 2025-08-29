<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $query = $conn->prepare("SELECT * FROM admins WHERE username=?");
    $query->bind_param("s", $username);
    $query->execute();
    $result = $query->get_result();
    $user = $result->fetch_assoc();

    if ($user && $password === $user['password']) {
        $_SESSION['admin'] = $username;
        header("Location: dashboard.php");
    } else {
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoLogin | Mystery Bin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(120deg, #e0f7fa, #c8e6c9, #f1f8e9);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            position: relative;
            overflow: hidden;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .nature-bg {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: -1;
        }

        .leaf {
            position: absolute;
            background-size: contain;
            background-repeat: no-repeat;
            opacity: 0.3;
        }

        .leaf-1 {
            width: 120px;
            height: 120px;
            top: 10%;
            left: 10%;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path fill="%232e7d32" d="M50,15 C30,5 10,20 15,40 C20,60 40,70 50,85 C60,70 80,60 85,40 C90,20 70,5 50,15 Z"></path></svg>');
            animation: float 15s infinite ease-in-out;
        }

        .leaf-2 {
            width: 80px;
            height: 80px;
            top: 70%;
            left: 15%;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path fill="%234caf50" d="M50,15 C30,5 10,20 15,40 C20,60 40,70 50,85 C60,70 80,60 85,40 C90,20 70,5 50,15 Z"></path></svg>');
            animation: float 12s infinite ease-in-out reverse;
        }

        .leaf-3 {
            width: 100px;
            height: 100px;
            top: 20%;
            right: 10%;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path fill="%23388e3c" d="M50,15 C30,5 10,20 15,40 C20,60 40,70 50,85 C60,70 80,60 85,40 C90,20 70,5 50,15 Z"></path></svg>');
            animation: float 18s infinite ease-in-out;
        }

        .leaf-4 {
            width: 60px;
            height: 60px;
            top: 65%;
            right: 15%;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path fill="%231b5e20" d="M50,15 C30,5 10,20 15,40 C20,60 40,70 50,85 C60,70 80,60 85,40 C90,20 70,5 50,15 Z"></path></svg>');
            animation: float 10s infinite ease-in-out reverse;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(3deg); }
            66% { transform: translateY(10px) rotate(-3deg); }
        }

        .login-container {
            display: flex;
            width: 850px;
            height: 500px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .login-image {
            flex: 1;
            background: linear-gradient(120deg, rgba(46, 125, 50, 0.7), rgba(76, 175, 80, 0.7)), url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect fill="%23ffffff" opacity="0.2" width="20" height="20"/></svg>');
            background-size: cover;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .login-image::before {
            content: "";
            position: absolute;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 60 60"><path fill="%23ffffff" opacity="0.1" d="M20,0 L40,0 L40,20 L60,20 L60,40 L40,40 L40,60 L20,60 L20,40 L0,40 L0,20 L20,20 Z"/></svg>');
            animation: backgroundMove 30s linear infinite;
        }

        @keyframes backgroundMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(-50%, -50%); }
        }

        .login-image-content {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .login-image h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .login-image p {
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .eco-features {
            text-align: left;
            margin-top: 30px;
        }

        .eco-feature {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .eco-feature i {
            margin-right: 10px;
            font-size: 1.2rem;
        }

        .login-form {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h2 {
            font-size: 2rem;
            color: #2e7d32;
            font-weight: 700;
        }

        .logo p {
            color: #666;
            margin-top: 5px;
        }

        .input-group {
            position: relative;
            margin-bottom: 25px;
        }

        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #2e7d32;
            font-size: 1.1rem;
        }

        .input-group input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .input-group input:focus {
            border-color: #2e7d32;
            outline: none;
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.2);
        }

        .login-btn {
            background: #2e7d32;
            color: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .login-btn:hover {
            background: #1b5e20;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
            display: <?php echo isset($error) ? 'block' : 'none'; ?>;
        }

        .additional-links {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }

        .additional-links a {
            color: #2e7d32;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .additional-links a:hover {
            text-decoration: underline;
            color: #1b5e20;
        }

        @media (max-width: 900px) {
            .login-container {
                flex-direction: column;
                width: 90%;
                height: auto;
                max-width: 500px;
            }
            
            .login-image {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="nature-bg">
        <div class="leaf leaf-1"></div>
        <div class="leaf leaf-2"></div>
        <div class="leaf leaf-3"></div>
        <div class="leaf leaf-4"></div>
    </div>

    <div class="login-container">
        <div class="login-image">
            <div class="login-image-content">
                <h1>Mystery Bin</h1>
                <p>Transforming waste management through technology and sustainability</p>
                
                <div class="eco-features">
                    <div class="eco-feature">
                        <i class="fas fa-recycle"></i>
                        <span>Smart recycling solutions</span>
                    </div>
                    <div class="eco-feature">
                        <i class="fas fa-leaf"></i>
                        <span>Eco-friendly rewards system</span>
                    </div>
                    <div class="eco-feature">
                        <i class="fas fa-chart-line"></i>
                        <span>Real-time analytics</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="login-form">
            <div class="logo">
                <h2><i class="fas fa-trash-alt"></i> Mystery Bin</h2>
                <p>Admin Portal</p>
            </div>

            <form method="post">
                <div class="input-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="Username" required>
                </div>

                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Password" required>
                </div>

                <button type="submit" class="login-btn">Login to Dashboard</button>

                <?php if (isset($error)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
            </form>

            <div class="additional-links">
                <p><a href="#"><i class="fas fa-question-circle"></i> Forgot your password?</a></p>
                <p>Need help? Contact <a href="#">support@mysterybin.com</a></p>
            </div>
        </div>
    </div>

    <script>
        // Add subtle interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input');
            
            inputs.forEach(input => {
                // Add focus effect
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'translateY(-5px)';
                });
                
                // Remove focus effect
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>