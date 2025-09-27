<?php
include('db_connection.php');

$grade_level = $_GET['grade_level'] ?? 0;
$section_id = $_GET['section_id'] ?? 0;
$lrn = $_GET['lrn'] ?? '';

// Get all subjects for this grade level and section
$subjectsStmt = $conn->prepare("
    SELECT s.id, s.subject_name, 
           GROUP_CONCAT(DISTINCT CONCAT(f.id, ':', f.name) SEPARATOR '|') as teachers,
           EXISTS(
               SELECT 1 FROM student_enrollments 
               WHERE student_lrn = ? AND subject_id = s.id
           ) as is_enrolled
    FROM subject_grade_strand_assignments a
    JOIN subjects s ON a.subject_id = s.id
    LEFT JOIN teacher_subjects ts ON s.id = ts.subject_id AND ts.section_id = ?
    LEFT JOIN faculty f ON ts.teacher_id = f.id
    WHERE a.grade_level = ?
    GROUP BY s.id
    ORDER BY s.subject_name
");

$subjectsStmt->bind_param("sii", $lrn, $section_id, $grade_level);
$subjectsStmt->execute();
$subjectsResult = $subjectsStmt->get_result();

while ($subject = $subjectsResult->fetch_assoc()) {
    $is_enrolled = $subject['is_enrolled'];
    
    echo '<div class="subject-item' . ($is_enrolled ? ' disabled' : '') . '">';
    echo '<label>';
    echo '<input type="checkbox" name="subjects[]" value="' . $subject['id'] . '" ';
    echo ($is_enrolled ? 'checked disabled' : '') . '>';
    echo htmlspecialchars($subject['subject_name']);
    echo '</label>';
    echo '</div>';
}
?>