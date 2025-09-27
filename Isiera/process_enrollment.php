<?php
include('db_connection.php');
session_start();

if (!isset($_SESSION['teacher_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized access']));
}

// Get JSON input data
$data = json_decode(file_get_contents('php://input'), true);
$lrn = $data['lrn'] ?? '';
$subjects = $data['subjects'] ?? [];
$section_id = $data['section_id'] ?? 0;

// Validate input
if (empty($lrn) || empty($subjects) || empty($section_id)) {
    die(json_encode(['status' => 'error', 'message' => 'Invalid input data']));
}

// Get complete student information including RFID
$studentInfo = $conn->prepare("
    SELECT first_name, middle_name, last_name, rfid, section, grade_level 
    FROM students 
    WHERE lrn = ?
");
$studentInfo->bind_param("s", $lrn);
$studentInfo->execute();
$studentData = $studentInfo->get_result()->fetch_assoc();

if (!$studentData) {
    die(json_encode(['status' => 'error', 'message' => 'Student not found']));
}

// Prepare student data
$full_name = trim($studentData['first_name'] . ' ' . 
    ($studentData['middle_name'] ? $studentData['middle_name'] . ' ' : '') . 
    $studentData['last_name']);
$rfid = $studentData['rfid'] ?? null;
$section_name = $studentData['section'];
$grade_level = $studentData['grade_level'];

// First check all subjects have teachers assigned
$subjectsWithoutTeachers = [];
$subjectsWithTeachers = [];

foreach ($subjects as $subject_id) {
    $teacherStmt = $conn->prepare("
        SELECT ts.teacher_id, f.name as teacher_name, s.subject_name
        FROM teacher_subjects ts
        JOIN subjects s ON ts.subject_id = s.id
        LEFT JOIN faculty f ON ts.teacher_id = f.id
        WHERE ts.subject_id = ? 
        AND ts.section_id = ?
        LIMIT 1
    ");
    $teacherStmt->bind_param("ii", $subject_id, $section_id);
    $teacherStmt->execute();
    $teacherData = $teacherStmt->get_result()->fetch_assoc();
    
    if (!$teacherData || !$teacherData['teacher_id']) {
        $subjectsWithoutTeachers[] = $subject_id;
    } else {
        $subjectsWithTeachers[$subject_id] = [
            'teacher_id' => $teacherData['teacher_id'],
            'teacher_name' => $teacherData['teacher_name'],
            'subject_name' => $teacherData['subject_name']
        ];
    }
}

// If any subjects lack teachers, return warning immediately
if (!empty($subjectsWithoutTeachers)) {
    // Get subject names for the warning message
    $subjectNames = [];
    $subjectStmt = $conn->prepare("SELECT subject_name FROM subjects WHERE id IN (".implode(',', array_fill(0, count($subjectsWithoutTeachers), '?')).")");
    $subjectStmt->bind_param(str_repeat('i', count($subjectsWithoutTeachers)), ...$subjectsWithoutTeachers);
    $subjectStmt->execute();
    $subjectResult = $subjectStmt->get_result();
    while ($row = $subjectResult->fetch_assoc()) {
        $subjectNames[] = $row['subject_name'];
    }
    
    die(json_encode([
        'status' => 'error',
        'message' => 'Cannot proceed with enrollment',
        'warning' => 'The following subjects have no assigned teachers: ' . implode(', ', $subjectNames),
        'subjects_without_teachers' => $subjectsWithoutTeachers
    ]));
}

// ... existing code ...

$conn->begin_transaction();
try {
    // Delete existing enrollments for this specific student only
    $deleteStmt = $conn->prepare("DELETE FROM student_enrollments WHERE student_lrn = ?");
    $deleteStmt->bind_param("s", $lrn);
    $deleteStmt->execute();

    // Prepare the insert statement with all fields
    $insertStmt = $conn->prepare("
        INSERT INTO student_enrollments 
        (student_lrn, student_name, rfid, section_name, grade_level, 
         subject_id, section_id, teacher_id, subject_name, teacher_name) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($subjects as $subject_id) {
        $teacherData = $subjectsWithTeachers[$subject_id];
        
        $insertStmt->bind_param(
            "sssssiiiss",
            $lrn,
            $full_name,
            $rfid,
            $section_name,
            $grade_level,
            $subject_id,
            $section_id,
            $teacherData['teacher_id'],
            $teacherData['subject_name'],
            $teacherData['teacher_name']
        );
        
        if (!$insertStmt->execute()) {
            throw new Exception("Failed to insert enrollment: " . $insertStmt->error);
        }
    }

    $conn->commit();
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Enrollment updated successfully for ' . $full_name
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>