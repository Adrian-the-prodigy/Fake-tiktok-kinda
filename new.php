<?php
session_start(); // Start the session

// Database connection
$db_file = __DIR__ . '/signup_db.sqlite';
$conn = new SQLite3($db_file);

// Create users table if it doesn't exist
$conn->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    profile_pic TEXT
);");


// Create posts table if it doesn't exist
$conn->exec("CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    message TEXT,
    media_path TEXT,
    media_type TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id)
);");

// Create likes table if it doesn't exist
$conn->exec("CREATE TABLE IF NOT EXISTS likes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    post_id INTEGER NOT NULL,
    FOREIGN KEY(user_id) REFERENCES users(id),
    FOREIGN KEY(post_id) REFERENCES posts(id)
);");

// Handle user sign-up or login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['signup_login'])) {
    $first_name = SQLite3::escapeString($_POST['first_name']);
    $last_name = SQLite3::escapeString($_POST['last_name']);
    $email = SQLite3::escapeString($_POST['email']);

    // Check if the email already exists in the database
    $check_email_sql = "SELECT * FROM users WHERE email = '$email'";
    $existing_user = $conn->querySingle($check_email_sql, true);

    if ($existing_user) {
        // If email exists, log in the user
        $_SESSION['user_email'] = $existing_user['email'];
        $_SESSION['first_name'] = $existing_user['first_name'];
        header("Location: " . $_SERVER['PHP_SELF']); // Redirect to the profile
        exit();
    } else {
        // Insert new user data into the database (new sign-up)
        $sql = "INSERT INTO users (first_name, last_name, email) VALUES ('$first_name', '$last_name', '$email')";
        if ($conn->exec($sql)) {
            $_SESSION['user_email'] = $email;
            $_SESSION['first_name'] = $first_name;
            header("Location: " . $_SERVER['PHP_SELF']); // Redirect to the profile
            exit();
        } else {
            $error = "Error: " . $conn->lastErrorMsg();
        }
    }
}

// Handle profile picture upload
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_pic'])) {
        $target_dir = "uploads/";

        // Ensure uploads directory exists
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $target_file = $target_dir . basename($_FILES["profile_pic"]["name"]);
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Check if the file is an actual image
        $check = getimagesize($_FILES["profile_pic"]["tmp_name"]);
        if ($check !== false) {
            $uploadOk = 1;
        } else {
            $uploadError = "File is not an image.";
            $uploadOk = 0;
        }

        // Check if file already exists
        if (file_exists($target_file)) {
            $uploadError = "Sorry, file already exists.";
            $uploadOk = 0;
        }

        // Check file size (limit: 2MB)
        if ($_FILES["profile_pic"]["size"] > 2000000) {
            $uploadError = "Sorry, your file is too large.";
            $uploadOk = 0;
        }

        // Allow only certain formats
        if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
            $uploadError = "Sorry, only JPG, JPEG, and PNG files are allowed.";
            $uploadOk = 0;
        }

        // If no errors, upload the file and update the database
        if ($uploadOk && move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
            $sql = "UPDATE users SET profile_pic = '" . basename($_FILES["profile_pic"]["name"]) . "' WHERE email = '$email'";
            $conn->exec($sql);
            $uploadSuccess = "Profile picture uploaded successfully!";
        } else {
            $uploadError = "Sorry, your file was not uploaded.";
        }
    }


if (isset($_SESSION['user_email'])) {
    $email = $_SESSION['user_email'];
    $user = $conn->querySingle("SELECT * FROM users WHERE email = '$email'", true);

 // Handle post submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_post'])) {
        $user_post = SQLite3::escapeString($_POST['user_post']);
        $user_id = $user['id']; // Get the logged-in user's ID
        $insert_post_sql = "INSERT INTO posts (user_id, message) VALUES ('$user_id', '$user_post')";
        if ($conn->exec($insert_post_sql)) {
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $post_error = "Error: Unable to add your post.";
        }
    }
	
	// Handle post deletion
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_post'])) {
        $post_id = (int) $_POST['post_id'];

        // Check if the post belongs to the logged-in user
        $check_post_owner_sql = "SELECT id FROM posts WHERE id = '$post_id' AND user_id = " . $user['id'];
        $post_owner_result = $conn->querySingle($check_post_owner_sql);

        if ($post_owner_result) {
            // Delete the post if it belongs to the user
            $delete_post_sql = "DELETE FROM posts WHERE id = '$post_id'";
            $conn->exec($delete_post_sql);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $delete_error = "You do not have permission to delete this post.";
        }
	}

       // Handle post submission with media upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_post'])) {
    $user_post = SQLite3::escapeString($_POST['user_post']);
    $user_id = $user['id']; // Get the logged-in user's ID

    $media_path = null;
    $media_type = null;

    // Check if a media file is uploaded
    if (isset($_FILES['media']) && $_FILES['media']['size'] > 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true); // Create uploads directory if not exists
        }

        $file_name = basename($_FILES['media']['name']);
        $target_file = $target_dir . $file_name;
        $media_type = mime_content_type($_FILES['media']['tmp_name']);

        // Validate file type (allow only images & videos)
        $allowed_types = ['image/jpeg', 'image/png', 'video/mp4'];
        if (in_array($media_type, $allowed_types)) {
            if (move_uploaded_file($_FILES['media']['tmp_name'], $target_file)) {
                $media_path = $file_name; // Save file name in the database
            } else {
                $post_error = "Error uploading the media file.";
            }
        } else {
            $post_error = "Invalid file format. Only JPG, PNG, and MP4 are allowed.";
        }
    }

    // Insert post into the database
    $insert_post_sql = "INSERT INTO posts (user_id, message, media_path, media_type) 
                        VALUES ('$user_id', '$user_post', '$media_path', '$media_type')";
    if ($conn->exec($insert_post_sql)) {
        header("Location: " . $_SERVER['PHP_SELF']); // Refresh the page
        exit();
    } else {
        $post_error = "Error: Unable to add your post.";
    }
}



    // Handle likes
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['like_post'])) {
        $post_id = (int)$_POST['post_id'];
        $user_id = $user['id'];

        // Check if user already liked the post
        $liked = $conn->querySingle("SELECT id FROM likes WHERE user_id = '$user_id' AND post_id = '$post_id'");

        if ($liked) {
            $conn->exec("DELETE FROM likes WHERE id = '$liked'");
        } else {
            $conn->exec("INSERT INTO likes (user_id, post_id) VALUES ('$user_id', '$post_id')");
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Fetch posts
    $posts = $conn->query("SELECT posts.*, users.first_name, users.last_name, 
        (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS like_count,
        (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id AND likes.user_id = '{$user['id']}') AS user_liked
        FROM posts
        JOIN users ON posts.user_id = users.id
        ORDER BY posts.created_at DESC");
}

    // Handle user search by email
    $searchResult = null;
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search_email'])) {
        $search_email = SQLite3::escapeString($_POST['search_email']);
        $search_sql = "SELECT first_name, last_name, email, profile_pic FROM users WHERE email = '$search_email'";
        $search_result = $conn->query($search_sql);
        
        // Check if search result is valid
        if ($search_result) {
            $searchResult = $search_result->fetchArray(SQLITE3_ASSOC);
        } else {
            $searchResult = null; // No results found
        }
    }
	
	// Create comments table if it doesn't exist
$conn->exec("CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(post_id) REFERENCES posts(id),
    FOREIGN KEY(user_id) REFERENCES users(id)
);");

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comment']) && isset($_POST['post_id'])) {
    $post_id = (int)$_POST['post_id'];
    $comment = SQLite3::escapeString($_POST['comment']);
    $user_id = $user['id']; // Get the logged-in user's ID

    $conn->exec("INSERT INTO comments (post_id, user_id, comment) VALUES ('$post_id', '$user_id', '$comment')");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch comments for each post (update your posts query to include a sub-query for comments)
$posts = $conn->query("
    SELECT posts.*, users.first_name, users.last_name,
    (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS like_count,
    (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id AND likes.user_id = '{$user['id']}') AS user_liked,
    (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) AS comment_count
    FROM posts
    JOIN users ON posts.user_id = users.id
    ORDER BY posts.created_at DESC
");

// Log out functionality
if (isset($_POST['logout'])) {
    session_destroy(); // End the session
    header("Location: " . $_SERVER['PHP_SELF']); // Reload the page
    exit();
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Posts</title>	
	<style>
        .profile-section {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        .profile-pic {
            border-radius: 50%;
            margin-top: 10px;
        }

        .popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: gray;
            padding: 20px;
            border: 1px solid #ccc;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            display: none;
            z-index: 1000;
            text-align: center;
        }

        .popup.active {
            display: block;
        }
		

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            z-index: 999;
        }

        .overlay.active {
            display: block;
        }

        .close-btn {
            background: #f44336;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            margin-top: 10px;
        }
    </style>
	
	<style>
    body {
        font-family: 'Roboto', Arial, sans-serif;
        margin: 0;
        background-color: #121212;
        color: #fff;
        padding: 20px;
    }

    .container {
        max-width: 600px;
        margin: auto;
        padding: 20px;
        background-color: #1e1e1e;
        box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.2);
        border-radius: 50px;
    }

    h1, h2 {
        font-weight: bold;
        color: #fff;
    }

    form {
        margin-bottom: 20px;
    }

    input[type="text"],
    input[type="email"],
    textarea {
        width: calc(100% - 20px);
        padding: 12px;
        margin: 10px 0;
        background-color: #2c2c2c;
        border: none;
        border-radius: 6px;
        color: #fff;
        font-size: 16px;
        box-sizing: border-box;
    }

    input::placeholder, textarea::placeholder {
        color: #aaa;
    }

    button {
        padding: 10px 20px;
        background-color: #fe2c55;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 16px;
        transition: background-color 0.3s;
    }

    button:hover {
        background-color: #ff577f;
    }

    .profile-section, 
    .post-section, 
    .post-display, 
    .search-section {
        margin-bottom: 30px;
    }

    .post {
        padding: 15px;
        margin-bottom: 15px;
        background-color: #2c2c2c;
        border-radius: 8px;
        animation: fadeIn 0.5s;
    }

    .post p {
        margin: 0;
    }

    .post strong {
        color: #fe2c55;
    }

    .post-time {
        font-size: 14px;
        color: #aaa;
    }

    .delete-btn {
        color: red;
        background: none;
        border: none;
        font-size: 14px;
        cursor: pointer;
        margin-left: 10px;
    }

    .delete-btn:hover {
        text-decoration: underline;
    }

    .profile-pic {
        display: block;
        margin: 20px auto;
        border-radius: 50%;
        width: 120px;
        height: 120px;
        object-fit: cover;
        border: 2px solid #fe2c55;
    }

    .error, 
    .success {
        text-align: center;
        font-size: 14px;
        color: #fe2c55;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    </style>
	
<style>
    .profile-container {
        display: flex;
        flex-direction: column; /* Stack image and text vertically */
        justify-content: center; /* Center content vertically */
        align-items: center; /* Center content horizontally */
        gap: 15px; /* Space between the image and text */
        background: BLACK;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Optional styling for better appearance */
        width: 250px; /* Optional: Set a fixed width for the container */
        margin: auto; /* Center the container horizontally in its parent */
    }
    .profile-pic {
        border-radius: 50%; /* Optional for circular profile picture */
    }
    .profile-text {
        font-size: 1.5em; /* Adjust text size if needed */
        color: white; /* Makes text stand out on the blue background */
        text-align: center; /* Ensures text is centered */
        margin: 0; /* Removes default margin */
    }
	.post-container {
         max-width: 600px;
        margin: auto;
        padding: 20px;
        background-color: #1e1e1e;
        box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.2);
        border-radius: 50px;
	}
	
	.comments {
    margin-top: 10px;
    padding-left: 15px;
    border-left: 2px solid #fe2c55;
}

.comment {
    margin-bottom: 10px;
    font-size: 14px;
}

.comment-time {
    font-size: 12px;
    color: #aaa;
}
</style>
</head>
<body>

<div class="container">
    <?php if (isset($user)): ?>
	
	<div class="profile-container">
    <?php if ($user['profile_pic']): ?>
        <img src="uploads/<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Profile Picture" width="100" class="profile-pic">
    <?php endif; ?>
    <h1 class="profile-text">Hey <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
	<br>
	<br>
	<!-- Button to Display Profile -->
    <button id="showProfileBtn">View Profile</button>
</div>
<br>
<br>
	
	<!-- Search for User by Email -->
        <div class="search-section">
            <form method="post">
                <input type="email" name="search_email" placeholder="search bar" required>
                <button type="submit">Search</button>
            </form>

            <?php if ($searchResult): ?>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($searchResult['first_name']) . ' ' . htmlspecialchars($searchResult['last_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($searchResult['email']); ?></p>
                <?php if ($searchResult['profile_pic']): ?>
                    <img src="uploads/<?php echo htmlspecialchars($searchResult['profile_pic']); ?>" alt="Profile Picture" width="100" class="profile-pic">
                <?php endif; ?>
            <?php endif; ?>
        </div>		
		<br>
		<br>
		<br>
		<br>
		
				<!-- Popup and Overlay -->
<div id="profilePopup" class="popup">
    <h2>User Profile</h2>
    <p><strong>Name:</strong> <?php echo htmlspecialchars($user['first_name']); ?></p>
    <?php if ($user['profile_pic']): ?>
        <img src="uploads/<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Profile Picture" width="150" class="profile-pic">
    <?php else: ?>
        <p><em>No profile picture uploaded.</em></p>
    <?php endif; ?>
	  <!-- Profile Picture Upload -->
    <form method="post" enctype="multipart/form-data">
        <label for="profile_pic">Upload/change pfp:</label>
        <input type="file" name="profile_pic" id="profile_pic" accept="image/*">
        <button type="submit">Upload</button>
    </form>
        <!-- Logout Button -->
        <form method="post">
            <button type="submit" name="logout">Logout</button>
        </form>
    <button class="close-btn" id="closePopupBtn">Close</button>
</div>
<div id="overlay" class="overlay"></div>
</div>
<br>
<br>
<div class="post-container">
        <!-- Post Form -->
        <form method="post" enctype="multipart/form-data">
            <textarea name="user_post" placeholder="Write a post..."></textarea><br>
            <label for="media">MEDIA:</label>
            <button><input type="file" name="media" accept="image/*,video/mp4"><br></button>
            <button type="submit">Post</button>
        </form>
</div>

        <!-- Display Posts -->
        <h2>All Posts</h2>
        <?php while ($post = $posts->fetchArray(SQLITE3_ASSOC)): ?>
            <div class="post">
                <p><strong><?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?>:</strong></p>
                <p><?php echo nl2br(htmlspecialchars($post['message'])); ?></p>
				<p class="post-time"><?php echo date('F j, Y, g:i a', strtotime($post['created_at'])); ?></p>
				
				 <!-- Display uploaded media -->
        <?php if (!empty($post['media_path'])): ?>
            <?php if (strpos($post['media_type'], 'image') !== false): ?>
                <img src="uploads/<?php echo htmlspecialchars($post['media_path']); ?>" alt="Post Image" width="300">
            <?php elseif (strpos($post['media_type'], 'video') !== false): ?>
                <video width="300" controls>
                    <source src="uploads/<?php echo htmlspecialchars($post['media_path']); ?>" type="<?php echo htmlspecialchars($post['media_type']); ?>">
                    Your browser does not support the video tag.
                </video>
            <?php endif; ?>
        <?php endif; ?>

				
				<!-- Delete Button (Only show if user is the post owner) -->
                        <?php if ($post['user_id'] == $user['id']): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                <button type="submit" name="delete_post" class="delete-btn">Delete</button>
                            </form>
                        <?php endif; ?>
				<br>
<br>
<br>
<br>				
				 <!-- Like Button -->
                <form method="post">
                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                    <button type="submit" name="like_post">
                        <?php echo $post['user_liked'] ? 'Unlike' : 'Like'; ?> (<?php echo $post['like_count']; ?>)
                    </button>
                </form>
            </div>
			
			<!-- Comments Section -->
        <h4>Comments (<?php echo $post['comment_count']; ?>)</h4>
        <?php
        $comments = $conn->query("SELECT comments.*, users.first_name, users.last_name FROM comments
                                  JOIN users ON comments.user_id = users.id
                                  WHERE comments.post_id = {$post['id']}
                                  ORDER BY comments.created_at ASC");
        while ($comment = $comments->fetchArray(SQLITE3_ASSOC)): ?>
            <div class="comment">
                <p><strong><?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?>:</strong></p>
                <p><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                <p class="post-time"><?php echo date('F j, Y, g:i a', strtotime($comment['created_at'])); ?></p>
            </div>
        <?php endwhile; ?>
        
        <!-- Add a new comment -->
        <form method="post">
            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
            <textarea name="comment" placeholder="Write a comment..." required></textarea>
            <button type="submit">Comment</button>
        </form>
    </div>
        <?php endwhile; ?>
                    </div>
					

    <?php else: ?>
       <!-- Sign-up/Login Form -->
        <div class="signup-login-section">
            <h1>Sign Up or Log In</h1>
            <form method="post">
                <input type="text" name="first_name" placeholder="First Name" required>
                <input type="text" name="last_name" placeholder="Last Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <button type="submit" name="signup_login">Submit</button>
            </form>

            <!-- Sign-up/Login error message -->
            <?php if (isset($error)): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<script>
    // JavaScript for handling popup
    const showProfileBtn = document.getElementById('showProfileBtn');
    const profilePopup = document.getElementById('profilePopup');
    const overlay = document.getElementById('overlay');
    const closePopupBtn = document.getElementById('closePopupBtn');

    showProfileBtn.addEventListener('click', () => {
        profilePopup.classList.add('active');
        overlay.classList.add('active');
    });

    closePopupBtn.addEventListener('click', () => {
        profilePopup.classList.remove('active');
        overlay.classList.remove('active');
    });

    overlay.addEventListener('click', () => {
        profilePopup.classList.remove('active');
        overlay.classList.remove('active');
    });
</script>

</body>
</html>
