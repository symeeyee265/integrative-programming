<?php
session_start();
require_once __DIR__ . '/dbConnection.php';
require_once __DIR__ . '/emailService.php';
require_once __DIR__ . '/oauth.php';
require_once __DIR__ . '/eligibilityCheck.php';

$errors = [];
$success = false;

// Initialize services
$emailService = new EmailService($conn);
$oauthService = new OAuthService($conn);
$eligibilityService = new EligibilityService($conn);

// Helper function to check for special characters (except space, dash, underscore)
function has_special_char($str) {
    return preg_match('/[^a-zA-Z0-9 \-_]/', $str);
}

// Helper function for password strength
function is_strong_password($password) {
    return strlen($password) >= 8 &&
        preg_match('/[A-Z]/', $password) &&
        preg_match('/[a-z]/', $password) &&
        preg_match('/[0-9]/', $password) &&
        preg_match('/[^a-zA-Z0-9]/', $password);
}

// Helper function for email validation
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && strpos($email, '@') !== false && strpos($email, '.') !== false;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize input data
    $full_name = trim($_POST['full_name']);
    $student_id = trim($_POST['student_id']);
    $email = trim($_POST['email']);
    $program = trim($_POST['program']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $date_of_birth = trim($_POST['date_of_birth']);
    $student_status = trim($_POST['student_status']);
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    // Validate for special characters (except password)
    if (has_special_char($full_name) || has_special_char($student_id) || has_special_char($program) || has_special_char($student_status)) {
        $errors[] = "Please don't put special character";
    }

    // Validate email
    if (!is_valid_email($email)) {
        $errors[] = "Please enter a valid email address.";
    }

    // Validate password strength
    if (!is_strong_password($password)) {
        $errors[] = "Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.";
    }

    // Validate password match
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Validate required fields
    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    }
    if (empty($student_id)) {
        $errors[] = "Student ID is required.";
    }
    if (empty($email)) {
        $errors[] = "Email is required.";
    }
    if (empty($program)) {
        $errors[] = "Program of study is required.";
    }
    if (empty($date_of_birth)) {
        $errors[] = "Date of birth is required.";
    }
    if (empty($student_status)) {
        $errors[] = "Student status is required.";
    }

    // Sanitize all inputs except password
    $full_name = htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8');
    $student_id = htmlspecialchars($student_id, ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $program = htmlspecialchars($program, ENT_QUOTES, 'UTF-8');
    $date_of_birth = htmlspecialchars($date_of_birth, ENT_QUOTES, 'UTF-8');
    $student_status = htmlspecialchars($student_status, ENT_QUOTES, 'UTF-8');

    // Verify reCAPTCHA v3
    if (!empty($recaptcha_response)) {
        $recaptcha_secret = 'YOUR_SECRET_KEY';
        $recaptcha_verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret}&response={$recaptcha_response}");
        $recaptcha_data = json_decode($recaptcha_verify);
        // Set a score threshold (0.5 in this example)
        if (!$recaptcha_data->success || $recaptcha_data->score < 0.5) {
            $errors[] = "reCAPTCHA verification failed.";
        }
    } else {
        $errors[] = "Please complete the reCAPTCHA verification.";
    }

    // Check eligibility
    if (empty($errors)) {
        $eligibility = $eligibilityService->mockGovernmentAPI($date_of_birth, $student_status, $student_status);
        if (!$eligibility['isEligible']) {
            $errors = array_merge($errors, $eligibility['errors']);
        }
    }

    // Check if student ID or email already exists
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE student_id = ? OR email = ?");
            $stmt->execute([$student_id, $email]);

            if ($stmt->rowCount() > 0) {
                $errors[] = "Student ID or email already registered.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error. Please try again later.";
        }
    }

    // If no errors, insert new user
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $is_admin = 0; // New users are not admins by default

            $stmt = $conn->prepare("
                INSERT INTO users (
                    student_id, password, full_name, email, program, is_admin,
                    date_of_birth, student_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $student_id, $hashed_password, $full_name, $email, $program, $is_admin,
                $date_of_birth, $student_status
            ]);

            $userId = $conn->lastInsertId();

            // Generate and send verification email
            $token = $emailService->generateVerificationToken($userId);
            if ($token) {
                $emailService->sendVerificationEmail($email, $full_name, $token);
            }

            $success = true;

        } catch (PDOException $e) {
            $errors[] = "Registration failed. Please try again.";
        }
    }
}

// After successful registration, show a message and redirect
if ($success) {
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta http-equiv='refresh' content='3;url=login.php'><title>Registration Success</title><style>body{font-family:sans-serif;background:#f5f7fa;display:flex;align-items:center;justify-content:center;height:100vh;} .success-box{background:#fff;padding:2rem 3rem;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);text-align:center;} .success-box h1{color:#27ae60;} </style></head><body><div class='success-box'><h1>You're all set! Registration completed successfully.</h1><p>Redirecting to login page...</p></div></body></html>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - EduVote</title>
    <script src="https://www.google.com/recaptcha/api.js?render=YOUR_SITE_KEY"></script>
    <style>
        /* Reuse the same styles from login.php for consistency */
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
            color: red;
            text-align: center;
            margin-bottom: 1rem;
        }

        .social-login {
            margin-top: 1rem;
            text-align: center;
        }

        .social-login button {
            margin: 0.5rem;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }

        .google-btn {
            background: #DB4437;
            color: white;
        }

        .facebook-btn {
            background: #4267B2;
            color: white;
        }

        .eligibility-status {
            margin: 1rem 0;
            padding: 1rem;
            border-radius: 4px;
        }

        .eligible {
            background: #d4edda;
            color: #155724;
        }

        .not-eligible {
            background: #f8d7da;
            color: #721c24;
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
            <h1>Student Registration</h1>

            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="post" id="registrationForm">
                <div class="form-group">
                    <label for="full-name">Full Name</label>
                    <input type="text" id="full-name" name="full_name" required>
                </div>

                <div class="form-group">
                    <label for="student-id">Student ID</label>
                    <input type="text" id="student-id" name="student_id" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="program">Program of Study</label>
                    <select id="program" name="program" required>
                        <option value="">Select your program</option>
                        <option value="Computer Science" <?php if (isset($_POST['program']) && $_POST['program'] === 'Computer Science') echo 'selected'; ?>>Computer Science</option>
                        <option value="Engineering" <?php if (isset($_POST['program']) && $_POST['program'] === 'Engineering') echo 'selected'; ?>>Engineering</option>
                        <option value="Business" <?php if (isset($_POST['program']) && $_POST['program'] === 'Business') echo 'selected'; ?>>Business</option>
                        <option value="Arts & Humanities" <?php if (isset($_POST['program']) && $_POST['program'] === 'Arts & Humanities') echo 'selected'; ?>>Arts & Humanities</option>
                        <option value="Natural Sciences" <?php if (isset($_POST['program']) && $_POST['program'] === 'Natural Sciences') echo 'selected'; ?>>Natural Sciences</option>
                        <option value="Other" <?php if (isset($_POST['program']) && $_POST['program'] === 'Other') echo 'selected'; ?>>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-group">
                    <label for="confirm-password">Confirm Password</label>
                    <input type="password" id="confirm-password" name="confirm_password" required>
                </div>

                <div class="form-group">
                    <label for="date-of-birth">Date of Birth</label>
                    <input type="date" id="date-of-birth" name="date_of_birth" required>
                </div>

                <div class="form-group">
                    <label for="student-status">Student Status</label>
                    <select id="student-status" name="student_status" required>
                        <option value="">Select Status</option>
                        <option value="Current Student">Current Student</option>
                        <option value="Alumni">Alumni</option>
                    </select>
                </div>

                <button type="submit" class="btn">Register</button>
            </form>

            <div class="auth-links">
                <a href="login.php">Already have an account? Login</a>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; <?= date('Y') ?> EduVote. All rights reserved.</p>
    </footer>

    <script>
        document.getElementById('registrationForm').addEventListener('submit', function (e) {
            e.preventDefault();
            grecaptcha.ready(function () {
                grecaptcha.execute('YOUR_SITE_KEY', {action:'register'}).then(function (token) {
                    var form = document.getElementById('registrationForm');
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'g-recaptcha-response';
                    input.value = token;
                    form.appendChild(input);
                    form.submit();
                });
            });
        });
    </script>
</body>
</html>    
