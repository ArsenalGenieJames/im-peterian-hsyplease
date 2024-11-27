<?php
// submitcommit.php
session_start();
include('db.php');

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    die("User not logged in.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'], $_POST['post_id'])) {
    $user_id = intval($_SESSION['user_id']);
    $post_id = intval($_POST['post_id']);
    $content = trim($_POST['content']); // Avoid empty or whitespace-only comments

    if (!empty($content)) {
        $insert_comment_query = "INSERT INTO comments (post_id, user_id, content, timestamp) VALUES (?, ?, ?, NOW())";
        $insert_comment_stmt = $conn->prepare($insert_comment_query);

        if ($insert_comment_stmt === false) {
            die("Error preparing query: " . $conn->error);
        }

        $insert_comment_stmt->bind_param("iis", $post_id, $user_id, $content);

        if ($insert_comment_stmt->execute()) {
            header("Location: profile.php"); // Redirect to prevent form resubmission
            exit();
        } else {
            echo "Error inserting comment: " . $insert_comment_stmt->error;
        }

        $insert_comment_stmt->close();
    } else {
        echo "Comment cannot be empty.";
    }
}

$conn->close();
