<?php
require_once __DIR__ . '/config/config.php';
session_start();

// Redirect if user is not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['club_id'])) {
    $club_id = $_POST['club_id'];
    $user_id = $_SESSION['user_id'];

    try {
        // Get the student record from the students table using the user_id.
        // Note: Here we select the primary key 'id' from students, which is used in club_members.
        $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $student_id = $stmt->fetchColumn();

        if (!$student_id) {
            throw new Exception("You are not registered as a student.");
        }

        // Check if the student is already a member of the club
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_members WHERE club_id = ? AND student_id = ?");
        $stmt->execute([$club_id, $student_id]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("You are already a member of this club.");
        }

        // Insert the student into the club as a regular member
        $default_position = 'member'; // Default position for new members
        $stmt = $pdo->prepare("INSERT INTO club_members (student_id, club_id, position) VALUES (?, ?, ?)");
        $stmt->execute([$student_id, $club_id, $default_position]);

        // Set success message
        $_SESSION['join_success'] = "You have successfully joined the club!";
    } catch (Exception $e) {
        $_SESSION['join_error'] = $e->getMessage();
    }

    // Redirect back to the club details page
    header("Location: club_details.php?id=$club_id");
    exit();
} else {
    // Redirect to clubs page if accessed improperly
    $_SESSION['join_error'] = "Invalid request. Please try again.";
    header("Location: clubs.php");
    exit();
}
?>