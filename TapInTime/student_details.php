<?php
// Include database connection
include('db_connection.php');

// Fetch the data including grade level and student type
$query = "SELECT section, grade_level, student_type, COUNT(*) AS student_count 
          FROM students 
          GROUP BY section, grade_level, student_type
          ORDER BY section ASC";

$result = mysqli_query($conn, $query);

// Initialize the students array
$students = [];

// Check if any data was fetched
if (mysqli_num_rows($result) > 0) {
    $students = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Information</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
<?php include('sidebar.php'); ?>

<div class="main-content">
    <h2>Student Information</h2>

    <!-- Search Bar -->
    <div class="search-container" style="display:none;">
        <div class="left-search">
            <form id="searchForm" onsubmit="return handleSearch(event)">
                <input type="text" id="searchInput" placeholder="Search by Section...">
                <button type="submit">Search</button>
            </form>
        </div>
    </div>    

    <!-- Grade Level and Student Type Dropdown -->
    <div class="year-levels">
        <?php
        $grades = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10'];
        $types = ['Regular Student', 'STI Student'];

        foreach ($grades as $grade) {
            echo "<div class='year-box-wrapper'>
                    <div class='year-box'>{$grade}</div>
                    <div class='dropdown'>";
            foreach ($types as $type) {
                echo "<div onclick=\"showStudents('{$grade}', '{$type}')\">{$type}</div>";
            }
            echo "  </div>
                </div>";
        }
        ?>
    </div>

    <!-- Student Table -->
    <table class="student-table" id="studentTable" style="display:none;">
        <thead>
            <tr>
                <th>Section</th>
                <th>No. of Students</th>
                <th>Student Type</th>
                <th class="actions-header" style="position: relative;">
  <div class="header-content">
    <span>Actions</span>
    <ion-icon name="ellipsis-vertical-outline" class="header-icon" id="headerDropdownToggle"></ion-icon>
  </div>
  
  <!-- Dropdown menu -->
  <div id="headerDropdownMenu" class="dropdown-menu">
    <div class="dropdown-item" onclick="archiveAll()">Archive</div>
  </div>
</th>
            </tr>
        </thead>

<tbody id="studentTableBody">
<?php
if (!empty($students)) {
    foreach ($students as $student) {
        $sectionFormatted = ucwords(strtolower($student['section']));
        $sectionUrl = urlencode($student['section']);
        echo "<tr data-grade='" . strtolower($student['grade_level']) . "' data-type='" . strtolower($student['student_type']) . "'>
                <td>{$sectionFormatted}</td>
                <td>{$student['student_count']}</td>
                <td>{$student['student_type']}</td>
                <td>
                    <button class='view-btn' title='View' onclick=\"location.href='details_student.php?section={$sectionUrl}'\">
                        <ion-icon name='eye-outline'></ion-icon>
                    </button>
                </td>
              </tr>";
    }
} else {
    echo "<tr id='noDataRow'><td colspan='4'>No data available.</td></tr>";
}
?>
</tbody>
        </table>
    </div>

<script>
function searchStudent() {
    const input = document.getElementById("searchInput").value.toUpperCase();
    const rows = document.querySelectorAll("#studentTableBody tr");

    let hasMatch = false;

    rows.forEach(row => {
        if (row.id === "noDataRow") {
            // Hide the no data row initially during filtering
            row.style.display = "none";
            return;
        }

        const grade = row.getAttribute("data-grade")?.trim().toLowerCase();
        const type = row.getAttribute("data-type")?.trim().toLowerCase();
        const section = row.querySelector("td:nth-child(1)")?.textContent.toUpperCase() || "";

        if (grade === currentGrade && type === currentType) {
            if (section.includes(input)) {
                row.style.display = "";
                hasMatch = true;
            } else {
                row.style.display = "none";
            }
        } else {
            row.style.display = "none";
        }
    });

    // Remove any existing no data row to avoid duplicates
    const existingNoDataRow = document.getElementById("noDataRow");
    if (existingNoDataRow) existingNoDataRow.remove();

if (!hasMatch) {
    const tbody = document.getElementById("studentTableBody");
    const hasAnyData = Array.from(rows).some(row =>
        row.id !== "noDataRow" &&
        row.getAttribute("data-grade") === currentGrade &&
        row.getAttribute("data-type") === currentType
    );

    const newRow = document.createElement("tr");
    newRow.id = "noDataRow";
    newRow.innerHTML = `<td colspan='4'>${hasAnyData ? "No matching results." : "No data available."}</td>`;
    tbody.appendChild(newRow);
    }
}

function showStudents(yearLevel, studentType) {
    currentGrade = yearLevel.trim().toLowerCase();
    currentType = studentType.trim().toLowerCase();

    document.querySelector(".year-levels").style.display = "none";
    document.querySelector(".search-container").style.display = "flex";

    document.getElementById("searchInput").value = "";
    document.getElementById("studentTable").style.display = "table";

    searchStudent();
}

document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("searchInput").addEventListener("input", searchStudent);
});

function redirectToStudentInfo(section) {
    window.location.href = `details_student.php?section=${encodeURIComponent(section)}`;
}

function archiveStudent(lrn) {
    if (!confirm(`Are you sure you want to archive student LRN: ${lrn}?`)) return;

    fetch('archive_student_action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `lrn=${encodeURIComponent(lrn)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Student archived successfully!');
            location.reload(); // Reload page to update list
        } else {
            alert('Error archiving student: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        alert('Request failed: ' + error);
    });
}

    const dropdownToggle = document.getElementById('headerDropdownToggle');
const dropdownMenu = document.getElementById('headerDropdownMenu');

dropdownToggle.addEventListener('click', function(event) {
  event.stopPropagation(); // Prevent event bubbling
  dropdownMenu.style.display = (dropdownMenu.style.display === 'block') ? 'none' : 'block';
});

// Hide dropdown if click outside
document.addEventListener('click', function() {
  dropdownMenu.style.display = 'none';
});

function archiveAll() {
  window.location.href = 'archive_students.php';
}
</script>

<!-- Ionicons -->
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>
