<?php
require_once __DIR__ . '/config/config.php';

if (!isset($_GET['id'])) {
    die("Event ID not specified.");
}

$event_id = $_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT events.*, clubs.name AS club_name FROM events 
                           JOIN clubs ON events.club_id = clubs.id 
                           WHERE events.id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        die("Event not found.");
    }
} catch (PDOException $e) {
    die("Error fetching event: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['name']); ?> - Event Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
        <h2 class="mb-4"><?php echo htmlspecialchars($event['name']); ?></h2>
        <p><strong>Date:</strong> <?php echo $event['date']; ?></p>
        <p><strong>Time:</strong> <?php echo $event['time']; ?></p>
        <p><strong>Location:</strong> <?php echo htmlspecialchars($event['location']); ?></p>
        <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
        <p><strong>Organized by:</strong> <a href="club_details.php?id=<?php echo $event['club_id']; ?>"><?php echo htmlspecialchars($event['club_name']); ?></a></p>
        <a href="index.php" class="btn btn-primary">Back to Events</a>
    </div>
</body>
</html>
