<?php
session_start();
require_once __DIR__ . '/config/config.php';

// Redirect if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];


if ($role === 'student') {
    $stmt = $pdo->prepare("SELECT cm.club_id, c.name FROM club_members cm 
                           JOIN students s ON cm.student_id = s.id 
                           JOIN clubs c ON cm.club_id = c.id 
                           WHERE s.user_id = ?");
    $stmt->execute([$user_id]);
    $userClubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($role === 'advisor') {
    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $teacher_id = $stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT ca.club_id, c.name FROM club_advisors ca 
                           JOIN clubs c ON ca.club_id = c.id 
                           WHERE ca.advisor_id = ?");
    $stmt->execute([$teacher_id]);
    $userClubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->query("SELECT id AS club_id, name FROM clubs");
    $userClubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (isset($_GET['club_id']) && !empty($_GET['club_id'])) {
    $club_id = $_GET['club_id'];
} elseif (!empty($userClubs)) {
    $club_id = $userClubs[0]['club_id'];
} else {
    $club_id = null;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['post_title'], $_POST['post_content'], $_POST['club_id'])) {
    $post_title = $_POST['post_title'];
    $post_content = $_POST['post_content'];
    $club_id = $_POST['club_id'];

    if (!empty($post_title) && !empty($post_content)) {
        $stmt = $pdo->prepare("INSERT INTO forum_posts (club_id, user_id, title, content) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$club_id, $user_id, $post_title, $post_content])) {
            $message = "Post created successfully!";
        } else {
            $message = "Failed to create post.";
        }
    } else {
        $message = "Post title and content cannot be empty.";
    }
}

// Handle comment creation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['comment_content'], $_POST['post_id'])) {
    $comment_content = $_POST['comment_content'];
    $post_id = $_POST['post_id'];

    if (!empty($comment_content)) {
        $stmt = $pdo->prepare("INSERT INTO forum_comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
        if ($stmt->execute([$post_id, $user_id, $comment_content])) {
            $message = "Comment added successfully!";
        } else {
            $message = "Failed to add comment.";
        }
    } else {
        $message = "Comment content cannot be empty.";
    }
}

$stmt = $pdo->prepare("SELECT forum_posts.id AS post_id, forum_posts.title, forum_posts.content AS post_content, forum_posts.created_at AS post_date, users.name AS posted_by 
                       FROM forum_posts 
                       JOIN users ON forum_posts.user_id = users.id 
                       WHERE forum_posts.club_id = ? 
                       ORDER BY forum_posts.created_at DESC");
$stmt->execute([$club_id]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Forum</title>
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
        .overview-section {
            margin-bottom: 2rem;
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
        <h1>Forum - Club <?php echo htmlspecialchars($club_id); ?></h1>

        <?php if (isset($message)): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="clubSelect" class="form-label">Select Your Club:</label>
                    <select name="club_id" id="clubSelect" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($userClubs as $club): ?>
                            <option value="<?= htmlspecialchars($club['club_id']) ?>" <?= ($club['club_id'] == $club_id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($club['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <h3 class="mt-4">Create a Post</h3>
        <form method="POST">
            <input type="hidden" name="club_id" value="<?= htmlspecialchars($club_id) ?>">
            <div class="mb-3">
                <label for="post_title" class="form-label">Post Title:</label>
                <input type="text" id="post_title" name="post_title" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="post_content" class="form-label">Post Content:</label>
                <textarea id="post_content" name="post_content" class="form-control" rows="4" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Post</button>
        </form>
        <h3 class="mt-5">Posts</h3>
        <?php foreach ($posts as $post): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($post['posted_by']); ?></h5>
                    <p class="card-text"><?php echo nl2br(htmlspecialchars($post['post_content'])); ?></p>
                    <p class="card-text"><small class="text-muted"><?php echo $post['post_date']; ?></small></p>

                    <!-- Comment Section -->
                    <form method="POST" class="mt-3">
                        <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                        <div class="mb-3">
                            <label for="comment_content" class="form-label">Add a Comment:</label>
                            <textarea id="comment_content" name="comment_content" class="form-control" rows="2" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-success">Comment</button>
                    </form>

                    <!-- Display Comments -->
                    <h6 class="mt-3">Comments:</h6>
                    <?php
                    $comments_stmt = $pdo->prepare("SELECT forum_comments.content AS comment_content, forum_comments.created_at AS comment_date, users.name AS commented_by 
                                                                         FROM forum_comments 
                                                                         JOIN users ON forum_comments.user_id = users.id 
                                                                         WHERE forum_comments.post_id = ? 
                                                                         ORDER BY forum_comments.created_at");
                    $comments_stmt->execute([$post['post_id']]);
                    $comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($comments as $comment):
                    ?>
                        <div class="border p-2 mb-2">
                            <p><strong><?php echo htmlspecialchars($comment['commented_by']); ?>:</strong> <?php echo nl2br(htmlspecialchars($comment['comment_content'])); ?></p>
                            <p class="text-muted"><?php echo $comment['comment_date']; ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
