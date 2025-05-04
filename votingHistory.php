<?php
session_start();
require_once 'dbConnection.php';

// if (!isset($_SESSION['student_id'])) {
//     header("Location: login.php");
//     exit();
// }

$user_id = $_SESSION['student_id']; // In real implementation, get user_id from session

// Fetch user's voting history
$stmt = $conn->prepare("
    SELECT e.title AS election_title, e.election_id, v.voted_at, 
           c.name AS candidate_name, p.title AS position_title,
           o.name AS option_name
    FROM votes v
    JOIN elections e ON v.election_id = e.election_id
    LEFT JOIN candidates c ON v.candidate_id = c.candidate_id
    LEFT JOIN positions p ON v.position_id = p.position_id
    LEFT JOIN options o ON v.option_id = o.option_id
    WHERE v.user_id = ?
    ORDER BY v.voted_at DESC
");
$stmt->execute([$user_id]);
$voting_history = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Voting History - EduVote</title>
    <style>
        /* Reuse your existing styles from homePage.php */
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
        
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .history-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .history-card h3 {
            color: #1a5276;
            margin-bottom: 0.5rem;
        }
        
        .history-meta {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-bottom: 0.5rem;
        }
        
        .vote-detail {
            margin-top: 0.5rem;
            padding: 0.8rem;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        footer {
            background: #1a5276;
            color: white;
            text-align: center;
            padding: 1.5rem;
            margin-top: 3rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #7f8c8d;
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
        <h1>My Voting History</h1>
        
        <?php if (count($voting_history) > 0): ?>
            <?php foreach ($voting_history as $vote): ?>
                <div class="history-card">
                    <h3><?php echo htmlspecialchars($vote['election_title']); ?></h3>
                    <div class="history-meta">
                        Voted on <?php echo date('F j, Y \a\t g:i a', strtotime($vote['voted_at'])); ?>
                    </div>
                    
                    <?php if ($vote['candidate_name']): ?>
                        <div class="vote-detail">
                            <strong>Position:</strong> <?php echo htmlspecialchars($vote['position_title']); ?><br>
                            <strong>Selected Candidate:</strong> <?php echo htmlspecialchars($vote['candidate_name']); ?>
                        </div>
                    <?php elseif ($vote['option_name']): ?>
                        <div class="vote-detail">
                            <strong>Selected Option:</strong> <?php echo htmlspecialchars($vote['option_name']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <a href="viewDetails.php?id=<?php echo $vote['election_id']; ?>" class="btn" style="display: inline-block; margin-top: 0.8rem;">View Election</a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <h3>No Voting History Found</h3>
                <p>You haven't participated in any elections yet.</p>
                <a href="homePage.php" class="btn">Browse Elections</a>
            </div>
        <?php endif; ?>
    </div>
    
    <footer>
        <p>&copy; 2025 EduVote - Campus Voting System</p>
        <p>Contact: <a href="mailto:support@eduvote.edu" style="color: white;">support@eduvote.edu</a></p>
    </footer>
</body>
</html>