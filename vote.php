<?php
session_start();
require_once 'dbConnection.php';
require_once 'receiptGenerator.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$election_id = (int)($_GET['election_id'] ?? 0);

// Check if already voted
if (in_array($election_id, $_SESSION['voted_elections'] ?? [])) {
    header("Location: alreadyVoted.php?election_id=$election_id");
    exit();
}

// Get election data
$stmt = $conn->prepare("SELECT * FROM elections WHERE election_id = ?");
$stmt->execute([$election_id]);
$election = $stmt->fetch();

if (!$election) {
    header("Location: homePage.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        $votes = $_POST['vote'];
        
        if ($election['election_type'] === 'candidates') {
            foreach ($votes as $position_id => $candidate_id) {
                $stmt = $conn->prepare("INSERT INTO votes (user_id, election_id, position_id, candidate_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $election_id, $position_id, $candidate_id]);
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO votes (user_id, election_id, option_id) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $election_id, $votes['option']]);
        }

        // Generate receipt
        $generator = $election['election_type'] === 'candidates' 
            ? new CandidateReceiptGenerator() 
            : new OptionReceiptGenerator();
        
        $receipt_id = $generator->generateReceipt($_SESSION['user_id'], $election_id, $votes);

        // Mark as voted
        $_SESSION['voted_elections'][] = $election_id;
        $conn->commit();

        header("Location: voteSuccess.php?election_id=$election_id&receipt_id=$receipt_id");
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error recording your vote. Please try again.";
    }
}

// Get voting options
if ($election['election_type'] === 'candidates') {
    $stmt = $conn->prepare("SELECT * FROM positions WHERE election_id = ?");
    $stmt->execute([$election_id]);
    $positions = $stmt->fetchAll();
    
    foreach ($positions as &$position) {
        $stmt = $conn->prepare("SELECT * FROM candidates WHERE position_id = ?");
        $stmt->execute([$position['position_id']]);
        $position['candidates'] = $stmt->fetchAll();
    }
} else {
    $stmt = $conn->prepare("SELECT * FROM options WHERE election_id = ?");
    $stmt->execute([$election_id]);
    $options = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote - <?php echo htmlspecialchars($election['title']); ?></title>
    <style>
        /* [Keep all existing styles] */
           * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: 'Segoe UI', Roboto, sans-serif;
            }

            .receipt-notice {
                background: #e8f8f5;
                padding: 1rem;
                border-radius: 4px;
                margin-bottom: 1rem;
                border-left: 4px solid #27ae60;
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

            .vote-form {
                background: white;
                border-radius: 8px;
                padding: 2rem;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }

            .position-group {
                margin-bottom: 2rem;
                padding-bottom: 1rem;
                border-bottom: 1px solid #eee;
            }

            .position-title {
                color: #1a5276;
                margin-bottom: 1rem;
            }

            .candidate-option {
                display: block;
                margin: 0.5rem 0;
                padding: 0.8rem;
                border: 1px solid #ddd;
                border-radius: 4px;
                cursor: pointer;
            }

            .candidate-option:hover {
                background: #f0f7fc;
                border-color: #3498db;
            }

            input[type="radio"] {
                margin-right: 10px;
            }

            .submit-btn {
                display: inline-block;
                padding: 0.8rem 1.5rem;
                background: #27ae60;
                color: white;
                border: none;
                border-radius: 4px;
                font-weight: 500;
                font-size: 1rem;
                cursor: pointer;
                margin-top: 1rem;
            }

            .submit-btn:hover {
                background: #219653;
            }

            .warning {
                color: #e74c3c;
                margin-bottom: 1rem;
                padding: 0.8rem;
                background: #fde8e8;
                border-radius: 4px;
            }

            .back-btn {
                display: inline-block;
                padding: 1.2rem 1.2rem;
                color: black;
                text-decoration: none;
                border-radius: 4px;
                font-weight: 500;
                margin-top: 1rem;
            }


            footer {
                background: #1a5276;
                color: white;
                text-align: center;
                padding: 1.5rem;
                margin-top: 3rem;
            }
    
        .receipt-notice {
            background: #e8f8f5;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            border-left: 4px solid #27ae60;
        }

        /* [Rest of your existing styles...] */
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
        <a href="homePage.php" class="back-btn">← Back to home page</a>

        <?php if (isset($error)): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <div class="receipt-notice">
            <h3>Important:</h3>
            <p>After voting, you'll receive a confirmation receipt and be able to view it anytime.</p>
        </div>

        <div class="vote-form">
            <h1>Vote: <?php echo htmlspecialchars($election['title']); ?></h1>

            <div class="warning">
                <strong>Important:</strong> You can only vote once per election. Your vote is final.
            </div>

            <form method="POST" action="vote.php?election_id=<?php echo $election_id; ?>">
                <?php if ($election['election_type'] === 'candidates'): ?>
                    <?php foreach ($positions as $position): ?>
                        <div class="position-group">
                            <h3 class="position-title"><?php echo htmlspecialchars($position['title']); ?></h3>
                            <?php foreach ($position['candidates'] as $candidate): ?>
                                <label class="candidate-option">
                                    <input type="radio" 
                                           name="vote[<?php echo $position['position_id']; ?>]"
                                           value="<?php echo $candidate['candidate_id']; ?>" 
                                           required>
                                    <?php echo htmlspecialchars($candidate['name']); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="position-group">
                        <h3 class="position-title">Select your preferred option:</h3>
                        <?php foreach ($options as $option): ?>
                            <label class="candidate-option">
                                <input type="radio" name="vote[option]" 
                                       value="<?php echo $option['option_id']; ?>" required>
                                <?php echo htmlspecialchars($option['name']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <button type="submit" class="submit-btn">Submit Vote</button>
            </form>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 EduVote - Campus Voting System</p>
        <p>Contact: <a href="mailto:support@eduvote.edu" style="color: white;">support@eduvote.edu</a></p>
    </footer>
</body>
</html>