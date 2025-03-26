<?php
require_once __DIR__ . '/config/config.php';
session_start();

// Redirect if user is not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Get club ID from URL
$club_id = $_GET['id'] ?? null;
if (!$club_id || !is_numeric($club_id)) {
    $_SESSION['error'] = "Invalid club ID.";
    header("Location: student_dashboard.php");
    exit();
}

// Verify user is president/secretary of this club
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT cm.position 
    FROM club_members cm
    JOIN students s ON cm.student_id = s.id
    WHERE s.user_id = ? AND cm.club_id = ?
");
$stmt->execute([$user_id, $club_id]);
$position = $stmt->fetchColumn();

if (!$position || !in_array($position, ['president', 'secretary'])) {
    $_SESSION['error'] = "You do not have permission to manage this club.";
    header("Location: club_details.php?id=$club_id");
    exit();
}

// Fetch club details
$stmt = $pdo->prepare("SELECT * FROM clubs WHERE id = ?");
$stmt->execute([$club_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch club members
$stmt = $pdo->prepare("
    SELECT u.name, u.email, cm.position, cm.id AS member_id 
    FROM club_members cm
    JOIN students s ON cm.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE cm.club_id = ?
");
$stmt->execute([$club_id]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch club events
$stmt = $pdo->prepare("SELECT * FROM events WHERE club_id = ?");
$stmt->execute([$club_id]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch expenses (club-level and event-specific)
$stmt = $pdo->prepare("
    SELECT 'club' AS source, e.id, e.description, e.amount, e.category, e.created_at 
    FROM expenses e 
    WHERE e.club_id = ?
    UNION
    SELECT 'event' AS source, eb.id, eb.item AS description, eb.cost AS amount, 'Event Budget' AS category, eb.created_at 
    FROM event_budgets eb 
    JOIN events ev ON eb.event_id = ev.id 
    WHERE ev.club_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$club_id, $club_id]);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Add member to club
        if (isset($_POST['add_member'])) {
            $email = $_POST['email'];
            $position = $_POST['position'] ?? 'member';

            // Find student by email
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'student'");
            $stmt->execute([$email]);
            $user_id_to_add = $stmt->fetchColumn();

            if (!$user_id_to_add) {
                throw new Exception("Student with email '$email' not found.");
            }

            // Get student ID from students table
            $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
            $stmt->execute([$user_id_to_add]);
            $student_id = $stmt->fetchColumn();

            if (!$student_id) {
                throw new Exception("Student record not found.");
            }

            // Check if student is already a member
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_members WHERE student_id = ? AND club_id = ?");
            $stmt->execute([$student_id, $club_id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Student is already a member of this club.");
            }

            // Insert into club_members
            $stmt = $pdo->prepare("INSERT INTO club_members (student_id, club_id, position) VALUES (?, ?, ?)");
            $stmt->execute([$student_id, $club_id, $position]);

            $_SESSION['success'] = "Member added successfully!";
        }

        // Remove member from club
        if (isset($_POST['remove_member'])) {
            $member_id = $_POST['member_id'];
            $stmt = $pdo->prepare("DELETE FROM club_members WHERE id = ?");
            $stmt->execute([$member_id]);
            $_SESSION['success'] = "Member removed successfully!";
        }

        // Create event
        if (isset($_POST['create_event'])) {
            $name = $_POST['event_name'];
            $description = $_POST['event_description'];
            $date = $_POST['event_date'];
            $time = $_POST['event_time'];
            $location = $_POST['event_location'];

            $stmt = $pdo->prepare("
                INSERT INTO events (name, description, date, time, location, club_id) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $date, $time, $location, $club_id]);
            $_SESSION['success'] = "Event created successfully!";
        }

        // Create announcement
        if (isset($_POST['create_announcement'])) {
            $title = $_POST['announcement_title'];
            $content = $_POST['announcement_content'];

            $stmt = $pdo->prepare("
                INSERT INTO club_announcements (club_id, user_id, title, content) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$club_id, $user_id, $title, $content]);
            $_SESSION['success'] = "Announcement created successfully!";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    header("Location: club_manager_dashboard.php?id=$club_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage <?= htmlspecialchars($club['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f9f9f9;
            color: #333;
        }
        .navbar {
            background-color: #000;
        }
        .navbar-brand, .navbar-nav .nav-link {
            color: #fff !important;
        }
        .card {
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .badge {
            background-color: #000;
            color: #fff;
        }
        .member-email {
            font-size: 0.85rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Club Management</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="student_dashboard.php">Student Dashboard</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php elseif (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <h2 class="mb-4">Manage <?= htmlspecialchars($club['name']) ?></h2>

        <!-- Add Member Form -->
        <div class="card mb-4">
            <div class="card-header bg-black text-white">
                <h3 class="mb-0">Add Member</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Student Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Position</label>
                        <select name="position" class="form-select" required>
                            <option value="member">Member</option>
                            <option value="president">President</option>
                            <option value="secretary">Secretary</option>
                            <option value="treasurer">Treasurer</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" name="add_member" class="btn btn-primary w-100">
                            Add Member
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Current Members -->
        <div class="card mb-4">
            <div class="card-header bg-black text-white">
                <h3 class="mb-0">Club Members</h3>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Position</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td><?= htmlspecialchars($member['name']) ?></td>
                                <td><?= htmlspecialchars($member['email']) ?></td>
                                <td><?= ucfirst($member['position']) ?></td>
                                <td>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure?')">
                                        <input type="hidden" name="member_id" value="<?= $member['member_id'] ?>">
                                        <button type="submit" name="remove_member" class="btn btn-danger btn-sm">
                                            Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create Event Form -->
        <div class="card mb-4">
            <div class="card-header bg-black text-white">
                <h3 class="mb-0">Create Event</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Event Name</label>
                        <input type="text" name="event_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Date</label>
                        <input type="date" name="event_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Time</label>
                        <input type="time" name="event_time" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Location</label>
                        <input type="text" name="event_location" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="event_description" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="create_event" class="btn btn-success">
                            Create Event
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Create Announcement Form -->
        <div class="card mb-4">
            <div class="card-header bg-black text-white">
                <h3 class="mb-0">Create Announcement</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Title</label>
                        <input type="text" name="announcement_title" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Content</label>
                        <textarea name="announcement_content" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="create_announcement" class="btn btn-warning">
                            Post Announcement
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Expenses Section -->
        <div class="card">
            <div class="card-header bg-black text-white">
                <h3 class="mb-0">Club Expenses</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($expenses)): ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Source</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Category</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td><?= ucfirst($expense['source']) ?></td>
                                    <td><?= htmlspecialchars($expense['description']) ?></td>
                                    <td>$<?= number_format($expense['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($expense['category']) ?></td>
                                    <td><?= date('M j, Y', strtotime($expense['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-muted">No expenses recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>