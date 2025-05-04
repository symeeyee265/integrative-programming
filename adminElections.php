<?php
session_start();
require_once 'dbConnection.php';

// Check if user is admin (uncomment in production)
// if (!isset($_SESSION['student_id']) || !$_SESSION['is_admin']) {
//     header("Location: login.php");
//     exit();
// }
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_election'])) {
// Create new election
        $title = $_POST['title'];
        $description = $_POST['description'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $election_type = $_POST['election_type'];

        $stmt = $conn->prepare("INSERT INTO elections (title, description, start_date, end_date, election_type) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $start_date, $end_date, $election_type]);

        $election_id = $conn->lastInsertId();

// Redirect to edit page for adding positions/options
        header("Location: adminElections.php?edit=" . $election_id);
        exit();
    } elseif (isset($_POST['update_election'])) {
// Update existing election
        $election_id = $_POST['election_id'];
        $title = $_POST['title'];
        $description = $_POST['description'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $conn->prepare("UPDATE elections 
                               SET title = ?, description = ?, start_date = ?, end_date = ?, is_active = ?
                               WHERE election_id = ?");
        $stmt->execute([$title, $description, $start_date, $end_date, $is_active, $election_id]);
    } elseif (isset($_POST['delete_election'])) {
// Delete election (cascade delete will handle related records)
        $election_id = $_POST['election_id'];
        $stmt = $conn->prepare("DELETE FROM elections WHERE election_id = ?");
        $stmt->execute([$election_id]);

// Delete a position
    } elseif (isset($_POST['delete_position'])) {
        $position_id = $_POST['position_id'];
        $stmt = $conn->prepare("DELETE FROM positions WHERE position_id = ?");
        $stmt->execute([$position_id]);

// Delete an option (if you have a table for options)
    } elseif (isset($_POST['delete_option'])) {
        $option_id = $_POST['option_id'];
        $stmt = $conn->prepare("DELETE FROM options WHERE option_id = ?");
        $stmt->execute([$option_id]);

// Delete a candidate
    } elseif (isset($_POST['delete_candidate'])) {
        $candidate_id = $_POST['candidate_id'];
        $stmt = $conn->prepare("DELETE FROM candidates WHERE candidate_id = ?");
        $stmt->execute([$candidate_id]);
    } elseif (isset($_POST['add_position'])) {
// Add position to election
        $election_id = $_POST['election_id'];
        $title = $_POST['position_title'];
        $description = $_POST['position_description'];
        $max_votes = $_POST['max_votes'] ?? 1;

        $stmt = $conn->prepare("INSERT INTO positions (election_id, title, description, max_votes) 
                               VALUES (?, ?, ?, ?)");
        $stmt->execute([$election_id, $title, $description, $max_votes]);
    } elseif (isset($_POST['add_candidate'])) {
// Add candidate to position
        $position_id = $_POST['position_id'];
        $name = $_POST['candidate_name'];
        $platform = $_POST['candidate_platform'];

        $stmt = $conn->prepare("INSERT INTO candidates (position_id, name, platform) 
                               VALUES (?, ?, ?)");
        $stmt->execute([$position_id, $name, $platform]);
    } elseif (isset($_POST['add_option'])) {
// Add option to election
        $election_id = $_POST['election_id'];
        $name = $_POST['option_name'];
        $description = $_POST['option_description'];

        $stmt = $conn->prepare("INSERT INTO options (election_id, name, description) 
                               VALUES (?, ?, ?)");
        $stmt->execute([$election_id, $name, $description]);
    }
}

// Fetch all elections
$elections = $conn->query("SELECT * FROM elections ORDER BY start_date DESC")->fetchAll();

// If editing, fetch election details
$editing_election = null;
$positions = [];
$options = [];

if (isset($_GET['edit'])) {
    $election_id = (int) $_GET['edit'];
    $editing_election = $conn->prepare("SELECT * FROM elections WHERE election_id = ?");
    $editing_election->execute([$election_id]);
    $editing_election = $editing_election->fetch();

    if ($editing_election) {
        if ($editing_election['election_type'] === 'candidates') {
            $positions = $conn->prepare("SELECT * FROM positions WHERE election_id = ?");
            $positions->execute([$election_id]);
            $positions = $positions->fetchAll();

// Get candidates for each position
            foreach ($positions as &$position) {
                $candidates = $conn->prepare("SELECT * FROM candidates WHERE position_id = ?");
                $candidates->execute([$position['position_id']]);
                $position['candidates'] = $candidates->fetchAll();
            }

            $unique_positions = [];
            $seen_ids = [];

            foreach ($positions as $pos) {
                if (!in_array($pos['position_id'], $seen_ids)) {
                    $unique_positions[] = $pos;
                    $seen_ids[] = $pos['position_id'];
                }
            }
            $positions = $unique_positions;
            
        } else {
            $options = $conn->prepare("SELECT * FROM options WHERE election_id = ?");
            $options->execute([$election_id]);
            $options = $options->fetchAll();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Manage Elections - Admin | EduVote</title>
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

            .admin-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 1.5rem;
            }

            .btn {
                display: inline-block;
                padding: 0.6rem 1.2rem;
                background: #2980b9;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                font-weight: 500;
                border: none;
                cursor: pointer;
            }

            .btn:hover {
                background: #2471a3;
            }

            .btn-success {
                background: #27ae60;
            }

            .btn-success:hover {
                background: #219653;
            }

            .btn-danger {
                background: #e74c3c;
            }

            .btn-danger:hover {
                background: #c0392b;
            }

            .btn-secondary {
                background: #7f8c8d;
            }

            .btn-secondary:hover {
                background: #6c7a7d;
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

            .status-badge {
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

            .inactive {
                background: #fde8e8;
                color: #e74c3c;
            }

            .upcoming {
                background: #ebf5fb;
                color: #2980b9;
            }

            .form-group {
                margin-bottom: 1.2rem;
            }

            .form-group label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 500;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                width: 100%;
                padding: 0.8rem;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 1rem;
            }

            .form-group textarea {
                min-height: 100px;
            }

            .form-row {
                display: flex;
                gap: 1rem;
            }

            .form-row .form-group {
                flex: 1;
            }

            .section-title {
                color: #1a5276;
                margin: 1.5rem 0 1rem;
                padding-bottom: 0.5rem;
                border-bottom: 1px solid #eee;
            }

            .position-card, .option-card {
                background: #f8f9fa;
                border-radius: 6px;
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .position-card h4, .option-card h4 {
                color: #1a5276;
                margin-bottom: 0.5rem;
            }

            .candidate-list {
                margin-top: 1rem;
                padding-left: 1rem;
                border-left: 2px solid #ddd;
            }

            .candidate-item {
                padding: 0.5rem 0;
                border-bottom: 1px dashed #ddd;
            }

            .action-links {
                display: flex;
                gap: 0.5rem;
                margin-top: 0.5rem;
            }

            .action-links a {
                font-size: 0.8rem;
                padding: 0.3rem 0.6rem;
            }

            footer {
                background: #1a5276;
                color: white;
                text-align: center;
                padding: 1.5rem;
                margin-top: 3rem;
            }

            @media (max-width: 768px) {
                .form-row {
                    flex-direction: column;
                    gap: 0;
                }

                .admin-header {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 1rem;
                }
            }
        </style>
    </head>
    <body>
        <header>
            <div class="logo">Edu<span>Vote</span> Admin</div>
            <nav>
                <a href="manageElections.php">Manage Elections</a>
                <a href="adminUsers.php">Manage Users</a>
                <a href="homePage.php">View Site</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <div class="container">
            <div class="admin-header">
                <h1>Manage Elections</h1>
                <button onclick="document.getElementById('create-election-modal').style.display = 'block'" 
                        class="btn btn-success">
                    Create New Election
                </button>
            </div>

            <!-- Elections List -->
            <div class="card">
                <h2>All Elections</h2>
                <?php if (count($elections) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Dates</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($elections as $election):
                                $current_date = date('Y-m-d H:i:s');
                                $is_active = ($election['start_date'] <= $current_date && $election['end_date'] >= $current_date);
                                $status_class = $is_active ? 'active' : ($election['start_date'] > $current_date ? 'upcoming' : 'inactive');
                                $status_text = $is_active ? 'Active' : ($election['start_date'] > $current_date ? 'Upcoming' : 'Ended');
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($election['title']); ?></td>
                                    <td><?php echo ucfirst($election['election_type']); ?></td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($election['start_date'])); ?> - 
                                        <?php echo date('M j, Y', strtotime($election['end_date'])); ?>
                                    </td>
                                    <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                    <td class="action-links">
                                        <a href="adminElections.php?edit=<?php echo $election['election_id']; ?>" class="btn btn-secondary">Edit</a>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="election_id" value="<?php echo $election['election_id']; ?>">
                                            <button type="submit" name="delete_election" class="btn btn-danger" 
                                                    onclick="return confirm('Are you sure you want to delete this election? This cannot be undone.');">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No elections found.</p>
                <?php endif; ?>
            </div>

            <!-- Edit Election Section -->
            <?php if ($editing_election): ?>
                <div class="card">
                    <h2>Edit Election: <?php echo htmlspecialchars($editing_election['title']); ?></h2>

                    <form method="POST">
                        <input type="hidden" name="election_id" value="<?php echo $editing_election['election_id']; ?>">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="title">Election Title</label>
                                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($editing_election['title']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="election_type">Election Type</label>
                                <select id="election_type" name="election_type" disabled>
                                    <option><?php echo ucfirst($editing_election['election_type']); ?></option>
                                </select>
                                <small>Type cannot be changed after creation</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description"><?php echo htmlspecialchars($editing_election['description']); ?></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="datetime-local" id="start_date" name="start_date" 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($editing_election['start_date'])); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="datetime-local" id="end_date" name="end_date" 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($editing_election['end_date'])); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_active" <?php echo $editing_election['is_active'] ? 'checked' : ''; ?>>
                                Active Election
                            </label>
                        </div>

                        <button type="submit" name="update_election" class="btn">Save Changes</button>
                    </form>

                    <!-- Positions/Candidates or Options Section -->
                    <?php if ($editing_election['election_type'] === 'candidates'): ?>
                        <?php
                        echo "<h5>Loaded " . count($positions) . " positions</h5>";
                        echo "<pre>";
                        foreach ($positions as $p) {
                            echo "Position: " . $p['title'] . " (ID: " . $p['position_id'] . ")\n";
                        }
                        echo "</pre>";
                        ?>
                        <h3 class="section-title">Positions & Candidates</h3>

                        <?php if (count($positions) > 0): ?>
                            <?php foreach ($positions as $position): ?>
                                <div class="position-card">
                                    <h4><?php echo htmlspecialchars($position['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($position['description']); ?></p>
                                    <small>Max votes: <?php echo $position['max_votes']; ?></small>

                                    <div class="action-links">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="position_id" value="<?php echo $position['position_id']; ?>">
                                            <button type="submit" name="delete_position" class="btn btn-danger" 
                                                    onclick="return confirm('Delete this position and all its candidates?')">
                                                Delete Position
                                            </button>
                                        </form>
                                    </div>

                                    <?php if (count($position['candidates']) > 0): ?>
                                        <div class="candidate-list">
                                            <h5>Candidates:</h5>
                                            <?php foreach ($position['candidates'] as $candidate): ?>
                                                <div class="candidate-item">
                                                    <strong><?php echo htmlspecialchars($candidate['name']); ?></strong>
                                                    <p><?php echo htmlspecialchars($candidate['platform']); ?></p>
                                                    <div class="action-links">
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="candidate_id" value="<?php echo $candidate['candidate_id']; ?>">
                                                            <button type="submit" name="delete_candidate" class="btn btn-danger" 
                                                                    onclick="return confirm('Delete this candidate?')">
                                                                Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p>No candidates yet.</p>
                                    <?php endif; ?>

                                    <!-- Add Candidate Form -->
                                    <form method="POST" style="margin-top: 1rem;">
                                        <input type="hidden" name="position_id" value="<?php echo $position['position_id']; ?>">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="candidate_name">Candidate Name</label>
                                                <input type="text" id="candidate_name" name="candidate_name" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="candidate_platform">Platform</label>
                                                <input type="text" id="candidate_platform" name="candidate_platform">
                                            </div>
                                        </div>
                                        <button type="submit" name="add_candidate" class="btn btn-secondary">Add Candidate</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No positions yet.</p>
                        <?php endif; ?>

                        <!-- Add Position Form -->
                        <form method="POST">
                            <input type="hidden" name="election_id" value="<?php echo $editing_election['election_id']; ?>">
                            <h3 class="section-title">Add New Position</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="position_title">Position Title</label>
                                    <input type="text" id="position_title" name="position_title" required>
                                </div>
                                <div class="form-group">
                                    <label for="max_votes">Max Votes</label>
                                    <input type="number" id="max_votes" name="max_votes" min="1" value="1">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="position_description">Description</label>
                                <textarea id="position_description" name="position_description"></textarea>
                            </div>
                            <button type="submit" name="add_position" class="btn btn-success">Add Position</button>
                        </form>

                    <?php else: ?>
                        <h3 class="section-title">Voting Options</h3>

                        <?php if (count($options) > 0): ?>
                            <?php foreach ($options as $option): ?>
                                <div class="option-card">
                                    <h4><?php echo htmlspecialchars($option['name']); ?></h4>
                                    <p><?php echo htmlspecialchars($option['description']); ?></p>
                                    <div class="action-links">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="option_id" value="<?php echo $option['option_id']; ?>">
                                            <button type="submit" name="delete_option" class="btn btn-danger" 
                                                    onclick="return confirm('Delete this option?')">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No options yet.</p>
                        <?php endif; ?>

                        <!-- Add Option Form -->
                        <form method="POST">
                            <input type="hidden" name="election_id" value="<?php echo $editing_election['election_id']; ?>">
                            <h3 class="section-title">Add New Option</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="option_name">Option Name</label>
                                    <input type="text" id="option_name" name="option_name" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="option_description">Description</label>
                                <textarea id="option_description" name="option_description"></textarea>
                            </div>
                            <button type="submit" name="add_option" class="btn btn-success">Add Option</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Create Election Modal -->
        <div id="create-election-modal" style="display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
            <div style="background-color: white; margin: 5% auto; padding: 2rem; border-radius: 8px; max-width: 600px; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
                <h2>Create New Election</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="new_title">Election Title</label>
                        <input type="text" id="new_title" name="title" required>
                    </div>

                    <div class="form-group">
                        <label for="new_description">Description</label>
                        <textarea id="new_description" name="description"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_start_date">Start Date</label>
                            <input type="datetime-local" id="new_start_date" name="start_date" required>
                        </div>
                        <div class="form-group">
                            <label for="new_end_date">End Date</label>
                            <input type="datetime-local" id="new_end_date" name="end_date" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_election_type">Election Type</label>
                        <select id="new_election_type" name="election_type" required>
                            <option value="candidates">Candidate-based (vote for people)</option>
                            <option value="options">Option-based (vote on choices)</option>
                        </select>
                    </div>

                    <div class="form-group" style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('create-election-modal').style.display = 'none'">Cancel</button>
                        <button type="submit" name="create_election" class="btn btn-success">Create Election</button>
                    </div>
                </form>
            </div>
        </div>

        <footer>
            <p>&copy; 2025 EduVote - Campus Voting System</p>
            <p>Contact: <a href="mailto:support@eduvote.edu" style="color: white;">support@eduvote.edu</a></p>
        </footer>

        <script>
            // Close modal when clicking outside
            window.onclick = function (event) {
                if (event.target == document.getElementById('create-election-modal')) {
                    document.getElementById('create-election-modal').style.display = "none";
                }
            }
        </script>
    </body>
</html>