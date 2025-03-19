<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
$conn = new mysqli('localhost', 'myuser', 'mypassword', 'myapp');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle user login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            echo "<div class='alert alert-success'>Login successful!</div>";
        } else {
            echo "<div class='alert alert-danger'>Invalid password!</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>User not found!</div>";
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pdf'])) {
    if (isset($_SESSION['user_id'])) {
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($_FILES['pdf']['name']);
        if (move_uploaded_file($_FILES['pdf']['tmp_name'], $target_file)) {
            $stmt = $conn->prepare("INSERT INTO pdfs (user_id, filename, filepath) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $_SESSION['user_id'], $_FILES['pdf']['name'], $target_file);
            if ($stmt->execute()) {
                echo "<div class='alert alert-success'>File uploaded successfully!</div>";
            } else {
                echo "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
            }
        } else {
            echo "<div class='alert alert-danger'>Error uploading file!</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>You must be logged in to upload files!</div>";
    }
}

// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete'])) {
    $file_id = $_POST['file_id'];
    $stmt = $conn->prepare("SELECT filepath FROM pdfs WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $file_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $file = $result->fetch_assoc();
        if (unlink($file['filepath'])) {
            $stmt = $conn->prepare("DELETE FROM pdfs WHERE id = ?");
            $stmt->bind_param("i", $file_id);
            if ($stmt->execute()) {
                echo "<div class='alert alert-success'>File deleted successfully!</div>";
            } else {
                echo "<div class='alert alert-danger'>Error deleting file from database!</div>";
            }
        } else {
            echo "<div class='alert alert-danger'>Error deleting file from server!</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>File not found or you do not have permission to delete it!</div>";
    }
}

// Fetch uploaded files for the logged-in user
$uploaded_files = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT id, filename, filepath FROM pdfs WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $uploaded_files[] = $row;
    }
}

// Handle logout
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Upload App</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
            min-height: 100vh;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-10px);
        }
        .btn-primary {
            background: linear-gradient(135deg, #6a11cb, #2575fc);
            border: none;
            transition: background 0.3s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #2575fc, #6a11cb);
        }
        .upload-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .file-list {
            list-style: none;
            padding: 0;
        }
        .file-list li {
            background: white;
            margin: 10px 0;
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .file-list li:hover {
            transform: translateX(10px);
        }
        .file-list a {
            color: #2575fc;
            text-decoration: none;
        }
        .file-list a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card p-4">
                        <h2 class="text-center mb-4">Login</h2>
                        <form method="POST">
                            <div class="mb-3">
                                <input type="text" name="username" class="form-control" placeholder="Username" required>
                            </div>
                            <div class="mb-3">
                                <input type="password" name="password" class="form-control" placeholder="Password" required>
                            </div>
                            <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
                        </form>
                        <p class="text-center mt-3">Don't have an account? <a href="register.php">Register here</a></p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="upload-section">
                <h2 class="text-center mb-4">Welcome, User!</h2>
                <h3 class="text-center mb-4">Upload PDF</h3>
                <form method="POST" enctype="multipart/form-data" class="text-center">
                    <input type="file" name="pdf" accept="application/pdf" class="form-control mb-3" required>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </form>
            </div>

            <div class="mt-5">
                <h3 class="text-center mb-4">Uploaded PDFs</h3>
                <ul class="file-list">
                    <?php foreach ($uploaded_files as $file): ?>
                        <li>
                            <a href="<?php echo $file['filepath']; ?>" target="_blank"><?php echo $file['filename']; ?></a>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                <button type="submit" name="delete" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <form method="POST" class="text-center mt-4">
                <button type="submit" name="logout" class="btn btn-danger">Logout</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
