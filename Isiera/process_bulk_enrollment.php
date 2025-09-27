<?php
include('db_connection.php');
session_start();

if (!isset($_SESSION['teacher_id'])) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$section_id = $data['section_id'] ?? null;
$subject_ids = $data['subject_ids'] ?? [];

if (!$section_id) {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(['success' => false, 'message' => 'Missing section ID']);
    exit();
}

// If no subjects selected, remove all enrollments for this section
if (empty($subject_ids)) {
    $deleteStmt = $conn->prepare("
        DELETE FROM student_enrollments 
        WHERE section_id = ?
    ");
    $deleteStmt->bind_param("i", $section_id);
    
    if ($deleteStmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Removed all subject enrollments for this section'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error removing enrollments: ' . $deleteStmt->error
        ]);
    }
    exit();
}

try {
    // Get section information
    $sectionStmt = $conn->prepare("SELECT section_name, grade_level FROM sections WHERE id = ?");
    $sectionStmt->bind_param("i", $section_id);
    $sectionStmt->execute();
    $sectionData = $sectionStmt->get_result()->fetch_assoc();
    
    if (!$sectionData) {
        throw new Exception("Section not found");
    }
    
    // Get all students in the section
    $studentStmt = $conn->prepare("
        SELECT lrn, first_name, middle_name, last_name, rfid 
        FROM students 
        WHERE section = ?
    ");
    $studentStmt->bind_param("s", $sectionData['section_name']);
    $studentStmt->execute();
    $students = $studentStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($students)) {
        echo json_encode(['success' => false, 'message' => 'No students found in this section']);
        exit();
    }

    // Check teacher assignments before starting transaction
    $subjectsWithoutTeachers = [];
    $subjectsWithTeachers = [];
    
    // Get all subject names at once for efficiency
    $subjectNames = [];
    $placeholders = implode(',', array_fill(0, count($subject_ids), '?'));
    $subjectStmt = $conn->prepare("SELECT id, subject_name FROM subjects WHERE id IN ($placeholders)");
    $subjectStmt->bind_param(str_repeat('i', count($subject_ids)), ...$subject_ids);
    $subjectStmt->execute();
    $subjectResult = $subjectStmt->get_result();
    while ($row = $subjectResult->fetch_assoc()) {
        $subjectNames[$row['id']] = $row['subject_name'];
    }
    
    foreach ($subject_ids as $subject_id) {
        if (!isset($subjectNames[$subject_id])) {
            $subjectsWithoutTeachers[] = $subject_id;
            continue;
        }
        
        $teacherStmt = $conn->prepare("
            SELECT ts.teacher_id, f.name as teacher_name
            FROM teacher_subjects ts
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
                'subject_name' => $subjectNames[$subject_id]
            ];
        }
    }

    // If any subjects lack teachers, return warning immediately
    if (!empty($subjectsWithoutTeachers)) {
        $missingSubjectNames = array_map(function($id) use ($subjectNames) {
            return $subjectNames[$id] ?? "Subject ID $id";
        }, $subjectsWithoutTeachers);
        
        echo json_encode([
            'success' => false,
            'message' => 'Cannot proceed with enrollment',
            'warning' => 'The following subjects have no assigned teachers: ' . implode(', ', $missingSubjectNames),
            'subjects_without_teachers' => $subjectsWithoutTeachers
        ]);
        exit();
    }

    $conn->begin_transaction();

    try {
        // First, remove ALL existing enrollments for ALL students in this section
        $deleteStmt = $conn->prepare("
            DELETE e FROM student_enrollments e
            JOIN students s ON e.student_lrn = s.lrn
            WHERE s.section = ?
        ");
        
        if (!$deleteStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $deleteStmt->bind_param("s", $sectionData['section_name']);
        
        if (!$deleteStmt->execute()) {
            throw new Exception("Delete failed: " . $deleteStmt->error);
        }

        // Prepare the insert statement
        $insertStmt = $conn->prepare("
            INSERT INTO student_enrollments 
            (student_lrn, student_name, rfid, section_name, grade_level, 
             subject_id, section_id, teacher_id, subject_name, teacher_name) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$insertStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $enrollmentCount = 0;
        foreach ($students as $student) {
            $full_name = trim($student['first_name'] . ' ' . 
                        ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . 
                        $student['last_name']);
            
            foreach ($subject_ids as $subject_id) {
                $teacherData = $subjectsWithTeachers[$subject_id];
                
                $insertStmt->bind_param(
                    "sssssiiiss",
                    $student['lrn'],
                    $full_name,
                    $student['rfid'],
                    $sectionData['section_name'],
                    $sectionData['grade_level'],
                    $subject_id,
                    $section_id,
                    $teacherData['teacher_id'],
                    $teacherData['subject_name'],
                    $teacherData['teacher_name']
                );
                
                if (!$insertStmt->execute()) {
                    throw new Exception("Insert failed: " . $insertStmt->error);
                }
                
                $enrollmentCount++;
            }
        }

        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Bulk enrollment completed successfully',
            'students_count' => count($students),
            'subjects_count' => count($subject_ids),
            'enrollments_created' => $enrollmentCount
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Bulk enrollment error: " . $e->getMessage());
        
        echo json_encode([
            'success' => false, 
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>