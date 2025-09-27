<?php
include('db_connection.php');

$query = "SELECT section, grade_level, COUNT(*) AS student_count 
          FROM students 
          GROUP BY section, grade_level
          ORDER BY section ASC";

$result = mysqli_query($conn, $query);
$students = [];

if (mysqli_num_rows($result) > 0) {
    $students = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ID Generation</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include('sidebar.php'); ?>

<div class="main-content">
    <div class="dropdown-nav">
        <label for="gradeSelect">Navigate to:</label>
        <select id="gradeSelect" onchange="showStudents(this.value)">
            <option value="">-- Select Grade Level --</option>
            <?php
            $grades = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
            foreach ($grades as $grade) {
                echo "<option value='$grade'>$grade</option>";
            }
            ?>
        </select>
    </div>

    <!-- Search Bar -->
    <div class="search-container" style="display:none;" id="searchContainer">
        <div class="left-search">
            <form id="searchForm" onsubmit="return handleSearch(event)">
                <input type="text" id="searchInput" placeholder="Search by Section...">
                <button type="submit">Search</button>
            </form>
        </div>
    </div>   

    <table class="student-table" id="studentTable" style="display:none;">
        <thead>
            <tr>
                <th>Section</th>
                <th>No. of Students</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="studentTableBody">
            <?php
            foreach ($students as $student) {
                $section = htmlspecialchars(ucwords(strtolower($student['section'])));
                echo "<tr data-grade='".strtolower($student['grade_level'])."' style='display:none;'>
                    <td>$section</td>
                    <td>{$student['student_count']}</td>
                    <td>
                        <button class='view-btn' onclick=\"location.href='id_generate.php?gradelevel=".urlencode($student['grade_level'])."&section=".urlencode($student['section'])."'\">
                            <ion-icon name='eye-outline'></ion-icon>
                        </button>
                    </td>
                </tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<script>
let currentGrade = "";

function showStudents(yearLevel) {
    const searchContainer = document.getElementById("searchContainer");
    const studentTable = document.getElementById("studentTable");
    
    if (!yearLevel) {
        // If no grade level is selected, hide both search and table
        searchContainer.style.display = "none";
        studentTable.style.display = "none";
        return;
    }
    
    currentGrade = yearLevel.toLowerCase();
    searchContainer.style.display = "flex";
    studentTable.style.display = "table";
    
    const tbody = document.getElementById("studentTableBody");
    let hasStudents = false;
    
    // Clear any existing no data row
    const oldNoDataRow = document.getElementById("noDataRow");
    if (oldNoDataRow) tbody.removeChild(oldNoDataRow);
    
    // Show/hide rows
    const rows = tbody.querySelectorAll("tr");
    rows.forEach(row => {
        if (row.id === "noDataRow") return;
        
        const rowGrade = row.getAttribute("data-grade");
        if (rowGrade === currentGrade) {
            row.style.display = "";
            hasStudents = true;
        } else {
            row.style.display = "none";
        }
    });
    
    // Add no data row if needed
    if (!hasStudents) {
        const noDataRow = document.createElement("tr");
        noDataRow.id = "noDataRow";
        noDataRow.className = "no-data-row";
        noDataRow.innerHTML = `<td colspan="3">No data available.</td>`;
        tbody.appendChild(noDataRow);
    }
    
    document.getElementById("searchInput").value = "";
}

function searchStudent() {
    const input = document.getElementById("searchInput").value.toLowerCase();
    const rows = document.querySelectorAll("#studentTableBody tr");
    let hasMatch = false;
    
    rows.forEach(row => {
        if (row.id === "noDataRow") {
            row.style.display = "none";
            return;
        }
        
        const grade = row.getAttribute("data-grade").toLowerCase();
        const section = row.querySelector("td:first-child")?.textContent.toLowerCase() || "";
        
        if (grade === currentGrade && section.includes(input)) {
            row.style.display = "";
            hasMatch = true;
        } else {
            row.style.display = "none";
        }
    });
    
    const existingNoDataRow = document.getElementById("noDataRow");
    if (existingNoDataRow) existingNoDataRow.remove();
    
    if (!hasMatch) {
        const tbody = document.getElementById("studentTableBody");
        const newRow = document.createElement("tr");
        newRow.id = "noDataRow";
        newRow.innerHTML = `<td colspan='3'>No matching results.</td>`;
        tbody.appendChild(newRow);
    }
}

document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("searchInput").addEventListener("input", searchStudent);
    // Initialize with no grade selected
    document.getElementById("studentTable").style.display = "none";
    document.getElementById("searchContainer").style.display = "none";
});

</script>

<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>