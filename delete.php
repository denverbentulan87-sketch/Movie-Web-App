<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ✅ GET MOVIE ID (watchlist_id)
$id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// ✅ PREPARED STATEMENT (safe)
$stmt = $conn->prepare("
    DELETE FROM movie_watchlist 
    WHERE watchlist_id=? AND user_id=?
");

$stmt->bind_param("ii", $id, $user_id);

if ($stmt->execute()) {
    header("Location: index.php");
    exit();
} else {
    echo "Error in deleting movie.";
}
?>