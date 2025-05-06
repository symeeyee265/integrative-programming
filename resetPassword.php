<?php
session_start();
require_once 'dbConnection.php';

$error = '';
$success = '';

$max_attempts = 3;
$lockout_time = 300; // 5 minutes in seconds

if (!isset($_SESSION['reset_attempts'])) {
    $_SESSION['reset_attempts'] = 0;
    $_SESSION['reset_last_attempt'] = time();
}

// Check if user is locked out
if ($_SESSION['reset_attempts'] >= $max_attempts) {
    $last_attempt = $_SESSION['reset_last_attempt'];
    if (time() - $last_attempt < $lockout_time) {
        $remaining = $lockout_time - (time() - $last_attempt);
        $error = "Too many reset attempts. Please try again in " . gmdate("i:s", $remaining) . " minutes.";
        // Stop further processing
        return;
    } else {
        // Reset after lockout period
        $_SESSION['reset_attempts'] = 0;
        $_SESSION['reset_last_attempt'] = time();
    }
}

// --- Strategy Pattern for Validation ---
interface ValidationStrategy {
    public function validate($value): array;
}

class PasswordValidation implements ValidationStrategy {
    public function validate($value): array {
        $errors = [];
        if (strlen($value) < 8) {
            $errors[] = "Password must be at least 8 characters";
        }
        if (!preg_match('/[A-Z]/', $value)) {
            $errors[] = "Password must include an uppercase letter";
        }
        if (!preg_match('/[a-z]/', $value)) {
            $errors[] = "Password must include a lowercase letter";
        }
        if (!preg_match('/[0-9]/', $value)) {
            $errors[] = "Password must include a number";
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $value)) {
            $errors[] = "Password must include a symbol";
        }
        return $errors;
    }
}

class EmailValidation implements ValidationStrategy {
    public function validate($value): array {
        $errors = [];
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        if (!preg_match('/@student\\.tarc\\.edu\\.my$/', $value)) {
            $errors[] = "Must be TARC student email";
        }
        return $errors;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'];
    $email = $_POST['email'];
    $security_question = $_POST['security_question'];
    $security_answer = trim(strtolower($_POST['security_answer']));
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Use strategy pattern for validation
    $passwordValidator = new PasswordValidation();
    $emailValidator = new EmailValidation();
    $passwordErrors = $passwordValidator->validate($new_password);
    $emailErrors = $emailValidator->validate($email);

    if (!empty($passwordErrors)) {
        $error = implode('<br>', $passwordErrors);
    } elseif (!empty($emailErrors)) {
        $error = implode('<br>', $emailErrors);
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if student ID and email match
        $stmt = $conn->prepare("SELECT * FROM users WHERE student_id = ? AND email = ?");
        $stmt->execute([$student_id, $email]);
        $user = $stmt->fetch();

        if ($user) {
            // Check if new password is the same as the old password
            if (password_verify($new_password, $user['password'])) {
                $error = "Reset password cannot set same like before.";
            } else {
                // Verify security answer
                $ch = curl_init('http://localhost:5001/verify_security');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'student_id' => $student_id,
                    'security_question' => $security_question,
                    'security_answer' => $security_answer
                ]));
                $response = curl_exec($ch);
                curl_close($ch);

                if ($response === false) {
                    $error = "Could not connect to verification service.";
                } else {
                    $result = json_decode($response, true);
                    if ($result === null) {
                        $error = "Verification service returned invalid response: " . htmlspecialchars($response);
                    } elseif ($result['result'] !== 'success') {
                        $error = "Invalid security answer.";
                        $_SESSION['reset_attempts']++;
                        $_SESSION['reset_last_attempt'] = time();
                    } else {
                        // Update password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE student_id = ?");
                        $update_stmt->execute([$hashed_password, $student_id]);

                        // Set session flag and redirect
                        $_SESSION['reset_success'] = true;
                        $_SESSION['reset_attempts'] = 0;
                        $_SESSION['reset_last_attempt'] = time();
                        $_SESSION['welcome_message'] = "Password reset successful! Please log in with your new password.";
                        header("Location: login.php");
                        exit(); // Important to prevent further execution
                    }
                }
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

        .strength-meter {
            height: 10px;
            background-color: #f0f0f0;
            border-radius: 5px;
            margin-top: 5px;
        }
        .strength-meter-bar {
            height: 100%;
            border-radius: 5px;
            background-color: #4CAF50;
        }
        .strength-weak {
            background-color: #FF5733;
        }
        .strength-medium {
            background-color: #FFC300;
        }
        .strength-strong {
            background-color: #4CAF50;
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
                <form method="POST" action="resetPassword.php">
                    <div class="form-group">
                        <label for="student-id">Student ID</label>
                        <input type="text" id="student-id" name="student_id" required oninput="validateStudentId(this)">
                        <span class="feedback" id="student_id_feedback"></span>
                    </div>

                    <div class="form-group">
                        <label for="email">Registered Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                     <div class="form-group">
                        <label for="security-question" class="required">Security Question</label>
                        <select id="security-question" name="security_question" required onchange="validateInput(this, 'security_question')">
                            <option value="">Select a question</option>
                            <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
                            <option value="What was your first pet's name?">What was your first pet's name?</option>
                            <option value="What is your favorite book?">What is your favorite book?</option>
                            <option value="What city were you born in?">What city were you born in?</option>
                        </select>
                        <span class="feedback" id="security_question_feedback"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="security-answer">Security Answer</label>
                        <input type="text" id="security-answer" name="security_answer" required 
                               oninput="convertToLowercase(this)">
                    </div>
                    <div class="form-group">
                        <label for="new-password">New Password</label>
                        <input type="password" id="new-password" name="new_password" required minlength="8" oninput="validatePassword()">
                        <button type="button" onclick="togglePasswordVisibility('new-password')">Show</button>
                        <span class="feedback" id="password_feedback"></span>
                        <div class="strength-meter" id="strengthMeter">
                            <div class="strength-meter-bar" id="strengthMeterBar"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm-password">Confirm Password</label>
                        <input type="password" id="confirm-password" name="confirm_password" required minlength="8" oninput="validateConfirmPassword()">
                        <button type="button" onclick="togglePasswordVisibility('confirm-password')">Show</button>
                        <span class="feedback" id="confirm_password_feedback"></span>
                    </div>

                    <button type="submit" class="btn">Reset Password</button>

                    <div class="auth-links">
                        <a href="login.php">Remember your password? Login</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Student ID validation: exactly 10 chars, 3 letters, 7 numbers, no special chars
    function validateStudentId(input) {
        const feedback = document.getElementById('student_id_feedback');
        let value = input.value.toUpperCase();
        input.value = value.replace(/[^A-Z0-9]/g, ''); // Only allow A-Z, 0-9
        let error = '';
        if (value.length !== 10) {
            error = 'Student ID must be exactly 10 characters.';
        } else {
            const letters = value.replace(/[^A-Z]/g, '').length;
            const numbers = value.replace(/[^0-9]/g, '').length;
            if (letters !== 3 || numbers !== 7) {
                error = 'Student ID must have 3 letters and 7 numbers.';
            }
        }
        if (error) {
            feedback.textContent = '⚠️ ' + error;
            input.classList.add('invalid');
            input.classList.remove('valid');
        } else {
            feedback.textContent = '';
            input.classList.remove('invalid');
            input.classList.add('valid');
        }
    }

    // Password strength and format validation
    function validatePassword() {
        const password = document.getElementById('new-password');
        const feedback = document.getElementById('password_feedback');
        const meter = document.getElementById('strengthMeterBar');
        const val = password.value;
        let strength = 0;
        let error = '';
        if (val.length >= 8) strength++;
        if (/[A-Z]/.test(val)) strength++;
        if (/[a-z]/.test(val)) strength++;
        if (/[0-9]/.test(val)) strength++;
        if (/[^a-zA-Z0-9]/.test(val)) strength++;

        // Strength meter
        meter.style.width = (strength * 20) + '%';
        meter.className = 'strength-meter-bar ' +
            (strength <= 2 ? 'strength-weak' : strength <= 4 ? 'strength-medium' : 'strength-strong');

        // Format feedback
        if (val.length < 8) {
            error = 'Password must be at least 8 characters.';
        } else if (!/[A-Z]/.test(val)) {
            error = 'Password must include an uppercase letter.';
        } else if (!/[a-z]/.test(val)) {
            error = 'Password must include a lowercase letter.';
        } else if (!/[0-9]/.test(val)) {
            error = 'Password must include a number.';
        } else if (!/[^a-zA-Z0-9]/.test(val)) {
            error = 'Password must include a symbol.';
        }

        if (error) {
            feedback.textContent = '⚠️ ' + error;
            password.classList.add('invalid');
            password.classList.remove('valid');
        } else {
            feedback.textContent = '';
            password.classList.remove('invalid');
            password.classList.add('valid');
        }
    }

    // Confirm password validation
    function validateConfirmPassword() {
        const password = document.getElementById('new-password');
        const confirmPassword = document.getElementById('confirm-password');
        const feedback = document.getElementById('confirm_password_feedback');
        if (confirmPassword.value !== password.value) {
            feedback.textContent = '⚠️ Passwords do not match.';
            confirmPassword.classList.add('invalid');
            confirmPassword.classList.remove('valid');
        } else {
            feedback.textContent = '';
            confirmPassword.classList.remove('invalid');
            confirmPassword.classList.add('valid');
        }
    }

    function togglePasswordVisibility(fieldId) {
        const input = document.getElementById(fieldId);
        if (input.type === 'password') {
            input.type = 'text';
        } else {
            input.type = 'password';
        }
    }

    // Convert security answer to lowercase
    function convertToLowercase(input) {
        input.value = input.value.toLowerCase();
    }
    </script>
</body>
</html>
