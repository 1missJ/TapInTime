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

// Get grade level for the section
$gradeLevel = '';
$gradeQuery = "SELECT grade_level FROM students WHERE section = ? LIMIT 1";
$gradeStmt = $conn->prepare($gradeQuery);
$gradeStmt->bind_param('s', $section);
$gradeStmt->execute();
$gradeResult = $gradeStmt->get_result();
if ($gradeRow = $gradeResult->fetch_assoc()) {
    $gradeLevel = $gradeRow['grade_level'];
}

// Fetch subjects that students are enrolled in FOR THIS SPECIFIC SECTION
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
    $period = isset($_GET['period']) ? $_GET['period'] : '';
    $date = isset($_GET['date']) ? $_GET['date'] : '';
    
    $attendanceData = [];
    
    if (!empty($section) && !empty($subject) && !empty($teacherId)) {
        if ($period === 'daily' && !empty($date)) {
            // Daily attendance query
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
        } elseif ($period === 'summary') {
            // Get the grade level for the section if not already set
            if (empty($gradeLevel)) {
                $gradeQuery = "SELECT grade_level FROM students WHERE section = ? LIMIT 1";
                $gradeStmt = $conn->prepare($gradeQuery);
                $gradeStmt->bind_param('s', $section);
                $gradeStmt->execute();
                $gradeResult = $gradeStmt->get_result();
                if ($gradeRow = $gradeResult->fetch_assoc()) {
                    $gradeLevel = $gradeRow['grade_level'];
                }
            }
            
            // Redirect to summary page with all required parameters
            header("Location: adviser_summary_section_attendance.php?section=" . urlencode($section) . 
                   "&subject=" . urlencode($subject) . 
                   "&teacher_id=" . urlencode($teacherId) . 
                   "&grade=" . urlencode($gradeLevel));
            exit();
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
    <h2>Section Attendance: <?php echo htmlspecialchars($section); ?></h2>

    <div class="filter-single-row">
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
        
        <!-- Period Selector -->
        <div class="filter-group">
            <label for="periodSelect">Period:</label>
            <select id="periodSelect" onchange="toggleDateInput()">
                <option value="daily">Daily</option>
                <option value="summary">Summary</option>
            </select>
        </div>
        
        <!-- Date Input (only shown for Daily period) -->
        <div class="filter-group date-input-container" id="dateInputContainer">
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
                    <td colspan="3">Please select subject and period to view attendance.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Store the subject-teacher mapping from PHP to JavaScript
    const subjectsWithTeachers = <?php echo json_encode($subjectsWithTeachers); ?>;
    let attendanceData = []; // Store attendance data for export

    function toggleDateInput() {
        const periodSelect = document.getElementById("periodSelect");
        const dateInputContainer = document.getElementById("dateInputContainer");
        
        if (periodSelect.value === 'daily') {
            dateInputContainer.style.display = 'block';
            // Load attendance when switching to daily mode if date is already selected
            if (document.getElementById("datePicker").value) {
                loadAttendance();
            }
        } else if (periodSelect.value === 'summary') {
            // Redirect to summary page
            const subject = document.getElementById("subjectSelect").value;
            const teacherId = document.getElementById("teacherId").value;
            
            if (subject && teacherId) {
                window.location.href = 'adviser_summary_section_attendance.php?section=<?php echo urlencode($section); ?>&subject=' + 
                                      encodeURIComponent(subject) + '&teacher_id=' + encodeURIComponent(teacherId) + 
                                      '&grade=<?php echo urlencode($gradeLevel); ?>';
            } else {
                alert('Please select a subject first');
                periodSelect.value = 'daily';
                dateInputContainer.style.display = 'block';
            }
        }
    }

    function loadTeacherForSubject() {
        const subjectSelect = document.getElementById("subjectSelect");
        const selectedOption = subjectSelect.options[subjectSelect.selectedIndex];
        const teacherInput = document.getElementById("teacherInput");
        const teacherIdInput = document.getElementById("teacherId");
        
        if (selectedOption.value) {
            teacherInput.value = selectedOption.getAttribute('data-teacher-name');
            teacherIdInput.value = selectedOption.getAttribute('data-teacher-id');
            
            // Automatically load attendance after selecting subject
            loadAttendance();
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
                            <td colspan="3">Please select subject and period to view attendance.</td>
                        </tr>
                    </tbody>
                </table>`;
        }
    }

    function loadAttendance() {
        const period = document.getElementById("periodSelect").value;
        const subject = document.getElementById("subjectSelect").value;
        const teacherId = document.getElementById("teacherId").value;
        const date = document.getElementById("datePicker").value;

        // Don't load if required fields are missing
        if (!subject || !teacherId) {
            return;
        }

        // If summary is selected, redirect to summary page
        if (period === 'summary') {
            window.location.href = 'adviser_summary_section_attendance.php?section=<?php echo urlencode($section); ?>&subject=' + 
                                  encodeURIComponent(subject) + '&teacher_id=' + encodeURIComponent(teacherId) + 
                                  '&grade=<?php echo urlencode($gradeLevel); ?>';
            return;
        }

        if (period === 'daily' && !date) {
            // Don't load daily attendance if no date is selected
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
                            <td colspan="3">Please select a date for daily attendance.</td>
                        </tr>
                    </tbody>
                </table>`;
            return;
        }

        // Build query parameters
        let params = new URLSearchParams();
        params.append('section', '<?php echo urlencode($section); ?>');
        params.append('subject', subject);
        params.append('teacher_id', teacherId);
        params.append('period', period);
        if (period === 'daily') {
            params.append('date', date);
        }
        params.append('ajax', 'get_attendance');

        // AJAX request to fetch attendance data
        fetch(`?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                let html = '';
                attendanceData = data; // Store data for export

                if (period === 'daily') {
                    html = `
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
                        data.forEach(entry => {
                            const remarkClass = entry.remark.toLowerCase();
                            html += `
                                <tr>
                                    <td>${entry.student_name}</td>
                                    <td>${entry.time}</td>
                                    <td class="${remarkClass}">${entry.remark}</td>
                                </tr>`;
                        });

                        // Add export button ONLY when there is data
                        html += `
                            <tr>
                                <td colspan="2"></td>
                                <td style="text-align: right; padding: 10px;">
                                    <button class="export-btn" onclick="exportToCSV('daily')">
                                        <ion-icon name="download-outline"></ion-icon>
                                    </button>
                                </td>
                            </tr>
                        `;
                    } else {
                        html += `<tr><td colspan="3" class="no-data-row">No attendance records found.</td></tr>`;
                    }
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
                attendanceData = [];
            });
    }
    
    function exportToCSV(period) {
        if (attendanceData.length === 0) {
            alert('No data to export');
            return;
        }
        
        const subject = document.getElementById("subjectSelect").value;
        const teacher = document.getElementById("teacherInput").value;
        const section = "<?php echo $section; ?>";
        
        let csvContent = '';
        let filename = '';
        
        if (period === 'daily') {
            const date = document.getElementById("datePicker").value;
            csvContent = "Daily Attendance Report\r\n";
            csvContent += `Section: ${section}\r\n`;
            csvContent += `Subject: ${subject}\r\n`;
            csvContent += `Teacher: ${teacher}\r\n`;
            csvContent += `Date: ${date}\r\n\r\n`;
            csvContent += "Student Name,Time In,Remark\r\n";
            
            attendanceData.forEach(row => {
                csvContent += `"${row.student_name}","${row.time}","${row.remark}"\r\n`;
            });
            
            filename = `daily_attendance_${section}_${subject}_${date}.csv`;
        }
        
        // Create download link
        const encodedUri = encodeURI("data:text/csv;charset=utf-8," + csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", filename);
        document.body.appendChild(link);
        
        // Trigger download
        link.click();
        document.body.removeChild(link);
    }

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('datePicker').value = today;
        toggleDateInput(); // Set initial state
        
        // Add event listeners for automatic loading
        document.getElementById('periodSelect').addEventListener('change', function() {
            if (this.value === 'daily') {
                toggleDateInput();
                // Load attendance immediately when period changes to daily (if conditions are met)
                const subject = document.getElementById("subjectSelect").value;
                const teacherId = document.getElementById("teacherId").value;
                if (subject && teacherId) {
                    loadAttendance();
                }
            }
        });
        
        document.getElementById('datePicker').addEventListener('change', loadAttendance);
    });
</script>

<!-- Ionicons -->
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>