<?php
include('db_connection.php');

// Get parameters from URL
$lrn = isset($_GET['lrn']) ? $_GET['lrn'] : '';
$section = isset($_GET['section']) ? $_GET['section'] : '';

// Check if this is an AJAX request for attendance data
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == 'get_attendance';
$subject = isset($_GET['subject']) ? $_GET['subject'] : '';
$month = isset($_GET['month']) ? $_GET['month'] : '';

// Fetch student info from database
$studentInfo = [];
if (!empty($lrn)) {
    $query = "SELECT s.lrn, CONCAT(s.first_name, ' ', s.last_name) as name
              FROM students s
              WHERE s.lrn = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $lrn);
    $stmt->execute();
    $result = $stmt->get_result();
    $studentInfo = $result->fetch_assoc();
}

// Fetch subjects with teacher names for dropdown based on student's enrolled subjects
$subjectsWithTeachers = [];
if (!empty($lrn)) {
    $query = "SELECT 
                s.id as subject_id,
                s.subject_name as subject,
                f.id as teacher_id,
                f.name as teacher_name
              FROM student_enrollments se
              JOIN subjects s ON se.subject_id = s.id
              JOIN faculty f ON se.teacher_id = f.id
              WHERE se.student_lrn = ?
              ORDER BY s.subject_name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $lrn);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $subjectsWithTeachers[] = $row;
    }
}

// Handle AJAX request for attendance data
if ($isAjax) {
    $attendanceData = [];
    if (!empty($lrn) && !empty($subject) && !empty($month)) {
        // Check if subject is numeric (ID) or string (name)
        if (is_numeric($subject)) {
            // Use subject_id for query
            $query = "SELECT 
                        DATE_FORMAT(a.attendance_date, '%Y-%m-%d') as date,
                        TIME_FORMAT(a.time_in, '%h:%i%p') as time,
                        a.status as remark,
                        sub.subject_name as subject_name,
                        f.name as teacher_name
                      FROM attendance a
                      JOIN students s ON a.student_id = s.id
                      JOIN subjects sub ON a.subject_id = sub.id
                      JOIN faculty f ON a.teacher_id = f.id
                      WHERE s.lrn = ?
                        AND sub.id = ?
                        AND DATE_FORMAT(a.attendance_date, '%Y-%m') = ?
                        AND DAYOFWEEK(a.attendance_date) NOT IN (1, 7)
                      ORDER BY a.attendance_date";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('sis', $lrn, $subject, $month);
        } else {
            // Use subject_name for query (fallback)
            $query = "SELECT 
                        DATE_FORMAT(a.attendance_date, '%Y-%m-%d') as date,
                        TIME_FORMAT(a.time_in, '%h:%i%p') as time,
                        a.status as remark,
                        sub.subject_name as subject_name,
                        f.name as teacher_name
                      FROM attendance a
                      JOIN students s ON a.student_id = s.id
                      JOIN subjects sub ON a.subject_id = sub.id
                      JOIN faculty f ON a.teacher_id = f.id
                      WHERE s.lrn = ?
                        AND sub.subject_name = ?
                        AND DATE_FORMAT(a.attendance_date, '%Y-%m') = ?
                        AND DAYOFWEEK(a.attendance_date) NOT IN (1, 7)
                      ORDER BY a.attendance_date";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('sss', $lrn, $subject, $month);
        }
        
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
<?php include('adviser_sidebar.php'); ?>

<div class="main-content">
    <div class="header-row">
        <h2>Attendance Records</h2>
        <div class="spacer"></div>
        <div class="student-info-row">
            <div class="student-info-item">
                <span class="info-label">LRN:</span>
                <span class="info-value"><?php echo htmlspecialchars($studentInfo['lrn'] ?? ''); ?></span>
            </div>
            <div class="student-info-item">
                <span class="info-label">Name:</span>
                <span class="info-value"><?php echo htmlspecialchars($studentInfo['name'] ?? ''); ?></span>
            </div>
        </div>
    </div>
        
    <!-- Single horizontal row for Subject, Teacher, and Month/Year -->
    <div class="filter-single-row">
<div class="filter-group">
    <label for="subjectSelect">Subject:</label>
    <select id="subjectSelect" class="form-input" onchange="updateTeacherField()">
        <option value="">Select Subject</option>
        <?php foreach ($subjectsWithTeachers as $subject): ?>
            <option 
                value="<?php echo htmlspecialchars($subject['subject_id']); ?>" 
                data-teacher-id="<?php echo htmlspecialchars($subject['teacher_id']); ?>"
                data-teacher-name="<?php echo htmlspecialchars($subject['teacher_name']); ?>"
                data-subject-name="<?php echo htmlspecialchars($subject['subject']); ?>"
            >
                <?php echo htmlspecialchars($subject['subject']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
            
            <!-- Teacher -->
            <div class="filter-group">
                <label for="teacherInput">Teacher:</label>
                <input type="text" id="teacherInput" class="form-input" readonly>
                <input type="hidden" id="teacherId" value="">
            </div>
            
            <!-- Month & Year - Always visible -->
            <div class="filter-group">
                <label for="subPeriodInput">Month & Year:</label>
                <input type="month" id="subPeriodInput" class="form-input">                    
            </div>
        </div>

    <!-- Attendance List -->
    <div class="attendance-list" id="attendanceList">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time in</th>
                    <th>Remark</th>
                </tr>
            </thead>
            <tbody id="attendanceTableBody">
                <tr class="no-data-row">
                    <td colspan="3">Please select a subject and month to view attendance.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    const lrn = "<?php echo htmlspecialchars($lrn); ?>";
    const subPeriodInput = document.getElementById("subPeriodInput");
    const subjectSelect = document.getElementById("subjectSelect");
    const teacherInput = document.getElementById("teacherInput");
    const teacherIdInput = document.getElementById("teacherId");
    let attendanceData = []; // Store attendance data for export
    
    function updateTeacherField() {
        const selectedOption = subjectSelect.options[subjectSelect.selectedIndex];
        if (selectedOption.value !== "select") {
            teacherInput.value = selectedOption.getAttribute('data-teacher-name');
            teacherIdInput.value = selectedOption.getAttribute('data-teacher-id');
        } else {
            teacherInput.value = "";
            teacherIdInput.value = "";
        }
        updateAttendanceTable();
    }
    
async function fetchAttendanceData(subjectId, monthYear) {
    try {
        const response = await fetch(`?lrn=${lrn}&subject=${encodeURIComponent(subjectId)}&month=${encodeURIComponent(monthYear)}&ajax=get_attendance`);
        return await response.json();
    } catch (error) {
        console.error('Error fetching attendance data:', error);
        return null;
    }
}

async function updateAttendanceTable() {
    const subjectId = subjectSelect.value;
    const monthYearValue = subPeriodInput.value;
    const tableBody = document.getElementById("attendanceTableBody");
    
    if (!subjectId || !monthYearValue) {
        tableBody.innerHTML = '<tr class="no-data-row"><td colspan="3">Please select a subject and month to view attendance.</td></tr>';
        return;
    }
    
    const data = await fetchAttendanceData(subjectId, monthYearValue);
    
    if (data && data.length > 0) {
        // Store data for export
        attendanceData = data;
        
        let html = data.map(record => `
            <tr>
                <td>${new Date(record.date).toLocaleDateString()}</td>
                <td>${record.time}</td>
                <td class="${record.remark.toLowerCase()}">${record.remark}</td>
            </tr>
        `).join('');
        
        // Add export button row
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
            
        tableBody.innerHTML = html;
    } else {
        tableBody.innerHTML = '<tr class="no-data-row"><td colspan="3">No attendance records found.</td></tr>';
        attendanceData = []; // Clear stored data
    }
}

function exportToCSV() {
    if (attendanceData.length === 0) {
        alert('No data to export');
        return;
    }
    
    const selectedOption = subjectSelect.options[subjectSelect.selectedIndex];
    const subjectName = selectedOption.getAttribute('data-subject-name') || selectedOption.text;
    const monthYearValue = subPeriodInput.value;
    const teacher = teacherInput.value;
    const studentName = "<?php echo htmlspecialchars($studentInfo['name'] ?? ''); ?>";
    const studentLRN = "<?php echo htmlspecialchars($studentInfo['lrn'] ?? ''); ?>";
    
    // Format month and year for display
    const [year, monthNum] = monthYearValue.split("-");
    const monthNames = ["January", "February", "March", "April", "May", "June",
                      "July", "August", "September", "October", "November", "December"];
    const subPeriodText = `${monthNames[parseInt(monthNum, 10) - 1]} ${year}`;
    
    // Create CSV content
    let csvContent = "Monthly Attendance Report\r\n";
    csvContent += `Student: ${studentName}\r\n`;
    csvContent += `LRN: ${studentLRN}\r\n`;
    csvContent += `Subject: ${subjectName}\r\n`;
    csvContent += `Teacher: ${teacher}\r\n`;
    csvContent += `Period: ${subPeriodText}\r\n\r\n`;
    
    csvContent += "Date,Time In,Remark\r\n";
    
    // Add data rows
    attendanceData.forEach(row => {
        csvContent += `"${new Date(row.date).toLocaleDateString()}","${row.time}","${row.remark}"\r\n`;
    });
    
    // Create download link
    const encodedUri = encodeURI("data:text/csv;charset=utf-8," + csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `monthly_attendance_${subjectName.replace(/\s+/g, '_')}_${subPeriodText.replace(' ', '_')}.csv`);
    document.body.appendChild(link);
    
    // Trigger download
    link.click();
    document.body.removeChild(link);
}

function updateTeacherField() {
    const selectedOption = subjectSelect.options[subjectSelect.selectedIndex];
    if (selectedOption.value !== "") {
        teacherInput.value = selectedOption.getAttribute('data-teacher-name');
        teacherIdInput.value = selectedOption.getAttribute('data-teacher-id');
    } else {
        teacherInput.value = "";
        teacherIdInput.value = "";
    }
    updateAttendanceTable();
}

// Initialize date picker with current month and add event listeners
document.addEventListener("DOMContentLoaded", function () {
    const today = new Date();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const year = today.getFullYear();
    subPeriodInput.value = `${year}-${month}`;
    
    // Add event listeners
    subPeriodInput.addEventListener("change", updateAttendanceTable);
    subjectSelect.addEventListener("change", updateAttendanceTable);
    
    // Trigger initial load if a subject is already selected
    if (subjectSelect.value) {
        updateAttendanceTable();
    }
});

    function capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
</script>

<!-- Ionicons -->
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>