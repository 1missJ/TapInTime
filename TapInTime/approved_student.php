<?php  
include 'db_connection.php'; // Ensure DB connection is included

if (isset($_GET['id'])) {
    $studentId = $_GET['id'];

    // Get student data from pending_students, including school_year and guardian_address
    $query = "SELECT * FROM pending_students WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();

    if ($student) {
        // Debugging: Check if required fields are fetched
        if (empty($student['section']) || empty($student['school_year']) || empty($student['guardian_address'])) {
            echo "<script>alert('Error: Section, School Year, or Guardian Address is missing for this student.'); window.location.href='student_verification.php';</script>";
            exit();
        }

        // Insert student data into students table (including guardian_address)
        $insertQuery = "INSERT INTO students (lrn, first_name, middle_name, last_name, email, section, school_year, date_of_birth, gender, citizenship, address, contact_number, guardian_name, guardian_contact, guardian_relationship, guardian_address, elementary_school, year_graduated, birth_certificate, id_photo, good_moral, student_signature)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param("ssssssssssssssssssssss", 
            $student['lrn'], 
            $student['first_name'], 
            $student['middle_name'], 
            $student['last_name'],
            $student['email'], 
            $student['section'],
            $student['school_year'],
            $student['date_of_birth'], 
            $student['gender'], 
            $student['citizenship'],
            $student['address'], 
            $student['contact_number'], 
            $student['guardian_name'], 
            $student['guardian_contact'],
            $student['guardian_relationship'], 
            $student['guardian_address'], // Added guardian_address
            $student['elementary_school'], 
            $student['year_graduated'], 
            $student['birth_certificate'], 
            $student['id_photo'], 
            $student['good_moral'], 
            $student['student_signature']
        );

        if ($insertStmt->execute()) {
            // Delete from pending_students after successful insert
            $deleteQuery = "DELETE FROM pending_students WHERE id = ?";
            $deleteStmt = $conn->prepare($deleteQuery);
            $deleteStmt->bind_param("i", $studentId);
            $deleteStmt->execute();

            echo "<script>alert('Student approved successfully!'); window.location.href='student_verification.php';</script>";
        } else {
            echo "<script>alert('Error approving student. Please check database connection and try again.'); window.location.href='student_verification.php';</script>";
        }
    } else {
        echo "<script>alert('Student not found.'); window.location.href='student_verification.php';</script>";
    }
}
?>
