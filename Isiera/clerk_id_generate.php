<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection first
require_once 'db_connection.php';

if (!$conn) {
    die("Database connection failed");
}

if (!isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['user_role'];
$allowed_pages_for_counselor = ['student_verification.php', 'student_details.php', 'id_generation.php'];
$current_page = basename($_SERVER['PHP_SELF']);

if ($role === 'counselor' && !in_array($current_page, $allowed_pages_for_counselor)) {
    echo "Access denied.";
    exit;
}

$student_type = $_GET['type'] ?? '';
$gradelevel = $_GET['gradelevel'] ?? '';
$section = $_GET['section'] ?? '';

// Now safe to escape strings
$student_type_safe = mysqli_real_escape_string($conn, $student_type);
$gradelevel_safe = mysqli_real_escape_string($conn, $gradelevel);
$section_safe = mysqli_real_escape_string($conn, $section);

$sql = "SELECT lrn, first_name, middle_name, last_name, email, rfid, created_at, student_type, section, grade_level
        FROM students
        WHERE 1=1";

if (!empty($student_type_safe)) $sql .= " AND student_type = '$student_type_safe'";
if (!empty($gradelevel_safe)) $sql .= " AND grade_level = '$gradelevel_safe'";
if (!empty($section_safe)) $sql .= " AND section = '$section_safe'";

$sql .= " ORDER BY LOWER(last_name), LOWER(first_name), LOWER(middle_name)";

$result = mysqli_query($conn, $sql);
if (!$result) die("Query failed: " . mysqli_error($conn));

$students = [];
while ($row = mysqli_fetch_assoc($result)) {
    $students[] = $row;
}

function capitalizeNamePart($name) {
    return mb_convert_case($name, MB_CASE_TITLE, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>ID Generation</title>
    <link rel="stylesheet" href="assets/css/style.css" />
    <link rel="stylesheet" href="assets/css/id_template.css" />
    <link rel="stylesheet" href="assets/css/rfid.css" />
    <style>
    .disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .tooltip {
        position: absolute;
        background-color: #333;
        color: white;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 14px;
        margin-top: 5px;
        z-index: 1;
        display: none;
    }
    
    .generate-btn:hover + .tooltip {
        display: block;
    }
</style>
</head>
<body>

<?php include('clerk_sidebar.php'); ?>

<div class="main-content">
    <h2>Student List</h2>

    <div class="search-container">
        <form id="searchForm" onsubmit="return handleSearch(event)">
            <input type="text" id="searchInput" placeholder="Search by LRN or Name...">
            <button type="submit">Search</button>
        </form>
    </div>

    <table class="student-table">
        <thead>
            <tr>
                <th>LRN</th>
                <th>Name</th>
                <th>Email</th>
                <th>RFID</th>
                <th>Registered Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="studentTableBody">
            <?php if (count($students) > 0): ?>
                <?php foreach ($students as $row): ?>
                    <?php 
                        $lastName = capitalizeNamePart($row['last_name']);
                        $firstName = capitalizeNamePart($row['first_name']);
                        $middleInitial = $row['middle_name'] ? strtoupper(mb_substr($row['middle_name'], 0, 1)) . '.' : '';
                        $full_name = "$lastName, $firstName" . ($middleInitial ? " $middleInitial" : '');
                    ?>
                    <tr class="student-row">
                        <td><?= htmlspecialchars($row['lrn']) ?></td>
                        <td><?= htmlspecialchars($full_name) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td>
                            <span class="rfid-value" data-lrn="<?= htmlspecialchars($row['lrn']) ?>">
                                <?= htmlspecialchars($row['rfid'] ?? 'Not Assigned') ?>
                            </span>
                            <button class="edit-rfid-btn" data-lrn="<?= htmlspecialchars($row['lrn']) ?>">✏️</button>
                        </td>
                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                        <td>
                            <?php if (!empty($row['rfid'])): ?>
                                <button class="generate-btn" data-lrn="<?= htmlspecialchars($row['lrn']) ?>">Generate</button>
                            <?php else: ?>
                                <button class="generate-btn disabled" disabled title="ID Print">Generate</button>
                                <div class="tooltip">RFID must be assigned first</div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr id="noDataRow" class="no-results-row"><td colspan="6">No data available.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- RFID Edit Modal -->
<div id="rfidModal" class="rfid-modal">
    <div class="rfid-modal-content">
        <span class="rfid-close" onclick="closeRfidModal()">&times;</span>
        <h3>Assign/Edit RFID</h3>
        <form id="rfidForm">
            <input type="hidden" id="rfidLRN">
            <label for="rfidInput">RFID Number:</label>
            <input type="text" id="rfidInput" required pattern="\d{10,12}" title="Please enter 10 to 12 digits only" maxlength="12" inputmode="numeric">
            <button type="submit">Save RFID</button>
        </form>
    </div>
</div>

<!-- ID Modal -->
<div id="idModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <iframe id="idFrame" width="100%" height="500px" style="border: none;"></iframe>
    </div>
</div>

<script>
// Search functionality
function handleSearch(event) {
    event.preventDefault();
    searchStudent();
    return false;
}

function searchStudent() {
    const input = document.getElementById("searchInput").value.toLowerCase().trim();
    const rows = document.querySelectorAll("#studentTableBody .student-row");
    let hasMatch = false;
    
    // First, show all rows if search is empty
    if (input === "") {
        rows.forEach(row => {
            row.style.display = "";
        });
        
        // Show/hide the initial no data row
        const noDataRow = document.getElementById("noDataRow");
        if (noDataRow) {
            noDataRow.style.display = rows.length === 0 ? "" : "none";
        }
        
        // Remove any no match row if it exists
        const noMatchRow = document.getElementById("noMatchRow");
        if (noMatchRow) noMatchRow.remove();
        
        return;
    }
    
    // Search through rows
    rows.forEach(row => {
        const lrn = row.querySelector("td:nth-child(1)")?.textContent.toLowerCase() || '';
        const name = row.querySelector("td:nth-child(2)")?.textContent.toLowerCase() || '';
        const email = row.querySelector("td:nth-child(3)")?.textContent.toLowerCase() || '';
        
        if (lrn.includes(input) || name.includes(input) || email.includes(input)) {
            row.style.display = "";
            hasMatch = true;
        } else {
            row.style.display = "none";
        }
    });
    
    // Handle no results
    const tbody = document.getElementById("studentTableBody");
    const noMatchRow = document.getElementById("noMatchRow");
    const noDataRow = document.getElementById("noDataRow");
    
    if (noMatchRow) noMatchRow.remove();
    
    if (!hasMatch) {
        // Hide the initial no data row if it exists
        if (noDataRow) noDataRow.style.display = "none";
        
        // Create or show no match row
        if (rows.length > 0) {
            const newRow = document.createElement("tr");
            newRow.id = "noMatchRow";
            newRow.className = "no-results-row";
            newRow.innerHTML = `<td colspan="6">No matching results.</td>`;
            tbody.appendChild(newRow);
        }
    }
}

document.addEventListener("DOMContentLoaded", function () {
    // Initialize search functionality
    document.getElementById("searchInput").addEventListener("input", searchStudent);
    
    // Generate ID Modal
    const generateButtons = document.querySelectorAll(".generate-btn");
    const modal = document.getElementById("idModal");
    const idFrame = document.getElementById("idFrame");
    const closeBtn = document.querySelector(".close");

    generateButtons.forEach(button => {
        button.addEventListener("click", function () {
            if (this.classList.contains("disabled")) {
                alert("RFID must be assigned first before generating an ID.");
                return;
            }

            const lrn = this.getAttribute("data-lrn");
            const rfidValue = document.querySelector(`.rfid-value[data-lrn="${lrn}"]`).textContent.trim();
            
            if (!rfidValue || rfidValue === 'Not Assigned') {
                alert("RFID must be assigned first before generating an ID.");
                return;
            }
            
            if (lrn) {
                idFrame.src = "id_template.php?lrn=" + lrn;
                modal.style.display = "flex";
            }
        });
    });

    closeBtn.addEventListener("click", () => modal.style.display = "none");
    window.addEventListener("click", event => {
        if (event.target === modal) modal.style.display = "none";
    });

    // Edit RFID
    const editButtons = document.querySelectorAll(".edit-rfid-btn");
    const rfidModal = document.getElementById("rfidModal");
    const rfidInput = document.getElementById("rfidInput");
    const rfidLRN = document.getElementById("rfidLRN");

    editButtons.forEach(button => {
        button.addEventListener("click", function () {
            const lrn = this.getAttribute("data-lrn");
            const rfidCell = this.parentElement.querySelector(".rfid-value").textContent.trim();
            rfidInput.value = (rfidCell !== 'Not Assigned') ? rfidCell : '';
            rfidLRN.value = lrn;
            rfidModal.style.display = "flex";
        });
    });

    // RFID Form Submit
    document.getElementById("rfidForm").addEventListener("submit", function (e) {
        e.preventDefault();
        const lrn = rfidLRN.value;
        const rfid = rfidInput.value.trim();

        if (!/^\d{10,12}$/.test(rfid)) {
            alert("RFID must be a numeric value with 10 to 12 digits only.");
            return;
        }

        fetch("update_rfid.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `lrn=${encodeURIComponent(lrn)}&rfid=${encodeURIComponent(rfid)}`
        })
        .then(response => response.text())
        .then(result => {
            if (result === "success") {
                document.querySelector(`.rfid-value[data-lrn="${lrn}"]`).textContent = rfid;
                closeRfidModal();
                
                // Enable generate button if it was disabled
                const generateBtn = document.querySelector(`.generate-btn[data-lrn="${lrn}"]`);
                if (generateBtn) {
                    generateBtn.classList.remove("disabled");
                    generateBtn.disabled = false;
                    generateBtn.title = "";
                    
                    // Remove tooltip if exists
                    const tooltip = generateBtn.nextElementSibling;
                    if (tooltip && tooltip.classList.contains("tooltip")) {
                        tooltip.remove();
                    }
                }
            } else {
                alert("Failed to update RFID.");
            }
        });
    });
});

function closeRfidModal() {
    document.getElementById("rfidModal").style.display = "none";
}
</script>

<!-- Ionicons -->
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

</body>
</html>