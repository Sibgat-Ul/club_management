<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
require_once __DIR__ . '/config/config.php';

// Fetch all users
$users = $pdo->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);

// Fetch clubs with advisor
$clubs = $pdo->query("
    SELECT c.*, u.name as advisor_name 
    FROM clubs c 
    LEFT JOIN club_advisors ca ON c.id = ca.club_id 
    LEFT JOIN teachers t ON ca.advisor_id = t.id 
    LEFT JOIN users u ON t.user_id = u.id
")->fetchAll(PDO::FETCH_ASSOC);

// $clubs = $pdo->query("SELECT c.*, GROUP_CONCAT(u.name SEPARATOR ', ') as advisor_name
// FROM clubs c 
// LEFT JOIN club_advisors ca ON c.id = ca.club_id
// LEFT JOIN teachers t ON ca.advisor_id = t.id
// LEFT JOIN users u ON t.user_id = u.id
// GROUP BY c.id
// ORDER BY c.name ASC
// ")->fetchAll(PDO::FETCH_ASSOC);

$students = $pdo->query("
    SELECT s.id, u.name, u.email, GROUP_CONCAT(cm.club_id) AS club_ids 
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN club_members cm ON s.id = cm.student_id
    GROUP BY s.id
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch teachers from users table
$teachers = $pdo->query("
    SELECT t.id, u.name, u.email, t.department 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id
")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['delete_user'])) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$_POST['user_id']]);
    }
    
    if (isset($_POST['delete_club'])) {
        $stmt = $pdo->prepare("DELETE FROM clubs WHERE id = ?");
        $stmt->execute([$_POST['club_id']]);
    }

    if (isset($_POST['assign_advisor'])) {
        // Remove existing advisor for the club first
        $stmt = $pdo->prepare("DELETE FROM club_advisors WHERE club_id = ?");
        $stmt->execute([$_POST['club_id']]);
        
        // Assign new advisor (using advisor_id column)
        $stmt = $pdo->prepare("INSERT INTO club_advisors (club_id, advisor_id) VALUES (?, ?)");
        $stmt->execute([$_POST['club_id'], $_POST['teacher_id']]);
    }

    if (isset($_POST['add_teacher'])) {
        // Insert a new user with role 'advisor'
        $name = $_POST['name'];
        $email = $_POST['email'];
        $department = $_POST['department'];
        $default_password = 'defaultpassword'; // No hashing, as requested
        $role = 'advisor';

        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $default_password, $role]);
        $user_id = $pdo->lastInsertId();

        // Insert into teachers table using the new user_id
        $stmt = $pdo->prepare("INSERT INTO teachers (user_id, department) VALUES (?, ?)");
        $stmt->execute([$user_id, $department]);
    }

    if (isset($_POST['create_club'])) {
        // Create new club
        $club_name = $_POST['club_name'];
        $description = $_POST['description'];
        $contact = $_POST['contact'];
        $type = $_POST['type'];
        
        $stmt = $pdo->prepare("INSERT INTO clubs (name, description, contact, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$club_name, $description, $contact, $type]);
    }
    header("Location: admin_dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .nav-tabs .nav-link.active {
            font-weight: bold;
        }
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

    <div class="container mt-4">
        <h2><i class="bi bi-speedometer2"></i> Admin Dashboard</h2>
        
        <ul class="nav nav-tabs mt-4" id="adminTabs">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#users">Users</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#clubs">Clubs</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#teachers">Teachers</a>
            </li>
        </ul>

        <div class="tab-content mt-3">
            <!-- Users Tab -->
            <div class="tab-pane fade show active" id="users">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="bi bi-people"></i> Manage Users</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['id']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['role']) ?></td>
                                    <td>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" name="delete_user" class="btn btn-danger btn-sm">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Clubs Tab -->
            <div class="tab-pane fade" id="clubs">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="bi bi-collection"></i> Manage Clubs</h4>
                    </div>
                    <div class="card-body">
                        <!-- Existing Clubs Table -->
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Advisor</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clubs as $club): ?>
                                <tr>
                                    <td><?= htmlspecialchars($club['id']) ?></td>
                                    <td><?= htmlspecialchars($club['name']) ?></td>
                                    <td><?= htmlspecialchars($club['advisor_name'] ?? 'None') ?></td>
                                    <td>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="club_id" value="<?= $club['id'] ?>">
                                            <button type="submit" name="delete_club" class="btn btn-danger btn-sm">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                        <a href="club_details.php?id=<?= $club['id'] ?>" class="btn btn-info btn-sm">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Create New Club Form -->
                <div class="card mt-4">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="bi bi-plus-circle"></i> Create New Club</h4>
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Club Name</label>
                                <input type="text" name="club_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact</label>
                                <input type="text" name="contact" class="form-control" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Type</label>
                                <select name="type" class="form-select" required>
                                    <option value="academic">Academic</option>
                                    <option value="sports">Sports</option>
                                    <option value="social">Social</option>
                                    <option value="environmental">Environmental</option>
                                    <option value="business">Business</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" name="create_club" class="btn btn-success w-100">
                                    <i class="bi bi-save"></i> Create
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="bi bi-person-plus"></i> Assign Advisor to Club</h4>
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">Select Club</label>
                                <select name="club_id" class="form-select" required>
                                    <?php foreach ($clubs as $club): ?>
                                    <option value="<?= $club['id'] ?>"><?= htmlspecialchars($club['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Select Teacher</label>
                                <select name="teacher_id" class="form-select" required>
                                    <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?= $teacher['id'] ?>">
                                        <?= htmlspecialchars($teacher['name']) ?> (<?= htmlspecialchars($teacher['department']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" name="assign_advisor" class="btn btn-primary w-100">
                                    <i class="bi bi-save"></i> Assign
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="bi bi-person-plus"></i> Assign Club Manager</h4>
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">Select Club</label>
                                <select name="club_id" class="form-select" id="clubSelect" required>
                                    <?php foreach ($clubs as $club): ?>
                                        <option value="<?= $club['id'] ?>"><?= htmlspecialchars($club['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Select Student (Existing Member)</label>
                                <select name="student_id" class="form-select" id="studentSelect" required>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?= $student['id'] ?>" data-club-ids="<?= htmlspecialchars($student['club_ids']) ?>">
                                            <?= htmlspecialchars($student['name']) ?> (<?= htmlspecialchars($student['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" name="assign_club_manager" class="btn btn-primary w-100">
                                    <i class="bi bi-save"></i> Assign
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
            </div>

            <!-- Teachers Tab -->
            <div class="tab-pane fade" id="teachers">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="bi bi-person-badge"></i> Manage Teachers</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teachers as $teacher): ?>
                                <tr>
                                    <td><?= htmlspecialchars($teacher['id']) ?></td>
                                    <td><?= htmlspecialchars($teacher['name']) ?></td>
                                    <td><?= htmlspecialchars($teacher['email']) ?></td>
                                    <td><?= htmlspecialchars($teacher['department']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Add New Teacher Form -->
                <div class="card mt-4">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="bi bi-person-plus"></i> Add New Teacher</h4>
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Department</label>
                                <input type="text" name="department" class="form-control" required>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" name="add_teacher" class="btn btn-primary w-100">
                                    <i class="bi bi-plus-lg"></i> Add
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('clubSelect').addEventListener('change', function() {
            const selectedClubId = this.value;
            const studentSelect = document.getElementById('studentSelect');
            
            // Reset and disable all options first
            Array.from(studentSelect.options).forEach(option => {
                option.disabled = true;
                option.style.display = 'none';
            });

            // Enable options that belong to the selected club
            Array.from(studentSelect.options).forEach(option => {
                const clubIds = option.dataset.clubIds.split(',').map(id => id.trim());
                if (clubIds.includes(selectedClubId)) {
                    option.disabled = false;
                    option.style.display = 'block';
                }
            });

            // Reset selection
            studentSelect.value = '';
        });
    </script>
</body>
</html>
