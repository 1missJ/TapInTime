<?php
// Start output buffering to prevent any output before headers
ob_start();

include('db_connection.php');

// Get filters and selected LRNs
$lrns = $_POST['selected_lrns'] ?? [];
$section = $_POST['section'] ?? '';
$filter = $_POST['filter'] ?? ''; // Changed from grade_level/student_type to filter

// Build redirect URL back to student_archive.php with correct parameters
$redirectUrl = "student_archive.php?section=" . urlencode($section) .
               "&filter=" . urlencode($filter);

// If no students selected, redirect back
if (empty($lrns)) {
    header("Location: $redirectUrl");
    ob_end_flush();
    exit;
}

try {
    $conn->begin_transaction();

    $fetchQuery = "SELECT * FROM archived_students WHERE lrn = ?";
    $insertQuery = "INSERT INTO students (
        lrn, rfid, first_name, middle_name, last_name, email, section, school_year, grade_level, student_type,
        date_of_birth, gender, citizenship, address, contact_number, guardian_name, guardian_contact,
        guardian_relationship, guardian_address, elementary_school, year_graduated, birth_certificate,
        id_photo, good_moral, student_signature
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $deleteQuery = "DELETE FROM archived_students WHERE lrn = ?";

    $fetchStmt = $conn->prepare($fetchQuery);
    $insertStmt = $conn->prepare($insertQuery);
    $deleteStmt = $conn->prepare($deleteQuery);

    foreach ($lrns as $lrn) {
        $fetchStmt->bind_param('s', $lrn);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $student = $result->fetch_assoc();

        if (!$student) continue;

        $insertStmt->bind_param(
            'sssssssssssssssssssssssss',
            $student['lrn'],
            $student['rfid'],
            $student['first_name'],
            $student['middle_name'],
            $student['last_name'],
            $student['email'],
            $student['section'],
            $student['school_year'],
            $student['grade_level'],
            $student['student_type'],
            $student['date_of_birth'],
            $student['gender'],
            $student['citizenship'],
            $student['address'],
            $student['contact_number'],
            $student['guardian_name'],
            $student['guardian_contact'],
            $student['guardian_relationship'],
            $student['guardian_address'],
            $student['elementary_school'],
            $student['year_graduated'],
            $student['birth_certificate'],
            $student['id_photo'],
            $student['good_moral'],
            $student['student_signature']
        );
        
        if (!$insertStmt->execute()) {
            throw new Exception("Failed to insert student with LRN: $lrn. Error: " . $insertStmt->error);
        }

        $deleteStmt->bind_param('s', $lrn);
        if (!$deleteStmt->execute()) {
            throw new Exception("Failed to delete archived student with LRN: $lrn. Error: " . $deleteStmt->error);
        }
    }

    $conn->commit();

    // Redirect back to archive page with same filters and success message
    header("Location: $redirectUrl&unarchive_success=1");
    ob_end_flush();
    exit;
} catch (Exception $e) {
    $conn->rollback();
    // Redirect back with error message
    header("Location: $redirectUrl&unarchive_error=1&message=" . urlencode($e->getMessage()));
    ob_end_flush();
    exit;
}
?>