<?php
include('db_connection.php');
session_start();

if (!isset($_SESSION['teacher_id'])) {
    header("Location: index.php");
    exit();
}

$teacherId = $_SESSION['teacher_id'];
$teacherName = $_SESSION['teacher_name'];

// Get the section this teacher advises
$sectionStmt = $conn->prepare("
    SELECT s.section_name 
    FROM section_advisers sa
    JOIN sections s ON sa.section_id = s.id
    WHERE sa.teacher_id = ?
");
$sectionStmt->bind_param("i", $teacherId);
$sectionStmt->execute();
$sectionResult = $sectionStmt->get_result();
$sectionRow = $sectionResult->fetch_assoc();

if (!$sectionRow) {
    die("You are not assigned as an adviser for any section.");
}

$adviserSection = $sectionRow['section_name'];

// Fetch students in this adviser's section
$studentStmt = $conn->prepare("
    SELECT s.lrn, CONCAT(s.last_name, ', ', s.first_name) AS full_name
    FROM students s
    WHERE s.section = ?
    ORDER BY s.last_name ASC
");
$studentStmt->bind_param("s", $adviserSection);
$studentStmt->execute();
$studentResult = $studentStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Attendance Monitoring - <?php echo htmlspecialchars($adviserSection); ?></title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="stylesheet" href="assets/css/attendance.css" />
</head>

<body>
<?php include('adviser_sidebar.php'); ?>

<!-- Main Content -->
<div class="main-content">
  <div class="section-header">
    <h2>Attendance Records - <?php echo htmlspecialchars($adviserSection); ?></h2>
  </div>

<!-- Combined Search and Section Attendance -->
<div class="search-container">
    <div class="left-search">
        <form id="searchForm" onsubmit="return handleSearch(event)">
            <input type="text" id="searchInput" placeholder="Search by LRN or Name...">
            <button type="submit">Search</button>
        </form>
    </div>
    <div class="right-actions">
        <button class="action-btn" onclick="window.location.href='adviser_section_attendance.php?section=<?php echo urlencode($adviserSection); ?>'">
            Section Attendance
        </button>
    </div>
</div>

  <!-- Student Table -->
  <table class="student-table" id="studentTable">
    <thead>
      <tr>
        <th>LRN</th>
        <th>Name</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="studentTableBody">
      <?php while ($row = $studentResult->fetch_assoc()): ?>
        <tr>
          <td><?php echo htmlspecialchars($row['lrn']); ?></td>
          <td><?php echo htmlspecialchars($row['full_name']); ?></td>
          <td>
            <button class="views-btn" onclick="window.location.href='adviser_attendance_page.php?lrn=<?php echo urlencode($row['lrn']); ?>'">
              <ion-icon name="eye-outline"></ion-icon>
            </button>  
            <button class="report-btn" onclick="window.location.href='adviser_summary_attendance_page.php?lrn=<?php echo urlencode($row['lrn']); ?>'">
              <ion-icon name="newspaper-outline"></ion-icon>
            </button> 
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<script>
  function searchStudent() {
    const input = document.getElementById("searchInput").value.toUpperCase().trim();
    const table = document.getElementById("studentTableBody");
    const rows = table.getElementsByTagName("tr");
    let hasMatches = false;

    // First, remove any existing "no results" message
    const noResultsRow = document.getElementById("noResultsRow");
    if (noResultsRow) {
      noResultsRow.remove();
    }

    // Search through all rows
    for (let i = 0; i < rows.length; i++) {
      const row = rows[i];
      const lrnCell = row.querySelector("td:first-child"); // LRN
      const nameCell = row.querySelector("td:nth-child(2)"); // Name

      if (lrnCell && nameCell) {
        const lrnText = lrnCell.textContent.toUpperCase();
        const nameText = nameCell.textContent.toUpperCase();

        if (input === '') {
          row.style.display = "";
          hasMatches = true;
        } else {
          const isMatch = lrnText.includes(input) || nameText.includes(input);
          row.style.display = isMatch ? "" : "none";
          if (isMatch) hasMatches = true;
        }
      }
    }

    // Show "No matching results" if no matches found (and search isn't empty)
    if (!hasMatches && input !== '') {
      const newRow = document.createElement('tr');
      newRow.id = "noResultsRow";
      newRow.innerHTML = `<td colspan="3" class="no-data">No matching results.</td>`;
      table.appendChild(newRow);
    }
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