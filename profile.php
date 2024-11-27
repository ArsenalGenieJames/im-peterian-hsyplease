<?php
session_start();
include('db.php');

// Ensure session variable for user ID is set
if (!isset($_SESSION['user_id'])) {
    die("User not logged in.");
}
$user_id = $_SESSION['user_id'];

// Handle post deletion
if (isset($_GET['delete_post_id'])) {
    $delete_post_id = intval($_GET['delete_post_id']);

    // Delete comments associated with the post
    $delete_comments_query = "DELETE FROM comments WHERE post_id = ?";
    $delete_comments_stmt = $conn->prepare($delete_comments_query);

    if ($delete_comments_stmt === false) {
        die("ERROR: Could not prepare query: $delete_comments_query. " . $conn->error);
    }

    $delete_comments_stmt->bind_param("i", $delete_post_id);
    $delete_comments_stmt->execute();
    $delete_comments_stmt->close();

    // Delete likes associated with the post
    $delete_likes_query = "DELETE FROM likes WHERE post_id = ?";
    $delete_likes_stmt = $conn->prepare($delete_likes_query);

    if ($delete_likes_stmt === false) {
        die("ERROR: Could not prepare query: $delete_likes_query. " . $conn->error);
    }

    $delete_likes_stmt->bind_param("i", $delete_post_id);
    $delete_likes_stmt->execute();
    $delete_likes_stmt->close();

    // Delete the post itself
    $delete_post_query = "DELETE FROM posts WHERE post_id = ? AND user_id = ?";
    $delete_post_stmt = $conn->prepare($delete_post_query);

    if ($delete_post_stmt === false) {
        die("ERROR: Could not prepare query: $delete_post_query. " . $conn->error);
    }

    $delete_post_stmt->bind_param("ii", $delete_post_id, $user_id);

    if ($delete_post_stmt->execute()) {
        echo "Post and associated data deleted successfully!";
        header("Location: profile.php");
        exit();
    } else {
        echo "Error deleting post: " . $delete_post_stmt->error;
    }
    $delete_post_stmt->close();
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $upload_dir = "uploads/profile_pictures/";
    $target_file = $upload_dir . basename($_FILES["profile_picture"]["name"]);
    $upload_ok = 1;

    // Validate the uploaded file
    $check = getimagesize($_FILES["profile_picture"]["tmp_name"]);
    if ($check === false) {
        echo "File is not an image.";
        $upload_ok = 0;
    }

    if ($_FILES["profile_picture"]["size"] > 2000000) {
        echo "Sorry, your file is too large.";
        $upload_ok = 0;
    }

    $allowed_formats = ["jpg", "jpeg", "png", "gif"];
    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    if (!in_array($file_type, $allowed_formats)) {
        echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        $upload_ok = 0;
    }

    if ($upload_ok === 1) {
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
            $update_query = "UPDATE users SET profile_picture = ? WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_query);

            if ($update_stmt === false) {
                die("ERROR: Could not prepare query: $update_query. " . $conn->error);
            }

            $update_stmt->bind_param("si", $target_file, $user_id);

            if ($update_stmt->execute()) {
                echo "Profile picture updated successfully!";
                header("Refresh: 0");
                exit();
            } else {
                echo "Error updating profile picture: " . $update_stmt->error;
            }
            $update_stmt->close();
        } else {
            echo "Error uploading your file.";
        }
    }
}

// Fetch the user's profile information
$query_user = "SELECT Name, Email, profile_picture FROM users WHERE user_id = ?";
$stmt_user = $conn->prepare($query_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();
$stmt_user->close();

// Fetch the user's posts
$post_query = "
    SELECT 
        p.post_id, 
        p.content, 
        p.media_url, 
        p.timestamp,   
        (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.post_id) AS like_count,  
        u.username, 
        u.profile_picture  
    FROM posts p  
    JOIN users u ON p.user_id = u.user_id  
    WHERE p.user_id = ? 
    ORDER BY p.timestamp DESC";

$post_stmt = $conn->prepare($post_query);
$post_stmt->bind_param("i", $user_id);
$post_stmt->execute();
$post_result = $post_stmt->get_result();

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="tabfooter.css">
    <style>
        .profile {
            max-width: 600px;
            margin: auto;
            padding: 20px;
            text-align: center;
            border: 1px solid #ccc;
            border-radius: 10px;
        }

        img {
            max-width: 150px;
            border-radius: 50%;
        }

        .post {
            margin: 20px 0;
            border: 1px solid #eee;
            padding: 10px;
            border-radius: 5px;
        }

        .delete-btn {
            color: red;
            cursor: pointer;
        }

        .media-content {
            max-width: 100%;
            border-radius: 5px;
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
        }

        .file-input-wrapper input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .file-input-wrapper i {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
        }
    </style>
</head>

<body>
    <div class="profile">
        <div class="container mt-2">
            <form id="uploadForm" action="profile.php" method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture" class="img-thumbnail rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                </div>
                <div class="file-input-wrapper mb-3">
                    <i class="fa-solid fa-plus"></i>
                    <input type="file" name="profile_picture" id="profile_picture" accept="image/*" required class="form-control">
                </div>

            </form>
        </div>


        <h1><?php echo htmlspecialchars($user['Name']); ?></h1>



        <div class="container">
            <div class="row">
                <div class="col">
                    <div class="profile-stats">
                        <h3 class="m-b-0 font-light">434K</h3>
                        <small>Friends</small>
                    </div>
                </div>
                <div class="col">
                    <div class="profile-stats">
                        <h3 class="m-b-0 font-light">5454</h3>
                        <small>Following</small>
                    </div>
                </div>
                <div class="col">
                    <div class="profile-stats">
                        <a href="#momentsday">Mo Mentsday</a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="mt-2">
        <?php while ($post = $post_result->fetch_assoc()): ?>
            <div class="post">
                <div class="container">
                    <div class="row">
                        <div class="col">
                            <img src="<?php echo htmlspecialchars($post['profile_picture']); ?>" alt="Profile Picture" class="rounded-circle me-3" style="width: 40px; height: 40px;">
                        </div>
                        <div class="col">
                            <h5 class="card-title"><?php echo htmlspecialchars($post['username']); ?></h5>
                            <!-- Display the date and time of the post -->
                            <p class="text-muted">
                                <?php
                                // Convert timestamp to a more readable format
                                $formatted_date = date("l, F j, Y \a\t g:i A", strtotime($post['timestamp']));
                                echo htmlspecialchars($formatted_date);
                                ?>
                            </p>
                        </div>
                    </div>
                </div>

                <p><?php echo htmlspecialchars($post['content']); ?></p>
                <?php if (!empty($post['media_url'])): ?>
                    <?php
                    $media_type = pathinfo($post['media_url'], PATHINFO_EXTENSION);
                    ?>
                    <?php if (in_array($media_type, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                        <img src="<?php echo htmlspecialchars($post['media_url']); ?>" alt="Post media" class="media-content">
                    <?php elseif (in_array($media_type, ['mp4', 'avi', 'mov'])): ?>
                        <video controls class="media-content">
                            <source src="<?php echo htmlspecialchars($post['media_url']); ?>" type="video/<?php echo $media_type; ?>">
                            Your browser does not support the video tag.
                        </video>
                    <?php endif; ?>
                <?php endif; ?>

                <p>Likes: <?php echo intval($post['like_count']); ?></p>

                <!-- Like Form -->
                <form action="like.php" method="post" class="d-inline">
                    <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($post['post_id']); ?>">
                    <button type="submit" class="btn btn-sm btn-link">Like (<?php echo htmlspecialchars($post['like_count']); ?>)</button>
                </form>

                <!-- Delete Post Link -->
                <a href="?delete_post_id=<?php echo htmlspecialchars($post['post_id']); ?>" class="delete-btn">Delete Post</a>

                <!-- Comment Form -->
                <form action="submitcommit.php" method="post">
                    <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($post['post_id']); ?>">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($_SESSION['user_id']); ?>">
                    <div>
                        <input type="text" name="content" placeholder="Add a comment..." required class="form-control my-2">
                        <button type="submit" class="btn btn-primary btn-sm">Submit</button>
                    </div>
                </form>
            </div> <!-- Close post div -->
        <?php endwhile; ?>
    </div>

    <a href="logout.php">Logout</a>

    <footer class="bg-light">
        <div class="container">
            <div class="row text-center mt-4 footertab">
                <div class="col">
                    <a href="home.php" class="text-decoration-none">
                        <i class="fas fa-home fa-2x footericon" data-page="home.php"></i>
                    </a>
                </div>
                <div class="col">
                    <a href="search.php" class="text-decoration-none">
                        <i class="fas fa-search fa-2x footericon" data-page="search.php"></i>
                    </a>
                </div>
                <div class="col">
                    <a href="post.php" class="text-decoration-none">
                        <i class="fas fa-plus fa-2x footericon" data-page="post.php"></i>
                    </a>
                </div>
                <div class="col">
                    <a href="notify.php" class="text-decoration-none">
                        <i class="fas fa-bell fa-2x footericon" data-page="notify.php"></i>
                    </a>
                </div>
                <div class="col">
                    <a href="profile.php" class="text-decoration-none">
                        <i class="fas fa-user fa-2x footericon" data-page="profile.php"></i>
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        document.getElementById('profile_picture').addEventListener('change', function() {
            document.getElementById('uploadForm').submit();
        });
    </script>
    <script src="./jscode/jscode.js"></script>
    <script src="./jscode/comment.js"></script>
</body>

</html>