<?php
include('db_connection.php');

// Check if this is an AJAX request for attendance data
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_attendance') {
    header('Content-Type: application/json');
    
    $subject = isset($_GET['subject']) ? $_GET['subject'] : '';
    $period = isset($_GET['period']) ? $_GET['period'] : '';
    $sectionName = isset($_GET['section_name']) ? $_GET['section_name'] : '';
    $monthYear = isset($_GET['monthYear']) ? $_GET['monthYear'] : '';
    
    if (empty($subject) || empty($period) || empty($sectionName)) {
        echo json_encode([]);
        exit;
    }
    
    $data = [];
    
    try {
        if ($period === 'monthly') {
            // Get data for all months - Exclude Saturdays (7) and Sundays (1)
            $query = "SELECT 
                        DATE_FORMAT(a.attendance_date, '%M %Y') as label,
                        YEAR(a.attendance_date) as year,
                        MONTH(a.attendance_date) as month,
                        COUNT(DISTINCT a.student_id) as total_students,
                        SUM(CASE WHEN a.status = 'Present' AND DAYOFWEEK(a.attendance_date) NOT IN (1, 7) THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN a.status = 'Absent' AND DAYOFWEEK(a.attendance_date) NOT IN (1, 7) THEN 1 ELSE 0 END) as absent,
                        f.name as teacher_name
                      FROM attendance a
                      JOIN students s ON a.student_id = s.id
                      JOIN subjects sub ON a.subject_id = sub.id
                      JOIN faculty f ON a.teacher_id = f.id
                      WHERE s.section = ?
                      AND sub.subject_name = ?
                      GROUP BY YEAR(a.attendance_date), MONTH(a.attendance_date), f.name
                      ORDER BY YEAR(a.attendance_date), MONTH(a.attendance_date)";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ss', $sectionName, $subject);
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
            } else {
                $data[] = [
                    'label' => 'No attendance records found',
                    'present' => 0,
                    'absent' => 0,
                    'teacher_name' => 'No records',
                    'total_students' => 0
                ];
            }
            
            echo json_encode($data);

        } elseif ($period === 'weekly' && !empty($monthYear)) {
            // Get weekly data for a specific month
            list($month, $year) = explode(' ', $monthYear);
            
            // Convert month name to number
            $monthNum = date('m', strtotime($month));
            $yearNum = intval($year);
            
            // Get the first and last day of the month
            $firstDayOfMonth = date('Y-m-01', strtotime("$year-$monthNum-01"));
            $lastDayOfMonth = date('Y-m-t', strtotime("$year-$monthNum-01"));
            
            // Find the first day of the first week that has days in this month
            // This could be in the previous month
            $firstDayOfFirstWeek = date('Y-m-d', strtotime("last monday", strtotime($firstDayOfMonth . " +1 week")));
            if (strtotime($firstDayOfFirstWeek) > strtotime($firstDayOfMonth)) {
                $firstDayOfFirstWeek = date('Y-m-d', strtotime("last monday", strtotime($firstDayOfMonth)));
            }
            
            // Initialize weeks array
            $weeklyData = [];
            $currentWeek = 1;
            $currentDate = $firstDayOfFirstWeek;
            
            // Process each week (allow up to 6 weeks to handle months that span 6 weeks)
            while ($currentWeek <= 6) {
                // Calculate end date for this week (Friday)
                $endDate = date('Y-m-d', strtotime($currentDate . " +4 days"));
                
                // Check if this week has any days in the current month
                $weekHasDaysInMonth = false;
                $tempDate = $currentDate;
                for ($i = 0; $i < 5; $i++) {
                    if (date('m', strtotime($tempDate)) == $monthNum) {
                        $weekHasDaysInMonth = true;
                        break;
                    }
                    $tempDate = date('Y-m-d', strtotime($tempDate . " +1 day"));
                }
                
                // Only process weeks that have at least one day in the current month
                if (!$weekHasDaysInMonth) {
                    // Move to next week and continue
                    $currentDate = date('Y-m-d', strtotime($currentDate . " +7 days"));
                    $currentWeek++;
                    continue;
                }
                
                // Adjust start date to the first day of the week that's in this month
                $adjustedStartDate = $currentDate;
                if (date('m', strtotime($adjustedStartDate)) != $monthNum) {
                    $adjustedStartDate = $firstDayOfMonth;
                }
                
                // Adjust end date to the last day of the week that's in this month
                $adjustedEndDate = $endDate;
                if (date('m', strtotime($adjustedEndDate)) != $monthNum) {
                    $adjustedEndDate = $lastDayOfMonth;
                }
                
                // Make sure we don't go beyond the month
                if (strtotime($adjustedStartDate) > strtotime($lastDayOfMonth)) {
                    break;
                }
                
                // Format dates for display
                $weekStartFormatted = date('M d', strtotime($adjustedStartDate));
                $weekEndFormatted = date('M d', strtotime($adjustedEndDate));
                
                // Get attendance data for this week (only Monday to Friday) - Exclude Saturdays and Sundays
                $query = "SELECT 
                            COUNT(DISTINCT a.student_id) as total_students,
                            SUM(CASE WHEN a.status = 'Present' AND DAYOFWEEK(a.attendance_date) BETWEEN 2 AND 6 THEN 1 ELSE 0 END) as present,
                            SUM(CASE WHEN a.status = 'Absent' AND DAYOFWEEK(a.attendance_date) BETWEEN 2 AND 6 THEN 1 ELSE 0 END) as absent
                          FROM attendance a
                          JOIN students s ON a.student_id = s.id
                          JOIN subjects sub ON a.subject_id = sub.id
                          WHERE s.section = ?
                          AND sub.subject_name = ?
                          AND a.attendance_date BETWEEN ? AND ?
                          AND DAYOFWEEK(a.attendance_date) NOT IN (1, 7)"; // Exclude Sundays (1) and Saturdays (7)
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param('ssss', $sectionName, $subject, $adjustedStartDate, $adjustedEndDate);
                $stmt->execute();
                $result = $stmt->get_result();
                $weekAttendance = $result->fetch_assoc();
                
                $weeklyData[] = [
                    'week' => $currentWeek,
                    'label' => "Week $currentWeek ($weekStartFormatted - $weekEndFormatted)",
                    'present' => $weekAttendance['present'] ?? 0,
                    'absent' => $weekAttendance['absent'] ?? 0,
                    'total_students' => $weekAttendance['total_students'] ?? 0
                ];
                
                // Move to next Monday
                $currentDate = date('Y-m-d', strtotime($currentDate . " +7 days"));
                $currentWeek++;
                
                // Break if we've passed the month
                if (strtotime($currentDate) > strtotime($lastDayOfMonth)) {
                    break;
                }
            }
            
            echo json_encode($weeklyData);
            exit;
        }

    } catch (Exception $e) {
        error_log("Error fetching attendance data: " . $e->getMessage());
        echo json_encode([]);
    }
    exit;
}

// Handle period selection redirection
if (isset($_GET['period'])) {
    $period = $_GET['period'];
    $section = $_GET['section'];
    $grade = $_GET['grade'];
    
    if ($period === 'daily') {
        header("Location: adviser_section_attendance.php?section=$section&grade=$grade");
        exit();
    } elseif ($period === 'summary') {
        // We're already on the summary page, no need to redirect
        if (basename($_SERVER['PHP_SELF']) !== 'adviser_summary_section_attendance.php') {
            header("Location: adviser_summary_section_attendance.php?section=$section&grade=$grade");
            exit();
        }
    }
}

// Regular page rendering
$sectionName = isset($_GET['section']) ? $_GET['section'] : '';
$gradeLevel = isset($_GET['grade']) ? $_GET['grade'] : '';

if (empty($sectionName) || empty($gradeLevel)) {
    die("Section name and grade level parameters are required");
}

// Get section info
$sectionInfo = [];
$query = "SELECT section as section_name, grade_level FROM students WHERE section = ? AND grade_level = ? GROUP BY section, grade_level LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $sectionName, $gradeLevel);
$stmt->execute();
$result = $stmt->get_result();
$sectionInfo = $result->fetch_assoc();

if (!$sectionInfo) {
    die("Section not found");
}

// Fetch subjects with teachers for THIS SPECIFIC SECTION from student_enrollments
$subjectsWithTeachers = [];
$query = "SELECT DISTINCT
            s.subject_name as subject,
            f.name as teacher_name
          FROM student_enrollments se
          JOIN students st ON se.student_lrn = st.lrn
          JOIN subjects s ON se.subject_id = s.id
          JOIN faculty f ON se.teacher_id = f.id
          WHERE st.section = ? AND st.grade_level = ?
          ORDER BY s.subject_name";
$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $sectionName, $gradeLevel);
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
    <title>Section Attendance - <?php echo htmlspecialchars($sectionInfo['section_name']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/attendance.css">
</head>
<body>
<?php include('adviser_sidebar.php'); ?>

<div class="main-content">
    <div class="header-row">
    <h2>Section Attendance: <?php echo htmlspecialchars($sectionInfo['section_name']); ?></h2>
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
        
        <!-- Period Selector -->
        <div class="filter-group">
            <label for="period">Period:</label>
            <select id="period" name="period" onchange="redirectToPeriod()">
                <option value="daily" <?php echo (basename($_SERVER['PHP_SELF']) === 'adviser_section_attendance.php') ? 'selected' : ''; ?>>Daily</option>
                <option value="summary" <?php echo (basename($_SERVER['PHP_SELF']) === 'adviser_summary_section_attendance.php') ? 'selected' : ''; ?>>Summary</option>
            </select>
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
    const sectionName = "<?php echo htmlspecialchars($sectionName); ?>";
    const gradeLevel = "<?php echo htmlspecialchars($gradeLevel); ?>";
    let attendanceData = []; // Store attendance data for export
    let weeklyData = {}; // Store weekly data by month for export
    
    function redirectToPeriod() {
        const periodSelect = document.getElementById("period");
        const selectedPeriod = periodSelect.value;
        const section = "<?php echo htmlspecialchars($sectionName); ?>";
        const grade = "<?php echo htmlspecialchars($gradeLevel); ?>";
        
        if (selectedPeriod === 'daily') {
            window.location.href = `adviser_section_attendance.php?section=${section}&grade=${grade}`;
        } else if (selectedPeriod === 'summary') {
            window.location.href = `adviser_summary_section_attendance.php?section=${section}&grade=${grade}`;
        }
    }
    
    function updateTeacherField() {
        const subjectSelect = document.getElementById("subjectSelect");
        const teacherInput = document.getElementById("teacherInput");
        const selectedOption = subjectSelect.options[subjectSelect.selectedIndex];
        
        if (selectedOption.value !== "select") {
            teacherInput.value = selectedOption.getAttribute('data-teacher-name');
            loadAttendance(); // This line calls the function to load attendance data
        } else {
            teacherInput.value = "";
            // Clear the table when no subject is selected
            const tableBody = document.querySelector("#attendanceTable tbody");
            tableBody.innerHTML = `
                <tr class="no-data">
                    <td colspan="3">Please select a subject to view attendance.</td>
                </tr>
            `;
        }
    }

    async function loadAttendance() {
        const subject = document.getElementById("subjectSelect").value;
        const tableBody = document.querySelector("#attendanceTable tbody");

        if (subject === 'select') {
            tableBody.innerHTML = `
                <tr class="no-data">
                    <td colspan="3">Please select a subject.</td>
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
            // First get monthly data
            const monthlyParams = new URLSearchParams();
            monthlyParams.append('ajax', 'get_attendance');
            monthlyParams.append('subject', subject);
            monthlyParams.append('period', 'monthly');
            monthlyParams.append('section_name', sectionName);
            
            const monthlyResponse = await fetch(`?${monthlyParams.toString()}`);
            if (!monthlyResponse.ok) throw new Error('Network error');
            const monthlyData = await monthlyResponse.json();
            
            // Store data for export
            attendanceData = monthlyData;
            weeklyData = {}; // Reset weekly data
            
            if (!monthlyData || monthlyData.length === 0) {
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
            
            // Render the table with monthly data
            let html = '';
            
            for (const month of monthlyData) {
                if (month.label === 'No attendance records found') {
                    html += `
                        <tr class="month-row">
                            <td>${month.label}</td>
                            <td class="present">${month.present}</td>
                            <td class="absent">${month.absent}</td>
                        </tr>
                    `;
                    continue;
                }
                
                totalPresent += parseInt(month.present) || 0;
                totalAbsent += parseInt(month.absent) || 0;
                
                // Get weekly data for this month
                const weeklyParams = new URLSearchParams();
                weeklyParams.append('ajax', 'get_attendance');
                weeklyParams.append('subject', subject);
                weeklyParams.append('period', 'weekly');
                weeklyParams.append('section_name', sectionName);
                weeklyParams.append('monthYear', month.label);
                
                const weeklyResponse = await fetch(`?${weeklyParams.toString()}`);
                if (!weeklyResponse.ok) throw new Error('Network error');
                const weekData = await weeklyResponse.json();
                
                // Store weekly data for export
                weeklyData[month.label] = weekData;
                
                // Add month row
                html += `
                    <tr class="month-row" data-month="${month.label}">
                        <td>${month.label}</td>
                        <td class="present">${month.present}</td>
                        <td class="absent">${month.absent}</td>
                    </tr>
                `;
                
                // Add weekly rows
                if (weekData && weekData.length > 0) {
                    weekData.forEach(week => {
                        html += `
                            <tr class="week-row" data-month="${month.label}">
                                <td>${week.label}</td>
                                <td class="present">${week.present}</td>
                                <td class="absent">${week.absent}</td>
                            </tr>
                        `;
                    });
                }
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
                            <ion-icon name="download-outline"></ion-icon>
                        </button>
                    </td>
                </tr>
            `;

            tableBody.innerHTML = html;
            
            // Add event listeners to month rows
            const monthRows = document.querySelectorAll('.month-row');
            monthRows.forEach(row => {
                row.addEventListener('dblclick', function() {
                    const month = this.getAttribute('data-month');
                    const weekRows = document.querySelectorAll(`.week-row[data-month="${month}"]`);
                    
                    // Toggle expanded class on month row
                    this.classList.toggle('expanded');
                    
                    // Toggle visibility of week rows
                    weekRows.forEach(weekRow => {
                        weekRow.classList.toggle('visible');
                    });
                });
            });
            
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
        const teacher = document.getElementById("teacherInput").value;
        const section = "<?php echo htmlspecialchars($sectionInfo['section_name']); ?>";
        const grade = "<?php echo htmlspecialchars($sectionInfo['grade_level']); ?>";
        
        // Create CSV content
        let csvContent = "Section Attendance Report\r\n";
        csvContent += `Section: ${section}\r\n`;
        csvContent += `Grade Level: ${grade}\r\n`;
        csvContent += `Subject: ${subject}\r\n`;
        csvContent += `Teacher: ${teacher}\r\n\r\n`;
        
        csvContent += "Period,Present,Absent,Total Students\r\n";
        
        // Add data rows - only include weekly data if the month is expanded
        attendanceData.forEach(month => {
            if (month.label !== 'No attendance records found') {
                // Add monthly data
                csvContent += `"${month.label}",${month.present},${month.absent},${month.total_students}\r\n`;
                
                // Check if this month is expanded (weekly data visible)
                const monthRow = document.querySelector(`.month-row[data-month="${month.label}"]`);
                if (monthRow && monthRow.classList.contains('expanded') && weeklyData[month.label]) {
                    // Add weekly data for this month
                    weeklyData[month.label].forEach(week => {
                        csvContent += `"  ${week.label}",${week.present},${week.absent},${week.total_students}\r\n`;
                    });
                }
            }
        });
        
        // Calculate totals
        const totalPresent = attendanceData.reduce((sum, row) => sum + (parseInt(row.present) || 0), 0);
        const totalAbsent = attendanceData.reduce((sum, row) => sum + (parseInt(row.absent) || 0), 0);
        
        csvContent += `"Total",${totalPresent},${totalAbsent},\r\n`;
        
        // Create download link
        const encodedUri = encodeURI("data:text/csv;charset=utf-8," + csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `section_attendance_${section}_${subject}.csv`);
        document.body.appendChild(link);
        
        // Trigger download
        link.click();
        document.body.removeChild(link);
    }

    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        // Set up event listener for subject selection
        document.getElementById('subjectSelect').addEventListener('change', updateTeacherField);
        
        // Set the correct period selection based on current page
        const currentPage = "<?php echo basename($_SERVER['PHP_SELF']); ?>";
        const periodSelect = document.getElementById('period');
        
        if (currentPage === 'adviser_section_attendance.php') {
            periodSelect.value = 'daily';
        } else if (currentPage === 'adviser_summary_section_attendance.php') {
            periodSelect.value = 'summary';
        }
    });
</script>

<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>