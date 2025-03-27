<?php
require_once 'config/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    $phone = isset($_POST['phone']) ? $_POST['phone'] : null;      
    $department = isset($_POST['department']) ? $_POST['department'] : null; 

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        if ($stmt->rowCount() > 0) {
            $error = "Email already exists. Please use a different email.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)");
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => $role
            ]);

            $user_id = $pdo->lastInsertId();

            if ($role === 'student') {
                $stmt = $pdo->prepare("INSERT INTO students (user_id, phone) VALUES (:user_id, :phone)");
                $stmt->execute(['user_id' => $user_id, 'phone' => $phone]);
            } elseif ($role === 'admin') {
                $stmt = $pdo->prepare("INSERT INTO admins (user_id) VALUES (:user_id)");
                $stmt->execute(['user_id' => $user_id]);
            } elseif ($role === 'advisor') {
                $stmt = $pdo->prepare("INSERT INTO teachers (user_id, department) VALUES (:user_id, :department)");
                $stmt->execute(['user_id' => $user_id, 'department' => $department]);
            }

            header("Location: login.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $error = "An error occurred. Please try again later.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Club Management</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
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
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header text-center">
                        <h3>Sign Up</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="admin">Admin</option>
                                    <option value="club_manager">Club Manager</option>
                                    <option value="student">Student</option>
                                    <option value="advisor">Advisor</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success w-100">Sign Up</button>
                        </form>
                        <div class="text-center mt-3">
                            <a href="login.php">Already have an account? Login here.</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>