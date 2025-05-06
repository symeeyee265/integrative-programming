<?php
session_start();
require_once 'dbConnection.php'; // Database configuration

// make CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // 32字节随机字符串（64字符）
}

$welcome_message = '';
if (isset($_SESSION['welcome_message'])) {
    $welcome_message = $_SESSION['welcome_message'];
    unset($_SESSION['welcome_message']); // Show only once
}

// Redirect to homepage if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: homePage.php");
    exit();
}

$error = "";
$max_attempts = 3; // Maximum number of login attempts
$lockout_time = 300; // Lockout time in seconds (5 minutes)

// Check if the user is locked out
if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= $max_attempts) {
    $last_attempt = isset($_SESSION['last_attempt']) ? $_SESSION['last_attempt'] : 0;
    if (time() - $last_attempt < $lockout_time) {
        $remaining_time = $lockout_time - (time() - $last_attempt);
        $error = "You have exceeded the maximum number of login attempts. Please try again in " . gmdate("i:s", $remaining_time) . " minutes.";
    } else {
        // Reset the login attempts after the lockout time has passed
        unset($_SESSION['login_attempts']);
        unset($_SESSION['last_attempt']);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. 验证 CSRF 令牌
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security token invalid.";
        // 计数失败尝试（与 reCAPTCHA 失败逻辑统一）
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = 1;
        } else {
            $_SESSION['login_attempts']++;
        }
        $_SESSION['last_attempt'] = time();
    } else {
        // 2. 继续验证 reCAPTCHA（仅当 CSRF 验证通过时）
        $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
        if (!empty($recaptcha_response)) {
            $recaptcha_secret = '6LfLcC0rAAAAALHgWE2Vo4ogBMtPTomQ7w2mmi92';
            $recaptcha_verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret}&response={$recaptcha_response}");
            $recaptcha_data = json_decode($recaptcha_verify);
            if (!$recaptcha_data->success || $recaptcha_data->score < 0.5) {
                $error = "reCAPTCHA verification failed.";
                // 计数失败尝试（与 CSRF 失败逻辑统一）
                if (!isset($_SESSION['login_attempts'])) {
                    $_SESSION['login_attempts'] = 1;
                } else {
                    $_SESSION['login_attempts']++;
                }
                $_SESSION['last_attempt'] = time();
            }
        } else {
            $error = "Please complete the reCAPTCHA verification.";
            // 计数失败尝试（用户未提交 reCAPTCHA）
            if (!isset($_SESSION['login_attempts'])) {
                $_SESSION['login_attempts'] = 1;
            } else {
                $_SESSION['login_attempts']++;
            }
            $_SESSION['last_attempt'] = time();
        }
    }
    
    // 3. 后续输入验证（仅当 CSRF 和 reCAPTCHA 均通过时）
    if (empty($error)) {
        $student_id = trim($_POST['student_id']);
        $password = $_POST['password'];

        // Validate inputs
        if (empty($student_id) || empty($password)) {
            $error = "Please enter Student ID and Password.";
        } else {
            try {
                // Prepare SQL statement to prevent SQL injection
                $stmt = $conn->prepare("SELECT user_id, student_id, password, is_admin FROM users WHERE student_id = :student_id");
                $stmt->bindParam(':student_id', $student_id);
                $stmt->execute();

                if ($stmt->rowCount() == 1) {
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Verify password (assuming passwords are hashed in the database)
                    if (password_verify($password, $user['password'])) {
                        // Reset the login attempts on successful login
                        unset($_SESSION['login_attempts']);
                        unset($_SESSION['last_attempt']);

                        // Set session variables
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['student_id'] = $user['student_id'];
                        $_SESSION['is_admin'] = $user['is_admin'];
                        $_SESSION['welcome_message'] = "Welcome, " . htmlspecialchars($user['student_id']) . "!";

                        // Redirect to appropriate page based on user type
                        if ($user['is_admin']) {
                            header("Location: admin_dashboard.php");
                        } else {
                            header("Location: homePage.php");
                        }
                        exit();
                    } else {
                        // Increment the login attempts on failed login
                        if (!isset($_SESSION['login_attempts'])) {
                            $_SESSION['login_attempts'] = 1;
                        } else {
                            $_SESSION['login_attempts']++;
                        }
                        $_SESSION['last_attempt'] = time();
                        $error = "Invalid Student ID or Password.";
                    }
                } else {
                    // Increment the login attempts on failed login
                    if (!isset($_SESSION['login_attempts'])) {
                        $_SESSION['login_attempts'] = 1;
                    } else {
                        $_SESSION['login_attempts']++;
                    }
                    $_SESSION['last_attempt'] = time();
                    $error = "Invalid Student ID or Password.";
                }
            } catch (PDOException $e) {
                $error = "Database error: Please try again later.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal - EduVote</title>
    <script src="https://www.google.com/recaptcha/api.js?render=6LfLcC0rAAAAAG3ZmASAUwWVyYf4dY4GBNmZRZFj"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #2c3e50;
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        header {
            background-color: #1a5276;
            color: white;
            padding: 1rem;
            text-align: center;
        }

       .logo {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

       .logo span {
            color: #3498db;
        }

        nav {
            margin-top: 1rem;
        }

        nav a {
            color: white;
            text-decoration: none;
            margin: 0 10px;
            font-weight: 500;
        }

       .auth-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
            flex: 1;
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            justify-content: center;
        }

       .auth-box {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }

       .auth-box h1 {
            text-align: center;
            color: #1a5276;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

       .tabs {
            display: flex;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #ddd;
        }

       .tab {
            padding: 0.5rem 1rem;
            cursor: pointer;
            font-weight: 500;
            color: #7f8c8d;
        }

       .tab.active {
            color: #1a5276;
            border-bottom: 2px solid #2980b9;
        }

       .form-group {
            margin-bottom: 1.5rem;
        }

       .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

       .form-group input, .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

       .btn {
            display: block;
            width: 100%;
            padding: 0.8rem;
            background: #2980b9;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            margin-top: 1rem;
        }

       .btn:hover {
            background: #2471a3;
        }

       .btn-outline {
            background: white;
            color: #2980b9;
            border: 1px solid #2980b9;
        }

       .btn-outline:hover {
            background: #ebf5fb;
        }

       .auth-links {
            margin-top: 1.5rem;
            text-align: center;
        }

       .auth-links a {
            color: #2980b9;
            text-decoration: none;
            display: block;
            margin-bottom: 0.5rem;
        }

        footer {
            background: #1a5276;
            color: white;
            text-align: center;
            padding: 1.5rem;
            margin-top: auto;
        }

       .hidden {
            display: none;
        }

        @media (max-width: 600px) {
           .auth-container {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="logo">Edu<span>Vote</span></div>
        <nav>
            <a href="homePage.php">Home</a>
            <a href="login.php">Login</a>
        </nav>
    </header>

    <div class="auth-container">
        <div class="auth-box">
            <div class="tabs">
                <div class="tab active" onclick="showLogin()">Student Login</div>
                <a href="register.php" class="tab">New Registration</a>
            </div>

            <!-- Login Form -->
            <div id="login-form">
                <h1>Student Login</h1>
                <?php if (!empty($error)): ?>
                    <p style="color: red; text-align: center;"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>
                <?php if (!empty($welcome_message)): ?>
                    <div class="success" style="text-align:center;"><?php echo $welcome_message; ?></div>
                <?php endif; ?>
                <form method="post" action="login.php" id="loginForm">
                   <!-- CSRFToken hidden word -->
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
 
                    <div class="form-group">
                        <label for="student-id">Student ID</label>
                        <input type="text" id="student-id" name="student_id" required placeholder="Enter your student ID"
                               oninput="this.value = this.value.toUpperCase()">
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required placeholder="Enter your password">
                        <button type="button" onclick="togglePasswordVisibility('password')">Show</button>
                    </div>

                    <button type="submit" class="btn">Login</button>
                </form>

                <div class="auth-links">
                    <a href="resetPassword.php">Forgot password?</a>
                    <a href="homePage.php">← Back to Homepage</a>
                </div>
            </div>
            <!-- Registration form can be added here in the future -->
        </div>
    </div>

    <footer>
        <p>&copy; 2025 EduVote - Campus Voting System</p>
        <p>Contact: <a href="mailto:support@eduvote.edu" style="color: white;">support@eduvote.edu</a></p>
    </footer>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function (e) {
            e.preventDefault();
            grecaptcha.ready(function () {
                grecaptcha.execute('6LfLcC0rAAAAAG3ZmASAUwWVyYf4dY4GBNmZRZFj', {action: 'login'}).then(function (token) {
                    var form = document.getElementById('loginForm');
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'g-recaptcha-response';
                    input.value = token;
                    form.appendChild(input);
                    form.submit();
                });
            });
        });

        function showLogin() {
            document.getElementById('login-form').classList.remove('hidden');
            // Add code to hide registration form if it exists
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab')[0].classList.add('active');
        }

        function showRegister() {
            // Add code to show registration form if it exists
            document.getElementById('login-form').classList.add('hidden');
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab')[1].classList.add('active');
        }

        function togglePasswordVisibility(fieldId) {
            const input = document.getElementById(fieldId);
            if (input.type === 'password') {
                input.type = 'text';
            } else {
                input.type = 'password';
            }
        }
    </script>
</body>

</html>    
