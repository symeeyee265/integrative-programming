<?php
session_start();
require_once 'dbConnection.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'];
    $email = $_POST['email'];
    $security_answer = trim($_POST['security_answer']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if student ID and email match
        $stmt = $conn->prepare("SELECT * FROM users WHERE student_id = ? AND email = ?");
        $stmt->execute([$student_id, $email]);
        $user = $stmt->fetch();

        if ($user) {
            // Verify security answer
            if ($security_answer !== $user['security_answer']) {
                $error = "Invalid security answer.";
            } else {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE student_id = ?");
                $update_stmt->execute([$hashed_password, $student_id]);

                // Set session flag and redirect
                $_SESSION['reset_success'] = true;
                header("Location: login.php");
                exit(); // Important to prevent further execution
            }
        } else {
            $error = "Invalid Student ID or Email.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - EduVote</title>
    <style>
        /* Reuse your existing login.php styles */
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-group input {
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

        .error {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 1rem;
        }

        .success {
            color: #27ae60;
            text-align: center;
            margin-bottom: 1rem;
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
            <h1>Reset Password</h1>

            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success"><?php echo $success; ?></div>
                <div class="auth-links">
                    <a href="login.php">Proceed to Login</a>
                </div>
            <?php else: ?>
                <form method="POST" action="reset_password.php">
                    <div class="form-group">
                        <label for="student-id">Student ID</label>
                        <input type="text" id="student-id" name="student_id" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Registered Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
    <label for="security-answer">Security Answer</label>
    <input type="text" id="security-answer" name="security_answer" required>
</div>
                    <div class="form-group">
                        <label for="new-password">New Password</label>
                        <input type="password" id="new-password" name="new_password" required minlength="8">
                    </div>

                    <div class="form-group">
                        <label for="confirm-password">Confirm Password</label>
                        <input type="password" id="confirm-password" name="confirm_password" required minlength="8">
                    </div>

                    <button type="submit" class="btn">Reset Password</button>

                    <div class="auth-links">
                        <a href="login.php">Remember your password? Login</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 EduVote - Campus Voting System</p>
        <p>Contact: <a href="mailto:support@eduvote.edu" style="color: white;">support@eduvote.edu</a></p>
    </footer>
</body>
</html>
