<?php
// Include database connection
include('db_connection.php');

// Check if this is an AJAX request for student details
if (isset($_GET['lrn']) && isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    $lrn = mysqli_real_escape_string($conn, $_GET['lrn']);
    $query = "SELECT * FROM students WHERE lrn = '$lrn'";
    $result = mysqli_query($conn, $query);

    if (!$result) {
        echo json_encode(['error' => 'Database error: ' . mysqli_error($conn)]);
        exit;
    }

    if (mysqli_num_rows($result) === 0) {
        echo json_encode(['error' => 'Student not found']);
        exit;
    }

    $student = mysqli_fetch_assoc($result);
    echo json_encode($student);
    exit;
}

// Regular page request - get filter parameters from URL
$gradeLevel = isset($_GET['grade_level']) ? $_GET['grade_level'] : '';
$studentType = isset($_GET['student_type']) ? $_GET['student_type'] : '';
$section = isset($_GET['section']) ? $_GET['section'] : '';

// Build SQL query with filters
$query = "SELECT lrn, 
    CONCAT(
        UPPER(LEFT(last_name, 1)), LOWER(SUBSTRING(last_name, 2)), ', ',
        UPPER(LEFT(first_name, 1)), LOWER(SUBSTRING(first_name, 2)), ' ',
        UPPER(LEFT(middle_name, 1)), '.'
    ) AS fullname, 
    email, student_type 
    FROM students
    WHERE 1=1";

if ($gradeLevel) {
    $query .= " AND grade_level = '" . mysqli_real_escape_string($conn, $gradeLevel) . "'";
}
if ($studentType) {
    $query .= " AND student_type = '" . mysqli_real_escape_string($conn, $studentType) . "'";
}
if ($section) {
    $query .= " AND section = '" . mysqli_real_escape_string($conn, $section) . "'";
}

$query .= " ORDER BY last_name ASC, first_name ASC, middle_name ASC";

$result = mysqli_query($conn, $query);

// Fetch data
$students = [];
if (mysqli_num_rows($result) > 0) {
    $students = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Details</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/profile.css">
</head>
<body>

<?php include('sidebar.php'); ?>

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

    <table class="student-table" id="studentTable">
        <thead>
            <tr>
                <th>LRN</th>
                <th>Full Name</th>
                <th>Email</th>
                <th class="actions-header" style="position: relative;">
                    <div class="header-content">
                        <span>Actions</span>
                        <ion-icon name="ellipsis-vertical-outline" class="header-icon" id="headerDropdownToggle"></ion-icon>
                    </div>
                    <div id="headerDropdownMenu" class="dropdown-menu">
                        <div class="dropdown-item" onclick="archiveAll()">Archive</div>
                    </div>
                </th>
            </tr>
        </thead>
        <tbody id="studentTableBody">
            <?php if (!empty($students)): ?>
                <?php foreach ($students as $student): ?>
                    <tr data-type="<?= strtolower($student['student_type']) ?>">
                        <td><?= $student['lrn'] ?></td>
                        <td><?= $student['fullname'] ?></td>
                        <td><?= $student['email'] ?></td>
                        <td>
                            <button class='edit' title="Edit" onclick='showStudentModal("<?= $student['lrn'] ?>", "edit")'>
                                <ion-icon name="create-outline"></ion-icon>
                            </button>
                            <button class='archive' title="Archive" onclick='archiveStudent("<?= $student['lrn'] ?>")'>
                                <ion-icon name="archive-outline"></ion-icon>
                            </button>
                            <button class='view' title="View" onclick='showStudentModal("<?= $student['lrn'] ?>", "view")'>
                                <ion-icon name='eye-outline'></ion-icon>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr id="noDataRow"><td colspan="4">No data available.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal for Student Details -->
<div id="studentModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        
        <form id="studentForm" enctype="multipart/form-data" onsubmit="return submitStudentForm(event)">
            <input type="hidden" id="modalMode" name="mode" value="view">
            <input type="hidden" name="lrn" id="modalLRN">
            
            <!-- Personal Information Section -->
            <h2>Personal Information</h2>
            <div class="form-row">
                <div class="form-column">
                    <label>LRN:</label>
                    <input type="text" id="modalLRNDisplay" readonly>
                </div>
                <div class="form-column">
                    <label>First Name:</label>
                    <input type="text" id="modalFirstName" name="first_name">
                </div>
                <div class="form-column">
                    <label>Middle Name:</label>
                    <input type="text" id="modalMiddleName" name="middle_name">
                </div>
                <div class="form-column">
                    <label>Last Name:</label>
                    <input type="text" id="modalLastName" name="last_name">
                </div>
            </div>

            <div class="form-row">
                <div class="form-column">
                    <label>Date of Birth:</label>
                    <input type="text" id="modalDOB" name="date_of_birth">
                </div>
                <div class="form-column">
                    <label>Gender:</label>
                    <select id="modalGender" name="gender">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                      </select>
                </div>
                <div class="form-column">
                    <label>Citizenship:</label>
                    <input type="text" id="modalCitizenship" name="citizenship">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-column">
                    <label>Contact Number:</label>
                    <input type="text" id="modalContact" name="contact_number">
                </div>
                <div class="form-column">
                    <label>Email Address:</label>
                    <input type="email" id="modalEmail" name="email">
                </div>
                <div class="form-column">
                    <label>Address:</label>
                    <input type="text" id="modalAddress" name="address">
                </div>
            </div>

            <!-- Guardian Information Section -->
            <h2>Parent/Guardian Information</h2>
            <div class="form-row">
                <div class="form-column">
                    <label>Guardian Name:</label>
                    <input type="text" id="modalGuardianName" name="guardian_name">
                </div>
                <div class="form-column">
                    <label>Contact Number:</label>
                    <input type="text" id="modalGuardianContact" name="guardian_contact">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-column">
                    <label>Guardian Address:</label>
                    <input type="text" id="modalGuardianAddress" name="guardian_address">
                </div>
                <div class="form-column">
                    <label>Relationship:</label>
                    <input type="text" id="modalGuardianRelationship" name="guardian_relationship">
                </div>
            </div>

            <!-- Academic Background -->
            <h2>Academic Background</h2>
            <div class="form-row">
                <div class="form-column">
                    <label>Elementary School:</label>
                    <input type="text" id="modalElemSchool" name="elementary_school">
                </div>
                <div class="form-column">
                    <label>Year Graduated:</label>
                    <input type="text" id="modalYearGraduated" name="year_graduated">
                </div>
            </div>

            <!-- Documents Section -->
            <h2>Documents</h2>
            <div class="grid-container">
                <div class="form-column">
                    <label>Recent ID Photo:</label>
                    <div class="image-upload-container">
                        <img id="modalIDPhoto" class="doc-img" src="uploads/default.png" 
                             onclick="handleImageClick('id_photo', this)">
                        <input type="file" id="id_photo" name="id_photo" accept="image/*" style="display:none;" 
                               onchange="previewImage(this, 'modalIDPhoto')">
                    </div>
                </div>
                <div class="form-column">
                    <label>Birth Certificate:</label>
                    <div class="image-upload-container">
                        <img id="modalBirthCert" class="doc-img" src="uploads/default.png" 
                             onclick="handleImageClick('birth_certificate', this)">
                        <input type="file" id="birth_certificate" name="birth_certificate" accept="image/*" style="display:none;" 
                               onchange="previewImage(this, 'modalBirthCert')">
                    </div>
                </div>
                <div class="form-column">
                    <label>Good Moral Certificate:</label>
                    <div class="image-upload-container">
                        <img id="modalGoodMoral" class="doc-img" src="uploads/default.png" 
                             onclick="handleImageClick('good_moral', this)">
                        <input type="file" id="good_moral" name="good_moral" accept="image/*" style="display:none;" 
                               onchange="previewImage(this, 'modalGoodMoral')">
                    </div>
                </div>
                <div class="form-column">
                    <label>Student Signature:</label>
                    <div class="image-upload-container">
                        <img id="modalSignatureImage" class="doc-img" src="uploads/default.png" 
                             onclick="handleImageClick('student_signature', this)">
                        <input type="file" id="student_signature" name="student_signature" accept="image/*" style="display:none;" 
                               onchange="previewImage(this, 'modalSignatureImage')">
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <button type="submit" class="save-btn" id="modalSaveBtn" style="display:none;">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function searchStudent() {
    const input = document.getElementById("searchInput").value.toUpperCase();
    const rows = document.querySelectorAll("#studentTableBody tr");
    let hasMatch = false;

    rows.forEach(row => {
        if (row.id === "noDataRow") {
            row.style.display = "none";
            return;
        }

        const lrn = row.cells[0].textContent.toUpperCase();
        const name = row.cells[1].textContent.toUpperCase();
        const match = lrn.includes(input) || name.includes(input);
        row.style.display = match ? "" : "none";
        if (match) hasMatch = true;
    });

    const existingNoDataRow = document.getElementById("noDataRow");
    if (existingNoDataRow) existingNoDataRow.remove();

    if (!hasMatch) {
        const tbody = document.getElementById("studentTableBody");
        const newRow = document.createElement("tr");
        newRow.id = "noDataRow";
        newRow.innerHTML = `<td colspan='4'>No matching results.</td>`;
        tbody.appendChild(newRow);
    }
}

document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("searchInput").addEventListener("input", searchStudent);
});

function archiveStudent(lrn) {
    if (!confirm(`Are you sure you want to archive student LRN: ${lrn}?`)) return;

    fetch('archive_student_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `lrn=${encodeURIComponent(lrn)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Student archived successfully!');
            location.reload();
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
    event.stopPropagation();
    dropdownMenu.style.display = (dropdownMenu.style.display === 'block') ? 'none' : 'block';
});

document.addEventListener('click', function() {
    dropdownMenu.style.display = 'none';
});

function archiveAll() {
    window.location.href = 'archive_students.php';
}

function capitalizeFirstLetter(str) {
    if (!str) return "";
    return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
}

function showStudentModal(lrn, mode) {
    // Set the mode (view or edit)
    document.getElementById('modalMode').value = mode;
    
    // Fetch student details
    fetch('details_student.php?lrn=' + lrn + '&ajax=1')
        .then(response => response.json())
        .then(student => {
            if (student.error) {
                alert(student.error);
                return;
            }

            // Populate the modal with student data
            document.getElementById("modalLRN").value = student.lrn || "";
            document.getElementById("modalLRNDisplay").value = student.lrn || "";
            document.getElementById("modalFirstName").value = student.first_name || "";
            document.getElementById("modalMiddleName").value = student.middle_name || "";
            document.getElementById("modalLastName").value = student.last_name || "";
            document.getElementById("modalDOB").value = student.date_of_birth || "";
            document.getElementById("modalGender").value = student.gender || "";
            document.getElementById("modalCitizenship").value = student.citizenship || "";
            document.getElementById("modalContact").value = student.contact_number || "";
            document.getElementById("modalEmail").value = student.email || "";
            document.getElementById("modalAddress").value = student.address || "";
            document.getElementById("modalGuardianName").value = student.guardian_name || "";
            document.getElementById("modalGuardianContact").value = student.guardian_contact || "";
            document.getElementById("modalGuardianAddress").value = student.guardian_address || "";
            document.getElementById("modalGuardianRelationship").value = student.guardian_relationship || "";
            document.getElementById("modalElemSchool").value = student.elementary_school || "";
            document.getElementById("modalYearGraduated").value = student.year_graduated || "";

    // Assign image paths correctly
    document.getElementById("modalIDPhoto").src = student.id_photo && student.id_photo.trim() !== "" 
        ? student.id_photo  // Use as is since it already includes "uploads/"
        : "uploads/default.png";

    document.getElementById("modalBirthCert").src = student.birth_certificate && student.birth_certificate.trim() !== "" 
        ? student.birth_certificate
        : "uploads/default.jpg";

    document.getElementById("modalGoodMoral").src = student.good_moral && student.good_moral.trim() !== "" 
        ? student.good_moral
        : "uploads/default.jpg";

    document.getElementById("modalSignatureImage").src = student.student_signature && student.student_signature.trim() !== "" 
        ? student.student_signature
        : "uploads/default.jpg";
            // Set edit mode for all fields
            const inputs = document.querySelectorAll('#studentForm input:not([type="hidden"]), #studentForm select');
            inputs.forEach(input => {
                input.readOnly = mode === 'view';
                if (input.tagName === 'SELECT') {
                    input.disabled = mode === 'view';
                }
            });

            // Set edit mode for images
            const images = document.querySelectorAll('.doc-img');
            images.forEach(img => {
                if (mode === 'edit') {
                    img.classList.add('editable');
                } else {
                    img.classList.remove('editable');
                }
            });

            // Show/hide save button based on mode
            document.getElementById('modalSaveBtn').style.display = mode === 'edit' ? 'block' : 'none';
            
            // Show the modal
            document.getElementById("studentModal").style.display = "block";
        })
        .catch(error => {
            console.error('Error fetching student details:', error);
            alert('Error loading student details');
        });
}

function handleImageClick(inputId, imgElement) {
    if (document.getElementById('modalMode').value === 'edit') {
        document.getElementById(inputId).click();
    }
}

function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(previewId).src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function submitStudentForm(event) {
    event.preventDefault();
    
    const form = document.getElementById('studentForm');
    const formData = new FormData(form);
    const saveBtn = document.getElementById('modalSaveBtn');
    
    // Show loading state
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';

    fetch('update_student.php', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        const data = await response.json();
        
        // Check for application-level success
        if (data.success) {
            return data;
        } else {
            throw new Error(data.error || 'Update failed');
        }
    })
    .then(data => {
        alert(data.message || 'Student updated successfully!');
        closeModal();
        location.reload();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error: ' + error.message);
    })
    .finally(() => {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save';
    });
}

// Function to close modal
function closeModal() {
    document.getElementById("studentModal").style.display = "none";
}
</script>

<!-- Ionicons -->
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

</body>
</html>