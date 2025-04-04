<?php
// Ipasok ang database connection
include 'db_connection.php'; 

// Query para kunin ang mga pending students
$sql = "SELECT * FROM pending_students";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Verification - TapInTime</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/verification.css">
</head>

<body>
    <!-- Include Sidebar -->
    <?php include('sidebar.php'); ?>

    <!-- Main Content -->
<div class="main-content">
    <h2>Student Verification</h2>

    <!-- Search Bar -->
    <div class="search-container">
        <input type="text" id="searchInput" placeholder="Search student...">
        <button onclick="searchStudent()">Search</button>
    </div>

    <table class="student-table">
        <thead>
            <tr>
                <th>LRN</th>
                <th>Name</th>
                <th>Email</th>
                <th>Registered Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        
    <tbody id="studentTableBody">
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . $row['lrn'] . "</td>";
                        echo "<td>" . $row['first_name'] . " " . $row['last_name'] . "</td>";
                        echo "<td>" . $row['email'] . "</td>";
                        echo "<td>" . $row['created_at'] . "</td>";
                        echo "<td>
                        <div class='action-buttons'>
                            <button class='approve-btn' onclick='approveStudent(" . $row['id'] . ")'>Approve</button>
                            <button class='reject-btn' onclick='rejectStudent(" . $row['id'] . ")'>Reject</button>
                            <button class='view-btn' onclick='viewStudent(" . json_encode($row) . ")'>
                                <ion-icon name='eye-outline'></ion-icon>
                            </button>
                        </div>
                      </td>";
                              
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='5'>No pending students found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
</div>

<!-- Modal for Student Details -->
<div id="studentModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>

        <h2>Student Details</h2>
            <div class="modal-form">
                <div class="form-row">
                    <div class="form-column">
                        <label>First Name:</label>
                        <input type="text" id="modalFirstName" readonly>
                    </div>
                    <div class="form-column">
                        <label>Middle Name:</label>
                        <input type="text" id="modalMiddleName" readonly>
                    </div>
                    <div class="form-column">
                        <label>Last Name:</label>
                        <input type="text" id="modalLastName" readonly>
                    </div>
                </div>

                <div class="modal-form">
                <div class="form-row">
                    <div class="form-column">
                        <label>LRN:</label>
                        <input type="text" id="modalLRN" readonly>
                    </div>
                    <div class="form-column">
                        <label>Date of Birth:</label>
                        <input type="date" id="modalDOB" readonly>
                    </div>
                    <div class="form-column">
                        <label>Gender:</label>
                        <input type="gender" id="modalGender" readonly>
                    </div>
                </div>
                
                <div class="modal-form">
                <div class="form-row">
                    <div class="form-column">
                        <label>Citizenship:</label>
                        <input type="text" id="modalCitizenship" readonly>
                    </div>
                    <div class="form-column">
                        <label>Contact Number:</label>
                        <input type="text" id="modalContact" readonly>
                    </div>
                </div>

                <div class="modal-form">
                <div class="form-row">
                    <div class="form-column">
                        <label>Address:</label>
                        <input type="text" id="modalAddress" readonly>
                    </div>
                    <div class="form-column">
                        <label>Email Address:</label>
                        <input type="text" id="modalEmail" readonly>
                    </div>
                </div>

                <div class="modal-form">
                <div class="form-row">
                    <div class="form-column">
                        <label>Section:</label>
                        <input type="text" id="modalSection" readonly>
                    </div>
                    <div class="form-column">
                        <label>School Year:</label>
                        <input type="text" id="modalSchoolYear" readonly>
                    </div>
                    <div class="form-column">
                        <label>Student Type:</label>
                        <input type="text" id="modalStudentType" readonly>
                    </div>
                </div>

                <h2>Parent/Guardian Information</h2>
                <div class="modal-form">
                <div class="form-row">
                    <div class="form-column">
                        <label>Guardian Name:</label>
                        <input type="text" id="modalGuardianName" readonly>
                    </div>
                    <div class="form-column">
                        <label>Contact Number:</label>
                        <input type="text" id="modalGuardianContact" readonly>
                    </div>
                    </div>
                    
                    <div class="modal-form">
                    <div class="form-column">
                        <label>Guardian Address:</label>
                        <input type="text" id="modalGuardianAddress" readonly>
                    </div>
                    <div class="form-column">
                        <label>Relationship:</label>
                        <input type="text" id="modalGuardianRelationship" readonly>
                    </div>
                </div>

        <h2>Academic Information</h2>
        <div class="modal-form">
                <div class="form-row">
                    <div class="form-column">
                        <label>Elementary School:</label>
                        <input type="text" id="modalElemSchool" readonly>
                    </div>
                    <div class="form-column">
                        <label>Year Graduated:</label>
                        <input type="text" id="modalYearGraduated" readonly>
                    </div>
                </div>
     
        <div class="grid-container">
            <div class="form-column">
                <label>Recent ID Photo:</label>
                <img id="modalIDPhoto" class="doc-img" readonly>
            </div>
            <div class="form-column">
                <label>Birth Certificate:</label>
                <img id="modalBirthCert" class="doc-img" readonly>
            </div>
            <div class="form-column">
                <label>Good Moral Certificate:</label>
                <img id="modalGoodMoral" class="doc-img" readonly>
            </div>
            <div class="form-column">
                <label>Student Signature:</label>
                <img id="modalSignatureImage" class="doc-img" readonly>
            </div>
        </div>
        

<!-- JavaScript for Search Functionality -->
<script>
function searchStudent() {
    var input, filter, table, tr, td, i, txtValue;
    input = document.getElementById("searchInput");
    filter = input.value.toUpperCase();
    table = document.getElementById("studentTableBody");
    tr = table.getElementsByTagName("tr");

    for (i = 0; i < tr.length; i++) {
        td = tr[i].getElementsByTagName("td")[1]; // Search by student name
        if (td) {
            txtValue = td.textContent || td.innerText;
            tr[i].style.display = txtValue.toUpperCase().indexOf(filter) > -1 ? "" : "none";
        }
    }
}

    function approveStudent(studentId) {
        if (confirm("Are you sure you want to approve this student?")) {
            window.location.href = `approved_student.php?id=${studentId}`;
        }
    }

    function rejectStudent(studentId) {
        if (confirm("Are you sure you want to reject this student?")) {
            window.location.href = `reject_student.php?id=${studentId}`;
        }
    }

    function viewStudent(student) {
    console.log("Student ID Photo Path from DB: ", student.id_photo); // Debugging

    document.getElementById("modalFirstName").value = student.first_name || "";
    document.getElementById("modalMiddleName").value = student.middle_name || "";
    document.getElementById("modalLastName").value = student.last_name || "";
    document.getElementById("modalLRN").value = student.lrn || "";
    document.getElementById("modalDOB").value = student.date_of_birth || "";
    document.getElementById("modalGender").value = student.gender || "";
    document.getElementById("modalCitizenship").value = student.citizenship || "";
    document.getElementById("modalAddress").value = student.address || "";
    document.getElementById("modalContact").value = student.contact_number || "";
    document.getElementById("modalEmail").value = student.email || "";
    document.getElementById("modalSection").value = student.section || "";
    document.getElementById("modalSchoolYear").value = student.school_year || "";
    document.getElementById("modalStudentType").value = student.student_type || "";

    document.getElementById("modalGuardianName").value = student.guardian_name || "";
    document.getElementById("modalGuardianAddress").value = student.guardian_address || "";
    document.getElementById("modalGuardianContact").value = student.guardian_contact || "";
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

    console.log("Final Image Path: ", document.getElementById("modalIDPhoto").src); // Debugging

    // Show the modal
    document.getElementById("studentModal").style.display = "block";
}

    // Close modal function
    function closeModal() {
        document.getElementById("studentModal").style.display = "none";
    }

    document.addEventListener("DOMContentLoaded", function () {
    // Ensure modal is hidden when the page loads
    document.getElementById("studentModal").style.display = "none";
});

</script>

        <!--Scripts-->
        <script src="assets/js/main.js"></script>      

        <!--ionicons-->
        <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
        <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

</body>
</html>