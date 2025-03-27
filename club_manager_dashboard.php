<?php
require_once __DIR__ . '/config/config.php';
session_start();

// Redirect if user is not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Get the student ID for the logged-in user
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student_id = $stmt->fetchColumn();

    if (!$student_id) {
        throw new Exception("Student record not found for the logged-in user.");
    }

    // Get the club ID for the student (assuming they can only belong to one club)
    $stmt = $pdo->prepare("SELECT club_id FROM club_members WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $club_id = $stmt->fetchColumn();

    if (!$club_id) {
        throw new Exception("You are not a member of any club.");
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: student_dashboard.php");
    exit();
}

// Fetch club details
$stmt = $pdo->prepare("SELECT * FROM clubs WHERE id = ?");
$stmt->execute([$club_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

// ------------------- OVERVIEW QUERIES ------------------- //
// Total members
$stmt = $pdo->prepare("SELECT COUNT(*) FROM club_members WHERE club_id = ?");
$stmt->execute([$club_id]);
$total_members = $stmt->fetchColumn();

// Club expenses (club-level)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE club_id = ?");
$stmt->execute([$club_id]);
$club_expenses = $stmt->fetchColumn();

// Event expenditures from event_budgets
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(eb.cost), 0) 
    FROM event_budgets eb 
    JOIN events ev ON eb.event_id = ev.id 
    WHERE ev.club_id = ?
");
$stmt->execute([$club_id]);
$event_expenses = $stmt->fetchColumn();

$total_budget = $club_expenses + $event_expenses;

// Due payments from dues table (for members in this club)
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM dues 
    WHERE status = 'unpaid' 
      AND member_id IN (SELECT id FROM club_members WHERE club_id = ?)
");
$stmt->execute([$club_id]);
$due_payments = $stmt->fetchColumn();

// Advisors of the club (joining teachers with users to get the name)
$stmt = $pdo->prepare("
    SELECT u.name, t.department 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    JOIN club_advisors ca ON t.id = ca.advisor_id 
    WHERE ca.club_id = ?
");
$stmt->execute([$club_id]);
$advisors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total events count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM events WHERE club_id = ?");
$stmt->execute([$club_id]);
$total_events = $stmt->fetchColumn();

// Data for Members Joined Graph (grouped by join date)
$stmt = $pdo->prepare("
    SELECT DATE(created_at) AS join_date, COUNT(*) AS count 
    FROM club_members 
    WHERE club_id = ? 
    GROUP BY DATE(created_at) 
    ORDER BY join_date ASC
");
$stmt->execute([$club_id]);
$memberJoinData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Data for Expenses per Event Graph
$stmt = $pdo->prepare("
    SELECT ev.name AS event_name, COALESCE(SUM(eb.cost), 0) AS total_event_expense
    FROM events ev 
    LEFT JOIN event_budgets eb ON ev.id = eb.event_id 
    WHERE ev.club_id = ?
    GROUP BY ev.id
");
$stmt->execute([$club_id]);
$expensesEventData = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Handle form submissions (Add Member, Remove Member, Create Event, Create Announcement, Add Event Expenditure)
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

            // Insert into club_announcements table
            $stmt = $pdo->prepare("
                INSERT INTO club_announcements (club_id, title, description) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$club_id, $title, $content]);
            $_SESSION['success'] = "Announcement created successfully!";
        }

        // Add event expenditure (budget)
        if (isset($_POST['add_expenditure'])) {
            $event_id = $_POST['event_id'];
            $item = $_POST['item'];
            $cost = $_POST['cost'];

            $stmt = $pdo->prepare("INSERT INTO event_budgets (event_id, item, cost) VALUES (?, ?, ?)");
            $stmt->execute([$event_id, $item, $cost]);
            $_SESSION['success'] = "Event expenditure added successfully!";
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
    <!-- Include Chart.js for graphs -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php elseif (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- OVERVIEW SECTION -->
        <section class="overview-section">
            <h2 class="mb-4">Club Overview: <?= htmlspecialchars($club['name']) ?></h2>
            <div class="overview-row">
                <!-- Left Column: Summary Card -->
                <div class="overview-col">
                    <div class="card">
                        <div class="card-header bg-black text-white">
                            Summary
                        </div>
                        <div class="card-body">
                            <p><strong>Club Budget:</strong> $<?= number_format($total_budget, 2) ?></p>
                            <p><strong>Total Events:</strong> <?= $total_events ?></p>
                            <p><strong>Total Members:</strong> <?= $total_members ?></p>
                            <p><strong>Advisors:</strong> 
                                <?php if (!empty($advisors)): ?>
                                    <?php foreach ($advisors as $advisor): ?>
                                        <?= htmlspecialchars($advisor['name']) ?> (<?= htmlspecialchars($advisor['department']) ?>)<br>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    None assigned.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Right Column: Reports & Analysis -->
                <div class="overview-col">
                    <div class="card">
                        <div class="card-header bg-black text-white">
                            Reports & Analysis
                        </div>
                        <div class="card-body">
                            <canvas id="membersJoinedChart"></canvas>
                            <hr>
                            <canvas id="expensesPerEventChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </section>

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
                        <button type="submit" name="add_member" class="btn btn-primary w-100">Add Member</button>
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
                                        <button type="submit" name="remove_member" class="btn btn-danger btn-sm">Remove</button>
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
                        <button type="submit" name="create_event" class="btn btn-success">Create Event</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Event Expenditure Form -->
        <div class="card mb-4">
            <div class="card-header bg-black text-white">
                <h3 class="mb-0">Add Event Expenditure</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Select Event</label>
                        <select name="event_id" class="form-select" required>
                            <?php foreach ($events as $event): ?>
                                <option value="<?= $event['id'] ?>"><?= htmlspecialchars($event['name']) ?> (<?= date('M j, Y', strtotime($event['date'])) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Item</label>
                        <input type="text" name="item" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cost</label>
                        <input type="number" step="0.01" name="cost" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="add_expenditure" class="btn btn-primary">Add Expenditure</button>
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
                        <button type="submit" name="create_announcement" class="btn btn-warning">Post Announcement</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Expenses Section -->
        <div class="card mb-4">
            <div class="card-header bg-black text-white">
                <h3 class="mb-0">Club Expenses</h3>
            </div>
            <div class="card-body">
                <!-- Collapsible form to add event expenditure -->
                <button class="btn btn-primary mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#addExpenditureForm" aria-expanded="false" aria-controls="addExpenditureForm">
                    Add Event Expenditure
                </button>
                <div class="collapse" id="addExpenditureForm">
                    <div class="card card-body mb-3">
                        <form method="POST" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Select Event</label>
                                <select name="event_id" class="form-select" required>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?= $event['id'] ?>">
                                            <?= htmlspecialchars($event['name']) ?> (<?= date('M j, Y', strtotime($event['date'])) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Item</label>
                                <input type="text" name="item" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cost</label>
                                <input type="number" step="0.01" name="cost" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="add_expenditure" class="btn btn-primary">Add Expenditure</button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- End of expenditure form -->

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

    <!-- Graphs Script -->
    <script>
        // Members Joined Chart
        const memberJoinLabels = [
            <?php foreach ($memberJoinData as $data): ?>
                "<?= $data['join_date'] ?>",
            <?php endforeach; ?>
        ];
        const memberJoinCounts = [
            <?php foreach ($memberJoinData as $data): ?>
                <?= $data['count'] ?>,
            <?php endforeach; ?>
        ];

        const ctxMembers = document.getElementById('membersJoinedChart').getContext('2d');
        const membersJoinedChart = new Chart(ctxMembers, {
            type: 'line',
            data: {
                labels: memberJoinLabels,
                datasets: [{
                    label: 'Members Joined',
                    data: memberJoinCounts,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        });

        // Expenses per Event Chart
        const expenseEventLabels = [
            <?php foreach ($expensesEventData as $data): ?>
                "<?= $data['event_name'] ?>",
            <?php endforeach; ?>
        ];
        const expenseEventTotals = [
            <?php foreach ($expensesEventData as $data): ?>
                <?= $data['total_event_expense'] ?>,
            <?php endforeach; ?>
        ];

        const ctxExpenses = document.getElementById('expensesPerEventChart').getContext('2d');
        const expensesPerEventChart = new Chart(ctxExpenses, {
            type: 'bar',
            data: {
                labels: expenseEventLabels,
                datasets: [{
                    label: 'Expenses per Event',
                    data: expenseEventTotals,
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
