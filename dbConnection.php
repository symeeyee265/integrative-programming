<?php
$host = 'localhost';
$dbname = 'votesystem';  // Ensure this matches your actual DB name
$username = 'root';      // Default MySQL username
$password = '';          // Default MySQL password (empty if you're using XAMPP default)

try {
    // Create PDO connection to the database
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Skipping the ALTER TABLE as columns already exist in your users table.
    // You do not need to add columns that already exist.

    // Create verification_tokens table if it doesn't exist already
    $conn->exec("CREATE TABLE IF NOT EXISTS verification_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        token VARCHAR(255),
        type ENUM('email_verification', 'password_reset'),
        expires_at DATETIME,
        used BOOLEAN DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )");

} catch(PDOException $e) {
    // Catch any PDO exceptions and display the error message
    die("Database connection failed: " . $e->getMessage());
}
?>
