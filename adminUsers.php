<?php
session_start();
require_once 'dbConnection.php';

// Uncomment in production
// if (!isset($_SESSION['student_id']) || !$_SESSION['is_admin']) {
//     header("Location: login.php");
//     exit();
// }

// Handle admin privilege updates only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin'])) {
    $user_id = $_POST['user_id'];
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    
    try {
        $stmt = $conn->prepare("UPDATE users SET is_admin = ? WHERE user_id = ?");
        $stmt->execute([$is_admin, $user_id]);
        $success = "Admin privileges updated successfully!";
    } catch (PDOException $e) {
        $error = "Error updating user: " . $e->getMessage();
    }
}

// Fetch all users (for admin viewing only)
$users = $conn->query("SELECT * FROM users ORDER BY full_name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin | EduVote</title>
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
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        th, td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        tr:hover {
            background: #f5f7fa;
        }
        
        .admin-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            background: #e8f8f5;
            color: #27ae60;
        }
        
        .error {
            color: #e74c3c;
            background: #fde8e8;
            padding: 0.8rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .success {
            color: #27ae60;
            background: #e8f8f5;
            padding: 0.8rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        footer {
            background: #1a5276;
            color: white;
            text-align: center;
            padding: 1.5rem;
            margin-top: 3rem;
        }
        
        .form-group {
            margin: 0.5rem 0;
        }
        
        .form-group label {
            display: inline-block;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">Edu<span>Vote</span> Admin</div>
        <nav>
            <a href="adminElections.php">Manage Elections</a>
            <a href="adminUsers.php">Manage users</a>
            <a href="homePage.php">View Site</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>
    
    <div class="container">
        <!-- Display success/error messages -->
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Users List -->
        <div class="card">
            <h2>Manage Admin Privileges</h2>
            <p>View all users and grant/revoke admin access. Users register themselves through the registration system.</p>
            
            <?php if (count($users) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Program</th>
                            <th>Admin Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['student_id']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['program']); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="is_admin" 
                                                   <?php echo $user['is_admin'] ? 'checked' : ''; ?>
                                                   onchange="this.form.submit()">
                                            Admin
                                        </label>
                                        <button type="submit" name="update_admin" style="display:none;"></button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No users found.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <footer>
        <p>&copy; 2025 EduVote - Campus Voting System</p>
        <p>Contact: <a href="mailto:support@eduvote.edu" style="color: white;">support@eduvote.edu</a></p>
    </footer>
</body>
</html>