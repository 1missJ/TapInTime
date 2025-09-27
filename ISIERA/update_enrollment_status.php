<?php
include('db_connection.php');
session_start();

if (!isset($_SESSION['teacher_id'])) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$section_id = $_GET['section_id'] ?? null;
$grade_level = $_GET['grade_level'] ?? null;

if (!$section_id || !$grade_level) {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

// Get section information
$sectionStmt = $conn->prepare("SELECT section_name, strand_id FROM sections WHERE id = ?");
$sectionStmt->bind_param("i", $section_id);
$sectionStmt->execute();
$sectionData = $sectionStmt->get_result()->fetch_assoc();

if (!$sectionData) {
    echo json_encode(['success' => false, 'message' => 'Section not found']);
    exit();
}

// Get total number of subjects for this section
$totalSubjectsStmt = $conn->prepare("
    SELECT COUNT(DISTINCT s.id) as total_subjects
    FROM subject_grade_strand_assignments a
    JOIN subjects s ON a.subject_id = s.id
    WHERE a.grade_level = ?
    " . (($grade_level >= 11) ? "AND a.strand_id = ?" : "")
);

if ($grade_level >= 11) {
    $totalSubjectsStmt->bind_param("ii", $grade_level, $sectionData['strand_id']);
} else {
    $totalSubjectsStmt->bind_param("i", $grade_level);
}

$totalSubjectsStmt->execute();
$totalSubjectsResult = $totalSubjectsStmt->get_result()->fetch_assoc();
$totalSubjects = $totalSubjectsResult['total_subjects'] ?? 0;

// Get students with their updated enrollment status
$studentStmt = $conn->prepare("
    SELECT s.lrn, 
           COUNT(e.subject_id) as enrolled_count
    FROM students s
    LEFT JOIN student_enrollments e ON s.lrn = e.student_lrn AND e.section_id = ?
    WHERE s.section = ?
    GROUP BY s.lrn
");
$studentStmt->bind_param("is", $section_id, $sectionData['section_name']);
$studentStmt->execute();
$studentResult = $studentStmt->get_result();

$students = [];
while ($row = $studentResult->fetch_assoc()) {
    // Determine enrollment status
    if ($row['enrolled_count'] == 0) {
        $statusClass = 'not-enrolled';
        $statusText = 'Not Enrolled';
    } elseif ($row['enrolled_count'] == $totalSubjects) {
        $statusClass = 'enrolled';
        $statusText = 'Fully Enrolled';
    } else {
        $statusClass = 'partial-enrolled';
        $statusText = 'Partially Enrolled';
    }
    
    $students[] = [
        'lrn' => $row['lrn'],
        'status_class' => $statusClass,
        'status_text' => $statusText
    ];
}

echo json_encode([
    'success' => true,
    'students' => $students,
    'total_subjects' => $totalSubjects
]);
?>