<?php
session_start();
include 'db_connection.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (preg_match('/^\d{12}$/', $username)) {
        // Student login logic
        $stmt = $conn->prepare("SELECT id, lrn, date_of_birth, first_name FROM students WHERE lrn = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $student = $result->fetch_assoc();
            $dob_db = $student['date_of_birth'];
            $dob_formatted = date("mdY", strtotime($dob_db));

            if ($password === $dob_formatted) {
                $_SESSION['student_id'] = $student['id'];
                $_SESSION['lrn'] = $student['lrn'];
                $_SESSION['student_name'] = $student['first_name'];
                $_SESSION['just_logged_in'] = true;
                header("Location: student_portal.php");
                exit();
            } else {
                $error = "Invalid LRN or date of birth!";
            }
        } else {
            $error = "Invalid LRN or date of birth!";
        }
        $stmt->close();
    } else {
        // Teacher login logic
        $stmt = $conn->prepare("SELECT f.id, f.teacher_id, f.dob, f.name 
                              FROM faculty f
                              JOIN section_advisers sa ON sa.teacher_id = f.id
                              WHERE f.teacher_id = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $teacher = $result->fetch_assoc();
            $dob_db = $teacher['dob'];
            $dob_formatted = date("mdY", strtotime($dob_db));

            if ($password === $dob_formatted) {
                $_SESSION['teacher_id'] = $teacher['id'];
                $_SESSION['teacher_name'] = $teacher['name'];
                $_SESSION['just_logged_in'] = true;
                header("Location: adviser_dashboard.php");
                exit();
            } else {
                $error = "Invalid Teacher ID or birthday!";
            }
        } else {
            // Admin login logic
            $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_role'] = $user['role'];

                    if ($user['role'] === 'admin') {
                        header("Location: clerk_dashboard.php");
                        exit();
                    } elseif ($user['role'] === 'superadmin') {
                        header("Location: dashboard.php");
                        exit();
                    } elseif ($user['role'] === 'counselor') {
                        header("Location: counselor_dashboard.php");
                        exit();
                    } else {
                        header("Location: dashboard.php");
                        exit();
                    }
                } else {
                    $error = "Invalid username or password!";
                }
            } else {
                $error = "Invalid username or password!";
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
  <title>TapTrack | Login</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: url('assets/imgs/dahs2.jpg') no-repeat center center fixed;
      background-size: cover;
      margin: 0;
      padding: 0;
    }

    body::before {
      content: "";
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: inherit;
      filter: blur(5px);
      z-index: -1;
    }

    .login-container {
      max-width: 380px;
      width: 90%;
      margin: 50px auto;
      padding: 30px 20px;
      border-radius: 16px;
      background: rgba(240, 240, 240, 0.85);
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
      color: #333;
    }

    .login-container h3 {
      font-weight: 600;
      margin-bottom: 20px;
      color: #222;
      font-size: 1.5rem;
    }

    .form-control {
      background: #fff;
      border: 1px solid #ccc;
      border-radius: 10px;
      padding: 12px;
      min-height: 44px;
      font-size: 16px; /* Prevent zoom on iOS */
    }

    .form-control:focus {
      box-shadow: none;
      border-color: #0d6efd;
    }

    .btn-primary {
      background-color: #0d6efd;
      border-radius: 10px;
      font-weight: 600;
      padding: 12px;
      min-height: 44px;
    }

    .btn-primary:hover {
      background-color: #0b5ed7;
    }

    .logo {
      width: 60px;
      height: 60px;
      background-color: #0d6efd;
      border-radius: 50%;
      margin: 0 auto 15px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 26px;
      color: #fff;
      font-weight: bold;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    a {
      color: #0d6efd;
      font-weight: 500;
    }

    a:hover {
      color: #0a58ca;
      text-decoration: underline;
    }

    .alert {
      font-size: 14px;
    }

    /* Mobile-specific adjustments */
    @media (max-width: 576px) {
      .login-container {
        margin: 20px auto;
        padding: 25px 15px;
      }
      
      body {
        padding: 15px;
      }
    }
  </style>
</head>
<body>
  <div class="container-fluid">
    <div class="row justify-content-center align-items-center min-vh-100">
      <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-4">
        <div class="login-container text-center">
          <div class="logo">TT</div>
          <h3>Welcome to Isiera</h3>
          <?php if (!empty($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
          <form method="POST" action="">
            <div class="mb-3">
              <input type="text" name="username" class="form-control" placeholder="Username" required>
            </div>
            <div class="mb-3">
              <input type="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
          </form>
          <hr>
          <p>Application for New Student <br><a href="register.php">Register here</a></p>
        </div>
      </div>
    </div>
  </div>
</body>
</html>