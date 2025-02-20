<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Student Details</title>
        <link rel="stylesheet" href="assets/css/style.css">
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
                        <a href="index.html">
                            <span class="icon"><ion-icon name="home-outline"></ion-icon></span>
                            <span class="title">Dashboard</span>
                        </a>
                    </li>
    
                    <li>
                        <a href="student_verification.html">
                            <span class="icon"><ion-icon name="checkmark-done-circle-outline"></ion-icon></span>
                            <span class="title">Student Verification</span>
                        </a>
                    </li>
    
                    <li>
                        <a href="student_details.html">
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
        <h2>Student Details</h2>

        <!-- Search Bar -->
        <div class="search-container">
            <input type="text" id="searchInput" placeholder="Search student...">
            <button onclick="searchStudent()">Search</button>
        </div>

    <div class="year-levels">
                <h3>Select Year Level</h3>
                <ul id="yearLevelList">
                    <li onclick="showStudents('Grade 7')">Grade 7</li>
                    <li onclick="showStudents('Grade 8')">Grade 8</li>
                    <li onclick="showStudents('Grade 9')">Grade 9</li>
                    <li onclick="showStudents('Grade 10')">Grade 10</li>
                </ul>
    </div>

    <table class="student-table" id="studentTable" style="display:none;">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Year Level</th>
                        <th>Section</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="studentTableBody"></tbody>
    </table>
    </div>

    <script>
    function showStudents(yearLevel) {
    document.querySelector(".year-levels").style.display = "none";
    document.getElementById("studentTable").style.display = "table";

    let students = {
        "Grade 7": [
            { id: "2025-001", name: "Juan Dela Cruz", email: "juancruz@email.com", section: "A" },
            { id: "2025-002", name: "Maria Santos", email: "mariasantos@email.com", section: "B" }
        ],
        "Grade 8": [
            { id: "2026-001", name: "Pedro Reyes", email: "pedroreyes@email.com", section: "A" },
            { id: "2026-002", name: "Ana Dela Rosa", email: "anadelarosa@email.com", section: "B" }
        ]
    };

    let tableBody = document.querySelector("#studentTable tbody");
    tableBody.innerHTML = "";

    students[yearLevel].forEach(student => {
        let row = `
            <tr>
                <td>${student.id}</td>
                <td>${student.name}</td>
                <td>${student.email}</td>
                <td>${yearLevel}</td>
                <td>${student.section}</td>
                <td>
                <button class="edit-btn"onclick="redirectToStudentInfo('${student.id}')">Edit</button>
                <button class="archive-btn">Archive</button>
                <button class="view-btn" onclick="redirectToStudentInfo('${student.id}')">View</button>
                </td>
            </tr>`;
        tableBody.innerHTML += row;
    });
}

function redirectToStudentInfo(studentId) {
    window.location.href = `student_profile.html?id=${studentId}`;
}

<!-- JavaScript for Search Functionality -->
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
</script>

        <!--Scripts-->
        <script src="assets/js/main.js"></script>      

        <!--ionicons-->
        <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
        <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

    </body>
</html>