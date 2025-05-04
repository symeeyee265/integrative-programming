<?php
session_start();
require_once 'dbConnection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$election_id = (int)($_GET['election_id'] ?? 0);
$receipt_id = $_GET['receipt_id'] ?? '';

try {
    // Verify vote and get election details
    $stmt = $conn->prepare("
        SELECT e.title, e.end_date 
        FROM elections e
        JOIN votes v ON v.election_id = e.election_id
        WHERE e.election_id = ? AND v.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$election_id, $_SESSION['user_id']]);
    $election = $stmt->fetch();

    if (!$election) {
        header("Location: homePage.php");
        exit();
    }

    $end_date = new DateTime($election['end_date']);
    $results_available = (new DateTime()) > $end_date;
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote Submitted - EduVote</title>
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

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            flex: 1;
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

        .card {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .success-icon {
            font-size: 4rem;
            color: #27ae60;
            margin-bottom: 1rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background: #2980b9;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #2471a3;
        }

        .btn-secondary {
            background: white;
            color: #2980b9;
            border: 1px solid #2980b9;
        }

        .btn-secondary:hover {
            background: #ebf5fb;
        }

        .btn-success {
            background: #27ae60;
        }

        .btn-success:hover {
            background: #219653;
        }

        footer {
            background: #1a5276;
            color: white;
            text-align: center;
            padding: 1.5rem;
            margin-top: auto;
        }

        .receipt-notice {
            background: #e8f8f5;
            padding: 1rem;
            border-radius: 4px;
            margin: 1.5rem 0;
            border-left: 4px solid #27ae60;
        }

        @media (max-width: 600px) {
            .container {
                padding: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }

            .action-buttons .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">Edu<span>Vote</span></div>
        <nav>
            <a href="homePage.php">Home</a>
            <a href="votingHistory.php">Profile</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <div class="card">
            <div class="success-icon">✓</div>
            <h1>Thank You for Voting!</h1>
            <p>Your vote in <strong><?php echo htmlspecialchars($election['title']); ?></strong> has been successfully recorded.</p>
            
            <div class="receipt-notice">
                <p>A confirmation receipt has been generated for your records.</p>
                <?php if ($receipt_id): ?>
                    <p>Your receipt ID: <strong><?php echo htmlspecialchars($receipt_id); ?></strong></p>
                <?php endif; ?>
            </div>

            <?php if ($results_available): ?>
                <p>Results are now available for this election.</p>
            <?php else: ?>
                <p>Results will be available after <?php echo $end_date->format('F j, Y g:i a'); ?>.</p>
            <?php endif; ?>
            
            <div class="action-buttons">
                <a href="homePage.php" class="btn">Return to Home</a>
                <a href="viewDetails.php?id=<?php echo $election_id; ?>" class="btn btn-secondary">View Election Details</a>
                <?php if ($receipt_id): ?>
                    <a href="receipt.php?id=<?php echo urlencode($receipt_id); ?>" class="btn btn-success">View Receipt</a>
                <?php endif; ?>
                <?php if ($results_available): ?>
                    <a href="results.php?election_id=<?php echo $election_id; ?>" class="btn btn-secondary">View Results</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 EduVote - Campus Voting System</p>
        <p>Contact: <a href="mailto:support@eduvote.edu" style="color: white;">support@eduvote.edu</a></p>
    </footer>
</body>
</html>