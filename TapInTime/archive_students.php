<?php
include('db_connection.php');

// Fetch grouped archive data by grade_level, section, student_type
$query = "SELECT grade_level, section, student_type, COUNT(*) AS total_students 
          FROM archived_students 
          GROUP BY grade_level, section, student_type 
          ORDER BY grade_level ASC, section ASC";
$result = mysqli_query($conn, $query);
$groups = mysqli_num_rows($result) > 0 ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Archive</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include('sidebar.php'); ?>

<div class="main-content">
    <h2>Archive</h2>

    <div class="search-container" style="display:none;">
        <form id="searchForm" onsubmit="return false;">
            <input type="text" id="searchInput" placeholder="Search by Section..." oninput="searchGroup()">
            <button type="submit">Search</button>
        </form>
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
                // Use onclick to trigger filtering
                echo "<div onclick=\"filterByGradeAndType('{$grade}', '{$type}')\">{$type}</div>";
            }
            echo "</div></div>";
        }
        ?>
    </div>

    <table class="student-table" id="studentTable" style="display: none;">
        <thead>
            <tr>
                <th>Section</th>
                <th>No. of Students</th>
                <th>Student Type</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="studentTableBody">
        <?php if (!empty($groups)): ?>
            <?php foreach ($groups as $group): ?>
                <tr 
                    data-grade="<?= htmlspecialchars(strtolower($group['grade_level'])) ?>" 
                    data-type="<?= htmlspecialchars(strtolower($group['student_type'])) ?>"
                    data-section="<?= htmlspecialchars($group['section']) ?>"
                >
                    <td><?= htmlspecialchars($group['section']) ?></td>
                    <td><?= htmlspecialchars($group['total_students']) ?></td>
                    <td><?= htmlspecialchars($group['student_type']) ?></td>
                    <td>
                        <button class='view' title='View' onclick="window.location.href='student_archive.php?section=<?= urlencode($group['section']) ?>&grade_level=<?= urlencode($group['grade_level']) ?>&student_type=<?= urlencode($group['student_type']) ?>'">
                            <ion-icon name='eye-outline'></ion-icon>
                        </button>
                        <button class='unarchive' title='Unarchive' onclick="unarchiveSection('<?= htmlspecialchars($group['section']) ?>', '<?= htmlspecialchars($group['student_type']) ?>')">
                            <ion-icon name='arrow-undo-outline'></ion-icon>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr id="noDataRow"><td colspan="4">No archived students found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
// Store selected filters globally
let selectedGrade = null;
let selectedType = null;

function searchGroup() {
    const input = document.getElementById("searchInput").value.toUpperCase();
    const rows = document.querySelectorAll("#studentTableBody tr");
    let hasMatch = false;

    const oldNoData = document.getElementById('noDataRow');
    if (oldNoData) oldNoData.remove();

    rows.forEach(row => {
        if (!row.hasAttribute("data-grade")) return;

        const rowGrade = row.getAttribute("data-grade");
        const rowType = row.getAttribute("data-type");

        // Only consider rows matching the current grade/type filter
        if (rowGrade !== selectedGrade || rowType !== selectedType) {
            row.style.display = "none";
            return;
        }

        const section = row.cells[0]?.textContent.toUpperCase() || "";
        const type = row.cells[2]?.textContent.toUpperCase() || "";
        const match = section.includes(input) || type.includes(input);

        row.style.display = match ? "" : "none";
        if (match) hasMatch = true;
    });

    if (!hasMatch) {
        const tbody = document.getElementById("studentTableBody");
        const noDataRow = document.createElement("tr");
        noDataRow.id = "noDataRow";
        noDataRow.innerHTML = `<td colspan="4">No matching results.</td>`;
        tbody.appendChild(noDataRow);
    }
}

function filterByGradeAndType(grade, type) {
    selectedGrade = grade.trim().toLowerCase();
    selectedType = type.trim().toLowerCase();

    // Hide grade/type selection
    document.querySelector(".year-levels").style.display = "none";

    // Show search bar
    document.querySelector(".search-container").style.display = "block";

    const table = document.getElementById("studentTable");
    const tbody = document.getElementById("studentTableBody");
    const rows = tbody.querySelectorAll("tr");

    // Remove existing no data row
    const oldNoData = document.getElementById('noDataRow');
    if (oldNoData) oldNoData.remove();

    let found = false;

    rows.forEach(row => {
        if (!row.hasAttribute("data-grade")) return;

        const rowGrade = row.getAttribute("data-grade");
        const rowType = row.getAttribute("data-type");

        if (rowGrade === selectedGrade && rowType === selectedType) {
            row.style.display = "";
            found = true;
        } else {
            row.style.display = "none";
        }
    });

    if (!found) {
        const noDataRow = document.createElement("tr");
        noDataRow.id = "noDataRow";
        noDataRow.innerHTML = `<td colspan="4">No data available.</td>`;
        tbody.appendChild(noDataRow);
    }

    table.style.display = "table";
}

function unarchiveSection(section, type) {
    if (!confirm(`Unarchive all students from section: ${section}, type: ${type}?`)) return;
    
    fetch('unarchive_section_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `section=${encodeURIComponent(section)}&type=${encodeURIComponent(type)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Section unarchived successfully!');

            // Remove the row from the DOM
            const rows = document.querySelectorAll("#studentTableBody tr");
            rows.forEach(row => {
                const rowSection = row.getAttribute("data-section");
                const rowType = row.getAttribute("data-type");
                if (rowSection === section && rowType === type.toLowerCase()) {
                    row.remove();
                }
            });

            // If no more rows match current filters, show 'No data'
            const visibleRows = Array.from(document.querySelectorAll("#studentTableBody tr"))
                .filter(r => r.style.display !== "none");

            if (visibleRows.length === 0) {
                const noDataRow = document.createElement("tr");
                noDataRow.id = "noDataRow";
                noDataRow.innerHTML = `<td colspan="4">No data available.</td>`;
                document.getElementById("studentTableBody").appendChild(noDataRow);
            }
        } else {
            alert('Failed to unarchive: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        alert('Request failed: ' + err);
    });
}
</script>

<!-- Ionicons -->
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>
