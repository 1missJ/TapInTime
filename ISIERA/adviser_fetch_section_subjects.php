<?php
include('db_connection.php');

$section_id = $_GET['section_id'] ?? 0;

// First, get the section name
$sectionStmt = $conn->prepare("SELECT section_name FROM sections WHERE id = ?");
$sectionStmt->bind_param("i", $section_id);
$sectionStmt->execute();
$sectionResult = $sectionStmt->get_result();
$sectionData = $sectionResult->fetch_assoc();

if (!$sectionData) {
    echo json_encode(['enrolled_subjects' => []]);
    exit();
}

$section_name = $sectionData['section_name'];

// Get total number of students in the section
$studentCountStmt = $conn->prepare("SELECT COUNT(*) as total FROM students WHERE section = ?");
$studentCountStmt->bind_param("s", $section_name);
$studentCountStmt->execute();
$studentCountResult = $studentCountStmt->get_result();
$studentCount = $studentCountResult->fetch_assoc()['total'];

// If no students in section, return empty array
if ($studentCount == 0) {
    echo json_encode(['enrolled_subjects' => []]);
    exit();
}

// Get subjects that are enrolled for the entire section
// (subjects where ALL students in the section are enrolled)
$enrolledSubjectsStmt = $conn->prepare("
    SELECT subject_id, COUNT(DISTINCT student_lrn) as enrolled_count
    FROM student_enrollments 
    WHERE section_id = ?
    GROUP BY subject_id
    HAVING enrolled_count = ?
");

$enrolledSubjectsStmt->bind_param("ii", $section_id, $studentCount);
$enrolledSubjectsStmt->execute();
$enrolledSubjectsResult = $enrolledSubjectsStmt->get_result();

$enrolled_subjects = [];
while ($row = $enrolledSubjectsResult->fetch_assoc()) {
    $enrolled_subjects[] = $row['subject_id'];
}

echo json_encode(['enrolled_subjects' => $enrolled_subjects]);
?>