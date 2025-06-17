<?php
include('db_connection.php');
$type = isset($_GET['type']) ? $_GET['type'] : 'Regular';

// Handle subject assignment
if (isset($_POST['assign_subjects'])) {
    $selected_subjects = $_POST['subjects'] ?? [];
    $student_type = $_POST['assign_student_type'];
    $grade_level = $_POST['grade_level'];

    foreach ($selected_subjects as $subject_id) {
        // Prevent duplication
        $check = $conn->prepare("SELECT * FROM assigned_grade_subjects WHERE subject_id = ? AND student_type = ? AND grade_level = ?");
        $check->bind_param("isi", $subject_id, $student_type, $grade_level);
        $check->execute();
        if ($check->get_result()->num_rows == 0) {
            $insert = $conn->prepare("INSERT INTO assigned_grade_subjects (subject_id, student_type, grade_level) VALUES (?, ?, ?)");
            $insert->bind_param("isi", $subject_id, $student_type, $grade_level);
            $insert->execute();
        }
    }

    echo "<script>alert('Subjects assigned successfully!'); window.location.href='subject_management.php';</script>";
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Subjects to Grade</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/subject.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>


<body>
    <div class="main-wrapper">
        <?php include('sidebar.php'); ?>

        <div class="assign-container">
            <h2>Assign Subjects to Grade - <?= htmlspecialchars($type) ?></h2>
            <form method="POST">
                <input type="hidden" name="assign_student_type" value="<?= htmlspecialchars($type) ?>">

                <label for="grade_level">Select Grade Level:</label>
                <select name="grade_level" required>
                    <option value="">-- Select Grade --</option>
                    <option value="7">Grade 7</option>
                    <option value="8">Grade 8</option>
                    <option value="9">Grade 9</option>
                    <option value="10">Grade 10</option>
                </select>

                <label for="subjects">Select Subjects:</label>
                <select id="subjects" name="subjects[]" multiple="multiple" required>
                    <?php
                    $stmt = $conn->prepare("SELECT id, subject_name, student_type FROM subjects WHERE student_type = ? OR student_type = 'Both'");
                    $stmt->bind_param("s", $type);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        echo "<option value='{$row['id']}'>" . htmlspecialchars($row['subject_name']) . " ({$row['student_type']})</option>";
                    }
                    ?>
                </select>

                <input type="submit" name="assign_subjects" class="assign-btn" value="Assign Subjects">
            </form>
        </div>
    </div>


<script>
    $(document).ready(function() {
        $('#subjects').select2({
            placeholder: "Select subjects...",
            width: '100%'
        });
    });
</script>

<!-- Ionicons -->
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>    

</body>
</html>
