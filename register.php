<!--name : sok yee-->


<?php
session_start();
require_once 'dbConnection.php';

// --- Strategy Pattern Classes ---
interface ValidationStrategy {
    public function validate($value):?string;
}

class RequiredValidation implements ValidationStrategy {
    public function validate($value):?string {
        return empty(trim($value))? "This field is required." : null;
    }
}

class PasswordStrengthValidation implements ValidationStrategy {
    public function validate($value):?string {
        if (strlen($value) < 8)
            return "Password must be at least 8 characters.";
        if (!preg_match('/[A-Z]/', $value))
            return "Include an uppercase letter.";
        if (!preg_match('/[a-z]/', $value))
            return "Include a lowercase letter.";
        if (!preg_match('/[0-9]/', $value))
            return "Include a number.";
        if (!preg_match('/[^a-zA-Z0-9]/', $value))
            return "Include a symbol.";
        return null;
    }
}

class EmailValidation implements ValidationStrategy {
    public function validate($value):?string {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL) || !preg_match('/@student\.tarc\.edu\.my$/', $value)) {
            return "Email must end with @student.tarc.edu.my";
        }
        return null;
    }
}

class FullNameValidation implements ValidationStrategy {
    public function validate($value):?string {
        if (strlen($value) > 20) {
            return "Full name must not exceed 20 characters.";
        }
        if (!preg_match('/^[a-zA-Z\s]+$/', $value)) {
            return "Full name can only contain letters and spaces.";
        }
        return null;
    }
}

class StudentIdValidation implements ValidationStrategy {
    public function validate($value):?string {
        if (strlen($value) !== 10) {
            return "Student ID must be exactly 10 characters.";
        }
        if (!preg_match('/^[0-9]{2}[A-Z]{3}[0-9]{5}$/', $value)) {
            return "Student ID must be in format: 2 numbers, 3 letters, followed by 5 numbers.";
        }
        return null;
    }
}

class SecurityAnswerValidation implements ValidationStrategy {
    public function validate($value):?string {
        if (!preg_match('/^[a-z0-9\s]+$/', $value)) {
            return "Security answer can only contain lowercase letters, numbers, and spaces.";
        }
        return null;
    }
}

class PasswordStrengthStrategy implements ValidationStrategy {
    public function validate($value):?string {
        return null;
    }

    public function getStrength($value): int {
        $strength = 0;
        if (strlen($value) >= 8)
            $strength++;
        if (preg_match('/[A-Z]/', $value))
            $strength++;
        if (preg_match('/[a-z]/', $value))
            $strength++;
        if (preg_match('/[0-9]/', $value))
            $strength++;
        if (preg_match('/[^a-zA-Z0-9]/', $value))
            $strength++;
        return $strength;
    }
}

class SecurityValidationStrategy implements ValidationStrategy {
    public function validate($value):?string {
        if (preg_match('/<script|onerror|onload|<img|<svg|<iframe|<object|<embed|<a /i', $value)) {
            return "Input contains potential XSS attack patterns.";
        }
        if (preg_match('/(select|insert|update|delete|drop|union|--|#|;)/i', $value)) {
            return "Input contains potential SQL injection patterns.";
        }
        return null;
    }
}

class AddressValidation implements ValidationStrategy {
    public function validate($value):?string {
        $wordCount = str_word_count($value);
        if ($wordCount > 50) {
            return "Address must not exceed 50 words.";
        }
        return null;
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
            if ($error)
                $errors[] = $error;
        }
        return $errors;
    }
}

// Helper functions
function has_special_char($str) {
    return preg_match('/[^a-zA-Z0-9 \-_]/', $str);
}

function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && preg_match('/@student\.tarc\.edu\.my$/', $email);
}

function sanitize_email($email) {
    return preg_replace('/[^a-zA-Z0-9@.]/', '', $email);
}

function sanitize_address($str) {
    return preg_replace('/[^a-zA-Z0-9 ,.-]/', '', $str);
}

function has_special_char_billing($str) {
    return preg_match('/[^a-zA-Z0-9 ,.-]/', $str);
}

// Helper functions for sanitization
function sanitizeInput($input) {
    // Remove all special characters except letters, numbers, and spaces
    return preg_replace('/[^a-zA-Z0-9\s]/', '', $input);
}

function sanitizeEmail($email) {
    // Only allow letters, numbers, @, and . for email
    return preg_replace('/[^a-zA-Z0-9@.]/', '', $email);
}

function sanitizeAddress($address) {
    // Allow letters, numbers, spaces, commas, periods, and hyphens for addresses
    return preg_replace('/[^a-zA-Z0-9\s,.-]/', '', $address);
}

$errors = [];
$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize input data
    $full_name = strtoupper(sanitizeInput(trim($_POST['full_name'])));
    $student_id = strtoupper(sanitizeInput(trim($_POST['student_id'])));
    $email = strtolower(sanitizeEmail(trim($_POST['email'])));
    $program = sanitizeInput(trim($_POST['program']));
    $security_question = sanitizeInput(trim($_POST['security_question']));
    $security_answer = strtolower(sanitizeInput(trim($_POST['security_answer'])));
    $billing_address = sanitizeAddress(trim($_POST['billing_address']));
    $current_address = sanitizeAddress(trim($_POST['current_address']));
    $password = $_POST['password']; // No sanitization for password
    $confirm_password = $_POST['confirm_password']; // No sanitization for password

    // Initialize validators
    $nameValidator = new FieldValidator();
    $nameValidator->addStrategy(new RequiredValidation());
    $nameValidator->addStrategy(new FullNameValidation());
    $nameValidator->addStrategy(new SecurityValidationStrategy());

    $studentIdValidator = new FieldValidator();
    $studentIdValidator->addStrategy(new RequiredValidation());
    $studentIdValidator->addStrategy(new StudentIdValidation());
    $studentIdValidator->addStrategy(new SecurityValidationStrategy());

    $emailValidator = new FieldValidator();
    $emailValidator->addStrategy(new RequiredValidation());
    $emailValidator->addStrategy(new EmailValidation());
    $emailValidator->addStrategy(new SecurityValidationStrategy());

    $passwordValidator = new FieldValidator();
    $passwordValidator->addStrategy(new RequiredValidation());
    $passwordValidator->addStrategy(new PasswordStrengthValidation());
    $passwordValidator->addStrategy(new SecurityValidationStrategy());

    $securityAnswerValidator = new FieldValidator();
    $securityAnswerValidator->addStrategy(new RequiredValidation());
    $securityAnswerValidator->addStrategy(new SecurityAnswerValidation());
    $securityAnswerValidator->addStrategy(new SecurityValidationStrategy());

    $addressValidator = new FieldValidator();
    $addressValidator->addStrategy(new RequiredValidation());
    $addressValidator->addStrategy(new AddressValidation());
    $addressValidator->addStrategy(new SecurityValidationStrategy());

    // Validate inputs
    $errors = array_merge(
        $nameValidator->validate($full_name),
        $studentIdValidator->validate($student_id),
        $emailValidator->validate($email),
        $passwordValidator->validate($password),
        $securityAnswerValidator->validate($security_answer),
        $addressValidator->validate($billing_address),
        $addressValidator->validate($current_address)
    );

    // Validate program
    if (empty($program)) {
        $errors[] = "Program of study is required.";
    }

    // Validate security question
    if (empty($security_question)) {
        $errors[] = "Security question is required.";
    }

    // Validate password confirmation
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Check if student ID or email already exists
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT student_id FROM users WHERE student_id = ? OR email = ?");
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
            
            $stmt = $conn->prepare("INSERT INTO users (student_id, password, full_name, email, program, security_question, security_answer, billing_address, current_address, is_admin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $student_id, 
                $hashed_password, 
                $full_name, 
                $email, 
                $program,
                $security_question,
                $security_answer,
                $billing_address,
                $current_address,
                $is_admin
            ]);
            
            $success = true;
        } catch (PDOException $e) {
            $errors[] = "Registration failed. Please try again.";
        }
    }
}

// Redirect to login page if registration was successful
if ($success) {
    header("Location: login.php?registration=success");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - EduVote</title>
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

        /* Password strength meter styles */
        .strength-meter {
            height: 5px;
            background-color: #eee;
            margin-top: 10px;
            border-radius: 3px;
            overflow: hidden;
        }

        .strength-meter-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s ease;
        }

        .strength-weak {
            background-color: #dc3545;
        }

        .strength-medium {
            background-color: #ffc107;
        }

        .strength-strong {
            background-color: #28a745;
        }

        .feedback {
            display: block;
            margin-top: 5px;
            font-size: 0.875rem;
            transition: color 0.3s ease;
        }

        .form-group {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
        }

        .password-toggle:hover {
            color: #333;
        }

        /* Form input styles */
        .form-input {
            padding: 10px;
            border: 2px solid #ccc;
            border-radius: 4px;
            width: 200px;
        }

        /* Address textarea specific styles */
        textarea.form-input {
            width: 100%;
            min-height: 100px;
            resize: vertical;
        }

        /* Valid input (green background and border) */
        .valid {
            background-color: #d4edda;
            border-color: #28a745;
        }

        /* Invalid input (red background and border) */
        .invalid {
            background-color: #f8d7da;
            border-color: #dc3545;
        }

        .form-group input.invalid {
            border-color: #dc3545;
            background-color: #fff8f8;
        }

        .form-group input.valid {
            border-color: #28a745;
            background-color: #f8fff8;
        }

        .form-group input:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.25);
        }

        .form-group input.invalid:focus {
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.25);
        }
    </style>
    <script>
        // Sanitization functions for client-side
        function sanitizeInput(value) {
            // Remove all special characters except letters, numbers, and spaces
            return value.replace(/[^a-zA-Z0-9\s]/g, '');
        }

        function sanitizeEmail(value) {
            // Only allow letters, numbers, @, and . for email
            return value.replace(/[^a-zA-Z0-9@.]/g, '');
        }

        function sanitizeAddress(value) {
            // Allow letters, numbers, spaces, commas, periods, and hyphens for addresses
            return value.replace(/[^a-zA-Z0-9\s,.-]/g, '');
        }

        // General input validation function
        function validateInput(input, fieldName) {
            const feedback = document.getElementById(`${fieldName}_feedback`);
            let value = input.value;
            let errorMessage = '';

            // Apply sanitization based on field type
            if (fieldName === 'email') {
                value = sanitizeEmail(value.toLowerCase());
                input.value = value; // Update input with sanitized value immediately
                
                // Real-time validation for email with detailed feedback
                if (value === '') {
                    errorMessage = 'Email is required';
                } else if (!value.includes('@')) {
                    errorMessage = 'Email must contain @ symbol';
                } else if (!value.includes('@student.tarc.edu.my')) {
                    if (value.includes('@') && !value.includes('@student.tarc.edu.my')) {
                        errorMessage = 'Email must end with @student.tarc.edu.my';
                    } else if (!value.includes('.')) {
                        errorMessage = 'Email must contain a domain (e.g., .edu.my)';
                    }
                } else if (value.split('@')[0].length === 0) {
                    errorMessage = 'Please enter your username before @student.tarc.edu.my';
                } else if (!value.match(/^[^@]+@student\.tarc\.edu\.my$/)) {
                    errorMessage = 'Invalid email format';
                }
            } else if (fieldName === 'billing_address' || fieldName === 'current_address') {
                value = sanitizeAddress(value);
            } else if (fieldName !== 'password' && fieldName !== 'confirm_password') {
                value = sanitizeInput(value);
            }

            // Update input value with sanitized version
            if (fieldName !== 'password' && fieldName !== 'confirm_password') {
                input.value = value;
            }

            if (input.type === 'text' || input.type === 'textarea') {
                if (value === '') {
                    errorMessage = 'This field is required.';
                } else if (fieldName === 'full_name') {
                    value = value.toUpperCase();
                    input.value = value;
                    if (value.length > 20) {
                        errorMessage = 'Full name must not exceed 20 characters.';
                    } else if (!/^[A-Z\s]+$/.test(value)) {
                        errorMessage = 'Full name can only contain letters and spaces.';
                    }
                } else if (fieldName === 'student_id') {
                    // Limit to 10 characters
                    if (value.length > 10) {
                        value = value.slice(0, 10);
                        input.value = value;
                    }
                    value = value.toUpperCase();
                    input.value = value;
                    
                    // Real-time validation for student ID
                    if (value.length !== 10) {
                        errorMessage = 'Student ID must be exactly 10 characters.';
                    } else if (!/^[0-9]{2}[A-Z]{3}[0-9]{5}$/.test(value)) {
                        errorMessage = 'Student ID must be in format: 2 numbers, 3 letters, followed by 5 numbers.';
                    }
                } else if (fieldName === 'email') {
                    // Real-time validation for email
                    if (!value.includes('@student.tarc.edu.my')) {
                        errorMessage = 'Email must end with @student.tarc.edu.my';
                    }
                } else if (fieldName === 'security_answer') {
                    value = value.toLowerCase();
                    input.value = value;
                    if (!/^[a-z0-9\s]+$/.test(value)) {
                        errorMessage = 'Security answer can only contain lowercase letters, numbers, and spaces.';
                    }
                } else if (fieldName === 'billing_address' || fieldName === 'current_address') {
                    const wordCount = value.trim().split(/\s+/).length;
                    if (wordCount > 50) {
                        errorMessage = 'Address must not exceed 50 words.';
                    }
                }
            }

            // Update visual feedback with more detailed styling
            if (errorMessage) {
                feedback.textContent = `⚠️ ${errorMessage}`;
                feedback.style.color = '#dc3545';
                input.classList.add('invalid');
                input.classList.remove('valid');
            } else {
                feedback.textContent = '✓ Valid format';
                feedback.style.color = '#28a745';
                input.classList.add('valid');
                input.classList.remove('invalid');
            }
        }

        // Add immediate validation on input
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            if (emailInput) {
                emailInput.addEventListener('input', function() {
                    validateInput(this, 'email');
                });
                emailInput.addEventListener('blur', function() {
                    validateInput(this, 'email');
                });
                // Validate on initial load
                validateInput(emailInput, 'email');
            }
            const inputs = document.querySelectorAll('input[type="text"], input[type="email"], textarea');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    validateInput(this, this.id);
                });
                // Validate on blur as well
                input.addEventListener('blur', function() {
                    validateInput(this, this.id);
                });
            });
        });

        // Password validation
        function validatePassword() {
            const password = document.getElementById('password');
            const passwordFeedback = document.getElementById('password_feedback');
            const val = password.value;
            let strength = 0;
            if (val.length >= 8)
                strength++;
            if (/[A-Z]/.test(val))
                strength++;
            if (/[a-z]/.test(val))
                strength++;
            if (/[0-9]/.test(val))
                strength++;
            if (/[^a-zA-Z0-9]/.test(val))
                strength++;

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

            updateStrengthMeter();
        }

        // Confirm password validation
        function validateConfirmPassword() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm-password');
            const confirmPasswordFeedback = document.getElementById('confirm_password_feedback');
            if (confirmPassword.value !== password.value) {
                confirmPasswordFeedback.textContent = '⚠️ Passwords do not match.';
                confirmPassword.classList.add('invalid');
                confirmPassword.classList.remove('valid');
            } else {
                confirmPasswordFeedback.textContent = '';
                confirmPassword.classList.add('valid');
                confirmPassword.classList.remove('invalid');
            }
        }

        function updateStrengthMeter() {
            const password = document.getElementById('password').value;
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            const meter = document.getElementById('strengthMeterBar');
            meter.style.width = (strength * 20) + '%';
            meter.className = 'strength-meter-bar ' +
                (strength <= 2 ? 'strength-weak' : strength <= 4 ? 'strength-medium' : 'strength-strong');
        }

        // Password show/hide
        function togglePasswordVisibility(fieldId) {
            const input = document.getElementById(fieldId);
            const button = input.nextElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                button.textContent = 'Hide';
            } else {
                input.type = 'password';
                button.textContent = 'Show';
            }
        }
    </script>
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

            <form action="register.php" method="post" onsubmit="return validateForm()">
                <div class="form-group">
                    <label for="full-name">Full Name</label>
                    <input type="text" id="full-name" name="full_name" class="form-input" required 
                           value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>" 
                           placeholder="As it appears on school records"
                           oninput="validateInput(this, 'full_name')">
                    <span class="feedback" id="full_name_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="reg-student-id">Student ID</label>
                    <input type="text" id="reg-student-id" name="student_id" class="form-input" required 
                           value="<?= isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : '' ?>" 
                           placeholder="Your official student ID"
                           oninput="validateInput(this, 'student_id')">
                    <span class="feedback" id="student_id_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="email">Institutional Email</label>
                    <input type="email" id="email" name="email" class="form-input" required 
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" 
                           placeholder="your.id@student.tarc.edu.my"
                           oninput="validateInput(this, 'email')">
                    <span class="feedback" id="email_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="program">Program of Study</label>
                    <select id="program" name="program" class="form-input" required
                            onchange="validateInput(this, 'program')">
                        <option value="">Select your program</option>
                        <option value="Computer Science" <?= (isset($_POST['program']) && $_POST['program'] === 'Computer Science') ? 'selected' : '' ?>>Computer Science</option>
                        <option value="Engineering" <?= (isset($_POST['program']) && $_POST['program'] === 'Engineering') ? 'selected' : '' ?>>Engineering</option>
                        <option value="Business" <?= (isset($_POST['program']) && $_POST['program'] === 'Business') ? 'selected' : '' ?>>Business</option>
                        <option value="Arts & Humanities" <?= (isset($_POST['program']) && $_POST['program'] === 'Arts & Humanities') ? 'selected' : '' ?>>Arts & Humanities</option>
                        <option value="Natural Sciences" <?= (isset($_POST['program']) && $_POST['program'] === 'Natural Sciences') ? 'selected' : '' ?>>Natural Sciences</option>
                        <option value="Other" <?= (isset($_POST['program']) && $_POST['program'] === 'Other') ? 'selected' : '' ?>>Other</option>
                    </select>
                    <span class="feedback" id="program_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="security-question">Security Question</label>
                    <select id="security-question" name="security_question" class="form-input" required>
                        <option value="">Select a question</option>
                        <option value="What is your mother's maiden name?" <?= (isset($_POST['security_question']) && $_POST['security_question'] === 'What is your mother\'s maiden name?') ? 'selected' : '' ?>>What is your mother's maiden name?</option>
                        <option value="What was your first pet's name?" <?= (isset($_POST['security_question']) && $_POST['security_question'] === 'What was your first pet\'s name?') ? 'selected' : '' ?>>What was your first pet's name?</option>
                        <option value="What is your favorite book?" <?= (isset($_POST['security_question']) && $_POST['security_question'] === 'What is your favorite book?') ? 'selected' : '' ?>>What is your favorite book?</option>
                        <option value="What city were you born in?" <?= (isset($_POST['security_question']) && $_POST['security_question'] === 'What city were you born in?') ? 'selected' : '' ?>>What city were you born in?</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="security-answer">Security Answer</label>
                    <input type="text" id="security-answer" name="security_answer" class="form-input" 
                           maxlength="255" required
                           value="<?= isset($_POST['security_answer']) ? htmlspecialchars($_POST['security_answer']) : '' ?>"
                           oninput="validateInput(this, 'security_answer')">
                    <span class="feedback" id="security_answer_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="billing-address">Billing Address</label>
                    <textarea id="billing-address" name="billing_address" class="form-input" 
                              maxlength="255" required
                              oninput="validateInput(this, 'billing_address')"
                              placeholder="Enter your billing address"
                              rows="4"><?= isset($_POST['billing_address']) ? htmlspecialchars($_POST['billing_address']) : '' ?></textarea>
                    <span class="feedback" id="billing_address_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="current-address">Current Address</label>
                    <textarea id="current-address" name="current_address" class="form-input" 
                              maxlength="255" required
                              oninput="validateInput(this, 'current_address')"
                              placeholder="Enter your current address"
                              rows="4"><?= isset($_POST['current_address']) ? htmlspecialchars($_POST['current_address']) : '' ?></textarea>
                    <span class="feedback" id="current_address_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="password">Create Password</label>
                    <input type="password" id="password" name="password" class="form-input" required 
                           placeholder="At least 8 characters"
                           oninput="validatePassword()">
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password')">Show</button>
                    <span class="feedback" id="password_feedback"></span>
                    <div class="strength-meter">
                        <div class="strength-meter-bar" id="strengthMeterBar"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm-password">Confirm Password</label>
                    <input type="password" id="confirm-password" name="confirm_password" class="form-input" required 
                           placeholder="Re-enter your password"
                           oninput="validateConfirmPassword()">
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirm-password')">Show</button>
                    <span class="feedback" id="confirm_password_feedback"></span>
                </div>

                <button type="submit" class="btn">Register</button>

                <div class="auth-links">
                    <a href="login.php">Already have an account? Login</a>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <p>&copy; 2024 EduVote - Campus Voting System</p>
        <p>Contact: <a href="mailto:support@eduvote.edu" style="color: white;">support@eduvote.edu</a></p>
    </footer>
</body>
</html><!--name : sok yee-->


<?php
session_start();
require_once 'dbConnection.php';

// --- Strategy Pattern Classes ---
interface ValidationStrategy {
    public function validate($value):?string;
}

class RequiredValidation implements ValidationStrategy {
    public function validate($value):?string {
        return empty(trim($value))? "This field is required." : null;
    }
}

class PasswordStrengthValidation implements ValidationStrategy {
    public function validate($value):?string {
        if (strlen($value) < 8)
            return "Password must be at least 8 characters.";
        if (!preg_match('/[A-Z]/', $value))
            return "Include an uppercase letter.";
        if (!preg_match('/[a-z]/', $value))
            return "Include a lowercase letter.";
        if (!preg_match('/[0-9]/', $value))
            return "Include a number.";
        if (!preg_match('/[^a-zA-Z0-9]/', $value))
            return "Include a symbol.";
        return null;
    }
}

class EmailValidation implements ValidationStrategy {
    public function validate($value):?string {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-zA-Z0-9]+@student\.tarc\.edu\.my$/', $value)) {
            return "Email must be a valid format and end with @student.tarc.edu.my. No special characters allowed except @ and .";
        }
        return null;
    }
}

class FullNameValidation implements ValidationStrategy {
    public function validate($value):?string {
        if (strlen($value) > 20) {
            return "Full name must not exceed 20 characters.";
        }
        if (!preg_match('/^[a-zA-Z\s]+$/', $value)) {
            return "Full name can only contain letters and spaces.";
        }
        return null;
    }
}

class StudentIdValidation implements ValidationStrategy {
    public function validate($value):?string {
        if (strlen($value) !== 10) {
            return "Student ID must be exactly 10 characters.";
        }
        if (!preg_match('/^[A-Z]{3}[0-9]{7}$/', $value)) {
            return "Student ID must be in format: 3 letters followed by 7 numbers (e.g., 23WMR10564).";
        }
        return null;
    }
}

class SecurityAnswerValidation implements ValidationStrategy {
    public function validate($value):?string {
        if (!preg_match('/^[a-z0-9\s]+$/', $value)) {
            return "Security answer can only contain lowercase letters, numbers, and spaces.";
        }
        return null;
    }
}

class PasswordStrengthStrategy implements ValidationStrategy {
    public function validate($value):?string {
        return null;
    }

    public function getStrength($value): int {
        $strength = 0;
        if (strlen($value) >= 8)
            $strength++;
        if (preg_match('/[A-Z]/', $value))
            $strength++;
        if (preg_match('/[a-z]/', $value))
            $strength++;
        if (preg_match('/[0-9]/', $value))
            $strength++;
        if (preg_match('/[^a-zA-Z0-9]/', $value))
            $strength++;
        return $strength;
    }
}

class SecurityValidationStrategy implements ValidationStrategy {
    public function validate($value):?string {
        if (preg_match('/<script|onerror|onload|<img|<svg|<iframe|<object|<embed|<a /i', $value)) {
            return "Input contains potential XSS attack patterns.";
        }
        if (preg_match('/(select|insert|update|delete|drop|union|--|#|;)/i', $value)) {
            return "Input contains potential SQL injection patterns.";
        }
        return null;
    }
}

class AddressValidation implements ValidationStrategy {
    public function validate($value):?string {
        $wordCount = str_word_count($value);
        if ($wordCount > 50) {
            return "Address must not exceed 50 words.";
        }
        return null;
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
            if ($error)
                $errors[] = $error;
        }
        return $errors;
    }
}

// Helper functions
function has_special_char($str) {
    return preg_match('/[^a-zA-Z0-9 \-_]/', $str);
}

function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && preg_match('/@student\.tarc\.edu\.my$/', $email);
}

function sanitize_email($email) {
    return preg_replace('/[^a-zA-Z0-9@.]/', '', $email);
}

function sanitize_address($str) {
    return preg_replace('/[^a-zA-Z0-9 ,.-]/', '', $str);
}

function has_special_char_billing($str) {
    return preg_match('/[^a-zA-Z0-9 ,.-]/', $str);
}

// Helper functions for sanitization
function sanitizeInput($input) {
    // Remove all special characters except letters, numbers, and spaces
    return preg_replace('/[^a-zA-Z0-9\s]/', '', $input);
}

function sanitizeEmail($email) {
    // Only allow letters, numbers, @, and . for email
    return preg_replace('/[^a-zA-Z0-9@.]/', '', $email);
}

function sanitizeAddress($address) {
    // Allow letters, numbers, spaces, commas, periods, and hyphens for addresses
    return preg_replace('/[^a-zA-Z0-9\s,.-]/', '', $address);
}

$errors = [];
$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize input data
    $full_name = strtoupper(sanitizeInput(trim($_POST['full_name'])));
    $student_id = strtoupper(sanitizeInput(trim($_POST['student_id'])));
    $email = strtolower(sanitizeEmail(trim($_POST['email'])));
    $program = sanitizeInput(trim($_POST['program']));
    $security_question = sanitizeInput(trim($_POST['security_question']));
    $security_answer = strtolower(sanitizeInput(trim($_POST['security_answer'])));
    $billing_address = sanitizeAddress(trim($_POST['billing_address']));
    $current_address = sanitizeAddress(trim($_POST['current_address']));
    $password = $_POST['password']; // No sanitization for password
    $confirm_password = $_POST['confirm_password']; // No sanitization for password

    // Initialize validators
    $nameValidator = new FieldValidator();
    $nameValidator->addStrategy(new RequiredValidation());
    $nameValidator->addStrategy(new FullNameValidation());
    $nameValidator->addStrategy(new SecurityValidationStrategy());

    $studentIdValidator = new FieldValidator();
    $studentIdValidator->addStrategy(new RequiredValidation());
    $studentIdValidator->addStrategy(new StudentIdValidation());
    $studentIdValidator->addStrategy(new SecurityValidationStrategy());

    $emailValidator = new FieldValidator();
    $emailValidator->addStrategy(new RequiredValidation());
    $emailValidator->addStrategy(new EmailValidation());
    $emailValidator->addStrategy(new SecurityValidationStrategy());

    $passwordValidator = new FieldValidator();
    $passwordValidator->addStrategy(new RequiredValidation());
    $passwordValidator->addStrategy(new PasswordStrengthValidation());
    $passwordValidator->addStrategy(new SecurityValidationStrategy());

    $securityAnswerValidator = new FieldValidator();
    $securityAnswerValidator->addStrategy(new RequiredValidation());
    $securityAnswerValidator->addStrategy(new SecurityAnswerValidation());
    $securityAnswerValidator->addStrategy(new SecurityValidationStrategy());

    $addressValidator = new FieldValidator();
    $addressValidator->addStrategy(new RequiredValidation());
    $addressValidator->addStrategy(new AddressValidation());
    $addressValidator->addStrategy(new SecurityValidationStrategy());

    // Validate inputs
    $errors = array_merge(
        $nameValidator->validate($full_name),
        $studentIdValidator->validate($student_id),
        $emailValidator->validate($email),
        $passwordValidator->validate($password),
        $securityAnswerValidator->validate($security_answer),
        $addressValidator->validate($billing_address),
        $addressValidator->validate($current_address)
    );

    // Validate program
    if (empty($program)) {
        $errors[] = "Program of study is required.";
    }

    // Validate security question
    if (empty($security_question)) {
        $errors[] = "Security question is required.";
    }

    // Validate password confirmation
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Check if student ID or email already exists
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT student_id FROM users WHERE student_id = ? OR email = ?");
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
            
            $stmt = $conn->prepare("INSERT INTO users (student_id, password, full_name, email, program, security_question, security_answer, billing_address, current_address, is_admin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $student_id, 
                $hashed_password, 
                $full_name, 
                $email, 
                $program,
                $security_question,
                $security_answer,
                $billing_address,
                $current_address,
                $is_admin
            ]);
            
            $success = true;
        } catch (PDOException $e) {
            $errors[] = "Registration failed. Please try again.";
        }
    }
}

// Redirect to login page if registration was successful
if ($success) {
    header("Location: login.php?registration=success");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - EduVote</title>
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

        /* Password strength meter styles */
        .strength-meter {
            height: 5px;
            background-color: #eee;
            margin-top: 10px;
            border-radius: 3px;
            overflow: hidden;
        }

        .strength-meter-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s ease;
        }

        .strength-weak {
            background-color: #dc3545;
        }

        .strength-medium {
            background-color: #ffc107;
        }

        .strength-strong {
            background-color: #28a745;
        }

        .feedback {
            display: block;
            margin-top: 5px;
            font-size: 0.875rem;
        }

        .form-group {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
        }

        .password-toggle:hover {
            color: #333;
        }

        /* Form input styles */
        .form-input {
            padding: 10px;
            border: 2px solid #ccc;
            border-radius: 4px;
            width: 200px;
        }

        /* Address textarea specific styles */
        textarea.form-input {
            width: 100%;
            min-height: 100px;
            resize: vertical;
        }

        /* Valid input (green background and border) */
        .valid {
            background-color: #d4edda;
            border-color: #28a745;
        }

        /* Invalid input (red background and border) */
        .invalid {
            background-color: #f8d7da;
            border-color: #dc3545;
        }
    </style>
    <script>
        // Sanitization functions for client-side
        function sanitizeInput(value) {
            // Remove all special characters except letters, numbers, and spaces
            return value.replace(/[^a-zA-Z0-9\s]/g, '');
        }

        function sanitizeEmail(value) {
            // Only allow letters, numbers, @, and . for email
            return value.replace(/[^a-zA-Z0-9@.]/g, '');
        }

        function sanitizeAddress(value) {
            // Allow letters, numbers, spaces, commas, periods, and hyphens for addresses
            return value.replace(/[^a-zA-Z0-9\s,.-]/g, '');
        }

        // General input validation function
        function validateInput(input, fieldName) {
            const feedback = document.getElementById(`${fieldName}_feedback`);
            let value = input.value;
            let errorMessage = '';

            // Apply sanitization based on field type
            if (fieldName === 'email') {
                value = sanitizeEmail(value.toLowerCase());
            } else if (fieldName === 'billing_address' || fieldName === 'current_address') {
                value = sanitizeAddress(value);
            } else if (fieldName !== 'password' && fieldName !== 'confirm_password') {
                value = sanitizeInput(value);
            }

            // Update input value with sanitized version
            if (fieldName !== 'password' && fieldName !== 'confirm_password') {
                input.value = value;
            }

            if (input.tagName === 'SELECT' && value === '') {
                errorMessage = 'This field is required.';
            } else if (input.type === 'text' || input.type === 'textarea') {
                if (value === '') {
                    errorMessage = 'This field is required.';
                } else if (fieldName === 'full_name') {
                    value = value.toUpperCase();
                    input.value = value;
                    if (value.length > 20) {
                        errorMessage = 'Full name must not exceed 20 characters.';
                    } else if (!/^[A-Z\s]+$/.test(value)) {
                        errorMessage = 'Full name can only contain letters and spaces.';
                    }
                } else if (fieldName === 'student_id') {
                    // Limit to 10 characters
                    if (value.length > 10) {
                        value = value.slice(0, 10);
                        input.value = value;
                    }
                    value = value.toUpperCase();
                    input.value = value;
                    if (value.length !== 10) {
                        errorMessage = 'Student ID must be exactly 10 characters.';
                    } else if (!/^[A-Z]{3}[0-9]{7}$/.test(value)) {
                        errorMessage = 'Student ID must be in format: 3 letters followed by 7 numbers (e.g., 23WMR10564).';
                    }
                } else if (fieldName === 'email') {
                    if (!/^[a-zA-Z0-9]+@student\.tarc\.edu\.my$/.test(value)) {
                        errorMessage = 'Email must end with @student.tarc.edu.my. No special characters allowed except @ and .';
                    }
                } else if (fieldName === 'security_answer') {
                    value = value.toLowerCase();
                    input.value = value;
                    if (!/^[a-z0-9\s]+$/.test(value)) {
                        errorMessage = 'Security answer can only contain lowercase letters, numbers, and spaces.';
                    }
                } else if (fieldName === 'billing_address' || fieldName === 'current_address') {
                    const wordCount = value.trim().split(/\s+/).length;
                    if (wordCount > 50) {
                        errorMessage = 'Address must not exceed 50 words.';
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
            if (val.length >= 8)
                strength++;
            if (/[A-Z]/.test(val))
                strength++;
            if (/[a-z]/.test(val))
                strength++;
            if (/[0-9]/.test(val))
                strength++;
            if (/[^a-zA-Z0-9]/.test(val))
                strength++;

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

            updateStrengthMeter();
        }

        // Confirm password validation
        function validateConfirmPassword() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm-password');
            const confirmPasswordFeedback = document.getElementById('confirm_password_feedback');
            if (confirmPassword.value !== password.value) {
                confirmPasswordFeedback.textContent = '⚠️ Passwords do not match.';
                confirmPassword.classList.add('invalid');
                confirmPassword.classList.remove('valid');
            } else {
                confirmPasswordFeedback.textContent = '';
                confirmPassword.classList.add('valid');
                confirmPassword.classList.remove('invalid');
            }
        }

        function updateStrengthMeter() {
            const password = document.getElementById('password').value;
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            const meter = document.getElementById('strengthMeterBar');
            meter.style.width = (strength * 20) + '%';
            meter.className = 'strength-meter-bar ' +
                (strength <= 2 ? 'strength-weak' : strength <= 4 ? 'strength-medium' : 'strength-strong');
        }

        // Password show/hide
        function togglePasswordVisibility(fieldId) {
            const input = document.getElementById(fieldId);
            const button = input.nextElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                button.textContent = 'Hide';
            } else {
                input.type = 'password';
                button.textContent = 'Show';
            }
        }
    </script>
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

            <form action="register.php" method="post" onsubmit="return validateForm()">
                <div class="form-group">
                    <label for="full-name">Full Name</label>
                    <input type="text" id="full-name" name="full_name" class="form-input" required 
                           value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>" 
                           placeholder="As it appears on school records"
                           oninput="validateInput(this, 'full_name')">
                    <span class="feedback" id="full_name_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="reg-student-id">Student ID</label>
                    <input type="text" id="reg-student-id" name="student_id" class="form-input" required 
                           value="<?= isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : '' ?>" 
                           placeholder="Your official student ID"
                           oninput="validateInput(this, 'student_id')">
                    <span class="feedback" id="student_id_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="email">Institutional Email</label>
                    <input type="email" id="email" name="email" class="form-input" required 
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" 
                           placeholder="your.id@student.tarc.edu.my"
                           oninput="validateInput(this, 'email')">
                    <span class="feedback" id="email_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="program">Program of Study</label>
                    <select id="program" name="program" class="form-input" required
                            onchange="validateInput(this, 'program')">
                        <option value="">Select your program</option>
                        <option value="Computer Science" <?= (isset($_POST['program']) && $_POST['program'] === 'Computer Science') ? 'selected' : '' ?>>Computer Science</option>
                        <option value="Engineering" <?= (isset($_POST['program']) && $_POST['program'] === 'Engineering') ? 'selected' : '' ?>>Engineering</option>
                        <option value="Business" <?= (isset($_POST['program']) && $_POST['program'] === 'Business') ? 'selected' : '' ?>>Business</option>
                        <option value="Arts & Humanities" <?= (isset($_POST['program']) && $_POST['program'] === 'Arts & Humanities') ? 'selected' : '' ?>>Arts & Humanities</option>
                        <option value="Natural Sciences" <?= (isset($_POST['program']) && $_POST['program'] === 'Natural Sciences') ? 'selected' : '' ?>>Natural Sciences</option>
                        <option value="Other" <?= (isset($_POST['program']) && $_POST['program'] === 'Other') ? 'selected' : '' ?>>Other</option>
                    </select>
                    <span class="feedback" id="program_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="security-question">Security Question</label>
                    <select id="security-question" name="security_question" class="form-input" required
                            onchange="validateInput(this, 'security_question')">
                        <option value="">Select a question</option>
                        <option value="What is your mother's maiden name?" <?= (isset($_POST['security_question']) && $_POST['security_question'] === 'What is your mother\'s maiden name?') ? 'selected' : '' ?>>What is your mother's maiden name?</option>
                        <option value="What was your first pet's name?" <?= (isset($_POST['security_question']) && $_POST['security_question'] === 'What was your first pet\'s name?') ? 'selected' : '' ?>>What was your first pet's name?</option>
                        <option value="What is your favorite book?" <?= (isset($_POST['security_question']) && $_POST['security_question'] === 'What is your favorite book?') ? 'selected' : '' ?>>What is your favorite book?</option>
                        <option value="What city were you born in?" <?= (isset($_POST['security_question']) && $_POST['security_question'] === 'What city were you born in?') ? 'selected' : '' ?>>What city were you born in?</option>
                    </select>
                    <span class="feedback" id="security_question_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="security-answer">Security Answer</label>
                    <input type="text" id="security-answer" name="security_answer" class="form-input" 
                           maxlength="255" required
                           value="<?= isset($_POST['security_answer']) ? htmlspecialchars($_POST['security_answer']) : '' ?>"
                           oninput="validateInput(this, 'security_answer')">
                    <span class="feedback" id="security_answer_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="billing-address">Billing Address</label>
                    <textarea id="billing-address" name="billing_address" class="form-input" 
                              maxlength="255" required
                              oninput="validateInput(this, 'billing_address')"
                              placeholder="Enter your billing address"
                              rows="4"><?= isset($_POST['billing_address']) ? htmlspecialchars($_POST['billing_address']) : '' ?></textarea>
                    <span class="feedback" id="billing_address_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="current-address">Current Address</label>
                    <textarea id="current-address" name="current_address" class="form-input" 
                              maxlength="255" required
                              oninput="validateInput(this, 'current_address')"
                              placeholder="Enter your current address"
                              rows="4"><?= isset($_POST['current_address']) ? htmlspecialchars($_POST['current_address']) : '' ?></textarea>
                    <span class="feedback" id="current_address_feedback"></span>
                </div>

                <div class="form-group">
                    <label for="password">Create Password</label>
                    <input type="password" id="password" name="password" class="form-input" required 
                           placeholder="At least 8 characters"
                           oninput="validatePassword()">
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password')">Show</button>
                    <span class="feedback" id="password_feedback"></span>
                    <div class="strength-meter">
                        <div class="strength-meter-bar" id="strengthMeterBar"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm-password">Confirm Password</label>
                    <input type="password" id="confirm-password" name="confirm_password" class="form-input" required 
                           placeholder="Re-enter your password"
                           oninput="validateConfirmPassword()">
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirm-password')">Show</button>
                    <span class="feedback" id="confirm_password_feedback"></span>
                </div>

                <button type="submit" class="btn">Register</button>

                <div class="auth-links">
                    <a href="login.php">Already have an account? Login</a>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <p>&copy; 2024 EduVote - Campus Voting System</p>
        <p>Contact: <a href="mailto:support@eduvote.edu" style="color: white;">support@eduvote.edu</a></p>
    </footer>
</body>
</html>
