<?php
include 'db_connection.php';

// Handle direct promotion for Grade 10 and 12
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['direct_promote'])) {
    $current_grade = $_POST['current_grade'];
    $current_section = $_POST['current_section'];
    
    // Determine the status based on grade level
    $status = ($current_grade == 'Grade 10') ? 'JHS Graduate' : 'SHS Graduate';
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // 1. Get all students in the section
        $stmt = $conn->prepare("SELECT * FROM students WHERE grade_level = ? AND section = ?");
        $stmt->bind_param("ss", $current_grade, $current_section);
        $stmt->execute();
        $result = $stmt->get_result();
        $students = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
// 2. Insert into archived_students table using LRN as primary identifier
$insert_stmt = $conn->prepare("INSERT INTO archived_students 
                              (rfid, lrn, first_name, middle_name, last_name, email, section, 
                               student_type, gender, date_of_birth, contact_number, address, 
                               citizenship, elementary_school, year_graduated, guardian_name, 
                               guardian_contact, guardian_address, guardian_relationship, 
                               birth_certificate, id_photo, good_moral, student_signature, 
                               grade_level, school_year, date_archived, archive_type)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");

foreach ($students as $student) {
    // Determine archive type based on grade level
    $archive_type = '';
    if ($current_grade == 'Grade 10') {
        $archive_type = 'JHS Graduate';
    } elseif ($current_grade == 'Grade 12') {
        $archive_type = 'SHS Graduate';
    } else {
        $archive_type = 'Promoted'; // For grades 7-9 and 11
    }
    
    $insert_stmt->bind_param(
        "ssssssssssssssssssssssssss", // Now 26 's' characters (added archive_type)
        $student['rfid'],
        $student['lrn'],
        $student['first_name'],
        $student['middle_name'],
        $student['last_name'],
        $student['email'],
        $student['section'],
        $student['student_type'],
        $student['gender'],
        $student['date_of_birth'],
        $student['contact_number'],
        $student['address'],
        $student['citizenship'],
        $student['elementary_school'],
        $student['year_graduated'],
        $student['guardian_name'],
        $student['guardian_contact'],
        $student['guardian_address'],
        $student['guardian_relationship'],
        $student['birth_certificate'],
        $student['id_photo'],
        $student['good_moral'],
        $student['student_signature'],
        $student['grade_level'],
        $student['school_year'],
        $archive_type
    );
    $insert_stmt->execute();
}
        $insert_stmt->close();
        
        // 3. Delete from students table
        $delete_stmt = $conn->prepare("DELETE FROM students WHERE grade_level = ? AND section = ?");
        $delete_stmt->bind_param("ss", $current_grade, $current_section);
        $delete_stmt->execute();
        $delete_stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        // Redirect to archived_student.php
        header("Location: student_promotion.php?grade=" . urlencode($current_grade));
        exit();
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        die("Error processing promotion: " . $e->getMessage());
    }
}

// Update the sections query part to this:
$sections_by_grade = [];
$grades = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];

foreach ($grades as $grade) {
    $sql = "SELECT section_name FROM sections WHERE grade_level = ? ORDER BY section_name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $grade);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sections = [];
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row['section_name'];
    }
    
    $sections_by_grade[$grade] = $sections;
    $stmt->close();
}

// For debugging, add this line to see what sections are being fetched:
error_log("Sections by grade: " . print_r($sections_by_grade, true));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Student Promotion</title>
  <link rel="stylesheet" href="assets/css/style.css" />  
  <link rel="stylesheet" href="assets/css/promotion_section.css" />  
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
  <div class="search-container" id="searchContainer" style="display:none;">
    <div class="left-search">
      <form id="searchForm" onsubmit="return handleSearch(event)">
        <input type="text" id="searchInput" placeholder="Search by Section..." />
        <button type="submit">Search</button>
      </form>
    </div>
  </div>     

  <!-- Student Table -->
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
$grades = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
foreach ($grades as $grade_level) {
        $sql = "SELECT section, COUNT(*) as total_students 
                FROM students 
                WHERE grade_level = ? 
                GROUP BY section
                ORDER BY section ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $grade_level);
        $stmt->execute();
        $result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $section = ucfirst(strtolower(htmlspecialchars($row['section'])));
    $count = $row['total_students'];

    // Check if grade is 10 or 12 for direct promotion
    $isGraduatingGrade = ($grade_level == 'Grade 10' || $grade_level == 'Grade 12');
    
    echo "<tr data-grade='" . strtolower($grade_level) . "' style='display: none;'>
            <td>{$section}</td>
            <td>{$count}</td>
            <td>
              <button class='view' onclick=\"location.href='promote_students.php?section=" 
                . urlencode($section) . "&grade_level=" 
                . urlencode($grade_level) . "'\"><ion-icon name='eye-outline'></ion-icon></button>
              " . ($isGraduatingGrade ? 
                "<form method='POST' style='display:inline;'>
                  <input type='hidden' name='current_grade' value='{$grade_level}'>
                  <input type='hidden' name='current_section' value='{$section}'>
                  <input type='hidden' name='direct_promote' value='1'>
                  <button type='submit' class='btn-promote'>Promote</button>
                </form>" : 
                "<button class='btn-promote' onclick='openSectionModal(" 
                  . json_encode($grade_level) . ", " 
                  . json_encode($section) . ")'>Promote</button>") . "
            </td>
          </tr>";
}
        $stmt->close();
      }
      ?>
    </tbody>
  </table>

<!-- Section Modal (Only for non-graduating grades) -->
<div class="section-modal" id="sectionModal" style="display:none;">
    <div class="section-modal-content">
        <span class="section-close" onclick="closeSectionModal()">&times;</span>
        <h3>Select New Section</h3>
        <form id="sectionForm" method="POST" action="student_promote.php">
            <select id="sectionSelect" name="new_section" required>
                <option value="">-- Select Section --</option>
                <!-- Options will be populated by JavaScript -->
            </select>
            <input type="hidden" name="current_grade" id="currentGradeInput" />
            <input type="hidden" name="current_section" id="currentSectionInput" />
            <input type="hidden" name="redirect_url" id="redirectUrlInput" />
            <button type="submit">Confirm</button>
        </form>
    </div>
</div>

  <!-- Scripts -->
  <script>
    let currentGrade = "";

    function searchStudent() {
      const input = document.getElementById("searchInput").value.toUpperCase();
      const rows = document.querySelectorAll("#studentTableBody tr");
      let hasMatch = false;

      rows.forEach(row => {
        if (row.id === "noDataRow") {
          row.remove();
          return;
        }

        const grade = row.getAttribute("data-grade");

        if (grade === currentGrade) {
          const section = row.querySelector("td")?.textContent.toUpperCase() || "";
          const matched = section.includes(input);
          row.style.display = matched ? "" : "none";
          if (matched) hasMatch = true;
        } else {
          row.style.display = "none";
        }
      });

      const existing = document.getElementById("noDataRow");
      if (existing) existing.remove();

      if (!hasMatch) {
        const tbody = document.getElementById("studentTableBody");
        const noRow = document.createElement("tr");
        noRow.id = "noDataRow";
        noRow.innerHTML = "<td colspan='3'>No matching results.</td>";
        tbody.appendChild(noRow);
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

      const tbody = document.getElementById("studentTableBody");
      const rows = tbody.querySelectorAll("tr");
      let found = false;

      // Remove old "no data" row if any
      const oldNoRow = document.getElementById("noDataRow");
      if (oldNoRow) oldNoRow.remove();

      rows.forEach(row => {
        const grade = row.getAttribute("data-grade");
        if (!grade) return; // Skip invalid rows

        if (grade === currentGrade) {
          row.style.display = "";
          found = true;
        } else {
          row.style.display = "none";
        }
      });

      if (!found) {
        const noRow = document.createElement("tr");
        noRow.id = "noDataRow";
        noRow.innerHTML = "<td colspan='3'>No data available.</td>";
        tbody.appendChild(noRow);
      }

      // Reset search filter
      document.getElementById("searchInput").value = "";
    }

    document.addEventListener("DOMContentLoaded", () => {
      document.getElementById("searchInput").addEventListener("input", searchStudent);

      const sectionModal = document.getElementById('sectionModal');
      const sectionInput = document.getElementById('sectionInput');
      const currentGradeInput = document.getElementById('currentGradeInput');
      const currentSectionInput = document.getElementById('currentSectionInput');

window.openSectionModal = function(gradeLevel, section) {
    // Only show modal for non-graduating grades
    if (gradeLevel !== 'Grade 10' && gradeLevel !== 'Grade 12') {
        currentGradeInput.value = gradeLevel;
        currentSectionInput.value = section;
        
        // Clear previous options
        const sectionSelect = document.getElementById('sectionSelect');
        sectionSelect.innerHTML = '<option value="">-- Select Section --</option>';
        
        // Get the next grade level
        const nextGrade = getNextGradeLevel(gradeLevel);
        console.log("Next grade level: ", nextGrade); // Debugging
        
        // Add sections for the next grade level
        const sections = <?php echo json_encode($sections_by_grade); ?>;
        console.log("All sections data: ", sections); // Debugging
        
        if (sections[nextGrade] && sections[nextGrade].length > 0) {
            console.log("Sections found for ", nextGrade, ": ", sections[nextGrade]); // Debugging
            sections[nextGrade].forEach(sectionName => {
                const option = document.createElement('option');
                option.value = sectionName;
                option.textContent = sectionName;
                sectionSelect.appendChild(option);
            });
        } else {
            console.log("No sections found for ", nextGrade); // Debugging
            // Add a disabled option if no sections available
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'No sections available for ' + nextGrade;
            option.disabled = true;
            sectionSelect.appendChild(option);
        }
        
        sectionModal.style.display = 'flex';
        sectionSelect.focus();

        const redirectUrl = `student_promotion.php?grade=${encodeURIComponent(gradeLevel)}`;
        document.getElementById("redirectUrlInput").value = redirectUrl;
    }
};

// Helper function to get next grade level
function getNextGradeLevel(currentGrade) {
    const gradeOrder = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
    const currentIndex = gradeOrder.indexOf(currentGrade);
    return gradeOrder[currentIndex + 1] || currentGrade;
}

      window.closeSectionModal = function() {
        sectionModal.style.display = 'none';
      };

      window.onclick = function(event) {
        if (event.target == sectionModal) {
          closeSectionModal();
        }
      };

      // Show students directly if redirected from promote
      const urlParams = new URLSearchParams(window.location.search);
      const grade = urlParams.get('grade');
      if (grade) {
        showStudents(grade);
      }
    });
  </script>

  <!-- Ionicons -->
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</div>

</body>
</html>