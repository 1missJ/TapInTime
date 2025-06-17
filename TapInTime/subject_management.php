<?php
include('db_connection.php');

// AJAX: Check if teacher exists
if (isset($_POST['ajax_check_teacher'])) {
    $teacherName = trim($_POST['ajax_check_teacher']);
    $stmt = $conn->prepare("SELECT 1 FROM faculty WHERE name = ? LIMIT 1");
    $stmt->bind_param("s", $teacherName);
    $stmt->execute();
    $result = $stmt->get_result();
    echo json_encode(['exists' => $result->num_rows > 0]);
    exit;
}

// AJAX: Check if section exists
if (isset($_POST['ajax_check_section'])) {
    $section = trim($_POST['ajax_check_section']);
    $stmt = $conn->prepare("SELECT 1 FROM students WHERE section = ? LIMIT 1");
    $stmt->bind_param("s", $section);
    $stmt->execute();
    $result = $stmt->get_result();
    echo json_encode(['exists' => $result->num_rows > 0]);
    exit;
}

if (isset($_POST['ajax_check_sections_grade'])) {
    $sections = array_filter(array_map('trim', explode(',', $_POST['sections'])));
    $missing = [];
    $mismatched = [];

    foreach ($sections as $section) {
        $stmt = $conn->prepare("SELECT DISTINCT grade_level FROM students WHERE section = ?");
        $stmt->bind_param("s", $section);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Check against selected grade level from frontend (sent separately if needed)
            $clientGrade = $_POST['grade_level'] ?? null;
            if ($clientGrade !== null && $row['grade_level'] != $clientGrade) {
                $mismatched[] = [
                    'section' => $section,
                    'grade_level' => $row['grade_level']
                ];
            }
        } else {
            $missing[] = $section;
        }
    }

    echo json_encode(['missing' => $missing, 'mismatched' => $mismatched]);
    exit;
}

// AJAX: Get student type of section
if (isset($_POST['ajax_get_section_type'])) {
    $section = trim($_POST['ajax_get_section_type']);
    $stmt = $conn->prepare("SELECT DISTINCT student_type FROM students WHERE section = ?");
    $stmt->bind_param("s", $section);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode(['student_type' => $row['student_type']]);
    } else {
        echo json_encode(['student_type' => null]);
    }
    exit;
}

// AJAX: Check if subjects exist
if (isset($_POST['ajax_check_subjects'])) {
    $subjects = array_filter(array_map('trim', explode(',', $_POST['ajax_check_subjects'])));
    $placeholders = implode(',', array_fill(0, count($subjects), '?'));
    $types = str_repeat('s', count($subjects));
    $stmt = $conn->prepare("SELECT subject_name FROM subjects WHERE subject_name IN ($placeholders)");
    $stmt->bind_param($types, ...$subjects);
    $stmt->execute();
    $result = $stmt->get_result();

    $existing = [];
    while ($row = $result->fetch_assoc()) {
        $existing[] = $row['subject_name'];
    }
    $missing = array_diff($subjects, $existing);
    echo json_encode(['missing' => array_values($missing)]);
    exit;
}

// AJAX: Get student type for subjects
if (isset($_POST['ajax_get_subject_types'])) {
    $subjects = array_filter(array_map('trim', explode(',', $_POST['ajax_get_subject_types'])));
    $placeholders = implode(',', array_fill(0, count($subjects), '?'));
    $types = str_repeat('s', count($subjects));
    $stmt = $conn->prepare("SELECT subject_name, student_type FROM subjects WHERE subject_name IN ($placeholders)");
    $stmt->bind_param($types, ...$subjects);
    $stmt->execute();
    $result = $stmt->get_result();

    $subjectTypes = [];
    while ($row = $result->fetch_assoc()) {
        $subjectTypes[$row['subject_name']] = $row['student_type'];
    }
    echo json_encode($subjectTypes);
    exit;
}

if (isset($_POST['assign_subject_modal'])) {
    $teacherName = trim($_POST['teacher_name']);
    $sections = array_filter(array_map('trim', explode(',', $_POST['section'])));
    $subjectNames = array_filter(array_map('trim', explode(',', $_POST['subject_names'])));
    $gradeLevel = $_POST['grade_level'];

    // Get teacher ID
    $stmt = $conn->prepare("SELECT id FROM faculty WHERE name = ?");
    $stmt->bind_param("s", $teacherName);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo "<script>alert('Teacher not found.'); window.history.back();</script>";
        exit;
    }

    $teacherId = $result->fetch_assoc()['id'];
    $validAssignments = [];
    $missingSubjects = [];

    foreach ($subjectNames as $subject) {
        // Get subject ID and student type
        $stmt = $conn->prepare("SELECT id, student_type FROM subjects WHERE subject_name = ?");
        $stmt->bind_param("s", $subject);
        $stmt->execute();
        $subResult = $stmt->get_result();

        if ($subResult->num_rows > 0) {
            $subjectRow = $subResult->fetch_assoc();
            $subjectId = $subjectRow['id'];
            $studentType = $subjectRow['student_type'];

            foreach ($sections as $section) {
                // Check if already assigned
                $check = $conn->prepare("SELECT id FROM assign WHERE teacher_id = ? AND subject_id = ? AND grade_level = ? AND section = ? AND student_type = ?");
                $check->bind_param("iiiss", $teacherId, $subjectId, $gradeLevel, $section, $studentType);
                $check->execute();
                $exists = $check->get_result();

                if ($exists->num_rows === 0) {
                    // Insert new assignment
                    $insert = $conn->prepare("INSERT INTO assign (teacher_id, subject_id, grade_level, section, student_type) VALUES (?, ?, ?, ?, ?)");
                    $insert->bind_param("iiiss", $teacherId, $subjectId, $gradeLevel, $section, $studentType);
                    $insert->execute();
                    $validAssignments[] = "$subject → $section";
                }
            }
        } else {
            $missingSubjects[] = $subject;
        }
    }

    if ($validAssignments) {
        echo "<script>alert('Assigned: " . implode(', ', $validAssignments) . " to $teacherName');</script>";
    }
    if ($missingSubjects) {
        echo "<script>alert('Missing subjects: " . implode(', ', $missingSubjects) . "');</script>";
    }
    if (!$validAssignments && !$missingSubjects) {
        echo "<script>alert('No new subjects assigned.');</script>";
    }
}

// Add subjects logic
if (isset($_POST['add_subject'])) {
    $subjects = array_filter(array_map('trim', explode(',', $_POST['subject_names'])));
    $type = $_POST['student_type'];
    foreach ($subjects as $subject) {
        $stmt = $conn->prepare("INSERT INTO subjects (subject_name, student_type) VALUES (?, ?)");
        $stmt->bind_param("ss", $subject, $type);
        $stmt->execute();
    }
    echo "<script>window.location.href='subject_management.php';</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Subject Management</title>
  <link rel="stylesheet" href="assets/css/style.css"/>
  <link rel="stylesheet" href="assets/css/subject.css"/>
  <link rel="stylesheet" href="assets/css/assign.css"/>
</head>
<body>

<?php include('sidebar.php'); ?>

<div class="subject-manager">
  <div class="subject-card">
    <form method="POST" action="">
      <label>Subject Names (comma separated):</label>
      <input type="text" name="subject_names" required />
      <input type="hidden" name="student_type" id="assign_student_type" required />
                <label>Student Type:</label>
                <select name="student_type" required>
                    <option value="">-- Select Type --</option>
                    <option value="Regular Student">Regular Student</option>
                    <option value="STI Student">STI Student</option>
                    <option value="Both">Both</option>
                </select>
      <input type="submit" name="add_subject" class="subject-btn" value="Add Subjects" />
    </form>
  </div>

  <div style="display: flex; justify-content: flex-end; margin-bottom: 10px;">
    <button class="assign-btn" data-subject-id="0" style="background-color: #9C27B0;">Assign</button>
  </div>

          <!-- Search Bar -->
<!-- Search Bar -->
<div class="search-container">
  <div class="left-search">
    <form id="searchForm" onsubmit="return false;">
      <input type="text" id="searchInput" placeholder="Search by Teacher's name..." onkeyup="searchStudent()" />
    </form>
  </div>
</div>

<!-- Subject Assignment Table -->
<table class="subject-table">
  <thead>
    <tr>
      <th>Teacher</th>
      <th>Assign</th>
    </tr>
  </thead>
  <tbody id="teacherTableBody">
    <?php
    include 'db_connection.php';

    // Step 1: Get actual student types per section
    $sectionTypes = [];
    $sectionTypeQuery = $conn->query("SELECT section, student_type FROM students GROUP BY section, student_type");
    while ($row = $sectionTypeQuery->fetch_assoc()) {
        $sectionTypes[$row['section']] = ucfirst($row['student_type']) . " Student";
    }

    // Step 2: Get teacher assignments
    $result = $conn->query("
      SELECT 
        faculty.name AS teacher_name,
        assign.grade_level,
        assign.section,
        subjects.subject_name
      FROM assign
      JOIN faculty ON assign.teacher_id = faculty.id
      JOIN subjects ON assign.subject_id = subjects.id
      ORDER BY faculty.name, assign.grade_level, assign.section, subjects.subject_name
    ");

    $assignments = [];

    while ($row = $result->fetch_assoc()) {
        $teacher = $row['teacher_name'];
        $gradeLevel = $row['grade_level'];
        $section = $row['section'];
        $subject = $row['subject_name'];
        $studentTypeLabel = $sectionTypes[$section] ?? 'Unknown Student';
        $gradeGroup = "Grade $gradeLevel ($studentTypeLabel)";

        if (!in_array($subject, $assignments[$teacher][$gradeGroup][$section] ?? [])) {
            $assignments[$teacher][$gradeGroup][$section][] = $subject;
        }
    }

    // Step 3: Output
    foreach ($assignments as $teacher => $gradeGroups) {
        echo "<tr class='teacher-row'><td><strong>$teacher</strong></td><td></td></tr>";
        foreach ($gradeGroups as $gradeGroup => $sections) {
            echo "<tr class='sub-row'><td></td><td><strong>$gradeGroup</strong></td></tr>";
            foreach ($sections as $sectionName => $subjects) {
                $subjectList = implode(', ', $subjects);
                echo "<tr class='sub-row'><td></td><td>$sectionName: $subjectList</td></tr>";
            }
        }
    }
    ?>
  </tbody>
</table>
</div>

<!-- Assign Modal -->
<div id="assignModal" class="assign-modal">
  <div class="assign-modal-content">
    <span class="assign-close" onclick="closeAssignModal()">&times;</span>
    <h3>Assign Subject</h3>
    <form method="POST" action="" id="assignForm">
      <input type="hidden" name="subject_id" id="assign_subject_id" />

      <div class="form-group">
        <label for="teacher_name">Teacher Name:</label>
        <input type="text" name="teacher_name" id="teacher_name" required />
        <div id="teacher_error" style="color: red;"></div>
      </div>

<div class="form-group">
  <label for="section">Section (comma separated):</label>
  <input type="text" name="section" id="section" required />
  <div id="section_error" style="color: red;"></div>
</div>

      <div class="form-group">
        <label for="subject_names">Subjects (comma separated):</label>
        <input type="text" name="subject_names" id="subject_names" required />
        <div id="subject_error" style="color: red;"></div>
      </div>

      <div class="form-group">
        <label>Student Type:</label>
        <div id="subject_types_display" style="margin-top: 5px; font-size: 14px; color: #444;"></div>
      </div>

      <div class="form-group">
                <label for="grade_level">Select Grade Level:</label>
                <select name="grade_level" required>
                    <option value="">-- Select Grade --</option>
                    <option value="7">Grade 7</option>
                    <option value="8">Grade 8</option>
                    <option value="9">Grade 9</option>
                    <option value="10">Grade 10</option>
                </select>
</div>

      <button type="submit" name="assign_subject_modal">Confirm</button>
    </form>
  </div>
</div>

<script>
function searchStudent() {
    const input = document.getElementById("searchInput").value.toUpperCase();
    const rows = document.querySelectorAll("#teacherTableBody tr");
    
    let currentTeacher = "";
    let hasVisible = false;
    let teacherVisibleMap = {};

    rows.forEach((row, index) => {
        const isTeacherRow = row.classList.contains("teacher-row");
        if (isTeacherRow) {
            currentTeacher = row.querySelector("td").textContent.trim().toUpperCase();
            teacherVisibleMap[currentTeacher] = false;
        } else if (currentTeacher) {
            // Save sub-rows under each teacher
            teacherVisibleMap[currentTeacher] = teacherVisibleMap[currentTeacher] || false;
        }
    });

    rows.forEach((row, index) => {
        const isTeacherRow = row.classList.contains("teacher-row");
        if (isTeacherRow) {
            currentTeacher = row.querySelector("td").textContent.trim().toUpperCase();
            const match = currentTeacher.includes(input);
            row.style.display = match ? "" : "none";
            teacherVisibleMap[currentTeacher] = match;
            hasVisible = hasVisible || match;
        } else {
            row.style.display = teacherVisibleMap[currentTeacher] ? "" : "none";
        }
    });

    // Handle "No matching results"
    const existingNoData = document.getElementById("noDataRow");
    if (existingNoData) existingNoData.remove();

    if (!hasVisible) {
        const tbody = document.getElementById("teacherTableBody");
        const newRow = document.createElement("tr");
        newRow.id = "noDataRow";
        newRow.innerHTML = `<td colspan="2">No matching results.</td>`;
        tbody.appendChild(newRow);
    }
}

  document.querySelectorAll(".assign-btn").forEach(button => {
  button.addEventListener("click", () => {
    document.getElementById("assign_subject_id").value = button.dataset.subjectId;
    document.getElementById("assignModal").classList.add("show");
  });
});

function closeAssignModal() {
  document.getElementById("assignModal").classList.remove("show");
}

window.addEventListener("click", function (event) {
  const modal = document.getElementById("assignModal");
  if (event.target === modal) {
    modal.classList.remove("show");
  }
});

document.getElementById("teacher_name").addEventListener("blur", function () {
  const name = this.value.trim();
  const errorDiv = document.getElementById("teacher_error");

  if (name === "") return errorDiv.textContent = "";

  fetch("subject_management.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: `ajax_check_teacher=${encodeURIComponent(name)}`
  })
  .then(res => res.json())
  .then(data => {
    errorDiv.textContent = data.exists ? "" : "Teacher not found in database.";
  });
});

document.getElementById("section").addEventListener("blur", function () {
  const sectionsRaw = this.value.trim();
  const gradeLevel = document.querySelector("select[name='grade_level']").value.trim();
  const errorDiv = document.getElementById("section_error");

  if (!sectionsRaw || !gradeLevel) {
    errorDiv.textContent = "";
    return;
  }

  const body = new URLSearchParams();
  body.append("ajax_check_sections_grade", "1");
  body.append("sections", sectionsRaw);

  fetch("subject_management.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: body.toString()
  })
    .then(res => res.json())
    .then(data => {
      if (data.missing.length > 0) {
        errorDiv.textContent = `Section not found in the database: ${data.missing.join(", ")}`;
      } else if (data.mismatched.length > 0) {
        errorDiv.innerHTML = data.mismatched.map(item => `${item.section} is in Grade ${item.grade_level}`).join("<br>");
      } else {
        errorDiv.textContent = "";
      }
    })
    .catch(err => {
      console.error("Fetch error:", err);
      errorDiv.textContent = "Error checking sections.";
    });
});

document.getElementById("subject_names").addEventListener("input", function () {
    const subjectInput = this.value;

    if (subjectInput.trim() === "") {
        document.getElementById("subject_types_display").innerText = "";
        return;
    }

    const xhr = new XMLHttpRequest();
    xhr.open("POST", "", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

    xhr.onload = function () {
        if (xhr.status === 200) {
            const subjectTypes = JSON.parse(xhr.responseText);
            let display = "";

            for (const [subject, type] of Object.entries(subjectTypes)) {
                display += `${subject} → ${type}<br>`;
            }

            document.getElementById("subject_types_display").innerHTML = display;
        }
    };

    xhr.send("ajax_get_subject_types=" + encodeURIComponent(subjectInput));
});

// Optional: Re-check on grade level change too
document.querySelector("select[name='grade_level']").addEventListener("change", () => {
  document.getElementById("section").dispatchEvent(new Event("blur"));
});

document.getElementById("subject_names").addEventListener("blur", function () {
  const subjects = this.value.trim();
  const errorDiv = document.getElementById("subject_error");
  const typesDisplay = document.getElementById("subject_types_display");

  if (subjects === "") {
    errorDiv.textContent = "";
    typesDisplay.textContent = "";
    return;
  }

  // First: validate missing subjects
  fetch("subject_management.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: `ajax_check_subjects=${encodeURIComponent(subjects)}`
  })
  .then(res => res.json())
  .then(data => {
    errorDiv.textContent = data.missing.length > 0 ? `Subjects not found: ${data.missing.join(', ')}` : "";
  });

  // Second: fetch student types for existing subjects
  fetch("subject_management.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: `ajax_get_subject_types=${encodeURIComponent(subjects)}`
  })
  .then(res => res.json())
  .then(subjectTypes => {
    const grouped = {};

    for (const [subject, type] of Object.entries(subjectTypes)) {
      if (!grouped[type]) grouped[type] = [];
      grouped[type].push(subject);
    }

    const displayText = Object.entries(grouped)
      .map(([type, subs]) => `${subs.join(', ')}: ${type}`)
      .join('<br>');

    typesDisplay.innerHTML = displayText;
  });
});

document.getElementById("subject_names").addEventListener("blur", function () {
  const subjects = this.value.trim();
  const sectionInput = document.getElementById("section").value.trim();
  const errorDiv = document.getElementById("subject_error");

  if (!subjects || !sectionInput) return;

  const sections = sectionInput.split(',').map(s => s.trim()).filter(Boolean);

  // Step 1: Fetch subject types
  fetch("subject_management.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: `ajax_get_subject_types=${encodeURIComponent(subjects)}`
  })
    .then(res => res.json())
    .then(subjectTypes => {
      // Step 2: For each section, fetch its student type
      const sectionTypePromises = sections.map(section =>
        fetch("subject_management.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `ajax_get_section_type=${encodeURIComponent(section)}`
        })
        .then(res => res.json())
        .then(data => ({
          section,
          student_type: data.student_type
        }))
      );

      Promise.all(sectionTypePromises).then(sectionTypes => {
        const mismatches = {};

        // Step 3: Compare subject types with each section's student type
        for (const [subject, subjType] of Object.entries(subjectTypes)) {
          if (subjType === "Both") continue;

          sectionTypes.forEach(({ section, student_type }) => {
            if (subjType !== student_type) {
              if (!mismatches[subject]) {
                mismatches[subject] = {
                  subjType,
                  sections: []
                };
              }
              mismatches[subject].sections.push(`${section} (${student_type})`);
            }
          });
        }

        // Step 4: Display errors if mismatches found
        if (Object.keys(mismatches).length > 0) {
          errorDiv.innerHTML = Object.entries(mismatches).map(
            ([subject, { subjType, sections }]) =>
              `${subject} (${subjType}) cannot be assigned to ${sections.join(', ')}`
          ).join('<br>');
        } else {
          errorDiv.textContent = "";
        }
      });
    });
});
</script>

<!-- Ionicons -->
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>