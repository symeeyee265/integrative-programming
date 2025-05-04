<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once 'dbConnection.php';

use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

class OAuthService {
    private $conn;
    private $googleProvider;

    public function __construct($conn) {
        $this->conn = $conn;
        
        // Initialize Google OAuth provider
        $this->googleProvider = new Google([
            'clientId'     => 'YOUR_GOOGLE_CLIENT_ID',
            'clientSecret' => 'YOUR_GOOGLE_CLIENT_SECRET',
            'redirectUri'  => 'http://localhost/voteSystem/oauth-callback.php',
        ]);
    }

    public function getGoogleAuthUrl() {
        $authUrl = $this->googleProvider->getAuthorizationUrl([
            'scope' => ['email', 'profile']
        ]);
        $_SESSION['oauth2state'] = $this->googleProvider->getState();
        return $authUrl;
    }

    public function handleGoogleCallback($code) {
        try {
            // Get access token
            $token = $this->googleProvider->getAccessToken('authorization_code', [
                'code' => $code
            ]);

            // Get user info
            $userInfo = $this->googleProvider->getResourceOwner($token);

            // Check if user exists
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$userInfo->getEmail()]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                // Create new user
                $stmt = $this->conn->prepare("
                    INSERT INTO users (email, full_name, social_login_id, social_login_provider, email_verified) 
                    VALUES (?, ?, ?, 'google', 1)
                ");
                $stmt->execute([
                    $userInfo->getEmail(),
                    $userInfo->getName(),
                    $userInfo->getId()
                ]);
                $userId = $this->conn->lastInsertId();
            } else {
                $userId = $user['user_id'];
            }

            return $userId;
        } catch (IdentityProviderException $e) {
            error_log("OAuth error: " . $e->getMessage());
            return false;
        }
    }
}
?> 