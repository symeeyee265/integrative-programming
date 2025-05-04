<?php
session_start();
require_once 'dbConnection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$election_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // Get election details
    $stmt = $conn->prepare("SELECT * FROM elections WHERE election_id = ?");
    $stmt->execute([$election_id]);
    $election = $stmt->fetch();

    if (!$election) {
        header("Location: homePage.php");
        exit();
    }

    // Check if user has already voted
    $stmt = $conn->prepare("SELECT 1 FROM votes WHERE user_id = ? AND election_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id'], $election_id]);
    $has_voted = $stmt->fetch();

    // Get election data based on type
    if ($election['election_type'] == 'candidates') {
        $positions = [];
        $stmt = $conn->prepare("SELECT * FROM positions WHERE election_id = ?");
        $stmt->execute([$election_id]);
        
        while ($position = $stmt->fetch()) {
            $stmt2 = $conn->prepare("SELECT * FROM candidates WHERE position_id = ?");
            $stmt2->execute([$position['position_id']]);
            $position['candidates'] = $stmt2->fetchAll();
            $positions[] = $position;
        }
    } elseif ($election['election_type'] == 'options') {
        $stmt = $conn->prepare("SELECT * FROM options WHERE election_id = ?");
        $stmt->execute([$election_id]);
        $options = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Calculate election status
$now = new DateTime();
$start = new DateTime($election['start_date']);
$end = new DateTime($election['end_date']);
$is_active = ($now >= $start && $now <= $end);
$is_upcoming = ($now < $start);
$is_ended = ($now > $end);
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($election['title']); ?> - EduVote</title>
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
                max-width: 1000px;
                margin: 0 auto;
                padding: 1rem;
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

            .election-details {
                background: white;
                border-radius: 8px;
                padding: 2rem;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                margin-bottom: 2rem;
            }

            .details-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
                margin: 1rem 0;
            }

            .detail-item strong {
                display: inline-block;
                min-width: 120px;
                color: #1a5276;
            }

            .candidates, .options {
                margin-top: 2rem;
            }

            .position-group {
                margin-bottom: 2rem;
                padding: 1rem;
                background: #f8f9fa;
                border-radius: 6px;
            }

            .position-title {
                color: #1a5276;
                margin-bottom: 1rem;
                padding-bottom: 0.5rem;
                border-bottom: 1px solid #ddd;
            }

            .candidate-card, .option-card {
                background: white;
                border-radius: 6px;
                padding: 1rem;
                margin-bottom: 1rem;
                border: 1px solid #eee;
            }

            .candidate-card h4, .option-card h4 {
                color: #1a5276;
                margin-bottom: 0.5rem;
            }

            .platform {
                color: #555;
                margin-top: 0.5rem;
            }

            .vote-btn {
                display: inline-block;
                padding: 0.6rem 1.2rem;
                background: #27ae60;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                font-weight: 500;
                margin-top: 1rem;
                cursor: pointer;
                border: none;
            }

            .vote-btn:hover {
                background: #219653;
            }

            .vote-btn:disabled {
                background: #95a5a6;
                cursor: not-allowed;
            }

            .back-btn {
                display: inline-block;
                padding: 0.5rem 1rem;
                color: #2980b9;
                text-decoration: none;
                font-weight: 500;
                margin-bottom: 1rem;
            }

            footer {
                background: #1a5276;
                color: white;
                text-align: center;
                padding: 1.5rem;
                margin-top: 3rem;
            }

            .status-badge {
                display: inline-block;
                padding: 0.3rem 0.6rem;
                border-radius: 20px;
                font-size: 0.8rem;
                font-weight: 500;
                margin-left: 1rem;
            }

            .status-active {
                background: #27ae60;
                color: white;
            }

            .status-ended {
                background: #e74c3c;
                color: white;
            }

            .status-upcoming {
                background: #f39c12;
                color: white;
            }

            .already-voted {
                color: #e74c3c;
                font-weight: 500;
                margin-top: 1rem;
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
            <a href="homePage.php" class="back-btn">← Back to Elections</a>

            <div class="election-details">
                <h1><?php echo htmlspecialchars($election['title']); ?>
                    <?php 
                    $now = new DateTime();
                    $start = new DateTime($election['start_date']);
                    $end = new DateTime($election['end_date']);
                    
                    if ($now < $start) {
                        echo '<span class="status-badge status-upcoming">Upcoming</span>';
                    } elseif ($now > $end) {
                        echo '<span class="status-badge status-ended">Ended</span>';
                    } else {
                        echo '<span class="status-badge status-active">Active</span>';
                    }
                    ?>
                </h1>
                <p><?php echo htmlspecialchars($election['description']); ?></p>

                <div class="details-grid">
                    <div class="detail-item">
                        <strong>Starts:</strong>
                        <span><?php echo date('F j, Y g:i a', strtotime($election['start_date'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <strong>Ends:</strong>
                        <span><?php echo date('F j, Y g:i a', strtotime($election['end_date'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <strong>Type:</strong>
                        <span><?php echo ucfirst($election['election_type']); ?> Election</span>
                    </div>
                    <div class="detail-item">
                        <strong>Status:</strong>
                        <span>
                            <?php 
                            if ($now < $start) {
                                echo "Voting hasn't started yet";
                            } elseif ($now > $end) {
                                echo "Voting has ended";
                            } else {
                                echo "Voting is open";
                            }
                            ?>
                        </span>
                    </div>
                </div>

                <?php if ($has_voted): ?>
                    <div class="already-voted">
                        You have already voted in this election.
                    </div>
                <?php endif; ?>

                <?php if ($election['election_type'] == 'candidates' && !empty($positions)): ?>
                    <div class="candidates">
                        <h2>Candidates</h2>
                        
                        <?php foreach ($positions as $position): ?>
                            <div class="position-group">
                                <h3 class="position-title">
                                    <?php echo htmlspecialchars($position['title']); ?>
                                    <?php if ($position['max_votes'] > 1): ?>
                                        <small>(Vote for up to <?php echo $position['max_votes']; ?> candidates)</small>
                                    <?php endif; ?>
                                </h3>
                                <p><?php echo htmlspecialchars($position['description']); ?></p>
                                
                                <?php foreach ($position['candidates'] as $candidate): ?>
                                    <div class="candidate-card">
                                        <h4><?php echo htmlspecialchars($candidate['name']); ?></h4>
                                        <?php if (!empty($candidate['photo_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($candidate['photo_url']); ?>" alt="Candidate photo" style="max-width: 150px; margin-bottom: 0.5rem;">
                                        <?php endif; ?>
                                        <div class="platform"><?php echo htmlspecialchars($candidate['platform']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if ($now >= $start && $now <= $end && !$has_voted): ?>
                            <form action="vote.php" method="post">
                                <input type="hidden" name="election_id" value="<?php echo $election_id; ?>">
                                <button type="submit" class="vote-btn">Vote Now</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php elseif ($election['election_type'] == 'options' && !empty($options)): ?>
                    <div class="options">
                        <h2>Voting Options</h2>
                        
                        <?php foreach ($options as $option): ?>
                            <div class="option-card">
                                <h4><?php echo htmlspecialchars($option['name']); ?></h4>
                                <p><?php echo htmlspecialchars($option['description']); ?></p>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if ($now >= $start && $now <= $end && !$has_voted): ?>
                            <form action="vote.php" method="post">
                                <input type="hidden" name="election_id" value="<?php echo $election_id; ?>">
                                <button type="submit" class="vote-btn">Vote Now</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <footer>
            <p>&copy; 2025 EduVote - Campus Voting System</p>
            <p>Contact: <a href="mailto:support@eduvote.edu" style="color: white;">support@eduvote.edu</a></p>
        </footer>
    </body>
</html>