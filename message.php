<?php
require_once __DIR__ . '/config/config.php';
session_start();

// Only allow students or advisors to access this page.
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'advisor'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$club_id = null;

// Determine the club ID based on role.
if ($role === 'student') {
    // Get the student record.
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student_id = $stmt->fetchColumn();
    if (!$student_id) {
        $_SESSION['error'] = "Student record not found.";
        header("Location: student_dashboard.php");
        exit();
    }
    // Get club ID from club_members.
    $stmt = $pdo->prepare("SELECT club_id FROM club_members WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $club_id = $stmt->fetchColumn();
    if (!$club_id) {
        $_SESSION['error'] = "You are not a member of any club.";
        header("Location: student_dashboard.php");
        exit();
    }
} elseif ($role === 'advisor') {
    // Get the teacher record.
    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $teacher_id = $stmt->fetchColumn();
    if (!$teacher_id) {
        $_SESSION['error'] = "Advisor record not found.";
        header("Location: login.php");
        exit();
    }
    // Get club ID from club_advisors (assuming one club per advisor for simplicity).
    $stmt = $pdo->prepare("SELECT club_id FROM club_advisors WHERE advisor_id = ?");
    $stmt->execute([$teacher_id]);
    $club_id = $stmt->fetchColumn();
    if (!$club_id) {
        $_SESSION['error'] = "You are not assigned as an advisor to any club.";
        header("Location: login.php");
        exit();
    }
}

// Process new message form submission.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $recipient_id = $_POST['recipient_id'];
    $subject = trim($_POST['subject']);
    $content = trim($_POST['content']);

    if (empty($recipient_id) || empty($subject) || empty($content)) {
        $_SESSION['error'] = "Please fill in all fields.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO messages (club_id, sender_id, recipient_id, subject, content) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$club_id, $user_id, $recipient_id, $subject, $content]);
        $_SESSION['success'] = "Message sent successfully!";
        header("Location: message.php");
        exit();
    }
}

// Fetch all messages for this club.
$stmt = $pdo->prepare("SELECT m.*, su.name AS sender_name, ru.name AS recipient_name 
                       FROM messages m
                       JOIN users su ON m.sender_id = su.id
                       LEFT JOIN users ru ON m.recipient_id = ru.id
                       WHERE m.club_id = ?
                       ORDER BY m.created_at ASC");
$stmt->execute([$club_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build a recipient list: fetch both club members and advisors for this club.
$membersRecipients = [];
$stmt = $pdo->prepare("SELECT u.id, u.name 
                       FROM club_members cm 
                       JOIN students s ON cm.student_id = s.id 
                       JOIN users u ON s.user_id = u.id 
                       WHERE cm.club_id = ?");
$stmt->execute([$club_id]);
$membersRecipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$advisorsRecipients = [];
$stmt = $pdo->prepare("SELECT u.id, u.name 
                       FROM club_advisors ca 
                       JOIN teachers t ON ca.advisor_id = t.id 
                       JOIN users u ON t.user_id = u.id 
                       WHERE ca.club_id = ?");
$stmt->execute([$club_id]);
$advisorsRecipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Messages</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .message-container { max-height: 400px; overflow-y: auto; }
        .message { padding: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 5px; }
        .message .meta { font-size: 0.9rem; color: #666; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
       <div class="container">
           <a class="navbar-brand" href="index.php">Club Management</a>
           <div class="navbar-nav ms-auto">
               <a class="nav-link" href="message.php">Messages</a>
               <a class="nav-link" href="logout.php">Logout</a>
           </div>
       </div>
    </nav>
    <div class="container mt-4">
       <?php if (isset($_SESSION['success'])): ?>
          <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
          <?php unset($_SESSION['success']); ?>
       <?php elseif (isset($_SESSION['error'])): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
          <?php unset($_SESSION['error']); ?>
       <?php endif; ?>

       <h2>Club Messages</h2>

       <!-- Message Form -->
       <div class="card mb-4">
           <div class="card-header">Send a Message</div>
           <div class="card-body">
               <form method="POST">
                   <div class="mb-3">
                       <label for="recipient_id" class="form-label">Recipient</label>
                       <select name="recipient_id" id="recipient_id" class="form-select" required>
                           <option value="">-- Select Recipient --</option>
                           <optgroup label="Members">
                           <?php foreach ($membersRecipients as $recip): 
                                  if ($recip['id'] == $user_id) continue; ?>
                               <option value="<?= $recip['id'] ?>"><?= htmlspecialchars($recip['name']) ?></option>
                           <?php endforeach; ?>
                           </optgroup>
                           <optgroup label="Advisors">
                           <?php foreach ($advisorsRecipients as $recip): 
                                  if ($recip['id'] == $user_id) continue; ?>
                               <option value="<?= $recip['id'] ?>"><?= htmlspecialchars($recip['name']) ?></option>
                           <?php endforeach; ?>
                           </optgroup>
                       </select>
                   </div>
                   <div class="mb-3">
                       <label for="subject" class="form-label">Subject</label>
                       <input type="text" name="subject" id="subject" class="form-control" required>
                   </div>
                   <div class="mb-3">
                       <label for="content" class="form-label">Message</label>
                       <textarea name="content" id="content" rows="4" class="form-control" required></textarea>
                   </div>
                   <button type="submit" name="send_message" class="btn btn-primary">Send Message</button>
               </form>
           </div>
       </div>

       <!-- Message List -->
       <div class="card">
           <div class="card-header">Message History</div>
           <div class="card-body message-container">
               <?php if (!empty($messages)): ?>
                   <?php foreach ($messages as $msg): ?>
                       <div class="message">
                           <div class="meta">
                               <strong>From:</strong> <?= htmlspecialchars($msg['sender_name']) ?>
                               <?php if ($msg['recipient_name']): ?>
                                  | <strong>To:</strong> <?= htmlspecialchars($msg['recipient_name']) ?>
                               <?php endif; ?>
                               | <small><?= date('M j, Y H:i', strtotime($msg['created_at'])) ?></small>
                           </div>
                           <?php if ($msg['subject']): ?>
                               <h5><?= htmlspecialchars($msg['subject']) ?></h5>
                           <?php endif; ?>
                           <p><?= nl2br(htmlspecialchars($msg['content'])) ?></p>
                       </div>
                   <?php endforeach; ?>
               <?php else: ?>
                   <p>No messages yet.</p>
               <?php endif; ?>
           </div>
       </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
