<?php
// Include database connection
include('db_connection.php');

// Fetch the data including grade level and student type, ordered by section alphabetically
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
    <title>ID Generation</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
<?php include('sidebar.php'); ?>

<div class="main-content">
    <h2>ID Generation</h2>

    <!-- Search Bar -->
    <div class="search-container" style="display:none;">
        <div class="left-search">
            <form id="searchForm" onsubmit="return handleSearch(event)">
                <input type="text" id="searchInput" placeholder="Search by section...">
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
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="studentTableBody">
<?php
if (!empty($students)) {
    foreach ($students as $student) {
        $sectionEscaped = htmlspecialchars(ucwords(strtolower($student['section'])));
        $typeEscaped = htmlspecialchars($student['student_type']);
        $sectionUrl = urlencode($student['section']);
        echo "<tr data-grade='{$student['grade_level']}' data-type='{$student['student_type']}'>
            <td>{$sectionEscaped}</td>
            <td>{$student['student_count']}</td>
            <td>{$typeEscaped}</td>
            <td>
                <button class='view-btn' title='View' onclick=\"location.href='id_generate.php?section={$sectionUrl}'\">
                    <ion-icon name='eye-outline'></ion-icon>
                </button>
            </td>
        </tr>";
    }
}
?>
        </tbody>
    </table>
</div>

<script>
let currentGrade = "";
let currentType = "";

function searchStudent() {
    const input = document.getElementById("searchInput").value.toUpperCase();
    const rows = document.querySelectorAll("#studentTableBody tr");

    let hasMatch = false;

    rows.forEach(row => {
        if (row.id === "noDataRow") {
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

    // Handle no match case
    const tbody = document.getElementById("studentTableBody");
    const existingNoDataRow = document.getElementById("noDataRow");
    if (existingNoDataRow) existingNoDataRow.remove();

    if (!hasMatch) {
        const newRow = document.createElement("tr");
        newRow.id = "noDataRow";
        newRow.innerHTML = `<td colspan="4">No matching results.</td>`;
        tbody.appendChild(newRow);
    }
}

document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("searchInput").addEventListener("input", searchStudent);
});

function showStudents(yearLevel, studentType) {
    currentGrade = yearLevel.trim().toLowerCase();
    currentType = studentType.trim().toLowerCase();

    document.querySelector(".year-levels").style.display = "none";
    document.querySelector(".search-container").style.display = "flex";

    const table = document.getElementById("studentTable");
    const tbody = document.getElementById("studentTableBody");
    const rows = tbody.querySelectorAll("tr");
    table.style.display = "table";

    let hasMatch = false;

    const noDataRow = document.getElementById("noDataRow");
    if (noDataRow) noDataRow.remove();

    rows.forEach(row => {
        const grade = row.getAttribute("data-grade")?.trim().toLowerCase();
        const type = row.getAttribute("data-type")?.trim().toLowerCase();

        if (grade === currentGrade && type === currentType) {
            row.style.display = "";
            hasMatch = true;
        } else {
            row.style.display = "none";
        }
    });

    if (!hasMatch) {
        const newRow = document.createElement("tr");
        newRow.id = "noDataRow";
        newRow.innerHTML = `<td colspan="4">No data available.</td>`;
        tbody.appendChild(newRow);
    }
}


function redirectToStudentInfo(section) {
    window.location.href = `details_student.php?section=${encodeURIComponent(section)}`;
}
</script>

<!-- Ionicons -->
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>
