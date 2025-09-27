<?php
// Start session at the very beginning to prevent header errors
session_start();

include('db_connection.php');

if (!isset($_SESSION['teacher_id'])) {
    header("Location: index.php");
    exit();
}

$teacherId = $_SESSION['teacher_id'];
$teacherName = $_SESSION['teacher_name'];

// Get adviser's section info
$sectionStmt = $conn->prepare("
    SELECT s.id, s.section_name, s.grade_level, s.strand_id
    FROM section_advisers sa
    JOIN sections s ON sa.section_id = s.id
    WHERE sa.teacher_id = ?
");
$sectionStmt->bind_param("i", $teacherId);
$sectionStmt->execute();
$sectionResult = $sectionStmt->get_result();
$sectionRow = $sectionResult->fetch_assoc();

if (!$sectionRow) {
    die("No section assigned to this teacher.");
}

$adviserSection = $sectionRow['section_name'];
$gradeLevel = (int) filter_var($sectionRow['grade_level'], FILTER_SANITIZE_NUMBER_INT);
$strandId = $sectionRow['strand_id'];
$sectionId = $sectionRow['id'];

// Get all subjects for this section with ALL possible teachers
$subjectsStmt = $conn->prepare("
    SELECT s.id, s.subject_name, 
           GROUP_CONCAT(DISTINCT CONCAT(f.id, ':', f.name) SEPARATOR '|') as teachers
    FROM subject_grade_strand_assignments a
    JOIN subjects s ON a.subject_id = s.id
    LEFT JOIN teacher_subjects ts ON s.id = ts.subject_id AND ts.section_id = ?
    LEFT JOIN faculty f ON ts.teacher_id = f.id
    WHERE a.grade_level = ?
    " . ($gradeLevel >= 11 ? "AND a.strand_id = ?" : "") . "
    GROUP BY s.id
    ORDER BY s.subject_name
");

if ($gradeLevel >= 11) {
    $subjectsStmt->bind_param("iii", $sectionId, $gradeLevel, $strandId);
} else {
    $subjectsStmt->bind_param("ii", $sectionId, $gradeLevel);
}
$subjectsStmt->execute();
$subjectsResult = $subjectsStmt->get_result();
$allSubjects = $subjectsResult->fetch_all(MYSQLI_ASSOC);
$totalSubjects = count($allSubjects);

// Get currently enrolled subjects for this section
$enrolledSubjectsStmt = $conn->prepare("
    SELECT DISTINCT subject_id 
    FROM student_enrollments 
    WHERE section_id = ?
");
$enrolledSubjectsStmt->bind_param("i", $sectionId);
$enrolledSubjectsStmt->execute();
$enrolledSubjectsResult = $enrolledSubjectsStmt->get_result();
$enrolledSubjects = [];
while ($row = $enrolledSubjectsResult->fetch_assoc()) {
    $enrolledSubjects[] = $row['subject_id'];
}

// Get subjects that are fully enrolled for the entire section
$fullyEnrolledSubjectsStmt = $conn->prepare("
    SELECT DISTINCT subject_id 
    FROM student_enrollments 
    WHERE section_id = ?
    GROUP BY subject_id
    HAVING COUNT(DISTINCT student_lrn) = (
        SELECT COUNT(*) 
        FROM students 
        WHERE section = ?
    )
");
$fullyEnrolledSubjectsStmt->bind_param("is", $sectionId, $adviserSection);
$fullyEnrolledSubjectsStmt->execute();
$fullyEnrolledResult = $fullyEnrolledSubjectsStmt->get_result();
$fullyEnrolledSubjects = [];
while ($row = $fullyEnrolledResult->fetch_assoc()) {
    $fullyEnrolledSubjects[] = $row['subject_id'];
}

// Get students with enrollment status
$studentStmt = $conn->prepare("
    SELECT s.lrn, s.first_name, s.middle_name, s.last_name, s.rfid, s.section, s.grade_level,
           COUNT(e.subject_id) as enrolled_count
    FROM students s
    LEFT JOIN student_enrollments e ON s.lrn = e.student_lrn
    WHERE s.section = ?
    GROUP BY s.lrn
    ORDER BY s.last_name ASC
");
$studentStmt->bind_param("s", $adviserSection);
$studentStmt->execute();
$studentResult = $studentStmt->get_result();
$students = $studentResult->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Subject Enrollment</title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <style>
.modal {
  display: none;
  position: fixed;
  z-index: 100;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0,0,0,0.4);
}

.modal-content {
  background-color: #fefefe;
  margin: 5% auto;
  padding: 25px;
  border-radius: 10px;
  box-shadow: 0 5px 15px rgba(0,0,0,0.1);
  width: 85%;
  max-width: 750px;
}

.close {
  color: #aaa;
  float: right;
  font-size: 28px;
  font-weight: bold;
  cursor: pointer;
}

.close:hover {
  color: red;
}

/* Consistent subject grid for both modals - UPDATED for 2 columns */
.subject-grid {
  display: grid;
  grid-template-columns: 1fr 1fr; /* Two equal columns */
  gap: 12px;
  margin: 20px 0;
}

.subject-item {
  background: #f8f9fa;
  padding: 12px;
  border-radius: 6px;
  border: 1px solid #dee2e6;
  display: flex;
  align-items: center;
  transition: all 0.2s;
  min-height: 50px; /* Ensure consistent height */
}

/* For odd number of items, make the last item span both columns */
.subject-item:last-child:nth-child(odd) {
  grid-column: 1 / -1;
  justify-self: center;
  width: 50%; /* Make it take half width but centered */
}

.subject-item.disabled {
  background-color: #e9ecef;
  opacity: 0.8;
}

.subject-item label {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  cursor: pointer;
}

.subject-item.disabled label {
  cursor: not-allowed;
}

.subject-item input[type="checkbox"]:disabled {
  cursor: not-allowed;
}

.subject-item input[type="checkbox"]:disabled + span {
  color: #6c757d;
}
    
    /* Enrollment status indicators */
    .enrollment-status {
      display: inline-block;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      margin-left: 5px;
    }
    
    .enrolled {
      background-color: #28a745;
    }
    
    .not-enrolled {
      background-color: #dc3545;
    }
    
    .partial-enrolled {
      background-color: #ffc107;
    }
    
    /* Action buttons */
    .action-btn {
      padding: 8px 16px;
      background: #4e73df;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      transition: background 0.3s;
    }
    
    .action-btn:hover {
      background: #2e59d9;
    }

    .bulk-actions {
      margin: 10px 0;
    }
    
    /* Warning message - matches the image */
    .warning-message {
      background-color: #fff3cd;
      color: #856404;
      padding: 12px;
      border-radius: 4px;
      border: 1px solid #ffeeba;
      margin: 15px 0;
      display: flex;
      align-items: center;
      font-family: Arial, sans-serif;
    }
    
    .warning-icon {
      margin-right: 10px;
      font-size: 20px;
    }
    
    /* Warning modal */
    .warning-modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.4);
    }
    
    .warning-modal-content {
      background-color: #fefefe;
      margin: 15% auto;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.2);
      width: 80%;
      max-width: 500px;
      text-align: center;
    }
    
    .warning-modal-buttons {
      margin-top: 20px;
      display: flex;
      justify-content: center;
      gap: 10px;
    }
    
    .warning-modal-btn {
      padding: 8px 16px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }
    
    .warning-modal-btn.ok {
      background-color: #4e73df;
      color: white;
    }
  </style>
</head>

<body>
<?php include('adviser_sidebar.php'); ?>

<!-- Main Content -->
<div class="main-content">
  <h2>List of Students in Section: <?= htmlspecialchars($adviserSection) ?></h2>

  <!-- Bulk Action -->
  <div class="bulk-actions">
    <button class="action-btn" onclick="openSectionEnrollmentModal()">Enroll Section to Subjects</button>
  </div>

  <!-- Search Bar -->
  <div class="search-container">
    <form id="searchForm" onsubmit="return false;">
      <input type="text" id="searchInput" placeholder="Search by student name..." />
      <button type="submit" class="action-btn">Search</button>
    </form>
  </div>

<!-- Student Table -->
<table class="student-table" id="studentTable">
  <thead>
    <tr>
      <th>LRN</th>
      <th>Name</th>
      <th>Enrollment Status</th>
      <th>Action</th>
    </tr>
  </thead>
  <tbody id="studentTableBody">
    <?php foreach ($students as $row): 
        $fullName = ucfirst(strtolower($row['last_name'])) . ', ' . ucfirst(strtolower($row['first_name']));
        if (!empty($row['middle_name'])) {
            $fullName .= ' ' . strtoupper(substr($row['middle_name'], 0, 1)) . '.';
        }
        
        // Determine enrollment status
        if ($row['enrolled_count'] == 0) {
            $statusClass = 'not-enrolled';
            $statusText = 'Not Enrolled';
        } elseif ($row['enrolled_count'] == $totalSubjects) {
            $statusClass = 'enrolled';
            $statusText = 'Fully Enrolled';
        } else {
            $statusClass = 'partial-enrolled';
            $statusText = 'Partially Enrolled';
        }
    ?>
      <tr>
        <td><?= htmlspecialchars($row['lrn']) ?></td>
        <td><?= htmlspecialchars($fullName) ?></td>
        <td>
          <span class="enrollment-status <?= $statusClass ?>" title="<?= $statusText ?>"></span>
          <?= $statusText ?>
        </td>
        <td>
          <!-- CHANGE THIS LINE: Pass RFID to the check function -->
          <button class="action-btn" onclick="checkRfidBeforeOpen('<?= $row['lrn'] ?>', '<?= htmlspecialchars($fullName) ?>', '<?= $row['rfid'] ?>')">Manage Enrollment</button>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<!-- Subject Enrollment Modal -->
<div id="subjectModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal()">&times;</span>
    <h3>Manage Enrollment</h3>
    <form id="enrollForm">
      <input type="hidden" id="modalLRN" name="lrn" />
      <div id="subjectList" class="subject-grid"></div>
      <div style="margin-top: 20px;">
        <label><input type="checkbox" id="selectAll" onchange="toggleSelectAll()" /> Select All</label>
        <button type="submit" class="action-btn" style="float: right;">Update Enrollment</button>
      </div>
    </form>
  </div>
</div>

<!-- Section Enrollment Modal -->
<div id="sectionModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeSectionModal()">&times;</span>
    <h3>Enroll Section to Subjects</h3>
    <form id="sectionEnrollForm">
      <div class="subject-grid">
        <?php foreach ($allSubjects as $subject): 
          $isFullyEnrolled = in_array($subject['id'], $fullyEnrolledSubjects);
        ?>
          <div class="subject-item <?= $isFullyEnrolled ? 'disabled' : '' ?>">
            <label>
              <input type="checkbox" name="sectionSubjects[]" value="<?= $subject['id'] ?>" 
                     <?= $isFullyEnrolled ? 'checked disabled' : '' ?>>
              <?= htmlspecialchars($subject['subject_name']) ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top: 20px;">
        <label><input type="checkbox" id="selectAllSection" onchange="toggleSelectAllSection()" /> Select All</label>
        <button type="submit" class="action-btn" style="float: right;">Enroll Section</button>
      </div>
    </form>
  </div>
</div>

<!-- Warning Modal for RFID Check -->
<div id="warningModal" class="warning-modal">
  <div class="warning-modal-content">
    <p id="warningText"></p>
    <div class="warning-modal-buttons">
      <button class="warning-modal-btn ok" onclick="closeWarningModal()">OK</button>
    </div>
  </div>
</div>

<script>
// Global variables
const gradeLevel = <?= $gradeLevel ?>;
const sectionId = <?= $sectionId ?>;
const totalSubjects = <?= $totalSubjects ?>;
const adviserSection = "<?= $adviserSection ?>";


// Check RFID before opening modal - UPDATED
function checkRfidBeforeOpen(lrn, studentName, rfid) {
  // Check if student has RFID
  const hasRfid = (rfid !== '' && rfid !== null && rfid !== 'Not Assigned ✓');
  
  if (!hasRfid) {
    // Show warning modal instead of subject modal
    document.getElementById("warningText").textContent = "RFID must be assigned first for " + studentName;
    document.getElementById("warningModal").style.display = "block";
  } else {
    // Student has RFID, open the subject modal
    openSubjectModal(lrn, studentName);
  }
}

function openSubjectModal(lrn, studentName) {
  document.getElementById("modalLRN").value = lrn;
  document.querySelector("#subjectModal h3").textContent = `Manage Enrollment for ${studentName}`;
  document.getElementById("subjectList").innerHTML = "Loading subjects...";
  
  fetch(`adviser_fetch_subjects.php?grade_level=${gradeLevel}&section_id=${sectionId}&lrn=${lrn}`)
    .then(response => response.text())
    .then(data => {
      // Wrap the returned data in the grid structure
      document.getElementById("subjectList").innerHTML = data;
      
      // After loading, disable already enrolled subjects
      const checkboxes = document.querySelectorAll('#subjectList input[type="checkbox"]');
      checkboxes.forEach(checkbox => {
        if (checkbox.checked) {
          checkbox.disabled = true;
          checkbox.closest('.subject-item').classList.add('disabled');
          checkbox.closest('.subject-item').title = 'This subject is already enrolled and cannot be unselected';
        }
      });
      
      // Update select all checkbox based on checked status
      updateSelectAllState();
    });

  document.getElementById("subjectModal").style.display = "block";
}

function openSectionEnrollmentModal() {
  document.getElementById("sectionModal").style.display = "block";
  refreshSectionModal();
}

function closeModal() {
  document.getElementById("subjectModal").style.display = "none";
}

function closeWarningModal() {
  document.getElementById("warningModal").style.display = "none";
}

function closeSectionModal() {
  document.getElementById("sectionModal").style.display = "none";
}

// Form submissions - UPDATED VERSION
document.getElementById("enrollForm").addEventListener("submit", function(e) {
  e.preventDefault();
  
  // Check if student has RFID (client-side validation)
  const lrn = document.getElementById("modalLRN").value;
  
  // Show loading state
  const submitBtn = e.target.querySelector('button[type="submit"]');
  const originalText = submitBtn.textContent;
  submitBtn.textContent = "Processing...";
  submitBtn.disabled = true;
  
  // We need to get the RFID status from the server to be sure
  fetch(`check_rfid_status.php?lrn=${lrn}`)
    .then(response => {
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.json();
    })
    .then(result => {
      if (!result.hasRfid) {
        alert("RFID must be assigned first before enrolling this student.");
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
        return;
      }
      
      // If student has RFID, proceed with enrollment
      const selectedSubjects = Array.from(document.querySelectorAll('#subjectList input[type="checkbox"]:checked'))
        .map(checkbox => checkbox.value);

      if (selectedSubjects.length === 0) {
        alert("Please select at least one subject");
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
        return;
      }

      return fetch("process_enrollment.php", {
        method: "POST",
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          lrn: lrn,
          subjects: selectedSubjects,
          section_id: sectionId
        })
      });
    })
    .then(response => {
      if (!response) return; // No response means we already handled an error
      
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.json();
    })
    .then(result => {
      if (result.status === 'success') {
        alert(result.message);
        closeModal();
        // Instead of reloading the page, update the status dynamically
        updateStudentEnrollmentStatus();
      } else {
        alert(result.message + (result.warning ? "\n" + result.warning : ""));
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert("An error occurred during enrollment: " + error.message);
    })
    .finally(() => {
      submitBtn.textContent = originalText;
      submitBtn.disabled = false;
    });
});

function refreshSectionModal() {
  // Fetch current FULL section enrollments (where all students are enrolled)
  fetch(`adviser_fetch_section_subjects.php?section_id=${sectionId}`)
    .then(response => response.json())
    .then(data => {
      const checkboxes = document.querySelectorAll('#sectionModal input[type="checkbox"]');
      checkboxes.forEach(checkbox => {
        const subjectId = parseInt(checkbox.value);
        
        // Reset all checkboxes first
        checkbox.checked = false;
        checkbox.disabled = false;
        checkbox.closest('.subject-item').classList.remove('disabled');
        checkbox.closest('.subject-item').title = '';
        
        // Check and disable if this subject is fully enrolled
        if (data.enrolled_subjects.includes(subjectId)) {
          checkbox.checked = true;
          checkbox.disabled = true;
          checkbox.closest('.subject-item').classList.add('disabled');
          checkbox.closest('.subject-item').title = 'This subject is already enrolled for ALL students and cannot be unselected';
        }
      });
      
      // Update select all checkbox state
      updateSelectAllSectionState();
    })
    .catch(error => {
      console.error('Error refreshing section modal:', error);
    });
}

// Add this function to update student enrollment status dynamically
function updateStudentEnrollmentStatus() {
    fetch(`update_enrollment_status.php?section_id=${sectionId}&grade_level=${gradeLevel}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update each student's enrollment status in the table
                data.students.forEach(student => {
                    // Find the row by LRN
                    const rows = document.querySelectorAll('#studentTableBody tr');
                    for (let row of rows) {
                        if (row.cells[0].textContent === student.lrn) {
                            const statusCell = row.cells[2];
                            const statusClass = student.status_class;
                            const statusText = student.status_text;
                            
                            statusCell.innerHTML = `
                                <span class="enrollment-status ${statusClass}" title="${statusText}"></span>
                                ${statusText}
                            `;
                            break;
                        }
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error updating enrollment status:', error);
        });
}

// Modify the bulk enrollment success handler
document.getElementById("sectionEnrollForm").addEventListener("submit", function(e) {
    e.preventDefault();
    // Only get checkboxes that are not disabled (not already enrolled)
    const selectedSubjects = Array.from(document.querySelectorAll('#sectionModal input[type="checkbox"]:checked:not(:disabled)'))
        .map(checkbox => checkbox.value);

    // Also include subjects that are already enrolled (disabled checkboxes)
    const enrolledSubjects = Array.from(document.querySelectorAll('#sectionModal input[type="checkbox"]:disabled'))
        .map(checkbox => checkbox.value);

    // Combine both arrays
    const allSubjects = [...enrolledSubjects, ...selectedSubjects];

    if (allSubjects.length === 0) {
        alert("Please select at least one subject");
        return;
    }

    if (confirm("Are you sure you want to enroll ALL students in this section to the selected subjects?")) {
        // Show loading indicator
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = "Processing...";
        submitBtn.disabled = true;

        fetch("process_bulk_enrollment.php", {
            method: "POST",
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                section_id: sectionId,
                subject_ids: allSubjects
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(result => {
            if (result.success) {
                alert(`Successfully enrolled ${result.students_count} students to ${result.subjects_count} subjects.`);
                
                // Refresh the section modal to show the updated enrollment status
                refreshSectionModal();
                
                // Update student enrollment status without page reload
                updateStudentEnrollmentStatus();
                
            } else {
                alert(result.message + (result.warning ? "\n" + result.warning : ""));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("An error occurred during bulk enrollment: " + error.message);
        })
        .finally(() => {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    }
});

// Helper functions
function toggleSelectAll() {
  const selectAll = document.getElementById("selectAll");
  const checkboxes = document.querySelectorAll('#subjectList input[type="checkbox"]:not(:disabled)');
  checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

function toggleSelectAllSection() {
  const selectAll = document.getElementById("selectAllSection");
  const checkboxes = document.querySelectorAll('#sectionModal input[type="checkbox"]:not(:disabled)');
  checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

function updateSelectAllState() {
  const checkboxes = document.querySelectorAll('#subjectList input[type="checkbox"]:not(:disabled)');
  const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
  const selectAll = document.getElementById("selectAll");
  
  if (checkedCount === checkboxes.length) {
    selectAll.checked = true;
    selectAll.indeterminate = false;
  } else if (checkedCount > 0) {
    selectAll.checked = false;
    selectAll.indeterminate = true;
  } else {
    selectAll.checked = false;
    selectAll.indeterminate = false;
  }
}

function updateSelectAllSectionState() {
  const checkboxes = document.querySelectorAll('#sectionModal input[type="checkbox"]:not(:disabled)');
  const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
  const selectAll = document.getElementById("selectAllSection");
  
  if (checkedCount === checkboxes.length) {
    selectAll.checked = true;
    selectAll.indeterminate = false;
  } else if (checkedCount > 0) {
    selectAll.checked = false;
    selectAll.indeterminate = true;
  } else {
    selectAll.checked = false;
    selectAll.indeterminate = false;
  }
}

function searchStudent() {
  const input = document.getElementById("searchInput").value.toUpperCase();
  const rows = document.querySelectorAll("#studentTableBody tr");

  rows.forEach(row => {
    const nameCell = row.querySelectorAll("td")[1];
    if (nameCell) {
      const studentName = nameCell.textContent.toUpperCase();
      row.style.display = studentName.includes(input) ? "" : "none";
    }
  });
}

// Initialize
document.addEventListener("DOMContentLoaded", () => {
  document.getElementById("searchInput").addEventListener("input", searchStudent);
  // Initialize section modal checkboxes on page load
  updateSelectAllSectionState();
});

</script>

<!-- Ionicons -->
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

</body>
</html>