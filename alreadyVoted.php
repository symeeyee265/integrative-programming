<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'dbConnection.php';

$election_id = isset($_GET['election_id']) ? (int) $_GET['election_id'] : 0;

// Fetch election title from database
$election_title = "this election"; // Default value
if ($election_id > 0) {
    $stmt = $conn->prepare("SELECT title FROM elections WHERE election_id = ?");
    $stmt->execute([$election_id]);
    $election = $stmt->fetch();

    if ($election) {
        $election_title = $election['title'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Already Voted - EduVote</title>
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

            .warning-message {
                text-align: center;
                padding: 2rem;
            }

            .warning-icon {
                font-size: 4rem;
                color: #e74c3c;
                margin-bottom: 1rem;
            }

            .action-buttons {
                display: flex;
                gap: 1rem;
                justify-content: center;
                margin-top: 2rem;
                flex-wrap: wrap;
            }

            .warning-message {
                background: #fde8e8;
                border-left: 4px solid #e74c3c;
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

            @media (max-width: 600px) {
                .action-buttons {
                    flex-direction: column;
                }

                .action-buttons .btn {
                    width: 100%;
                }
            }

        </style>
    </head>
    <body>
        <header>
            <div class="logo">Edu<span>Vote</span></div>
            <nav>
                <a href="homePage.php">Home</a>
                <a href="#">Elections</a>
                <a href="#">Profile</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <div class="container">
            <div class="card warning-message">
                <div class="warning-icon">!</div>
                <h1>You've Already Voted</h1>
                <p>Our records show you've already participated in <strong><?php echo htmlspecialchars($election_title); ?></strong>.</p>
                <p>Each student can only vote once per election.</p>
                <div class="action-buttons">
                    <a href="homePage.php" class="btn">View Other Elections</a>
                    <?php if ($election_id > 0): ?>
                        <a href="viewDetails.php?id=<?php echo $election_id; ?>" class="btn btn-secondary">View Election Details</a>
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