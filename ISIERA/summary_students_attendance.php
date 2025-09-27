<?php
include('db_connection.php');

// Check if this is an AJAX request for attendance data
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_attendance') {
    header('Content-Type: application/json');
    
    $subject = isset($_GET['subject']) ? $_GET['subject'] : '';
    $period = isset($_GET['period']) ? $_GET['period'] : '';
    $lrn = isset($_GET['lrn']) ? $_GET['lrn'] : '';
    $monthYear = isset($_GET['monthYear']) ? $_GET['monthYear'] : '';
    
    if (empty($subject) || empty($period) || empty($lrn)) {
        echo json_encode([]);
        exit;
    }
    
    $data = [];
    
    try {
        if ($period === 'monthly') {
            // Monthly view - count by month
            $query = "SELECT 
                        DATE_FORMAT(a.attendance_date, '%M %Y') as label,
                        YEAR(a.attendance_date) as year,
                        MONTH(a.attendance_date) as month,
                        COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present,
                        COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent,
                        f.name as teacher_name
                      FROM attendance a
                      JOIN students s ON a.student_id = s.id
                      JOIN subjects sub ON a.subject_id = sub.id
                      JOIN faculty f ON a.teacher_id = f.id
                      WHERE s.lrn = ? 
                      AND sub.subject_name = ?
                      GROUP BY YEAR(a.attendance_date), MONTH(a.attendance_date), f.name
                      ORDER BY YEAR(a.attendance_date), MONTH(a.attendance_date)";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ss', $lrn, $subject);
            
        } elseif ($period === 'weekly') {
            // Weekly view - count by week according to division calendar (Sunday to Saturday)
           // Weekly view - count by week according to division calendar (Sunday to Saturday)
if (!empty($monthYear)) {
    // Convert YYYY-MM format to year and month
    $date = DateTime::createFromFormat('Y-m', $monthYear);
    $year = $date->format('Y');
    $monthNum = $date->format('m');
    
    // Get the first and last day of the month
    $firstDayOfMonth = date('Y-m-01', strtotime($monthYear . '-01'));
    $lastDayOfMonth = date('Y-m-t', strtotime($monthYear . '-01'));
    
    // Create weeks according to division calendar (Sunday to Saturday)
    $weeks = [];
    $currentDate = $firstDayOfMonth;
    
    // Find the Sunday of the week that contains the first day of the month
    $weekStart = date('Y-m-d', strtotime('last sunday', strtotime($currentDate)));
    if (strtotime($weekStart) < strtotime($firstDayOfMonth)) {
        $weekStart = $firstDayOfMonth;
    }
    
    while ($weekStart <= $lastDayOfMonth) {
        $weekEnd = date('Y-m-d', strtotime('saturday', strtotime($weekStart)));
        
        // Ensure week doesn't extend beyond the month
        if ($weekEnd > $lastDayOfMonth) {
            $weekEnd = $lastDayOfMonth;
        }
        
        $weekNumber = count($weeks) + 1;
        $weekLabel = "Week $weekNumber (" . date('M j', strtotime($weekStart)) . " - " . 
                    date('M j', strtotime($weekEnd)) . ")";
        
        $weeks[] = [
            'number' => $weekNumber,
            'start' => $weekStart,
            'end' => $weekEnd,
            'label' => $weekLabel
        ];
        
        // Move to next week
        $weekStart = date('Y-m-d', strtotime($weekEnd . ' +1 day'));
    }
    
    // Query for each week
    foreach ($weeks as $week) {
        $query = "SELECT 
                    COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present,
                    COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent,
                    f.name as teacher_name
                  FROM attendance a
                  JOIN students s ON a.student_id = s.id
                  JOIN subjects sub ON a.subject_id = sub.id
                  JOIN faculty f ON a.teacher_id = f.id
                  WHERE s.lrn = ? 
                  AND sub.subject_name = ?
                  AND a.attendance_date BETWEEN ? AND ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssss', $lrn, $subject, $week['start'], $week['end']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $data[] = [
                'label' => $week['label'],
                'present' => $row['present'],
                'absent' => $row['absent'],
                'teacher_name' => $row['teacher_name']
            ];
        } else {
            $data[] = [
                'label' => $week['label'],
                'present' => 0,
                'absent' => 0,
                'teacher_name' => 'No records'
            ];
        }
    }
    
    // We've manually built the data, so we can skip the rest
    echo json_encode($data);
    exit;

            } else {
                // All weekly data (not filtered by month)
                $query = "SELECT 
                            CONCAT('Week ', WEEK(a.attendance_date, 1), ' (', 
                                   DATE_FORMAT(DATE_SUB(a.attendance_date, INTERVAL WEEKDAY(a.attendance_date) DAY), '%M %d'), ' - ',
                                   DATE_FORMAT(DATE_ADD(a.attendance_date, INTERVAL (6 - WEEKDAY(a.attendance_date)) DAY), '%M %d'), ')') as label,
                            YEAR(a.attendance_date) as year,
                            MONTH(a.attendance_date) as month,
                            WEEK(a.attendance_date, 1) as week_number,
                            COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present,
                            COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent,
                            f.name as teacher_name
                          FROM attendance a
                          JOIN students s ON a.student_id = s.id
                          JOIN subjects sub ON a.subject_id = sub.id
                          JOIN faculty f ON a.teacher_id = f.id
                          WHERE s.lrn = ? 
                          AND sub.subject_name = ?
                          GROUP BY YEAR(a.attendance_date), WEEK(a.attendance_date, 1), f.name
                          ORDER BY YEAR(a.attendance_date), WEEK(a.attendance_date, 1)";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param('ss', $lrn, $subject);
            }
        }
        
        // Execute the query for non-weekly-monthly cases
        if ($period !== 'weekly' || empty($monthYear)) {
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    // For weekly view with specific month, format the label better
                    if ($period === 'weekly' && !empty($monthYear) && isset($row['week_start']) && isset($row['week_end'])) {
                        $row['label'] = "Week " . $row['week_number'] . " (" . 
                                       date('M d', strtotime($row['week_start'])) . " - " . 
                                       date('M d', strtotime($row['week_end'])) . ")";
                    }
                    $data[] = $row;
                }
            } else {
                $data[] = [
                    'label' => 'No attendance records found',
                    'present' => 0,
                    'absent' => 0,
                    'teacher_name' => 'No records'
                ];
            }
        }

        echo json_encode($data);
    } catch (Exception $e) {
        error_log("Error fetching attendance data: " . $e->getMessage());
        echo json_encode([]);
    }
    exit;
}

// Regular page rendering
$lrn = isset($_GET['lrn']) ? $_GET['lrn'] : '';
$section = isset($_GET['section']) ? $_GET['section'] : '';

if (empty($lrn)) {
    die("LRN parameter is required");
}

// Get student info including section_id
$studentInfo = [];
$query = "SELECT s.lrn, CONCAT(s.first_name, ' ', s.last_name) as name, s.section
          FROM students s
          WHERE s.lrn = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $lrn);
$stmt->execute();
$result = $stmt->get_result();
$studentInfo = $result->fetch_assoc();

if (!$studentInfo) {
    die("Student not found");
}

// Fetch subjects with teachers for THIS SPECIFIC STUDENT from student_enrollments
$subjectsWithTeachers = [];
$query = "SELECT 
            s.id as subject_id,
            s.subject_name as subject,
            f.id as teacher_id,
            f.name as teacher_name
          FROM student_enrollments se
          JOIN subjects s ON se.subject_id = s.id
          JOIN faculty f ON se.teacher_id = f.id
          WHERE se.student_lrn = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $lrn);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $subjectsWithTeachers[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Attendance - <?php echo htmlspecialchars($studentInfo['name']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/attendance.css">
    <style>
        .present { color: green; font-weight: bold; }
        .absent { color: red; font-weight: bold; }
        .totals-row { background-color: #f0f0f0; font-weight: bold; }
        .no-data { text-align: center; color: #666; }
        .month-year-selector { display: none; }
        .filter-group { margin-right: 15px; }
        .export-btn { background: #4CAF50; color: white; border: none; padding: 5px 10px; cursor: pointer; }
    </style>
</head>
<body>
<?php include('sidebar.php'); ?>

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
        
    <div class="filter-single-row">
        <!-- Subject -->
        <div class="filter-group">
            <label for="subjectSelect">Subject:</label>
            <select id="subjectSelect" class="form-input" onchange="updateTeacherField()">
                <option value="select">Select Subject</option>
<?php foreach ($subjectsWithTeachers as $subject): ?>
    <option 
        value="<?php echo htmlspecialchars($subject['subject']); ?>" 
        data-teacher-name="<?php echo htmlspecialchars($subject['teacher_name']); ?>"
    >
        <?php echo htmlspecialchars($subject['subject']); ?>
    </option>
<?php endforeach; ?>
            </select>
        </div>
        
        <!-- Teacher Display -->
        <div class="filter-group">
            <label for="teacherInput">Teacher:</label>
            <input type="text" id="teacherInput" class="form-input" readonly>
        </div>

        <div class="filter-group">
            <label for="periodSelect">Period:</label>
            <select id="periodSelect" class="form-input" onchange="toggleMonthYearSelector()">
                <option value="monthly">Monthly</option>
                <option value="weekly">Weekly</option>
            </select>
        </div>
        
        <!-- Month & Year Selector - Only shows when Weekly is selected -->
        <div class="filter-group month-year-selector" id="monthYearSelector">
            <label for="monthYearInput">Month & Year:</label>
            <input type="month" id="monthYearInput" class="form-input" onchange="loadAttendance()">                  
        </div>
    </div>

    <!-- Attendance Summary Table -->
    <div id="attendanceList" class="attendance-list">
        <table id="attendanceTable">
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Present</th>
                    <th>Absent</th>
                </tr>
            </thead>
            <tbody>
                <tr class="no-data">
                    <td colspan="3">Please select a subject to view attendance.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    const lrn = "<?php echo htmlspecialchars($lrn); ?>";
    const section = "<?php echo htmlspecialchars($section); ?>";
    let attendanceData = []; // Store attendance data for export
    
    function toggleMonthYearSelector() {
        const period = document.getElementById("periodSelect").value;
        const monthYearSelector = document.getElementById("monthYearSelector");
        
        if (period === 'weekly') {
            monthYearSelector.style.display = 'flex';
        } else {
            monthYearSelector.style.display = 'none';
            // Clear the month input when switching to monthly view
            document.getElementById("monthYearInput").value = "";
        }
        
        loadAttendance();
    }
    
    function updateTeacherField() {
        const subjectSelect = document.getElementById("subjectSelect");
        const teacherInput = document.getElementById("teacherInput");
        const selectedOption = subjectSelect.options[subjectSelect.selectedIndex];
        
        if (selectedOption.value !== "select") {
            teacherInput.value = selectedOption.getAttribute('data-teacher-name');
            loadAttendance();
        } else {
            teacherInput.value = "";
        }
    }

    async function loadAttendance() {
        const subject = document.getElementById("subjectSelect").value;
        const period = document.getElementById("periodSelect").value;
        const monthYearInput = document.getElementById("monthYearInput").value;
        const tableBody = document.querySelector("#attendanceTable tbody");

        if (subject === 'select') {
            tableBody.innerHTML = `
                <tr class="no-data">
                    <td colspan="3">Please select a subject.</td>
                </tr>
            `;
            return;
        }

        // For weekly view, require a month selection
        if (period === 'weekly' && !monthYearInput) {
            tableBody.innerHTML = `
                <tr class="no-data">
                    <td colspan="3">Please select a month to view weekly attendance.</td>
                </tr>
            `;
            return;
        }

        tableBody.innerHTML = `
            <tr>
                <td colspan="3">Loading attendance data...</td>
            </tr>
        `;
        
        try {
            const params = new URLSearchParams();
            params.append('ajax', 'get_attendance');
            params.append('subject', subject);
            params.append('period', period);
            params.append('lrn', lrn);
            
            // Add monthYear parameter if it's set and we're in weekly view
            if (period === 'weekly' && monthYearInput) {
                params.append('monthYear', monthYearInput);
            }
            
            const response = await fetch(`?${params.toString()}`);
            if (!response.ok) throw new Error('Network error');
            const data = await response.json();
            
            // Store data for export
            attendanceData = data;
            
            if (!data || data.length === 0) {
                tableBody.innerHTML = `
                    <tr class="no-data">
                    <td colspan="3">No attendance records found.</td>
                    </tr>
                `;
                return;
            }

            // Calculate totals
            let totalPresent = 0;
            let totalAbsent = 0;
            
            // Render the table
            let html = '';
            
            for (const record of data) {
                if (record.label === 'No attendance records found') {
                    html += `
                        <tr class="data-row">
                            <td>${record.label}</td>
                            <td class="present">${record.present}</td>
                            <td class="absent">${record.absent}</td>
                        </tr>
                    `;
                    continue;
                }
                
                totalPresent += parseInt(record.present) || 0;
                totalAbsent += parseInt(record.absent) || 0;
                
                html += `
                    <tr class="data-row">
                        <td>${record.label}</td>
                        <td class="present">${record.present}</td>
                        <td class="absent">${record.absent}</td>
                    </tr>
                `;
            }

            // Add totals row
            html += `
                <tr class="totals-row">
                    <td><strong>Total</strong></td>
                    <td class="present"><strong>${totalPresent}</strong></td>
                    <td class="absent"><strong>${totalAbsent}</strong></td>
                </tr>
            `;
            
            // Add export button row - positioned to the right
            html += `
                <tr>
                    <td colspan="2"></td>
                    <td style="text-align: right; padding: 10px;">
                        <button class="export-btn" onclick="exportToCSV()">
                            Export CSV <ion-icon name="download-outline"></ion-icon>
                        </button>
                    </td>
                </tr>
            `;

            tableBody.innerHTML = html;
            
        } catch (error) {
            tableBody.innerHTML = `
                <tr class="no-data">
                    <td colspan="3">Error loading data. Please try again.</td>
                </tr>
            `;
            console.error(error);
        }
    }
    
    function exportToCSV() {
        if (attendanceData.length === 0) {
            alert('No data to export');
            return;
        }
        
        const subject = document.getElementById("subjectSelect").value;
        const period = document.getElementById("periodSelect").value;
        const monthYearInput = document.getElementById("monthYearInput").value;
        const teacher = document.getElementById("teacherInput").value;
        const studentName = "<?php echo htmlspecialchars($studentInfo['name'] ?? ''); ?>";
        const studentLRN = "<?php echo htmlspecialchars($studentInfo['lrn'] ?? ''); ?>";
        
        // Create CSV content
        let csvContent = "Attendance Report\r\n";
        csvContent += `Student: ${studentName}\r\n`;
        csvContent += `LRN: ${studentLRN}\r\n`;
        csvContent += `Subject: ${subject}\r\n`;
        csvContent += `Teacher: ${teacher}\r\n`;
        csvContent += `Period: ${period.charAt(0).toUpperCase() + period.slice(1)}\r\n`;
        
        if (period === 'weekly' && monthYearInput) {
            // Format the month for display (e.g., "2023-07" becomes "July 2023")
            const date = new Date(monthYearInput + '-01');
            const monthName = date.toLocaleString('default', { month: 'long' });
            const year = date.getFullYear();
            csvContent += `Month: ${monthName} ${year}\r\n`;
        }
        
        csvContent += "\r\nPeriod,Present,Absent\r\n";
        
        // Add data rows
        attendanceData.forEach(record => {
            if (record.label !== 'No attendance records found') {
                csvContent += `"${record.label}",${record.present},${record.absent}\r\n`;
            }
        });
        
        // Calculate totals
        const totalPresent = attendanceData.reduce((sum, row) => sum + (parseInt(row.present) || 0), 0);
        const totalAbsent = attendanceData.reduce((sum, row) => sum + (parseInt(row.absent) || 0), 0);
        
        csvContent += `"Total",${totalPresent},${totalAbsent}\r\n`;
        
        // Create download link
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        
        let fileName = `attendance_${period}_${subject.replace(/\s+/g, '_')}_${studentLRN}`;
        if (period === 'weekly' && monthYearInput) {
            fileName += `_${monthYearInput.replace('-', '_')}`;
        }
        fileName += '.csv';
        
        link.setAttribute("download", fileName);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        
        // Trigger download
        link.click();
        document.body.removeChild(link);
    }

    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        // Set up event listeners
        document.getElementById('subjectSelect').addEventListener('change', updateTeacherField);
        document.getElementById('periodSelect').addEventListener('change', toggleMonthYearSelector);
        
        // Initialize the month/year selector visibility
        toggleMonthYearSelector();
        
        // Set current month as default for the month picker
        const today = new Date();
        const currentMonth = today.toISOString().slice(0, 7); // YYYY-MM format
        document.getElementById("monthYearInput").value = currentMonth;
    });
</script>

<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>