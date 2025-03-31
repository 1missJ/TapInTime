<?php
// Include database connection
include('db_connection.php');

// Fetch specific columns for students (without any grade-level restriction in the query)
$query = "SELECT lrn, CONCAT(first_name, ' ', middle_name, ' ', last_name) AS fullname, email, section 
          FROM students"; // Query fetches all students' data
$result = mysqli_query($conn, $query);

// Initialize the students array
$students = [];

// Check if any students were fetched
if (mysqli_num_rows($result) > 0) {
    // Fetch the results into the array
    $students = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Details</title>
    <!-- Main CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
<!-- Include Sidebar -->
<?php include('sidebar.php'); ?>

    <!-- Main Content -->
    <div class="main-content">
        <h2>Student Details</h2>

        <!-- Search Bar -->
        <div class="search-container">
            <input type="text" id="searchInput" placeholder="Search student...">
            <button onclick="searchStudent()">Search</button>
        </div>

        <!-- Year Level List -->
        <div class="year-levels">
            <div class="year-box" onclick="showStudents('Grade 7')">Grade 7</div>
            <div class="year-box" onclick="showStudents('Grade 8')">Grade 8</div>
            <div class="year-box" onclick="showStudents('Grade 9')">Grade 9</div>
            <div class="year-box" onclick="showStudents('Grade 10')">Grade 10</div>
        </div>

        <!-- Student Table -->
        <table class="student-table" id="studentTable" style="display:none;">
            <thead>
                <tr>
                    <th>LRN</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Section</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="studentTableBody">
                <?php
                // Check if there are students fetched
                foreach ($students as $student) {
                    echo "<tr>
                    <td>{$student['lrn']}</td>
                    <td>{$student['fullname']}</td>
                    <td>{$student['email']}</td>
                    <td>{$student['section']}</td>
                    <td>
                        <button class='edit-btn' onclick='redirectToStudentInfo(\"{$student['lrn']}\")'>Edit</button>
                        <button class='archive-btn'>Archive</button>
                        <button class='view-btn' onclick='redirectToStudentInfo(\"{$student['lrn']}\")'>
                            <ion-icon name='eye-outline'></ion-icon>
                        </button>
                    </td>
                  </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- JavaScript -->
    <script>
        function searchStudent() {
            var input, filter, table, tr, td, i, txtValue;
            input = document.getElementById("searchInput");
            filter = input.value.toUpperCase();
            table = document.getElementById("studentTableBody");
            tr = table.getElementsByTagName("tr");

            for (i = 0; i < tr.length; i++) {
                td = tr[i].getElementsByTagName("td")[1];
                if (td) {
                    txtValue = td.textContent || td.innerText;
                    tr[i].style.display = txtValue.toUpperCase().indexOf(filter) > -1 ? "" : "none";
                }
            }
        }

        // Show students for a selected year level
        function showStudents(yearLevel) {
            if (yearLevel === 'Grade 7') {
                document.querySelector(".year-levels").style.display = "none";
                document.getElementById("studentTable").style.display = "table"; // Only show Grade 7
            } else {
                // Hide data for other grades
                alert('No data for ' + yearLevel);
            }
        }

        function redirectToStudentInfo(studentLrn) {
            window.location.href = `student_profile.php?lrn=${studentLrn}`;
        }
    </script>

    <!-- Ionicons -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>
