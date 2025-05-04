<?php
session_start();
require_once 'dbConnection.php';
require_once 'emailService.php';

$message = '';
$success = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $emailService = new EmailService($conn);
    
    if ($emailService->verifyToken($token)) {
        $message = 'Your email has been verified successfully! You can now log in to your account.';
        $success = true;
    } else {
        $message = 'Invalid or expired verification link. Please try registering again.';
    }
} else {
    $message = 'No verification token provided.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - EduVote</title>
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

        .verification-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .verification-container h1 {
            color: #1a5276;
            margin-bottom: 1rem;
        }

        .message {
            margin: 1rem 0;
            padding: 1rem;
            border-radius: 4px;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }

        .btn {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background: #2980b9;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 1rem;
        }

        .btn:hover {
            background: #2471a3;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <h1>Email Verification</h1>
        <div class="message <?= $success ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php if ($success): ?>
            <a href="login.php" class="btn">Go to Login</a>
        <?php else: ?>
            <a href="register.php" class="btn">Register Again</a>
        <?php endif; ?>
    </div>
</body>
</html> 