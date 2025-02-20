<?php
session_start(); // Start session
include 'db_connection.php'; // Database connection

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Check if the user exists in the database
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        // Direct comparison since passwords are stored as plain text
        if ($password === $user['password']) {  
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: dashboard.php"); // Redirect to dashboard
            exit();
        } else {
            $error = "Invalid username or password!";
        }
    } else {
        $error = "Invalid username or password!";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
      body {
    background: url('assets/imgs/dahs_pic.jpg') no-repeat center center fixed;
    background-size: cover;
}

body::before {
    content: "";
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: inherit;
    filter: blur(2px); /* Adjust the blur intensity */
    z-index: -1; /* Send it behind everything */
}

.login-container {
    width: 350px;
    margin: 100px auto;
    padding: 20px;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 10px;
}

    </style>
</head>
<body>
    <div class="container">
        <div class="login-container text-center">
            <h3>Admin Login</h3>
            <?php if (!empty($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
            <form method="POST" action="">
                <div class="mb-3">
                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                </div>
                <div class="mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                </div>
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
            <hr>
            <p>Are you a student? <a href="register.php">Register here</a></p>
        </div>
    </div>
</body>
</html>
