<?php
session_start();
require_once __DIR__ . '/config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['HTTP_REFERER'] ?? 'clubs.php';
    header("Location: login.php");
    exit();
}

// Check if club ID is provided
if (!isset($_POST['club_id']) || !is_numeric($_POST['club_id'])) {
    header("Location: clubs.php");
    exit();
}

$club_id = $_POST['club_id'];
$user_id = $_SESSION['user_id'];

try {
    // Begin transaction
    $pdo->beginTransaction();

    // 1. Get the student ID associated with this user
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception("Student record not found");
    }

    $student_id = $student['id'];

    // 2. Check if already a member
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_members WHERE student_id = ? AND club_id = ?");
    $stmt->execute([$student_id, $club_id]);
    $is_member = $stmt->fetchColumn();

    if ($is_member) {
        throw new Exception("You are already a member of this club");
    }

    // 3. Add student to club as regular member
    $stmt = $pdo->prepare("INSERT INTO club_members (student_id, club_id, position) VALUES (?, ?, 'member')");
    $stmt->execute([$student_id, $club_id]);

    // 4. Create initial due for the member (if your system requires dues)
    $member_id = $pdo->lastInsertId();
    $stmt = $pdo->prepare("
        INSERT INTO dues (member_id, amount, due_date, status) 
        VALUES (?, 20.00, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'unpaid')
    ");
    $stmt->execute([$member_id]);

    // Commit transaction
    $pdo->commit();

    // Success - redirect back to club page
    $_SESSION['success_message'] = "You have successfully joined the club!";
    header("Location: club_details.php?id=" . $club_id);
    exit();

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'clubs.php'));
    exit();
}