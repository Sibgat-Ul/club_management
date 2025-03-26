<?php
require_once __DIR__ . '/config/config.php'; // Correct path to config file
session_start(); // Enable sessions

// Fetch all clubs with their details
$clubs = $pdo->query("
    SELECT c.id, c.name, c.description, c.type, COUNT(cm.student_id) AS total_members
    FROM clubs c
    LEFT JOIN club_members cm ON c.id = cm.club_id
    GROUP BY c.id
    ORDER BY c.name ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clubs - Club Management</title>
    <!-- Bootstrap CSS -->
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
        .member-count {
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
        <h2 class="mb-4">All Clubs</h2>

        <div class="row">
            <?php foreach ($clubs as $club): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($club['name']) ?></h5>
                            <p class="card-text text-muted">
                                <strong>Type:</strong> <?= ucfirst($club['type']) ?><br>
                                <span class="member-count"><strong>Members:</strong> <?= $club['total_members'] ?></span>
                            </p>
                            <p class="card-text"><?= nl2br(htmlspecialchars(substr($club['description'], 0, 100))) ?>...</p>
                            <a href="club_details.php?id=<?= $club['id'] ?>" class="btn btn-primary w-100">
                                View Club Details
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($clubs)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        No clubs available at this time.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>