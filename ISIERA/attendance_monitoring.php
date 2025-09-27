<?php
include('db_connection.php');

// Fetch sections data from database
$sections = [];
$query = "SELECT 
            section as section_name,
            grade_level,
            COUNT(*) as student_count
          FROM students
          GROUP BY section, grade_level
          ORDER BY grade_level, section";

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $sections[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Attendance Monitoring</title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <style>
    /** Buttons **/
    .report-btn {
        padding: 8px 16px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        background-color: #dc3545;
        color: white;
        font-weight: bold;
        transition: 0.3s;
    }

    .report-btn:hover {
        background-color: #c82333;
    }

    .student-btn {
        padding: 8px 16px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        background-color: #ffc107;
        color: white;
        font-weight: bold;
        transition: 0.3s;
    }

    .student-btn:hover {
        background-color: #e0a800;
    }

    .views-btn {
        padding: 8px 16px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        background-color: #3535dc;
        color: white;
        font-weight: bold;
        transition: 0.3s;
    }

    .views-btn:hover {
        color: #0056b3;
    }
  </style>
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
  <div class="search-container" id="searchContainer" style="display: none;">
    <div class="left-search">
      <form id="searchForm" onsubmit="return false;">
        <input type="text" id="searchInput" placeholder="Search by section..." />
        <button type="submit">Search</button>
      </form>
    </div>
  </div>

  <!-- Student Table -->
  <table class="student-table" id="studentTable" style="display: none;">
    <thead>
      <tr>
        <th>Section</th>
        <th>No. of Students</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="studentTableBody">
      <?php
      // Convert PHP sections data to JavaScript format
      echo '<script>';
      echo 'const allSections = ' . json_encode($sections) . ';';
      echo '</script>';
      ?>
    </tbody>
  </table>

<script>
    let currentGrade = "";

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
            const section = row.querySelector("td:nth-child(1)")?.textContent.toUpperCase() || "";

            if (grade === currentGrade) {
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

        const existingNoDataRow = document.getElementById("noDataRow");
        if (existingNoDataRow) existingNoDataRow.remove();

        if (!hasMatch && input !== "") {
            const tbody = document.getElementById("studentTableBody");
            const newRow = document.createElement("tr");
            newRow.id = "noDataRow";
            newRow.innerHTML = `<td colspan='3'>No matching results.</td>`;
            tbody.appendChild(newRow);
        }
    }

    function showStudents(yearLevel) {
        const searchContainer = document.getElementById("searchContainer");
        const studentTable = document.getElementById("studentTable");
        
        if (!yearLevel) {
            // If no grade level is selected, hide both search and table
            searchContainer.style.display = "none";
            studentTable.style.display = "none";
            return;
        }
        
        currentGrade = yearLevel.trim().toLowerCase();
        searchContainer.style.display = "flex";
        studentTable.style.display = "table";

        // Clear search input
        document.getElementById("searchInput").value = '';

        // Filter sections by grade level
        const filteredSections = allSections.filter(section => 
            section.grade_level === yearLevel
        );

        const tbody = document.getElementById("studentTableBody");
        tbody.innerHTML = ''; // Clear existing rows

        if (filteredSections.length === 0) {
            const newRow = document.createElement("tr");
            newRow.id = "noDataRow";
            newRow.innerHTML = `<td colspan="3">No data available.</td>`;
            tbody.appendChild(newRow);
            return;
        }

        // Populate table with filtered sections
        filteredSections.forEach(section => {
            const row = document.createElement('tr');
            row.setAttribute('data-grade', section.grade_level.toLowerCase());
            
            row.innerHTML = `
                <td>${section.section_name}</td>
                <td>${section.student_count}</td>
                <td>
                    <button class="views-btn" title='Daily Attendance' onclick="location.href='attendance_daily.php?section=${encodeURIComponent(section.section_name)}&grade=${encodeURIComponent(section.grade_level)}'">
                        <ion-icon name="eye-outline"></ion-icon>
                    </button>
                    <button class="student-btn" title='Individual Attendance' onclick="location.href='attendance_students.php?section=${encodeURIComponent(section.section_name)}&grade=${encodeURIComponent(section.grade_level)}'">
                        <ion-icon name="person-outline"></ion-icon>
                    </button>
                    <button class="report-btn" title='Report Attendance' onclick="location.href='attendance_report.php?section=${encodeURIComponent(section.section_name)}&grade=${encodeURIComponent(section.grade_level)}'">
                        <ion-icon name="document-outline"></ion-icon>
                    </button>
                </td>
            `;
            
            tbody.appendChild(row);
        });
    }

    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById("searchInput").addEventListener("input", searchStudent);
    });
</script>

  <!-- Ionicons -->
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>