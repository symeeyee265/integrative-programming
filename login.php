<?php


require_once 'dbConnection.php'; // Database configuration

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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal - EduVote</title>
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
                <form method="post" action="login.php">
                    <div class="form-group">
                        <label for="student-id">Student ID</label>
                        <input type="text" id="student-id" name="student_id" required placeholder="Enter your student ID"
                               oninput="this.value = this.value.toUpperCase()">
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required placeholder="Enter your password">
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
    </script>
</body>

</html>    
