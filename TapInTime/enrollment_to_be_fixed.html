<?php
session_start();
include('db_connection.php'); // adjust this to your connection file

$lrn = $_SESSION['lrn']; // assuming you stored LRN in session on login

// Get student info
$stmt = $conn->prepare("SELECT id, grade_level, student_type FROM students WHERE lrn = ?");
$stmt->bind_param("s", $lrn);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

$student_id = $student['id'];
$grade_level = $student['grade_level'];
$student_type = $student['student_type'];

$stmt = $conn->prepare("SELECT 
                            subjects.id, 
                            subjects.subject_name, 
                            faculty.name AS teacher_name
                        FROM assigned_grade_subjects ags
                        JOIN subjects ON ags.subject_id = subjects.id
                        LEFT JOIN assigned_subjects ass ON ass.subject_id = subjects.id
                        LEFT JOIN faculty ON ass.teacher_id = faculty.id
                        WHERE ags.grade_level = ?
                        AND (subjects.student_type = ? OR subjects.student_type = 'Both')");
$stmt->bind_param("is", $grade_level, $student_type);
$stmt->execute();
$subjects = $stmt->get_result();


$enrolled_stmt = $conn->prepare("SELECT s.subject_name 
    FROM assigned_grade_subjects ags
    JOIN subjects s ON ags.subject_id = s.id
    WHERE ags.grade_level = ?
    AND (s.student_type = ? OR s.student_type = 'Both')");
$enrolled_stmt->bind_param("is", $grade_level, $student_type);
$enrolled_stmt->execute();
$enrolled_subjects = $enrolled_stmt->get_result();


// Handle enrollment action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subject_id'])) {
    $subject_id = $_POST['subject_id'];

    // Prevent double enrollment
    $check = $conn->prepare("SELECT * FROM enrollments WHERE student_id = ? AND subject_id = ?");
    $check->bind_param("ii", $student_id, $subject_id);
    $check->execute();
    $check_result = $check->get_result();

    if ($check_result->num_rows == 0) {
        $enroll = $conn->prepare("INSERT INTO enrollments (student_id, subject_id) VALUES (?, ?)");
        $enroll->bind_param("ii", $student_id, $subject_id);
        $enroll->execute();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enrollment | Student Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/student_portal_nav.css">
    <style>
        .subject {
            margin-bottom: 25px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 15px;
        }
        .subject h3 {
            margin: 0;
            font-size: 20px;
        }
        .subject p {
            margin: 5px 0;
        }
        .subject form {
            margin-top: 10px;
        }
        .enroll-btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 6px 12px;
            cursor: pointer;
        }
        .enroll-btn:hover {
            background-color: #0056b3;
        }
        .enrollment-status {
            margin-top: 40px;
            background: #f0f0f0;
            padding: 20px;
        }
    </style>
</head>
<body>

<?php include('student_portal_navigation.php'); ?>

<p>Grade Level: <?= $grade_level ?> | Student Type: <?= $student_type ?></p>

<hr>

<h2>Available Subjects to Enroll</h2>

<?php while ($subject = $subjects->fetch_assoc()): ?>
    <div class="subject">
        <h3><?= htmlspecialchars($subject['subject_name']) ?></h3>
        <p>Teacher: <?= htmlspecialchars($subject['teacher_name'] ?? 'TBA') ?></p>
        <form method="POST">
            <input type="hidden" name="subject_id" value="<?= $subject['id'] ?>">
            <button type="submit" class="enroll-btn">Enroll</button>
        </form>
    </div>
<?php endwhile; ?>


<!-- Ionicons -->
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>

