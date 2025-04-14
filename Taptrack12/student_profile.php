<?php
// Include database connection
include 'db_connection.php';

// Check if LRN is passed in URL
if (isset($_GET['lrn'])) {
    $lrn = $_GET['lrn'];  // Get LRN from URL
} else {
    echo "<script>alert('Student not found!'); window.location='student_details.php';</script>";
    exit();
}

// Initialize variables
$first_name = $middle_name = $last_name = $date_of_birth = $gender = "";
$guardian_name = $guardian_contact = $guardian_address = $guardian_relationship = "";
$elementary_school = $year_graduated = "";
$birth_certificate = $good_moral = $student_signature = "";

$id_photo_path = !empty($id_photo) ? "uploads/$id_photo" : "assets/imgs/placeholder.png";
$birth_certificate_path = !empty($birth_certificate) ? "uploads/$birth_certificate" : "assets/imgs/placeholder.png";
$good_moral_path = !empty($good_moral) ? "uploads/$good_moral" : "assets/imgs/placeholder.png";
$student_signature_path = !empty($student_signature) ? "uploads/$student_signature" : "assets/imgs/placeholder.png";

// Fetch student profile based on LRN
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data from POST
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $last_name = $_POST['last_name'];
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $citizenship = $_POST['citizenship'];
    $contact_number = $_POST['contact_number'];
    $address = $_POST['address'];
    $email = $_POST['email'];
    $guardian_name = $_POST['guardian_name'];
    $guardian_contact = $_POST['guardian_contact'];
    $guardian_address = $_POST['guardian_address'];
    $guardian_relationship = $_POST['guardian_relationship'];
    $elementary_school = $_POST['elementary_school'];
    $year_graduated = $_POST['year_graduated'];

    // Update student profile in the database
    $sql = "UPDATE students SET first_name = ?, middle_name = ?, last_name = ?, date_of_birth = ?, gender = ?, citizenship = ?, contact_number = ?, address = ?, email = ?, guardian_name = ?, guardian_contact = ?, guardian_address = ?, guardian_relationship = ?, elementary_school = ?, year_graduated = ? WHERE lrn = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssssss", $first_name, $middle_name, $last_name, $date_of_birth, $gender, $citizenship, $contact_number, $address, $email, $guardian_name, $guardian_contact, $guardian_address, $guardian_relationship, $elementary_school, $year_graduated, $lrn);

    // Execute and check for success
    if ($stmt->execute()) {
        echo "<script>alert('Student details updated successfully'); window.location='student_details.php?lrn=" . $lrn . "';</script>";
    } else {
        echo "<script>alert('Error updating student details.');</script>";
    }
    $stmt->close();
} else {
    // Fetch student data if not updating
    $sql = "SELECT * FROM students WHERE lrn = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $lrn);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $first_name = $row['first_name'];
        $middle_name = $row['middle_name'];
        $last_name = $row['last_name'];
        $date_of_birth = $row['date_of_birth'];
        $gender = $row['gender'];
        $citizenship = $row['citizenship'];
        $contact_number = $row['contact_number'];
        $address = $row['address'];
        $email = $row['email'];
        $guardian_name = $row['guardian_name'];
        $guardian_contact = $row['guardian_contact'];
        $guardian_address = $row['guardian_address'];
        $guardian_relationship = $row['guardian_relationship'];
        $elementary_school = $row['elementary_school'];
        $year_graduated = $row['year_graduated'];
        $id_photo = $row['id_photo'];
        $birth_certificate = $row['birth_certificate'];
        $good_moral = $row['good_moral'];
        $student_signature = $row['student_signature'];
    }else {
        echo "<script>alert('No student data found!');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <!-- Include Sidebar -->
    <?php include('sidebar.php'); ?>

    <div class="main-content">
        <div class="row">
            <div class="col-md-8">
                <form method="POST" action="">
                    <div class="row mb-4">
                        <div class="col-md-3">
                        <div class="rectangle-container">
                            <img src="<?php echo !empty($id_photo) ? $id_photo : 'assets/imgs/placeholder.png'; ?>" class="rectangle-img">
                        </div>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="form-section">
                        <h2>Personal Information</h2>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="first_name">First Name:</label>
                                <input type="text" class="form-control" name="first_name" id="first_name" value="<?php echo $first_name; ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="middle_name">Middle Name:</label>
                                <input type="text" class="form-control" name="middle_name" id="middle_name" value="<?php echo $middle_name; ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="last_name">Last Name:</label>
                                <input type="text" class="form-control" name="last_name" id="last_name" value="<?php echo $last_name; ?>">
                            </div>
                            </div>

                            <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="lrn">LRN:</label>
                                <input type="text" class="form-control" name="lrn" id="lrn" value="<?php echo $lrn; ?>" readonly>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="dob">Date of Birth:</label>
                                <input type="text" class="form-control" name="date_of_birth" id="date_of_birth" value="<?php echo $date_of_birth; ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="gender">Gender:</label>
                                <input type="text" class="form-control" name="gender" id="gender" value="<?php echo $gender; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="lrn">Citizenship</label>
                                <input type="text" class="form-control" name="citizenship" id="citizenship" value="<?php echo $citizenship; ?>" readonly>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="dob">Contact Number:</label>
                                <input type="text" class="form-control" name="contact_number" id="contact_number" value="<?php echo $contact_number; ?>">
                            </div>
                    </div>

                    <div class="row">
                    <div class="col-md-4 form-group">
                                <label for="lrn">Address</label>
                                <input type="text" class="form-control" name="address" id="address" value="<?php echo $address; ?>" readonly>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="dob">Email Address:</label>
                                <input type="text" class="form-control" name="email" id="email" value="<?php echo $email; ?>">
                            </div>
                        </div>
                            
                    <!-- Parent/Guardian Information -->
                    <div class="form-section">
                        <h2>Parent/Guardian Information</h2>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="guardian_name">Guardian Name:</label>
                                <input type="text" class="form-control" name="guardian_name" id="guardian_name" value="<?php echo $guardian_name; ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="guardian_contact">Guardian Contact:</label>
                                <input type="text" class="form-control" name="guardian_contact" id="guardian_contact" value="<?php echo $guardian_contact; ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="guardian_address">Guardian Address:</label>
                                <input type="text" class="form-control" name="guardian_address" id="guardian_address" value="<?php echo $guardian_address; ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="guardian_relationship">Relationship:</label>
                                <input type="text" class="form-control" name="guardian_relationship" id="guardian_relationship" value="<?php echo $guardian_relationship; ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Academic Information -->
                    <div class="form-section">
                        <h2>Academic Information</h2>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="elementary_school">Elementary School:</label>
                                <input type="text" class="form-control" name="elementary_school" id="elementary_school" value="<?php echo $elementary_school; ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="year_graduated">Year Graduated:</label>
                                <input type="text" class="form-control" name="year_graduated" id="year_graduated" value="<?php echo $year_graduated; ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Required Documents -->
                    <div class="form-section">
                    <h2>Documents</h2>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="rectangle-container">
                                <label for="birth_certificate">Birth Certificate:</label>
                                <img src="<?php echo !empty($birth_certificate) ? $birth_certificate : 'assets/imgs/placeholder.png'; ?>" class="document-box">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="rectangle-container">
                                <label for="good_moral">Good Moral Certificate:</label>
                                <img src="<?php echo !empty($good_moral) ? $good_moral : 'assets/imgs/placeholder.png'; ?>" class="document-box">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="rectangle-container">
                                <label for="student_signature">Student Signature:</label>
                                <img src="<?php echo !empty($student_signature) ? $student_signature : 'assets/imgs/placeholder.png'; ?>" class="document-box">
                            </div>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="form-buttons">
                        <button class="save-btn" type="submit">Save</button>
                        <button class="close-btn" id="closeBtn" type="button">Close</button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById("closeBtn").addEventListener('click', function() {
            window.history.back();
        });
    </script>

    <!-- Main JS -->
    <script src="assets/js/main.js"></script>      
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>