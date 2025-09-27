<?php
// archived_students.php
include('db_connection.php');

// Get parameters from URL
$section = isset($_GET['section']) ? $_GET['section'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';

// Escape values for security
$sectionEscaped = mysqli_real_escape_string($conn, $section);
$filterEscaped = mysqli_real_escape_string($conn, $filter);

// Build the query based on filter type
if ($filter === 'JHS Graduate' || $filter === 'SHS Graduate') {
    $query = "SELECT 
                lrn,
                CONCAT(UPPER(LEFT(last_name,1)), LOWER(SUBSTRING(last_name,2)), ', ',
                       UPPER(LEFT(first_name,1)), LOWER(SUBSTRING(first_name,2)), ' ',
                       UPPER(LEFT(middle_name,1)), '.') AS fullname,
                email,
                grade_level
              FROM archived_students
              WHERE section = '$sectionEscaped'
                AND archive_type = '$filterEscaped'
              ORDER BY last_name, first_name, middle_name";
} else {
    $query = "SELECT 
                lrn,
                CONCAT(UPPER(LEFT(last_name,1)), LOWER(SUBSTRING(last_name,2)), ', ',
                       UPPER(LEFT(first_name,1)), LOWER(SUBSTRING(first_name,2)), ' ',
                       UPPER(LEFT(middle_name,1)), '.') AS fullname,
                email,
                grade_level
              FROM archived_students
              WHERE section = '$sectionEscaped'
                AND grade_level = '$filterEscaped'
              ORDER BY last_name, first_name, middle_name";
}

$result = mysqli_query($conn, $query);
$students = mysqli_num_rows($result) > 0 ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Archived Students - <?= htmlspecialchars($section) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include('sidebar.php'); ?>

<div class="main-content">
    
    <h2>List of Archived Students</h2>

    <div class="search-container">
        <form id="searchForm" onsubmit="return false;">
            <input type="text" id="searchInput" placeholder="Search by LRN or Name..." oninput="searchStudent()">
            <button type="submit">Search</button>
        </form>
    </div>

    <form method="POST" action="unarchive_student_action.php" id="unarchiveForm">
    <input type="hidden" name="section" value="<?= htmlspecialchars($section) ?>">
    <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        
        <table class="student-table" id="studentTable">
            <thead>
                <tr>
                    <th>LRN</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th style="position: relative;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span>Actions</span>
                            <ion-icon name="ellipsis-vertical-outline" id="archiveHeaderDropdownToggle" style="cursor: pointer;"></ion-icon>
                        </div>
                        <div id="archiveHeaderDropdownMenu" class="dropdown-menu" style="position: absolute; top: 100%; right: 0; background: white; border: 1px solid #ccc; display: none;">
                            <div class="dropdown-item" id="selectAllCheckboxes" style="padding: 8px 15px; cursor: pointer;">Select All</div>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody id="studentTableBody">
                <?php if (!empty($students)): ?>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= htmlspecialchars($student['lrn']) ?></td>
                            <td><?= htmlspecialchars($student['fullname']) ?></td>
                            <td><?= htmlspecialchars($student['email']) ?></td>
                            <td>
                                <input type="checkbox" class="student-checkbox" name="selected_lrns[]" value="<?= $student['lrn'] ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="noDataRow"><td colspan="4">No data available.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4">
                        <div class="unarchive-btn-container">
                            <span class="error-message" id="selectionError">Please select at least one student to unarchive.</span>
                            <button type="button" class="unarchive-btn" id="unarchiveBtn">Unarchive</button>
                        </div>
                    </td>
                </tr>
            </tfoot>
        </table>
    </form>
</div>

<!-- Scripts -->
<script>
function searchStudent() {
    const input = document.getElementById("searchInput").value.toUpperCase();
    const rows = document.querySelectorAll("#studentTableBody tr");

    let hasMatch = false;
    document.getElementById("noDataRow")?.remove();

    rows.forEach(row => {
        if (row.id === "noDataRow") return;
        
        const lrn = row.cells[0].textContent.toUpperCase();
        const name = row.cells[1].textContent.toUpperCase();
        const match = lrn.includes(input) || name.includes(input);
        row.style.display = match ? "" : "none";
        if (match) hasMatch = true;
    });

    if (!hasMatch) {
        const tbody = document.getElementById("studentTableBody");
        const noMatch = document.createElement("tr");
        noMatch.id = "noDataRow";
        noMatch.innerHTML = "<td colspan='4'>No matching results.</td>";
        tbody.appendChild(noMatch);
    }
}

// Select All / Deselect All Logic
const archiveToggle = document.getElementById('archiveHeaderDropdownToggle');
const archiveDropdown = document.getElementById('archiveHeaderDropdownMenu');
const selectAllCheckboxes = document.getElementById('selectAllCheckboxes');
let allChecked = false;

archiveToggle.addEventListener('click', e => {
    e.stopPropagation();
    archiveDropdown.style.display = archiveDropdown.style.display === 'block' ? 'none' : 'block';
});

document.addEventListener('click', () => {
    archiveDropdown.style.display = 'none';
});

selectAllCheckboxes.addEventListener('click', () => {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    allChecked = !allChecked;
    checkboxes.forEach(cb => cb.checked = allChecked);
    selectAllCheckboxes.textContent = allChecked ? 'Deselect All' : 'Select All';
    archiveDropdown.style.display = 'none';
});

// Function to check if at least one checkbox is selected
function isAtLeastOneSelected() {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    for (let i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].checked) {
            return true;
        }
    }
    return false;
}

// Handle unarchive button click
document.getElementById('unarchiveBtn').addEventListener('click', function() {
    if (!isAtLeastOneSelected()) {
        // Show error message if no students are selected
        document.getElementById('selectionError').style.display = 'inline';
    } else {
        // Hide error message and submit the form
        document.getElementById('selectionError').style.display = 'none';
        document.getElementById('unarchiveForm').submit();
    }
});
</script>

<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>