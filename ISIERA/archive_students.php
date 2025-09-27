<?php
include('db_connection.php');

// Get filter from URL if set
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$validFilters = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12', 'JHS Graduate', 'SHS Graduate'];
$showTable = in_array($filter, $validFilters);

// Only build query if valid filter is selected
if ($showTable) {
    if ($filter === 'JHS Graduate' || $filter === 'SHS Graduate') {
        // Fetch based on archive_type (for graduates only) and include year from date_archived
        $query = "SELECT 
                    section, 
                    year_graduated,
                    YEAR(date_archived) as archive_year,
                    COUNT(*) AS total_students 
                  FROM archived_students 
                  WHERE archive_type = ?
                  GROUP BY section, year_graduated, YEAR(date_archived)
                  ORDER BY section ASC, archive_year DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $filter);
    } else {
        // Fetch based on grade_level, and make sure NOT to include graduate archive_type
        $query = "SELECT 
                    section, 
                    COUNT(*) AS total_students 
                  FROM archived_students 
                  WHERE grade_level = ? AND (archive_type IS NULL OR archive_type = '')
                  GROUP BY section 
                  ORDER BY section ASC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $filter);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    
    // Process the results to ensure unique section-year combinations
    $groups = [];
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            if ($filter === 'JHS Graduate' || $filter === 'SHS Graduate') {
                $key = $row['section'] . '_' . $row['archive_year'];
            } else {
                $key = $row['section'];
            }
            
            // If we already have this section-archive_year combination, sum the student counts
            if (isset($groups[$key])) {
                $groups[$key]['total_students'] += $row['total_students'];
            } else {
                $groups[$key] = $row;
            }
        }
        $groups = array_values($groups);
    }
}
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
    <div class="dropdown-nav">
        <label for="archiveFilter">Navigate to:</label>
        <select id="archiveFilter" onchange="filterByType(this.value)">
            <option value="">--- Select Grade Level ---</option>
            <option value="Grade 7">Grade 7</option>
            <option value="Grade 8">Grade 8</option>
            <option value="Grade 9">Grade 9</option>
            <option value="Grade 10">Grade 10</option>
            <option value="Grade 11">Grade 11</option>
            <option value="Grade 12">Grade 12</option>
            <option value="JHS Graduate" <?= $filter === 'JHS Graduate' ? 'selected' : '' ?>>JHS Graduates</option>
            <option value="SHS Graduate" <?= $filter === 'SHS Graduate' ? 'selected' : '' ?>>SHS Graduates</option>
        </select>
    </div>

    <?php if ($showTable): ?>
    <h2 id="archiveHeading">Archived Students</h2>

    <div class="search-container">
        <form id="searchForm" onsubmit="return false;">
            <input type="text" id="searchInput" placeholder="Search by Section..." oninput="searchSections()">
            <button type="submit">Search</button>
        </form>
    </div>

    <table class="student-table" id="studentTable">
        <thead>
            <tr>
                <th>Section</th>
                <th>No. of Students</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="studentTableBody">
            <?php if (!empty($groups)): ?>
                <?php foreach ($groups as $group): ?>
                    <?php 
                    // Format section name based on whether it's a graduate archive
                    if ($filter === 'JHS Graduate' || $filter === 'SHS Graduate') {
                        // Use archive year for display but keep actual year_graduated for functionality
                        $archiveYear = isset($group['archive_year']) ? $group['archive_year'] : date('Y');
                        $displaySection = htmlspecialchars($group['section']) . ' (' . $archiveYear . ')';
                        $originalSection = htmlspecialchars($group['section']);
                        $yearGraduated = htmlspecialchars($group['year_graduated']);
                        $archiveYearValue = $archiveYear;
                    } else {
                        $displaySection = htmlspecialchars($group['section']);
                        $originalSection = htmlspecialchars($group['section']);
                        $yearGraduated = '';
                        $archiveYearValue = '';
                    }
                    ?>
                    <tr data-section="<?= $originalSection ?>" data-year="<?= $yearGraduated ?>" data-archive-year="<?= $archiveYearValue ?>">
                        <td><?= $displaySection ?></td>
                        <td><?= htmlspecialchars($group['total_students']) ?></td>
                        <td>
                            <button class='view' title='View' 
                                onclick="window.location.href='student_archive.php?section=<?= urlencode($originalSection) ?>&year=<?= urlencode($yearGraduated) ?>&archive_year=<?= urlencode($archiveYearValue) ?>&filter=<?= urlencode($filter) ?>'">
                                <ion-icon name='eye-outline'></ion-icon>
                            </button>
                            <button class='unarchive-btn' title='Unarchive' 
                                onclick="unarchiveSection('<?= $originalSection ?>', '<?= $yearGraduated ?>', '<?= $archiveYearValue ?>')">
                                <ion-icon name='arrow-undo-outline'></ion-icon>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr id="noDataRow"><td colspan="3">No data available.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
function filterByType(filterValue) {
    // If empty value (Select Filter was chosen), reload without filter
    if (!filterValue) {
        window.location.href = 'archive_students.php';
        return;
    }
    
    // Otherwise, reload with the selected filter
    window.location.href = 'archive_students.php?filter=' + encodeURIComponent(filterValue);
}

function searchSections() {
    const input = document.getElementById("searchInput").value.toUpperCase();
    const rows = document.querySelectorAll("#studentTableBody tr");
    let hasMatch = false;

    document.getElementById('noDataRow')?.remove();

    rows.forEach(row => {
        if (row.id === "noDataRow") return;

        const section = row.cells[0]?.textContent.toUpperCase() || "";
        const match = section.includes(input);

        row.style.display = match ? "" : "none";
        if (match) hasMatch = true;
    });

    if (!hasMatch) {
        const tbody = document.getElementById("studentTableBody");
        const noDataRow = document.createElement("tr");
        noDataRow.id = "noDataRow";
        noDataRow.innerHTML = `<td colspan="3">No matching results.</td>`;
        tbody.appendChild(noDataRow);
    }
}

function unarchiveSection(section, yearGraduated, archiveYear) {
    console.log('Unarchive button clicked for section:', section, 'Year Graduated:', yearGraduated, 'Archive Year:', archiveYear);
    
    // For non-graduate archives, use just the section name without year
    const displayName = archiveYear ? `${section} (${archiveYear})` : section;
    
    if (!confirm(`Are you sure you want to unarchive all students from ${displayName}?`)) {
        console.log('Unarchive cancelled by user');
        return;
    }

    // Get the current filter value
    const filter = document.getElementById('archiveFilter').value;
    console.log('Current filter value:', filter);
    
    if (!filter) {
        alert('Please select a filter from the dropdown first');
        return;
    }

    console.log('Sending request to unarchive_section_action.php');
    
    fetch('unarchive_section_action.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `section=${encodeURIComponent(section)}&year_graduated=${encodeURIComponent(yearGraduated)}&archive_year=${encodeURIComponent(archiveYear)}&filter=${encodeURIComponent(filter)}`
    })
    .then(response => {
        console.log('Received response, status:', response.status);
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            alert(`Successfully unarchived ${displayName}!`);
            window.location.reload();
        } else {
            throw new Error(data.error || 'Unknown error occurred');
        }
    })
    .catch(error => {
        console.error('Error during unarchive:', error);
        alert('Unarchive failed: ' + error.message);
    });
}

// Initialize based on URL parameters
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const filter = urlParams.get('filter');
    
    if (filter) {
        document.getElementById('archiveFilter').value = filter;
    }
});
</script>

<!-- Ionicons -->
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>