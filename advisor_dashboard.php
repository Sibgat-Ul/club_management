<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'advisor') {
    header("Location: login.php");
    exit();
}
require_once __DIR__ . '/config/config.php';

// Get advisor's teacher id from the teachers table using the session's user_id
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$advisor_id = $stmt->fetchColumn();
if (!$advisor_id) {
    die("Advisor record not found.");
}

$message = "";

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Meeting creation
    if (isset($_POST['create_meeting'])) {
        $club_id = $_POST['club_id_meeting'];
        $date = $_POST['date'];
        $time = $_POST['time'];
        $location = $_POST['location'];

        $stmt = $pdo->prepare("INSERT INTO club_advisor_meeting (club_id, advisor_id, date, time, location) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$club_id, $advisor_id, $date, $time, $location])) {
            $message = "Meeting scheduled successfully!";
        } else {
            $message = "Failed to schedule meeting.";
        }
    }

    // 2. Promote/Demote Club Member
    if (isset($_POST['update_member_role'])) {
        $club_member_id = $_POST['club_member_id'];
        $new_role = $_POST['new_role']; // Expected: 'president', 'secretary', 'treasurer', or 'member'

        // Get the club_id for this member record
        $stmt = $pdo->prepare("SELECT club_id FROM club_members WHERE id = ?");
        $stmt->execute([$club_member_id]);
        $club_id = $stmt->fetchColumn();

        if (!$club_id) {
            $message = "Club member not found.";
        } else {
            // If new role is one of the officer roles (and not "member"), demote any existing member in that club with that role.
            if (in_array($new_role, ['president', 'secretary', 'treasurer'])) {
                $stmt = $pdo->prepare("UPDATE club_members SET position = 'member' WHERE club_id = ? AND position = ?");
                $stmt->execute([$club_id, $new_role]);
            }
            // Update the selected member's role
            $stmt = $pdo->prepare("UPDATE club_members SET position = ? WHERE id = ?");
            if ($stmt->execute([$new_role, $club_member_id])) {
                $message = "Member role updated successfully!";
            } else {
                $message = "Failed to update member role.";
            }
        }
    }

    // 3. Create Club Activity
    if (isset($_POST['create_activity'])) {
        $club_id = $_POST['activity_club_id'];
        $activity_date = $_POST['activity_date'];
        $activity_time = $_POST['activity_time'];
        $activity_location = $_POST['activity_location'];
        $activity_description = $_POST['activity_description'];

        $stmt = $pdo->prepare("INSERT INTO club_activity (club_id, activity_date, activity_time, activity_location, activity_description) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$club_id, $activity_date, $activity_time, $activity_location, $activity_description])) {
            $message = "Club activity created successfully!";
        } else {
            $message = "Failed to create club activity.";
        }
    }

    header("Location: advisor_dashboard.php");
    exit();
}

// Fetch advisor's clubs
$stmt = $pdo->prepare("SELECT clubs.id, clubs.name FROM clubs 
                       JOIN club_advisors ON clubs.id = club_advisors.club_id 
                       WHERE club_advisors.advisor_id = ?");
$stmt->execute([$advisor_id]);
$clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch upcoming meetings for this advisor
$meetings_stmt = $pdo->prepare("SELECT clubs.name AS club_name, date, time, location 
                                FROM club_advisor_meeting 
                                JOIN clubs ON club_advisor_meeting.club_id = clubs.id 
                                WHERE club_advisor_meeting.advisor_id = ? 
                                ORDER BY date, time");
$meetings_stmt->execute([$advisor_id]);
$meetings = $meetings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch club members for clubs that the advisor is assigned to
// We get each member's club_member id, club id, current position, and member name.
$clubIds = array_column($clubs, 'id');
$clubMembers = [];
if (!empty($clubIds)) {
    $placeholders = implode(',', array_fill(0, count($clubIds), '?'));
    $stmt = $pdo->prepare("
        SELECT cm.id AS club_member_id, cm.club_id, cm.position, u.name AS member_name, c.name AS club_name
        FROM club_members cm
        JOIN students s ON cm.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN clubs c ON cm.club_id = c.id
        WHERE c.id IN ($placeholders)
    ");
    $stmt->execute($clubIds);
    $clubMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advisor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f9f9f9; color: #333; }
        .navbar { background-color: #000; }
        .navbar-brand, .navbar-nav .nav-link { color: #fff !important; }
        .card { border: none; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .badge { background-color: #000; color: #fff; }
        .member-email { font-size: 0.85rem; color: #6c757d; }
        .overview-section { margin-bottom: 2rem; }
        /* Two-column overview layout */
        .overview-row { display: flex; flex-wrap: wrap; gap: 1rem; }
        .overview-col { flex: 1; min-width: 300px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Club Management</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="forum.php">Forum</a>
                <a class="nav-link" href="message.php">Message</a>
                <a class="nav-link" href="clubs.php">Clubs</a>
                <a class="nav-link" href="events.php">Events</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a class="nav-link" href="<?= $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : ($_SESSION['role'] === 'club_manager' ? 'club_manager_dashboard.php' : 'student_dashboard.php') ?>">Dashboard</a>
                    <a class="nav-link" href="logout.php">Logout</a>
                <?php else: ?>
                    <a class="nav-link" href="login.php">Login</a>
                    <a class="nav-link" href="signup.php">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <div class="container mt-5">
        <h2>Welcome, Advisor!</h2>
        <?php if (!empty($message)): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- Meeting Scheduling Section -->
        <h3 class="mt-4">Schedule a Meeting</h3>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Select Club:</label>
                <select name="club_id_meeting" class="form-control" required>
                    <?php foreach ($clubs as $club): ?>
                        <option value="<?php echo $club['id']; ?>"><?php echo htmlspecialchars($club['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Date:</label>
                <input type="date" name="date" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Time:</label>
                <input type="time" name="time" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Location:</label>
                <input type="text" name="location" class="form-control" required>
            </div>
            <button type="submit" name="create_meeting" class="btn btn-primary">Schedule Meeting</button>
        </form>

        <!-- Upcoming Meetings Section -->
        <h3 class="mt-5">Upcoming Meetings</h3>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Club</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Location</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($meetings as $meeting): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($meeting['club_name']); ?></td>
                        <td><?php echo htmlspecialchars($meeting['date']); ?></td>
                        <td><?php echo htmlspecialchars($meeting['time']); ?></td>
                        <td><?php echo htmlspecialchars($meeting['location']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Manage Club Members Section -->
        <h3 class="mt-5">Promote/Demote Club Member</h3>
        <form method="POST" class="row g-3">
            <div class="col-md-5">
                <label class="form-label">Select Club Member:</label>
                <select name="club_member_id" class="form-select" required>
                    <?php foreach ($clubMembers as $member): ?>
                        <option value="<?= $member['club_member_id'] ?>">
                            <?= htmlspecialchars($member['member_name']) ?> (<?= htmlspecialchars($member['club_name']) ?>, current: <?= htmlspecialchars($member['position']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">New Role:</label>
                <select name="new_role" class="form-select" required>
                    <option value="member">Member</option>
                    <option value="president">President</option>
                    <option value="secretary">Secretary</option>
                    <option value="treasurer">Treasurer</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" name="update_member_role" class="btn btn-warning w-100">Update Role</button>
            </div>
        </form>

        <!-- Create Club Activity Section -->
        <h3 class="mt-5">Create Club Activity</h3>
        <form method="POST" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Select Club:</label>
                <select name="activity_club_id" class="form-select" required>
                    <?php foreach ($clubs as $club): ?>
                        <option value="<?= $club['id'] ?>"><?= htmlspecialchars($club['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Date:</label>
                <input type="date" name="activity_date" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Time:</label>
                <input type="time" name="activity_time" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Location:</label>
                <input type="text" name="activity_location" class="form-control" required>
            </div>
            <div class="col-md-12">
                <label class="form-label">Description:</label>
                <textarea name="activity_description" class="form-control" rows="3" required></textarea>
            </div>
            <div class="col-md-2">
                <button type="submit" name="create_activity" class="btn btn-success w-100">Create Activity</button>
            </div>
        </form>

        <a href="logout.php" class="btn btn-danger mt-3">Logout</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
