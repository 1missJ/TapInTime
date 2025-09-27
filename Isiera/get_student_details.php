<?php
// get_student_details.php
header('Content-Type: application/json');
include('db_connection.php');

if (!isset($_GET['lrn'])) {
    echo json_encode(['error' => 'LRN not provided']);
    exit;
}

$lrn = mysqli_real_escape_string($conn, $_GET['lrn']);

$query = "SELECT * FROM students WHERE lrn = '$lrn'";
$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode(['error' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['error' => 'Student not found']);
    exit;
}

$student = mysqli_fetch_assoc($result);
echo json_encode($student);
?>