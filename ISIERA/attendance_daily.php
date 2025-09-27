<?php
include('db_connection.php');

// Get section from query parameter
$section = isset($_GET['section']) ? $_GET['section'] : 'Mabini';

// First, get the section_id and student_type of the selected section
$sectionInfo = [];
$infoQuery = "SELECT id, student_type FROM sections WHERE section_name = ?";
$infoStmt = $conn->prepare($infoQuery);
$infoStmt->bind_param('s', $section);
$infoStmt->execute();
$infoResult = $infoStmt->get_result();
if ($infoRow = $infoResult->fetch_assoc()) {
    $sectionInfo = $infoRow;
}

// Fetch subjects that are actually enrolled by students in this section
$subjectsWithTeachers = [];
if (!empty($sectionInfo['id'])) {
    $subjectsQuery = "SELECT DISTINCT
                        s.id as subject_id,
                        s.subject_name as subject,
                        f.id as teacher_id,
                        f.name as teacher_name
                      FROM student_enrollments se
                      JOIN subjects s ON se.subject_id = s.id
                      JOIN teacher_subjects ts ON s.id = ts.subject_id AND ts.section_id = ?
                      JOIN faculty f ON ts.teacher_id = f.id
                      WHERE se.section_id = ?";
    $subjectsStmt = $conn->prepare($subjectsQuery);
    $subjectsStmt->bind_param('ii', $sectionInfo['id'], $sectionInfo['id']);
    $subjectsStmt->execute();
    $subjectsResult = $subjectsStmt->get_result();
    
    while ($subjectRow = $subjectsResult->fetch_assoc()) {
        $subjectsWithTeachers[] = [
            'subject_id' => $subjectRow['subject_id'],
            'subject' => $subjectRow['subject'],
            'teacher_id' => $subjectRow['teacher_id'],
            'teacher_name' => $subjectRow['teacher_name']
        ];
    }
}

// Check if this is an AJAX request for attendance data
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_attendance') {
    $subject = isset($_GET['subject']) ? $_GET['subject'] : '';
    $teacherId = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : '';
    $date = isset($_GET['date']) ? $_GET['date'] : '';
    
    $attendanceData = [];
    if (!empty($section) && !empty($subject) && !empty($date) && !empty($teacherId)) {
        $query = "SELECT 
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    sub.subject_name as subject,
                    f.name as teacher_name,
                    DATE_FORMAT(a.attendance_date, '%Y-%m-%d') as date,
                    TIME_FORMAT(a.time_in, '%h:%i%p') as time,
                    a.status as remark
                  FROM attendance a
                  JOIN students s ON a.student_id = s.id
                  JOIN subjects sub ON a.subject_id = sub.id
                  JOIN faculty f ON a.teacher_id = f.id
                  JOIN sections sec ON a.section_id = sec.id
                  WHERE sec.section_name = ?
                    AND sub.subject_name = ?
                    AND f.id = ?
                    AND a.attendance_date = ?
                  ORDER BY a.time_in ASC";

        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssis', $section, $subject, $teacherId, $date);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $attendanceData[] = $row;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($attendanceData);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance - <?php echo htmlspecialchars($section); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/attendance.css">
</head>
<body>
<?php include('sidebar.php'); ?>

<div class="main-content">
    <h2>Section Attendance: <?php echo htmlspecialchars($section); ?></h2>

    <div class="filter-single-row">
        <!-- Subject -->
        <div class="filter-group">
            <label for="subjectSelect">Subject:</label>
            <select id="subjectSelect" onchange="loadTeacherForSubject()">
                <option value="">Select Subject</option>
                <?php foreach ($subjectsWithTeachers as $subject): ?>
                    <option value="<?php echo htmlspecialchars($subject['subject']); ?>" 
                            data-teacher-id="<?php echo htmlspecialchars($subject['teacher_id']); ?>"
                            data-teacher-name="<?php echo htmlspecialchars($subject['teacher_name']); ?>">
                        <?php echo htmlspecialchars($subject['subject']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <label for="teacherInput">Teacher:</label>
            <input type="text" id="teacherInput" class="teacher-input" readonly>
            <input type="hidden" id="teacherId" value="">
        </div>
        
        <div class="filter-group">
            <label for="datePicker">Date:</label>
            <input type="date" id="datePicker" onchange="loadAttendance()">
        </div>
    </div>

    <!-- Attendance List -->
    <div class="attendance-list" id="attendanceList">
        <table>
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Time In</th>
                    <th>Remark</th>
                </tr>
            </thead>
            <tbody>
                <tr class="no-data-row">
                    <td colspan="3">Please select subject and date to view attendance.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Store the subject-teacher mapping from PHP to JavaScript
    const subjectsWithTeachers = <?php echo json_encode($subjectsWithTeachers); ?>;
    let attendanceData = []; // Store attendance data for export

    function loadTeacherForSubject() {
        const subjectSelect = document.getElementById("subjectSelect");
        const selectedOption = subjectSelect.options[subjectSelect.selectedIndex];
        const teacherInput = document.getElementById("teacherInput");
        const teacherIdInput = document.getElementById("teacherId");
        
        if (selectedOption.value) {
            teacherInput.value = selectedOption.getAttribute('data-teacher-name');
            teacherIdInput.value = selectedOption.getAttribute('data-teacher-id');
            
            // Automatically load attendance if date is already selected
            const date = document.getElementById("datePicker").value;
            if (date) {
                loadAttendance();
            }
        } else {
            teacherInput.value = "";
            teacherIdInput.value = "";
            
            // Clear attendance list when subject is cleared
            document.getElementById("attendanceList").innerHTML = `
                <table>
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Time In</th>
                            <th>Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="no-data-row">
                            <td colspan="3">Please select subject and date to view attendance.</td>
                        </tr>
                    </tbody>
                </table>`;
        }
    }

    function loadAttendance() {
        const date = document.getElementById("datePicker").value;
        const subject = document.getElementById("subjectSelect").value;
        const teacherId = document.getElementById("teacherId").value;

        if (date && subject && teacherId) {
            // AJAX request to fetch attendance data
            fetch(`?section=<?php echo urlencode($section); ?>&subject=${encodeURIComponent(subject)}&teacher_id=${teacherId}&date=${date}&ajax=get_attendance`)
                .then(response => response.json())
                .then(data => {
                    let html = `
                        <table>
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Time In</th>
                                    <th>Remark</th>
                                </tr>
                            </thead>
                            <tbody>`;

                    if (data.length > 0) {
                        // Store data for export
                        attendanceData = data;
                        
                        data.forEach(entry => {
                            const remarkClass = entry.remark.toLowerCase();
                            html += `
                                <tr>
                                    <td>${entry.student_name}</td>
                                    <td>${entry.time}</td>
                                    <td class="${remarkClass}">${entry.remark}</td>
                                </tr>`;
                        });
                        
            // Add export button row - positioned to the right
            html += `
                <tr>
                    <td colspan="2"></td>
                    <td style="text-align: right; padding: 10px;">
                        <button class="export-btn" onclick="exportToCSV()">
                            <ion-icon name="download-outline"></ion-icon>
                        </button>
                    </td>
                </tr>
            `;
            
                    } else {
                        html += `<tr><td colspan="3" class="no-data-row">No attendance records found.</td></tr>`;
                        attendanceData = []; // Clear stored data
                    }

                    html += `</tbody></table>`;
                    document.getElementById("attendanceList").innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById("attendanceList").innerHTML = `
                        <table>
                            <thead>
                                <tr>
                                    <th colspan="3">Error loading attendance data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="3" class="error">Failed to load attendance data. Please try again.</td>
                                </tr>
                            </tbody>
                        </table>`;
                    attendanceData = []; // Clear stored data
                });
        } else {
            // Show placeholder if not all selections are made
            document.getElementById("attendanceList").innerHTML = `
                <table>
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Time In</th>
                            <th>Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="no-data-row">
                            <td colspan="3">Please select subject and date to view attendance.</td>
                        </tr>
                    </tbody>
                </table>`;
            attendanceData = []; // Clear stored data
        }
    }
    
    function exportToCSV() {
        if (attendanceData.length === 0) {
            alert('No data to export');
            return;
        }
        
        const subject = document.getElementById("subjectSelect").value;
        const teacher = document.getElementById("teacherInput").value;
        const date = document.getElementById("datePicker").value;
        const section = "<?php echo $section; ?>";
        
        // Create CSV content
        let csvContent = "Daily Attendance Report\r\n";
        csvContent += `Section: ${section}\r\n`;
        csvContent += `Subject: ${subject}\r\n`;
        csvContent += `Teacher: ${teacher}\r\n`;
        csvContent += `Date: ${date}\r\n\r\n`;
        
        csvContent += "Student Name,Time In,Remark\r\n";
        
        // Add data rows
        attendanceData.forEach(row => {
            csvContent += `"${row.student_name}","${row.time}","${row.remark}"\r\n`;
        });
        
        // Create download link
        const encodedUri = encodeURI("data:text/csv;charset=utf-8," + csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `daily_attendance_${section}_${subject}_${date}.csv`);
        document.body.appendChild(link);
        
        // Trigger download
        link.click();
        document.body.removeChild(link);
    }

    // Initialize date picker with today's date
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('datePicker').value = today;
    });
</script>

<!-- Ionicons -->
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>