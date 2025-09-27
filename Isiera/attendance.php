<?php
session_start();
include('db_connection.php');

if (!isset($_SESSION['student_id'])) {
    header("Location: index.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$filter = $_GET['filter'] ?? 'monthly';
$selectedSubject = $_GET['subject'] ?? 'all';
$selectedMonth = $_GET['month'] ?? date('Y-m');

// First, get the student's section from student_enrollments
$sectionQuery = "SELECT section_id FROM student_enrollments WHERE student_lrn = (SELECT lrn FROM students WHERE id = ?) LIMIT 1";
$stmt = $conn->prepare($sectionQuery);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$sectionResult = $stmt->get_result();
$studentSection = $sectionResult->fetch_assoc();
$section_id = $studentSection ? $studentSection['section_id'] : null;

// Fetch enrolled subjects for this student with teacher information
$subjects = [];
if ($section_id) {
    $subject_query = "SELECT DISTINCT
                        s.id as subject_id,
                        s.subject_name,
                        f.id as faculty_id,
                        f.name as faculty_name
                      FROM student_enrollments se
                      JOIN subjects s ON se.subject_id = s.id
                      JOIN teacher_subjects ts ON s.id = ts.subject_id AND ts.section_id = ?
                      JOIN faculty f ON ts.teacher_id = f.id
                      WHERE se.student_lrn = (SELECT lrn FROM students WHERE id = ?)";
    
    $stmt = $conn->prepare($subject_query);
    $stmt->bind_param("ii", $section_id, $student_id);
    $stmt->execute();
    $subject_result = $stmt->get_result();

    while ($row = $subject_result->fetch_assoc()) {
        $subjects[$row['subject_id']] = [
            'subject_name' => $row['subject_name'],
            'faculty_id' => $row['faculty_id'],
            'faculty_name' => $row['faculty_name']
        ];
    }
}

// Set filtering condition for regular view
$dateCondition = "";
if ($filter === 'monthly') {
    $firstDay = date('Y-m-01', strtotime($selectedMonth));
    $lastDay = date('Y-m-t', strtotime($selectedMonth));
    $dateCondition = "AND a.attendance_date BETWEEN '$firstDay' AND '$lastDay'";
}

// Add subject filter condition
$subjectCondition = "";
if ($selectedSubject !== 'all' && is_numeric($selectedSubject)) {
    $subjectCondition = "AND a.subject_id = ?";
}

// For summary view, get monthly data
$monthlyData = [];
$weeklyData = [];
if ($filter === 'summary') {
    // Get monthly summary data
    $summary_query = "SELECT 
                        DATE_FORMAT(a.attendance_date, '%M %Y') as month_year,
                        YEAR(a.attendance_date) as year,
                        MONTH(a.attendance_date) as month,
                        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent
                      FROM attendance a 
                      WHERE a.student_id = ? 
                      $subjectCondition 
                      GROUP BY YEAR(a.attendance_date), MONTH(a.attendance_date)
                      ORDER BY YEAR(a.attendance_date) ASC, MONTH(a.attendance_date) ASC";
    
    $stmt = $conn->prepare($summary_query);
    if ($selectedSubject !== 'all' && is_numeric($selectedSubject)) {
        $stmt->bind_param("ii", $student_id, $selectedSubject);
    } else {
        $stmt->bind_param("i", $student_id);
    }
    $stmt->execute();
    $monthly_result = $stmt->get_result();
    
    while ($row = $monthly_result->fetch_assoc()) {
        $monthlyData[] = $row;
        
        // Get weekly data for this month (excluding weekends)
        $monthYear = $row['month_year'];
        list($monthName, $year) = explode(' ', $monthYear);
        $monthNum = date('m', strtotime($monthName));
        
        // Calculate weeks manually (Week 1-5, excluding weekends)
        $weeks = getWeeksForMonth($year, $monthNum);
        
        $weeklyData[$monthYear] = [];
        
        foreach ($weeks as $weekNum => $week) {
            $week_query = "SELECT 
                            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
                            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent
                         FROM attendance a 
                         WHERE a.student_id = ? 
                         AND a.attendance_date BETWEEN ? AND ?
                         AND DAYOFWEEK(a.attendance_date) NOT IN (1, 7)
                         $subjectCondition";
            
            $weekly_stmt = $conn->prepare($week_query);
            if ($selectedSubject !== 'all' && is_numeric($selectedSubject)) {
                $weekly_stmt->bind_param("issi", $student_id, $week['start'], $week['end'], $selectedSubject);
            } else {
                $weekly_stmt->bind_param("iss", $student_id, $week['start'], $week['end']);
            }
            $weekly_stmt->execute();
            $week_result = $weekly_stmt->get_result();
            $week_data = $week_result->fetch_assoc();
            
            if ($week_data) {
                $weeklyData[$monthYear][] = [
                    'week_number' => $weekNum + 1,
                    'week_start' => $week['start'],
                    'week_end' => $week['end'],
                    'present' => $week_data['present'] ?: 0,
                    'absent' => $week_data['absent'] ?: 0
                ];
            }
        }
    }
} else {
    // Fetch regular attendance records for other filters
    $attendance_query = "SELECT a.attendance_date, a.time_in, a.status
                         FROM attendance a 
                         WHERE a.student_id = ? 
                         $dateCondition 
                         $subjectCondition 
                         ORDER BY a.attendance_date DESC, a.time_in DESC";

    $stmt = $conn->prepare($attendance_query);
    if ($selectedSubject !== 'all' && is_numeric($selectedSubject)) {
        $stmt->bind_param("ii", $student_id, $selectedSubject);
    } else {
        $stmt->bind_param("i", $student_id);
    }
    $stmt->execute();
    $attendance_result = $stmt->get_result();
}

// Function to calculate weeks for a month (excluding weekends)
function getWeeksForMonth($year, $month) {
    $weeks = [];
    $firstDay = date('Y-m-01', strtotime("$year-$month-01"));
    $lastDay = date('Y-m-t', strtotime("$year-$month-01"));
    
    $currentDate = $firstDay;
    $weekNum = 0;
    
    while ($currentDate <= $lastDay) {
        // Skip weekends
        $dayOfWeek = date('N', strtotime($currentDate));
        if ($dayOfWeek < 6) {
            if (!isset($weeks[$weekNum])) {
                $weeks[$weekNum] = [
                    'start' => $currentDate,
                    'end' => $currentDate
                ];
            } else {
                $weeks[$weekNum]['end'] = $currentDate;
            }
        }
        
        $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        
        // If we've reached Sunday or end of month, start new week
        if (date('N', strtotime($currentDate)) == 1 || $currentDate > $lastDay) {
            $weekNum++;
        }
    }
    
    return $weeks;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Record</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .month-row {
            cursor: pointer;
            background-color: #f8f9fa;
        }
        .month-row:hover {
            background-color: #e9ecef;
        }
        .week-row {
            display: none;
            background-color: #fafafa;
        }
        .week-row.visible {
            display: table-row;
        }
        .summary-totals {
            font-weight: bold;
            background-color: #e3f2fd;
        }
        .no-data {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #6c757d;
        }
    </style>
</head>
<body>

<?php include('student_portal_navigation.php'); ?>

<div class="container mt-5">
    <h3 class="text-primary">Attendance Record</h3>

    <!-- Filters -->
    <form method="GET" class="mb-4 d-flex gap-4 flex-wrap align-items-end">
        <!-- Subject Dropdown (from enrolled subjects) -->
        <div>
            <label for="subject" class="form-label">Subject:</label>
            <select name="subject" id="subject" class="form-select" onchange="updateFacultyInfo(this.value); this.form.submit()">
                <option value="all" <?= $selectedSubject === 'all' ? 'selected' : '' ?>>Select Subject</option>
                <?php foreach ($subjects as $id => $subject_info): ?>
                    <option value="<?= $id ?>" <?= $selectedSubject == $id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($subject_info['subject_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Faculty Information Display -->
        <div class="filter-group">
            <label class="form-label">Teacher:</label>
            <div id="facultyInfo" class="p-2 border rounded bg-light">
                <?php if ($selectedSubject !== 'all' && isset($subjects[$selectedSubject])): ?>
                    <div>
                        <strong><?= htmlspecialchars($subjects[$selectedSubject]['faculty_name']) ?></strong>
                    </div>
                <?php else: ?>
                    <div class="text-muted">Select a subject to view teacher</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter by Date Range -->
        <div>
            <label for="filter" class="form-label">Filter by:</label>
            <select name="filter" id="filter" class="form-select" onchange="this.form.submit()">
                <option value="monthly" <?= $filter === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                <option value="summary" <?= $filter === 'summary' ? 'selected' : '' ?>>Summary</option>
            </select>
        </div>

        <!-- Month Selector (only shown when monthly filter is selected) -->
        <?php if ($filter === 'monthly'): ?>
        <div>
            <label for="month" class="form-label">Select Month:</label>
            <input type="month" name="month" id="month" class="form-control" 
                   value="<?= htmlspecialchars($selectedMonth) ?>" 
                   onchange="this.form.submit()">
        </div>
        <?php endif; ?>
    </form>

    <?php if ($filter === 'summary'): ?>
    <!-- Summary View -->
    <div class="table-responsive">
        <table class="table table-bordered table-striped text-center">
            <thead class="table-primary">
                <tr>
                    <th>Month</th>
                    <th>Present</th>
                    <th>Absent</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($monthlyData) && $selectedSubject !== 'all'): ?>
                    <?php 
                    $totalPresent = 0;
                    $totalAbsent = 0;
                    ?>
                    <?php foreach ($monthlyData as $month): ?>
                        <?php 
                        $totalPresent += $month['present'];
                        $totalAbsent += $month['absent'];
                        ?>
                        <tr class="month-row" data-month="<?= htmlspecialchars($month['month_year']) ?>">
                            <td><?= htmlspecialchars($month['month_year']) ?></td>
                            <td><?= $month['present'] ?></td>
                            <td><?= $month['absent'] ?></td>
                        </tr>
                        
                        <!-- Weekly rows for this month -->
                        <?php if (isset($weeklyData[$month['month_year']])): ?>
                            <?php foreach ($weeklyData[$month['month_year']] as $week): ?>
                                <tr class="week-row" data-month="<?= htmlspecialchars($month['month_year']) ?>">
                                    <td>Week <?= $week['week_number'] ?> (<?= date('M d', strtotime($week['week_start'])) ?> - <?= date('M d', strtotime($week['week_end'])) ?>)</td>
                                    <td><?= $week['present'] ?></td>
                                    <td><?= $week['absent'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <!-- Totals row -->
                    <tr class="summary-totals">
                        <td><strong>Total</strong></td>
                        <td><strong><?= $totalPresent ?></strong></td>
                        <td><strong><?= $totalAbsent ?></strong></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="no-data">
                            <?php if ($selectedSubject === 'all'): ?>
                                Please select a subject to view attendance data
                            <?php else: ?>
                                No data available.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php else: ?>
    <!-- Regular Attendance Table -->
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead class="table-primary">
                <tr>
                    <th>Date</th>
                    <th>Time In</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (isset($attendance_result) && $attendance_result->num_rows > 0 && $selectedSubject !== 'all'): ?>
                    <?php while ($row = $attendance_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars(date("F j, Y", strtotime($row['attendance_date']))) ?></td>
                            <td><?= htmlspecialchars(date("h:i A", strtotime($row['time_in']))) ?></td>
                            <td><?= htmlspecialchars($row['status']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="no-data">
                            <?php if ($selectedSubject === 'all'): ?>
                                Please select a subject to view attendance data
                            <?php else: ?>
                                No data available.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
    // Function to update faculty information based on selected subject
    function updateFacultyInfo(subjectId) {
        const facultyInfo = document.getElementById('facultyInfo');
        
        if (subjectId === 'all') {
            facultyInfo.innerHTML = '<div class="text-muted">Select a subject to view teacher</div>';
            return;
        }
        
        // Get faculty data from PHP array (converted to JS object)
        const subjects = <?= json_encode($subjects) ?>;
        
        if (subjects[subjectId]) {
            const faculty = subjects[subjectId];
            facultyInfo.innerHTML = `
                <div><strong>${faculty.faculty_name}</strong></div>
            `;
        } else {
            facultyInfo.innerHTML = '<div class="text-muted">Teacher information not available</div>';
        }
    }
    
    // Initialize faculty info on page load
    document.addEventListener('DOMContentLoaded', function() {
        const subjectSelect = document.getElementById('subject');
        updateFacultyInfo(subjectSelect.value);
    });

    // Add event listeners to month rows for toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const monthRows = document.querySelectorAll('.month-row');
        monthRows.forEach(row => {
            row.addEventListener('dblclick', function() {
                const month = this.getAttribute('data-month');
                const weekRows = document.querySelectorAll('.week-row[data-month="' + month + '"]');
                
                // Toggle visibility of week rows
                weekRows.forEach(weekRow => {
                    weekRow.classList.toggle('visible');
                });
            });
        });
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>