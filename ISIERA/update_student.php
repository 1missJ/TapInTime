<?php
// update_student.php
include('db_connection.php');
session_start();

// Set headers FIRST before any output
header('Content-Type: application/json');

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Get the LRN from the form
$lrn = isset($_POST['lrn']) ? mysqli_real_escape_string($conn, $_POST['lrn']) : null;
if (!$lrn) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'LRN is required']);
    exit;
}

// Prepare the update query
$fields = [
    'first_name' => $_POST['first_name'] ?? '',
    'middle_name' => $_POST['middle_name'] ?? '',
    'last_name' => $_POST['last_name'] ?? '',
    'date_of_birth' => $_POST['date_of_birth'] ?? '',
    'gender' => $_POST['gender'] ?? '',
    'citizenship' => $_POST['citizenship'] ?? '',
    'contact_number' => $_POST['contact_number'] ?? '',
    'email' => $_POST['email'] ?? '',
    'address' => $_POST['address'] ?? '',
    'guardian_name' => $_POST['guardian_name'] ?? '',
    'guardian_contact' => $_POST['guardian_contact'] ?? '',
    'guardian_address' => $_POST['guardian_address'] ?? '',
    'guardian_relationship' => $_POST['guardian_relationship'] ?? '',
    'elementary_school' => $_POST['elementary_school'] ?? '',
    'year_graduated' => $_POST['year_graduated'] ?? '',
];

// Handle file uploads
$fileFields = ['id_photo', 'birth_certificate', 'good_moral', 'student_signature'];
foreach ($fileFields as $field) {
    if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        // Ensure upload directory exists
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileName = $lrn . '_' . $field . '_' . time() . '.' . pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES[$field]['tmp_name'], $uploadPath)) {
            $fields[$field] = $uploadPath;
            
            // Delete old file if it exists
            $oldFileQuery = "SELECT $field FROM students WHERE lrn = '$lrn'";
            $oldFileResult = mysqli_query($conn, $oldFileQuery);
            if ($oldFileRow = mysqli_fetch_assoc($oldFileResult)) {
                if ($oldFileRow[$field] && file_exists($oldFileRow[$field]) && $oldFileRow[$field] !== $uploadPath) {
                    unlink($oldFileRow[$field]);
                }
            }
        }
    }
}

// Build the SQL update statement
$updates = [];
foreach ($fields as $field => $value) {
    if ($value !== '') {
        $escapedValue = mysqli_real_escape_string($conn, $value);
        $updates[] = "$field = '$escapedValue'";
    }
}

if (empty($updates)) {
    echo json_encode(['success' => false, 'error' => 'No fields to update']);
    exit;
}

$updateQuery = "UPDATE students SET " . implode(', ', $updates) . " WHERE lrn = '$lrn'";

// Execute the update
if (mysqli_query($conn, $updateQuery)) {
    http_response_code(200); // Success
    echo json_encode([
        'success' => true,
        'message' => 'Student updated successfully!'
    ]);
} else {
    http_response_code(500); // Server Error
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . mysqli_error($conn)
    ]);
}

// Close connection
mysqli_close($conn);
exit;

function updateStudentEnrollments($conn, $lrn, $firstName, $middleName, $lastName, $rfid, $section, $gradeLevel) {
    $updateQuery = "UPDATE enrollments SET 
        first_name = '" . mysqli_real_escape_string($conn, $firstName) . "',
        middle_name = '" . mysqli_real_escape_string($conn, $middleName) . "',
        last_name = '" . mysqli_real_escape_string($conn, $lastName) . "',
        rfid = '" . mysqli_real_escape_string($conn, $rfid) . "',
        section = '" . mysqli_real_escape_string($conn, $section) . "',
        grade_level = '" . mysqli_real_escape_string($conn, $gradeLevel) . "'
        WHERE student_lrn = '$lrn'";
    
    return mysqli_query($conn, $updateQuery);
}
?>