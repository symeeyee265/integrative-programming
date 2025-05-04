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

// --- Strategy Pattern Classes ---
interface ValidationStrategy {
    public function validate($value): ?string;
}
class RequiredValidation implements ValidationStrategy {
    public function validate($value): ?string {
        return empty(trim($value)) ? "This field is required." : null;
    }
}
class PasswordStrengthValidation implements ValidationStrategy {
    public function validate($value): ?string {
        if (strlen($value) < 8) return "Password must be at least 8 characters.";
        if (!preg_match('/[A-Z]/', $value)) return "Include an uppercase letter.";
        if (!preg_match('/[a-z]/', $value)) return "Include a lowercase letter.";
        if (!preg_match('/[0-9]/', $value)) return "Include a number.";
        if (!preg_match('/[^a-zA-Z0-9]/', $value)) return "Include a symbol.";
        return null;
    }
}
class EmailValidation implements ValidationStrategy {
    public function validate($value): ?string {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL) ||!str_ends_with($value, '@.com')) return "Invalid email format. Email must end with @.com";
        return null;
    }
}
class SecurityAnswerValidation implements ValidationStrategy {
    public function validate($value): ?string {
        return preg_match('/[^a-zA-Z0-9 \\-_]/', $value) ? "No special characters allowed." : null;
    }
}
class FieldValidator {
    private $strategies = [];
    public function addStrategy(ValidationStrategy $strategy) {
        $this->strategies[] = $strategy;
    }
    public function validate($value): array {
        $errors = [];
        foreach ($this->strategies as $strategy) {
            $error = $strategy->validate($value);
            if ($error) $errors[] = $error;
        }
        return $errors;
    }
}

// Helper function to check for special characters (except space, dash, underscore)
function has_special_char($str) {
    return preg_match('/[^a-zA-Z0-9 \-_]/', $str);
}

// Helper function for email validation
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && str_ends_with($email, '@.com');
}

// Helper: Only allow certain characters in email
function sanitize_email($email) {
    return preg_replace('/[^a-zA-Z0-9@.]/', '', $email);
}

// Helper: Only allow certain characters in addresses
function sanitize_address($str) {
    return preg_replace('/[^a-zA-Z0-9 ,.]/', '', $str);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize input data
    $full_name = htmlspecialchars(trim($_POST['full_name']), ENT_QUOTES, 'UTF-8');
    $student_id = htmlspecialchars(trim($_POST['student_id']), ENT_QUOTES, 'UTF-8');
    $email = sanitize_email(trim($_POST['email']));
    $program = htmlspecialchars(trim($_POST['program']), ENT_QUOTES, 'UTF-8');
    $password = $_POST['password']; // Don't sanitize password
    $confirm_password = $_POST['confirm_password'];
    $date_of_birth = htmlspecialchars(trim($_POST['date_of_birth']), ENT_QUOTES, 'UTF-8');
    $student_status = htmlspecialchars(trim($_POST['student_status']), ENT_QUOTES, 'UTF-8');
    $billing_address = sanitize_address(trim($_POST['billing_address']));
    $current_address = sanitize_address(trim($_POST['current_address']));
    $recaptcha_response = $_POST['g-recaptcha-response']?? '';

    // Length restrictions
    if (strlen($full_name) > 100) $errors[] = "Full name too long.";
    if (strlen($student_id) > 20) $errors[] = "Student ID too long.";
    if (strlen($email) > 100) $errors[] = "Email too long.";
    if (strlen($program) > 50) $errors[] = "Program too long.";
    if (strlen($billing_address) > 255) $errors[] = "Billing address too long.";
    if (strlen($current_address) > 255) $errors[] = "Current address too long.";

    // Special character validation
    if (has_special_char($full_name)) $errors[] = "Please don't put special character in Full Name.";
    if (has_special_char($student_id)) $errors[] = "Please don't put special character in Student ID.";
    if (has_special_char($program)) $errors[] = "Please don't put special character in Program.";
    if (has_special_char($student_status)) $errors[] = "Please don't put special character in Student Status.";

    // Email validation
    if (!is_valid_email($email)) $errors[] = "Please enter a valid email address ending with @.com.";

    // Password validation (example: at least 8 chars, upper, lower, number, symbol)
    if (strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = "Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.";
    }
    if ($password!== $confirm_password) $errors[] = "Passwords do not match.";

    // Required fields
    if (empty($full_name)) $errors[] = "Full name is required.";
    if (empty($student_id)) $errors[] = "Student ID is required.";
    if (empty($email)) $errors[] = "Email is required.";
    if (empty($program)) $errors[] = "Program of study is required.";
    if (empty($date_of_birth)) $errors[] = "Date of birth is required.";
    if (empty($student_status)) $errors[] = "Student status is required.";
    if (empty($billing_address)) $errors[] = "Billing address is required.";
    if (empty($current_address)) $errors[] = "Current address is required.";

    // Verify reCAPTCHA v3
    if (!empty($recaptcha_response)) {
        $recaptcha_secret = '6LfLcC0rAAAAALHgWE2Vo4ogBMtPTomQ7w2mmi92';
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
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE student_id =? OR email =?");
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
                    date_of_birth, student_status, billing_address, current_address
                ) VALUES (?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $student_id, $hashed_password, $full_name, $email, $program, $is_admin,
                $date_of_birth, $student_status, $billing_address, $current_address
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

    $securityAnswerValidator = new FieldValidator();
    $securityAnswerValidator->addStrategy(new RequiredValidation());
    $securityAnswerValidator->addStrategy(new SecurityAnswerValidation());
    $securityAnswerErrors = $securityAnswerValidator->validate($_POST['security_answer']);
}

// After successful registration, show a message and redirect
if ($success) {
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta http-equiv='refresh' content='3;url=login.php'><title>Registration Success</title><style>body{font-family:sans-serif;background:#f5f7fa;display:flex;align-items:center;justify-content:center;height:100vh;} .success-box{background:#fff;padding:2rem 3rem;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);text-align:center;} .success-box h1{color:#27ae60;} </style></head><body><div class='success-box'><h1>You've signed up successfully.</h1><p>Redirecting to login page...</p></div></body></html>";
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
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

        .form-group label.required::after {
            content: '*';
            color: red;
            margin-left: 3px;
        }

        .form-group input,.form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            height: 100px; /* Increase the height of textareas */
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

        .strength-meter { height: 5px; width: 100%; background: #eee; margin-top: 2px; }
        .strength-meter-bar { height: 100%; transition: width 0.3s; }
        .strength-weak { background: #e74c3c; }
        .strength-medium { background: #f1c40f; }
        .strength-strong { background: #2ecc71; }
        .show-hide { cursor: pointer; }

        .invalid {
            border: 1px solid red;
        }
        .valid {
            border: 1px solid green;
        }

        form {
            width: 300px;
            margin: 0 auto;
        }

        input {
            width: 100%;
            padding: 10px;
            border: 2px solid #ccc;
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 16px;
        }

       .error input {
            border-color: red;
        }

       .success input {
            border-color: green;
        }

        i {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
        }

       .error i {
            color: red;
        }

       .success i {
            color: green;
        }

        p {
            background-color: red;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            margin: 0;
            display: none;
        }

       .error p {
            display: block;
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
                    <label for="full-name" class="required">Full Name</label>
                    <input type="text" id="full-name" name="full_name" required oninput="validateInput(this, 'full_name'); this.value = this.value.toUpperCase()">
                    <span class="feedback" id="full_name_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="student-id" class="required">Student ID</label>
                    <input type="text" id="student-id" name="student_id" required oninput="validateInput(this,'student_id'); this.value = this.value.toUpperCase()">
                    <span class="feedback" id="student_id_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="email" class="required">Email</label>
                    <input type="email" id="email" name="email" required oninput="validateInput(this, 'email')">
                    <span class="feedback" id="email_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="program" class="required">Program of Study</label>
                    <select id="program" name="program" required onchange="validateInput(this, 'program')">
                        <option value="">Select your program</option>
                        <option value="Computer Science" <?php if (isset($_POST['program']) && $_POST['program'] === 'Computer Science') echo'selected';?>>Computer Science</option>
                        <option value="Engineering" <?php if (isset($_POST['program']) && $_POST['program'] === 'Engineering') echo'selected';?>>Engineering</option>
                        <option value="Business" <?php if (isset($_POST['program']) && $_POST['program'] === 'Business') echo'selected';?>>Business</option>
                        <option value="Arts & Humanities" <?php if (isset($_POST['program']) && $_POST['program'] === 'Arts & Humanities') echo'selected';?>>Arts & Humanities</option>
                        <option value="Natural Sciences" <?php if (isset($_POST['program']) && $_POST['program'] === 'Natural Sciences') echo'selected';?>>Natural Sciences</option>
                        <option value="Other" <?php if (isset($_POST['program']) && $_POST['program'] === 'Other') echo'selected';?>>Other</option>
                    </select>
                    <span class="feedback" id="program_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="password" class="required">Password</label>
                    <input type="password" id="password" name="password" required oninput="validatePassword()">
                    <span class="show-hide" onclick="togglePassword()">👁️</span>
                    <span class="feedback" id="password_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="confirm-password" class="required">Confirm Password</label>
                    <input type="password" id="confirm-password" name="confirm_password" required oninput="validateConfirmPassword()">
                    <span class="feedback" id="confirm_password_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="date-of-birth" class="required">Date of Birth</label>
                    <input type="date" id="date-of-birth" name="date_of_birth" required oninput="validateInput(this, 'date_of_birth')">
                    <span class="feedback" id="date_of_birth_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="student-status" class="required">Student Status</label>
                    <select id="student-status" name="student_status" required onchange="validateInput(this,'student_status')">
                        <option value="">Select Status</option>
                        <option value="Current Student">Current Student</option>
                        <option value="Alumni">Alumni</option>
                    </select>
                    <span class="feedback" id="student_status_feedback"></span>
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
                    <label for="security-answer" class="required">Security Answer</label>
                    <input type="text" id="security-answer" name="security_answer" maxlength="255" required oninput="validateInput(this, 'security_answer')">
                    <span class="feedback" id="security_answer_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="billing-address" class="required">Billing Address</label>
                    <textarea id="billing-address" name="billing_address" maxlength="255" required oninput="validateInput(this, 'billing_address')"></textarea>
                    <span class="feedback" id="billing_address_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="current-address" class="required">Current Address</label>
                    <textarea id="current-address" name="current_address" maxlength="255" required oninput="validateInput(this, 'current_address')"></textarea>
                    <span class="feedback" id="current_address_feedback"></span>
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
                grecaptcha.execute('6LfLcC0rAAAAAG3ZmASAUwWVyYf4dY4GBNmZRZFj', {action:'register'}).then(function (token) {
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

        // Password show/hide
        function togglePassword() {
            var pwd = document.getElementById('password');
            pwd.type = pwd.type === 'password'? 'text' : 'password';
        }

        // General input validation function
        function validateInput(input, fieldName) {
            const feedback = document.getElementById(`${fieldName}_feedback`);
            const value = input.value;
            let errorMessage = '';

            if (input.tagName === 'SELECT' && value === '') {
                errorMessage = 'This field is required.';
            } else if (input.type === 'text' || input.type === 'textarea') {
                if (value === '') {
                    errorMessage = 'This field is required.';
                } else if (fieldName === 'full_name' || fieldName ==='student_id' || fieldName === 'program' || fieldName ==='student_status') {
                    const specialCharRegex = /[^a-zA-Z0-9 \-_]/;
                    if (specialCharRegex.test(value)) {
                        errorMessage = `Please don't put special character in ${fieldName.replace('_','')}`;
                    }
                } else if (fieldName === 'email') {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.com$/;
                    if (!emailRegex.test(value)) {
                        errorMessage = 'Please enter a valid email address ending with @.com.';
                    }
                }
            }

            if (errorMessage) {
                feedback.textContent = `⚠️ ${errorMessage}`;
                input.classList.add('invalid');
                input.classList.remove('valid');
            } else {
                feedback.textContent = '';
                input.classList.add('valid');
                input.classList.remove('invalid');
            }
        }

        // Password validation
        function validatePassword() {
            const password = document.getElementById('password');
            const passwordFeedback = document.getElementById('password_feedback');
            const val = password.value;
            let strength = 0;
            if (val.length >= 8) strength++;
            if (/[A-Z]/.test(val)) strength++;
            if (/[a-z]/.test(val)) strength++;
            if (/[0-9]/.test(val)) strength++;
            if (/[^a-zA-Z0-9]/.test(val)) strength++;

            let errorMessage = '';
            if (val.length < 8) {
                errorMessage = 'Password must be at least 8 characters.';
            } else if (!/[A-Z]/.test(val)) {
                errorMessage = 'Include an uppercase letter.';
            } else if (!/[a-z]/.test(val)) {
                errorMessage = 'Include a lowercase letter.';
            } else if (!/[0-9]/.test(val)) {
                errorMessage = 'Include a number.';
            } else if (!/[^a-zA-Z0-9]/.test(val)) {
                errorMessage = 'Include a symbol.';
            }

            if (errorMessage) {
                passwordFeedback.textContent = `⚠️ ${errorMessage}`;
                password.classList.add('invalid');
                password.classList.remove('valid');
            } else {
                passwordFeedback.textContent = '';
                password.classList.add('valid');
                password.classList.remove('invalid');
            }
        }

        // Confirm password validation
        function validateConfirmPassword() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm-password');
            const confirmPasswordFeedback = document.getElementById('confirm_password_feedback');
            if (confirmPassword.value!== password.value) {
                confirmPasswordFeedback.textContent = '⚠️ Passwords do not match.';
                confirmPassword.classList.add('invalid');
                confirmPassword.classList.remove('valid');
            } else {
                confirmPasswordFeedback.textContent = '';
                confirmPassword.classList.add('valid');
                confirmPassword.classList.remove('invalid');
            }
        }
    </script>
</body>
</html>    
