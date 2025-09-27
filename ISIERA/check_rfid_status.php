<?php
// check_rfid_status.php
include('db_connection.php');
session_start();

if (!isset($_SESSION['teacher_id'])) {
    die(json_encode(['hasRfid' => false]));
}

$lrn = $_GET['lrn'] ?? '';

if (empty($lrn)) {
    die(json_encode(['hasRfid' => false]));
}

// Check if student has RFID
$stmt = $conn->prepare("SELECT rfid FROM students WHERE lrn = ?");
$stmt->bind_param("s", $lrn);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

$hasRfid = ($result && !empty($result['rfid']) && $result['rfid'] !== 'Not Assigned ✓');

echo json_encode(['hasRfid' => $hasRfid]);
?>