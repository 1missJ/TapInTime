<?php
// Include database connection
include 'db_connection.php';

$lrn = isset($_GET['lrn']) ? $_GET['lrn'] : '';
if (empty($lrn)) {
    die("Invalid LRN.");
}

$sql = "SELECT first_name, middle_name, last_name, id_photo, guardian_name, guardian_address, guardian_contact, school_year 
        FROM students WHERE lrn = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $lrn);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    die("Student not found.");
}

// Format student's name
$middle_initial = !empty($student['middle_name']) ? strtoupper(substr($student['middle_name'], 0, 1)) . '.' : '';
$full_name = strtoupper($student['first_name'] . ' ' . $middle_initial . ' ' . $student['last_name']);

// Set image path
$id_photo_path = htmlspecialchars($student['id_photo']);

if (!empty($id_photo_path) && file_exists($id_photo_path)) {
    $id_photo_path = $id_photo_path;
} else {
    $id_photo_path = "assets/default.png"; // Default image if not found
}

// Debugging output
if (!file_exists($id_photo_path)) {
    error_log("File not found: " . $id_photo_path);
}

// Guardian details
$guardian_name = !empty($student['guardian_name']) ? htmlspecialchars($student['guardian_name']) : 'N/A';
$guardian_address = !empty($student['guardian_address']) ? htmlspecialchars($student['guardian_address']) : 'N/A';
$guardian_contact = !empty($student['guardian_contact']) ? htmlspecialchars($student['guardian_contact']) : 'N/A';

// Fetch school year
$school_year = !empty($student['school_year']) ? htmlspecialchars($student['school_year']) : 'N/A';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student ID - TapInTime</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/id_template.css">
</head>
<body>

<div class="print-area">
    <div class="id-wrapper">
        <!-- Front ID -->
        <div class="id-container">
            <img src="assets/id/id_f.jpg" class="background-img">
            <img id="modalIDPhoto" src="<?php echo $id_photo_path; ?>" class="id-photo">
            <div class="student-name"><?php echo $full_name; ?></div>
            <div class="student-lrn"><?php echo htmlspecialchars($lrn); ?></div>
        </div>

        <!-- Back ID -->
<div class="id-container">
    <img src="assets/id/id_b.jpg" class="background-img">
    
    <!-- School Year Section -->
    <div class="school-year"><?php echo $school_year; ?></div>
    <div class="guardian-info">
        <div class="guardian-name"><?php echo $guardian_name; ?></div>
        <div class="guardian-address"><?php echo $guardian_address; ?></div>
        <div class="guardian-contact"><?php echo $guardian_contact; ?></div>
    </div>
</div>

        <!-- Print Button positioned correctly below Back ID -->
        <button class="print-btn" onclick="window.print();">Print ID</button>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        function adjustFontSize(element, maxWidth, minFontSize) {
            let fontSize = 20; // Default font size
            element.style.fontSize = fontSize + "px";

            while (element.scrollWidth > maxWidth && fontSize > minFontSize) {
                fontSize--; // Reduce font size if the text overflows
                element.style.fontSize = fontSize + "px";
            }
        }

        let nameElement = document.querySelector(".student-name");
        if (nameElement) {
            adjustFontSize(nameElement, 240, 12); // Ensure name fits in white space
        }
    });
</script>

</body>
</html>
