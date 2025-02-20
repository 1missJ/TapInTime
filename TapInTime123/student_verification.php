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
    <div class="container">
        <div class="navigation">
            <ul>

                <li class="brand-logo">
                    <a href="index.html">
                        <div class="logo-container">
                            <img src="assets/imgs/dahs.jpg" alt="TapInTime Logo">
                        </div>
                        <span class="title">TapInTime</span>
                    </a>
                </li>                    
                
                <li>
                    <a href="dashboard.php">
                        <span class="icon"><ion-icon name="home-outline"></ion-icon></span>
                        <span class="title">Dashboard</span>
                    </a>
                </li>

                <li>
                    <a href="student_verification.php">
                        <span class="icon"><ion-icon name="checkmark-done-circle-outline"></ion-icon></span>
                        <span class="title">Student Verification</span>
                    </a>
                </li>

                <li>
                    <a href="student_details.php">
                        <span class="icon"><ion-icon name="people-circle-outline"></ion-icon> </span>
                        <span class="title">Student Details</span>
                    </a>
                </li>

                <li>
                    <a href="id_generation.html">
                        <span class="icon"><ion-icon name="card-outline"></ion-icon></span>
                        <span class="title">ID Generation</span>
                    </a>
                </li>

                <li>
                    <a href="#">
                        <span class="icon"><ion-icon name="qr-code-outline"></ion-icon></span>
                        <span class="title">RFID Assignment</span>
                    </a>
                </li>

                <li>
                    <a href="#">
                        <span class="icon"><ion-icon name="school-outline"></ion-icon></span>
                        <span class="title">Faculty Registration</span>
                    </a>
                </li>

                <li>
                    <a href="#">
                        <span class="icon"><ion-icon name="library-outline"></ion-icon></span>
                        <span class="title">Subject Management</span>
                    </a>
                </li>

                <li>
                    <a href="#">
                        <span class="icon"><ion-icon name="stats-chart-outline"></ion-icon></span>
                        <span class="title">Attendance Monitoring</span>
                    </a>
                </li>

                <li>
                    <a href="#">
                        <span class="icon"><ion-icon name="ribbon-outline"></ion-icon></span>
                        <span class="title">Students Promotion</span>
                    </a>
                </li>

                <li>
                    <a href="#">
                        <span class="icon"><ion-icon name="person-outline"></ion-icon></span>
                        <span class="title">Users</span>
                    </a>
                </li>

                <li>
                    <a href="#">
                        <span class="icon"><ion-icon name="log-in-outline"></ion-icon></span>
                        <span class="title">Sign out</span>
                    </a>
                </li>

            </ul>
        </div>
    </div>

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
                                <button class='approve-btn' onclick='approveStudent(" . $row['id'] . ")'>Approve</button>
                                <button class='reject-btn' onclick='rejectStudent(" . $row['id'] . ")'>Reject</button>
                                 <button class='btn btn-info' onclick='viewStudent(" . json_encode($row) . ")'>
                                 <ion-icon name='eye-outline'></ion-icon> View </button>
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
            <h3>Student Details</h3>

            <h4>Personal Information</h4>
            <p><strong>First Name:</strong> <span id="modalFirstName"></span></p>
            <p><strong>Middle Name:</strong> <span id="modalMiddleName"></span></p>
            <p><strong>Last Name:</strong> <span id="modalLastName"></span></p>
            <p><strong>LRN:</strong> <span id="modalLRN"></span></p>
            <p><strong>Date of Birth:</strong> <span id="modalDOB"></span></p>
            <p><strong>Gender:</strong> <span id="modalGender"></span></p>
            <p><strong>Citizenship:</strong> <span id="modalCitizenship"></span></p>
            <p><strong>Address:</strong> <span id="modalAddress"></span></p>
            <p><strong>Contact Number:</strong> <span id="modalContact"></span></p>
            <p><strong>Email Address:</strong> <span id="modalEmail"></span></p>

            <h4>Parent/Guardian Information</h4>
            <p><strong>Full Name:</strong> <span id="modalGuardianName"></span></p>
            <p><strong>Contact Number:</strong> <span id="modalGuardianContact"></span></p>
            <p><strong>Relationship to Student:</strong> <span id="modalGuardianRelation"></span></p>

            <h4>Academic Information</h4>
            <p><strong>Elementary School:</strong> <span id="modalElemSchool"></span></p>
            <p><strong>Year Graduated:</strong> <span id="modalYearGraduated"></span></p>

            <h4>Required Documents</h4>
            <p><strong>Recent ID Photo:</strong> <br> 
            <img id="modalIDPhoto" src="" alt="ID Photo" class="doc-img">
            </p>
            <p><strong>Birth Certificate:</strong> <br> 
            <img id="modalBirthCert" src="" alt="Birth Certificate" class="doc-img">
            </p>

            <p><strong>Certificate of Good Moral Character:</strong> <br>
                <img id="modalGoodMoral" src="" alt="Certificate of Good Moral" class="doc-img"> </p>

            <p><strong>Student Signature:</strong> <span id="modalSignature"></span>
                <img id="modalSignatureImage" src="" alt="Student Signature" class="doc-img"></p>

            <h4>Section (Given by the Adviser)</h4>
            <p><strong>Assigned Section:</strong> <span id="modalSection"></span></p>
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


  function redirectToStudentInfo(studentId) {
        window.location.href = `student_profile.php?id=${studentId}`;
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
        // Populate the modal with student details
        document.getElementById("modalFirstName").textContent = student.first_name || "N/A";
        document.getElementById("modalMiddleName").textContent = student.middle_name || "N/A";
        document.getElementById("modalLastName").textContent = student.last_name || "N/A";
        document.getElementById("modalLRN").textContent = student.lrn || "N/A";
        document.getElementById("modalDOB").textContent = student.date_of_birth || "N/A";
        document.getElementById("modalGender").textContent = student.gender || "N/A";
        document.getElementById("modalCitizenship").textContent = student.citizenship || "N/A";
        document.getElementById("modalAddress").textContent = student.address || "N/A";
        document.getElementById("modalContact").textContent = student.contact_number || "N/A";
        document.getElementById("modalEmail").textContent = student.email || "N/A";
        
        document.getElementById("modalGuardianName").textContent = student.guardian_name || "N/A";
        document.getElementById("modalGuardianContact").textContent = student.guardian_contact || "N/A";
        document.getElementById("modalGuardianRelation").textContent = student.guardian_relation || "N/A";

        document.getElementById("modalElemSchool").textContent = student.elementary_school || "N/A";
        document.getElementById("modalYearGraduated").textContent = student.year_graduated || "N/A";

        document.getElementById("modalIDPhoto").src = "/uploads/" + (student.id_photo || "default.jpg");
        document.getElementById("modalBirthCert").src = "/uploads/" + (student.birth_certificate || "default.jpg");
        document.getElementById("modalGoodMoral").src = "uploads/" + (student.good_moral || "default.jpg");
        document.getElementById("modalSignatureImage").src = "uploads/" + (student.signature || "default.jpg");

        document.getElementById("modalSection").textContent = student.assigned_section || "Not Assigned";

        // Show the modal
        document.getElementById("studentModal").style.display = "block";
    }

    // Close modal function
    function closeModal() {
        document.getElementById("studentModal").style.display = "none";
    }




</script>

        <!--Scripts-->
        <script src="assets/js/main.js"></script>      

        <!--ionicons-->
        <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
        <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

</body>
</html>