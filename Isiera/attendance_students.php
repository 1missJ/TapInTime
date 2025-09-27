<?php
include('db_connection.php');

// Get section from query parameter or default to empty
$section = isset($_GET['section']) ? $_GET['section'] : '';

// Fetch students from database filtered by section
$students = [];
$query = "SELECT s.lrn, CONCAT(s.first_name, ' ', s.last_name) as name 
          FROM students s
          JOIN sections sec ON s.section = sec.section_name
          WHERE sec.section_name = ?
          ORDER BY s.last_name, s.first_name";

$stmt = $conn->prepare($query);
$stmt->bind_param('s', $section);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student List</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/attendance.css">
</head>
<body>
<?php include('sidebar.php'); ?>

<!-- Main Content -->
<div class="main-content">
    <h2>Student List</h2>

    <div class="search-container">
        <div class="left-search">
            <form id="searchForm" onsubmit="return handleSearch(event)">
                <input type="text" id="searchInput" placeholder="Search by LRN or Name...">
                <button type="submit">Search</button>
            </form>
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
            <?php if (count($students) > 0): ?>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($student['lrn']); ?></td>
                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                        <td>
                            <button class="views-btn" title='View' onclick="viewAttendance('<?php echo htmlspecialchars($student['lrn']); ?>')">
                                <ion-icon name='eye-outline'></ion-icon>
                            </button> 
                                <button class="report-btn" title='Summary' onclick="summaryAttendance('<?php echo htmlspecialchars($student['lrn']); ?>')">
                                <ion-icon name="newspaper-outline"></ion-icon>
                            </button>                         
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" class="no-data">No data available.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

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

        // Search through all rows (except the header)
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            // Skip rows that are already "no data" messages
            if (row.classList.contains('no-data')) continue;
            
            const lrnCell = row.getElementsByTagName("td")[0]; // LRN
            const nameCell = row.getElementsByTagName("td")[1]; // Name

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
            newRow.innerHTML = `<td colspan="3" class="no-data-row">No matching results.</td>`;
            table.appendChild(newRow);
        }
    }

    function handleKeyPress(event) {
        if (event.key === "Enter") {
            event.preventDefault();
            searchStudent();
        }
    }

    function viewAttendance(lrn) {
        window.location.href = 'students_attendance.php?lrn=' + lrn + '&section=<?php echo urlencode($section); ?>';
    }

    function summaryAttendance(lrn) {
        window.location.href = 'summary_students_attendance.php?lrn=' + lrn + '&section=<?php echo urlencode($section); ?>';
    }

    // Add event listener for input changes
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById("searchInput").addEventListener("input", searchStudent);
        document.getElementById("searchInput").addEventListener("keypress", handleKeyPress);
    });
</script>

    <!-- Ionicons -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>