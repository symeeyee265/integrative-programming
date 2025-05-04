<?php
session_start();
require_once 'dbConnection.php';

// Uncomment in production
// if (!isset($_SESSION['student_id'])) {
//     header("Location: login.php");
//     exit();
// }

// Fetch active and upcoming elections from database
$current_date = date('Y-m-d');
$elections = $conn->query("
    SELECT * FROM elections 
    WHERE ((start_date <= '$current_date' AND end_date >= '$current_date') 
    OR start_date > '$current_date')
    AND is_active = 1
    ORDER BY start_date ASC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduVote - Campus Voting System</title>
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
        
        .hero {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .hero h1 {
            font-size: 2.2rem;
            color: #1a5276;
            margin-bottom: 1rem;
        }
        
        .elections {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .election-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .election-card h3 {
            color: #1a5276;
            margin-bottom: 0.5rem;
        }
        
        .election-meta {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-bottom: 1rem;
        }
        
        .status {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .active {
            background: #e8f8f5;
            color: #27ae60;
        }
        
        .upcoming {
            background: #ebf5fb;
            color: #2980b9;
        }
        
        .btn {
            display: inline-block;
            padding: 0.6rem 1.2rem;
            background: #2980b9;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            margin-top: 0.5rem;
        }
        
        .btn:hover {
            background: #2471a3;
        }
        
        .login-promo {
            text-align: center;
            margin: 3rem 0;
        }
        
        footer {
            background: #1a5276;
            color: white;
            text-align: center;
            padding: 1.5rem;
            margin-top: 3rem;
        }
        
        @media (max-width: 600px) {
            .elections {
                grid-template-columns: 1fr;
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
        <section class="hero">
            <h1>Campus Voting Made Simple</h1>
            <p>Participate in student elections and campus decisions securely with your school credentials.</p>
        </section>
        
        <h2>Current Elections</h2>
        <div class="elections">
            <?php if (count($elections) > 0): ?>
                <?php foreach ($elections as $election): 
                    $is_active = (strtotime($election['start_date']) <= time() && strtotime($election['end_date']) >= time());
                    $status_class = $is_active ? 'active' : 'upcoming';
                    $status_text = $is_active ? 'Active' : 'Upcoming';
                    $date_label = $is_active ? 'Ends: ' : 'Starts: ';
                    $date_value = $is_active ? $election['end_date'] : $election['start_date'];
                    $button_text = $is_active ? 'Vote Now' : 'View Details';
                    $button_action = $is_active ? 'vote.php?election_id='.$election['election_id'] : 'viewDetails.php?id='.$election['election_id'];
                ?>
                <div class="election-card">
                    <h3><?php echo htmlspecialchars($election['title']); ?></h3>
                    <div class="election-meta">
                        <span><?php echo $date_label . date('M j, Y', strtotime($date_value)); ?></span>
                        <span class="status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                    </div>
                    <p><?php echo htmlspecialchars($election['description']); ?></p>
                    <a href="<?php echo $button_action; ?>" class="btn"><?php echo $button_text; ?></a>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No elections available at this time. Please check back later.</p>
            <?php endif; ?>
        </div>
        
        <?php if (!isset($_SESSION['student_id'])): ?>
        <div class="login-promo">
            <p>Ready to vote? <a href="login.php" class="btn">Student Login</a></p>
        </div>
        <?php endif; ?>
    </div>
    
    <footer>
        <p>&copy; <?php echo date('Y'); ?> EduVote - Campus Voting System</p>
        <p>Contact: <a href="mailto:support@eduvote.edu" style="color: white;">support@eduvote.edu</a></p>
    </footer>
</body>
</html>