<?php
include 'db_connection.php';
include 'transparent_image.php';

$lrn = $_GET['lrn'] ?? '';
if (empty($lrn)) die("Invalid LRN.");

$sql = "SELECT first_name, middle_name, last_name, id_photo, guardian_name, guardian_address, guardian_contact, school_year, student_signature FROM students WHERE lrn = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $lrn);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
if (!$student) die("Student not found.");

$middle_initial = !empty($student['middle_name']) ? strtoupper(substr($student['middle_name'], 0, 1)) . '.' : '';
$full_name = strtoupper($student['first_name'] . ' ' . $middle_initial . ' ' . $student['last_name']);
$id_photo_path = (!empty($student['id_photo']) && file_exists($student['id_photo'])) ? $student['id_photo'] : "assets/default.png";

function formatGuardianName($name) {
    $parts = explode(" ", trim($name));
    $first = ucfirst(strtolower($parts[0] ?? ''));
    $middle = isset($parts[1]) ? strtoupper(substr($parts[1], 0, 1)) . '.' : '';
    $last = implode(" ", array_map('ucfirst', array_slice($parts, 2)));
    return trim("$first $middle $last");
}

$guardian_name = !empty($student['guardian_name']) ? htmlspecialchars(formatGuardianName($student['guardian_name'])) : 'N/A';
$guardian_address = !empty($student['guardian_address']) ? htmlspecialchars($student['guardian_address']) : 'N/A';
$guardian_contact = !empty($student['guardian_contact']) ? htmlspecialchars($student['guardian_contact']) : 'N/A';
$school_year = !empty($student['school_year']) ? htmlspecialchars($student['school_year']) : 'N/A';

$principal_result = $conn->query("SELECT principal_name, principal_signature FROM principal LIMIT 1");
$principal_row = ($principal_result && $principal_result->num_rows > 0) ? $principal_result->fetch_assoc() : null;
$principal_name = $principal_row ? strtoupper($principal_row['principal_name']) : 'PRINCIPAL';
$principal_signature_transparent = null;
if (!empty($principal_row['principal_signature']) && file_exists($principal_row['principal_signature'])) {
    $principal_signature_transparent = makeImageTransparent($principal_row['principal_signature']);
}

$student_signature_transparent = '';
if (!empty($student['student_signature']) && file_exists($student['student_signature'])) {
    $student_signature_transparent = makeImageTransparent($student['student_signature']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student ID - TapInTime</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/id_template.css">
</head>
<body>

<div class="print-area">
  <button id="toggleBtn" class="toggle-btn">➡️</button>
  <button id="printFrontBtn" class="print-btn" onclick="printID('frontID')">Print Front ID</button>
  <button id="printBackBtn" class="print-btn" onclick="printID('backID')" style="display: none;">Print Back ID</button>

  <!-- FRONT ID -->
  <div class="id-wrapper" id="frontID">
    <div class="id-container">
      <img src="assets/id/id_f.jpg" class="background-img">
      <img src="<?= $id_photo_path ?>" class="id-photo">
      <div class="student-name-container">
        <div class="student-name"><?= $full_name ?></div>
      </div>
      <div class="student-lrn-container">
        <div class="student-lrn"><?= htmlspecialchars($lrn) ?></div>
      </div>
<?php if ($principal_signature_transparent !== null): ?>
  <div class="principal-signature-container">
    <img src="<?= htmlspecialchars($principal_signature_transparent) ?>" alt="Principal Signature" class="principal-signature">
  </div>
<?php endif; ?>
      <div class="principal-name-container">
        <div class="principal"><?= htmlspecialchars($principal_name) ?></div>
      </div>
    </div>
  </div>

  <!-- BACK ID -->
  <div class="id-wrapper" id="backID" style="display: none;">
    <div class="id-container">
      <img src="assets/id/id_b.jpg" class="background-img">
      <div class="school-year-container">
        <div class="school-year"><?= $school_year ?></div>
      </div>
      <div class="guardian-info-container">
        <div class="guardian-info">
          <div class="guardian-name"><?= $guardian_name ?></div>
          <div class="guardian-address"><?= $guardian_address ?></div>
          <div class="guardian-contact"><?= $guardian_contact ?></div>
        </div>
      </div>
      <?php if (!empty($student_signature_transparent)): ?>
        <div class="student-signature-container">
          <img src="<?= htmlspecialchars($student_signature_transparent) ?>" alt="Student Signature" class="student-signature">
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.getElementById("toggleBtn").addEventListener("click", function () {
  const front = document.getElementById("frontID");
  const back = document.getElementById("backID");
  const toggleBtn = document.getElementById("toggleBtn");
  const printFrontBtn = document.getElementById("printFrontBtn");
  const printBackBtn = document.getElementById("printBackBtn");

  if (front.style.display !== "none") {
    front.style.display = "none";
    back.style.display = "block";
    toggleBtn.innerText = "⬅️";
    printFrontBtn.style.display = "none";
    printBackBtn.style.display = "inline-block";
  } else {
    front.style.display = "block";
    back.style.display = "none";
    toggleBtn.innerText = "➡️";
    printFrontBtn.style.display = "inline-block";
    printBackBtn.style.display = "none";
  }
});

function printID(id) {
  const element = document.getElementById(id).cloneNode(true);
  const btn = element.querySelector('.print-btn');
  if (btn) btn.remove();

  const printWindow = window.open('', '', 'width=800,height=600');
  printWindow.document.write(`
    <html>
      <head>
        <title>Print ID</title>
        <link rel="stylesheet" href="assets/css/style.css">
        <link rel="stylesheet" href="assets/css/id_template.css">
        <style>
@media print {
  .id-container {
    width: 3.375in;
    height: 2.125in;
    position: relative;
    overflow: hidden;
    background: none;
    margin: 0 auto;
  }

  .background-img {
    width: 100% !important;
    height: 100% !important;
  }

  .id-wrapper {
    position: static !important;
    display: block !important;
    transform: none !important;
  }

  @page {
    size: 3.375in 2.125in;
    margin: 0;
  }

  body, html {
    margin: 0;
    padding: 0;
  }

  .id-photo {
  position: absolute;
  top: 34.5px;     /* Adjust vertically to align with white box */
  left: 164.5px;   /* Adjust horizontally to align with white box */
  width: 161.5px;  /* Set exact width of the white box */
  height: 59px; /* Set exact height of the white box */
  object-fit: cover;
  z-index: 1;
}

  .student-name-container {
    position: absolute;
    top: 100px; /* adjust to match template */
    left: 164.5px;
    width: 100%;
    text-align: center;
  }

.student-name {
    color: rgb(4, 76, 154);
    font-weight: bold;
    font-family: 'Arial Unicode MS', sans-serif;
    font-size: 10px; /* Start at max */
    line-height: 1;
    white-space: nowrap; /* Stay on one line */
}

  .student-lrn-container {
    position: absolute;
    top: 127px; /* adjust to match template */
    left: 164.5px;
    width: 100%;
    text-align: center;
  }

.student-lrn {
    font-size: 10px;
    font-family: 'Roboto', sans-serif;
    color: black;
    font-weight: bold;
    white-space: nowrap;
}

  .principal-signature-container {
    position: absolute;
    top: 155px; /* adjust to match template */
    left: 164.5px;
    width: 100%;
    text-align: center;
  }

.principal-signature {
  width: 75px;
  height: auto;
  display: block;
  margin: 0 auto;
  pointer-events: none;
  user-select: none;
}

  .principal-name-container {
    position: absolute;
    top: 163px; /* adjust to match template */
    left: 164.5px;
    width: 100%;
    text-align: center;
  }

.principal {
    font-size: 10px;
    font-family: 'Roboto', sans-serif;
    color: black;
    font-weight: bold;
    white-space: nowrap;
}

  .student-year-container {
    position: absolute;
    top: 100px; /* adjust to match template */
    left: 164.5px;
    width: 100%;
    text-align: center;
  }

.school-year {
    font-size: 10px;
    font-family: 'Arial Unicode MS', sans-serif;
    color: rgba(0, 0, 0, 0.496);
    font-weight: bold;
    white-space: nowrap;
    overflow: hidden;
    text-align: center;
}

  .guardian-info-container {
    position: absolute;
    top: 85px; /* adjust to match template */
    left: 164.5px;
    width: 100%;
    text-align: center;
  }

  .guardian-info {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    width: 100%;
    height: 100%;
    box-sizing: border-box;
    overflow: hidden;
}

.guardian-name {
    font-family: 'Arial Unicode MS', sans-serif;
    color: rgba(0, 0, 0, 0.704);
    font-weight: bold;
    font-size: 10px;
    line-height: 1;
    text-align: center;
    max-width: 100%;
    overflow: hidden;

    display: -webkit-box;
    -webkit-box-orient: vertical;
    white-space: normal;
    word-wrap: break-word;
}

.guardian-address,
.guardian-contact {
    font-size: 10px; /* slightly smaller to help fit */
    font-family: 'Arial Unicode MS', sans-serif;
    color: rgba(0, 0, 0, 0.704);
    font-weight: bold;
    max-width: 100%;
    overflow-wrap: break-word;
    word-break: break-word;
    white-space: normal; /* allow wrapping */
    text-overflow: clip;
    margin-top: 4px;
}

  .student-signature-container  {
    position: absolute;
    top: 165px; /* adjust to match template */
    left: 164.5px;
    width: 100%;
    text-align: center;
  }

.student-signature {
    width: 65px;
    height: auto;
    opacity: 0.95;
    display: inline-block;
}
        </style>
      </head>
      <body onload="window.print(); window.close();">
        ${element.innerHTML}
      </body>
    </html>
  `);
  printWindow.document.close();
}

document.addEventListener("DOMContentLoaded", function () {
  const nameContainer = document.querySelector('.student-name-container');
  const nameText = document.querySelector('.student-name');
  if (nameContainer && nameText) {
    let fontSize = 16;
    nameText.style.fontSize = fontSize + "px";
    while ((nameText.scrollWidth > nameContainer.clientWidth) && fontSize > 8) {
      fontSize -= 0.5;
      nameText.style.fontSize = fontSize + "px";
    }
  }

  const guardianName = document.querySelector('.guardian-name');
  const guardianContainer = document.querySelector('.guardian-info-container');
  if (guardianName && guardianContainer) {
    let fontSize = parseFloat(window.getComputedStyle(guardianName).fontSize);
    const minFontSize = 10;
    while ((guardianName.scrollHeight > guardianName.offsetHeight || guardianName.scrollWidth > guardianContainer.clientWidth) && fontSize > minFontSize) {
      fontSize -= 0.5;
      guardianName.style.fontSize = fontSize + "px";
    }
  }
});
</script>

</body>
</html>