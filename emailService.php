<?php
require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->mailer = new PHPMailer(true);
        
        // Configure SMTP
        $this->mailer->isSMTP();
        $this->mailer->Host = 'smtp.gmail.com';
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = 'your-email@gmail.com'; // Replace with your email
        $this->mailer->Password = 'your-app-password'; // Replace with your app password
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = 587;
        $this->mailer->setFrom('your-email@gmail.com', 'EduVote System');
    }

    public function sendVerificationEmail($email, $name, $token) {
        try {
            $this->mailer->addAddress($email, $name);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Verify your EduVote account';
            
            $verificationLink = "http://" . $_SERVER['HTTP_HOST'] . "/verify.php?token=" . $token;
            
            $this->mailer->Body = "
                <h2>Welcome to EduVote!</h2>
                <p>Dear $name,</p>
                <p>Thank you for registering with EduVote. Please click the link below to verify your email address:</p>
                <p><a href='$verificationLink'>$verificationLink</a></p>
                <p>This link will expire in 24 hours.</p>
                <p>If you did not register for EduVote, please ignore this email.</p>
            ";
            
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    public function sendTwoFactorCode($email, $name, $code) {
        try {
            $this->mailer->addAddress($email, $name);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Your EduVote 2FA Code';
            
            $this->mailer->Body = "
                <h2>Two-Factor Authentication</h2>
                <p>Dear $name,</p>
                <p>Your two-factor authentication code is:</p>
                <h3>$code</h3>
                <p>This code will expire in 5 minutes.</p>
                <p>If you did not request this code, please secure your account immediately.</p>
            ";
            
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("2FA email sending failed: " . $e->getMessage());
            return false;
        }
    }

    public function generateVerificationToken($userId) {
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        try {
            $stmt = $this->conn->prepare("INSERT INTO verification_tokens (user_id, token, type, expires_at) VALUES (?, ?, 'email_verification', ?)");
            $stmt->execute([$userId, $token, $expiry]);
            return $token;
        } catch (PDOException $e) {
            error_log("Token generation failed: " . $e->getMessage());
            return false;
        }
    }

    public function verifyToken($token) {
        try {
            $stmt = $this->conn->prepare("SELECT user_id, expires_at, used FROM verification_tokens WHERE token = ? AND type = 'email_verification'");
            $stmt->execute([$token]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                return false;
            }
            
            if ($result['used'] || strtotime($result['expires_at']) < time()) {
                return false;
            }
            
            // Mark token as used
            $stmt = $this->conn->prepare("UPDATE verification_tokens SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            // Update user's email verification status
            $stmt = $this->conn->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
            $stmt->execute([$result['user_id']]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Token verification failed: " . $e->getMessage());
            return false;
        }
    }
}
?>