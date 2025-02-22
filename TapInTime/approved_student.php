<?php
include 'db_connection.php'; // Siguraduhin na tama ang DB connection

if (isset($_GET['id'])) {
    $studentId = $_GET['id'];

    // Kunin ang student data mula sa pending_students
    $query = "SELECT * FROM pending_students WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();

    if ($student) {
        // Ipasok ang student data sa students table
        $insertQuery = "INSERT INTO students (lrn, first_name, middle_name, last_name, email, date_of_birth, gender, citizenship, address, contact_number, guardian_name, guardian_contact, guardian_relationship, elementary_school, year_graduated, birth_certificate, id_photo, good_moral, student_signature)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param("sssssssssssssssssss", 
            $student['lrn'], 
            $student['first_name'], 
            $student['middle_name'], 
            $student['last_name'],
            $student['email'], 
            $student['date_of_birth'], 
            $student['gender'], 
            $student['citizenship'],
            $student['address'], 
            $student['contact_number'], 
            $student['guardian_name'], 
            $student['guardian_contact'],
            $student['guardian_relationship'], 
            $student['elementary_school'], 
            $student['year_graduated'], 
            $student['birth_certificate'], // Dapat birth_certificate hindi birth_cert
            $student['id_photo'], 
            $student['good_moral'], 
            $student['student_signature']
        );

        if ($insertStmt->execute()) {
            // Kapag na-save sa students table, i-delete sa pending_students
            $deleteQuery = "DELETE FROM pending_students WHERE id = ?";
            $deleteStmt = $conn->prepare($deleteQuery);
            $deleteStmt->bind_param("i", $studentId);
            $deleteStmt->execute();

            echo "<script>alert('Student approved successfully!'); window.location.href='student_verification.php';</script>";
        } else {
            echo "<script>alert('Error approving student.'); window.location.href='student_verification.php';</script>";
        }
    } else {
        echo "<script>alert('Student not found.'); window.location.href='student_verification.php';</script>";
    }
}
?>