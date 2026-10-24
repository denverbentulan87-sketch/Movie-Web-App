<?php
include 'db.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: homepage.php?modal=login");
    exit();
}

if(isset($_POST['submit'])){

    $user_id = $_SESSION['user_id'];
    $movie_title = $_POST['movie_title'];
    $genre = $_POST['genre'];
    $status = $_POST['status'];
    $rating = !empty($_POST['rating']) ? $_POST['rating'] : NULL;

    $stmt = $conn->prepare("
        INSERT INTO movie_watchlist 
        (user_id, movie_title, genre, status, rating, date_added) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param("isssi", $user_id, $movie_title, $genre, $status, $rating);

    if($stmt->execute()){
        header("Location: index.php");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32.png">
<link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
<title>Add Movie</title>

<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700&family=DM+Sans&display=swap" rel="stylesheet">

<style>
body {
    margin: 0;
    font-family: 'DM Sans', sans-serif;
    background: #0b0b10;
    color: #eee;
}

/* Center container */
.container {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}

/* Card form */
.form-card {
    background: #14141c;
    padding: 30px;
    border-radius: 16px;
    width: 350px;
    box-shadow: 0 0 20px rgba(0,0,0,0.5);
}

h2 {
    font-family: 'Syne';
    margin-bottom: 20px;
}

/* Inputs */
input, select {
    width: 100%;
    padding: 10px;
    margin-bottom: 15px;
    border-radius: 10px;
    border: none;
    background: #1a1a24;
    color: white;
}

/* Button */
button {
    width: 100%;
    padding: 12px;
    background: #c8a96e;
    border: none;
    border-radius: 10px;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: #e0c48c;
}

/* Back link */
.back {
    display: block;
    text-align: center;
    margin-top: 10px;
    color: #aaa;
    text-decoration: none;
    font-size: 13px;
}
</style>
</head>

<body>

<div class="container">
    <div class="form-card">
        <h2>Add Movie</h2>

        <form method="POST">

            <input type="text" name="movie_title" placeholder="Movie title" required>

            <input type="text" name="genre" placeholder="Genre (Action, Drama...)" required>

            <select name="status">
                <option value="unwatched">Unwatched</option>
                <option value="watching">Watching</option>
                <option value="watched">Watched</option>
            </select>

            <input type="number" name="rating" min="1" max="10" placeholder="Rating (1-10)">

            <button type="submit" name="submit">Save Movie</button>

        </form>

        <a href="index.php" class="back">← Back to Watchlist</a>
    </div>
</div>

</body>
</html>