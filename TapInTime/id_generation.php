<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Verification - TapInTime</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/ids.css">
</head>
<body>
    <!-- Include Sidebar -->
    <?php include('sidebar.php'); ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <h2>ID Generation</h2>

        <!-- Search Bar -->
        <div class="search-container">
            <input type="text" id="searchInput" placeholder="Search student...">
            <button onclick="searchStudent()">Search</button>
        </div>

        <?php
        // Include database connection
        include 'db_connection.php';

        // Fetch student data from the database
        $sql = "SELECT lrn, first_name, middle_name, last_name, email, created_at FROM students";
        $result = mysqli_query($conn, $sql);

        if (!$result) {
            die("Query failed: " . mysqli_error($conn));
        }
        ?>

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
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <?php 
                        // Combine first_name, middle_name, and last_name into "Name"
                        $full_name = $row['first_name'] . ' ' . 
                                    (!empty($row['middle_name']) ? $row['middle_name'] . ' ' : '') . 
                                    $row['last_name']; 
                    ?>
                    <tr>
                        <td><?php echo $row['lrn']; ?></td>
                        <td><?php echo $full_name; ?></td>
                        <td><?php echo $row['email']; ?></td>
                        <td><?php echo $row['created_at']; ?></td>
                        <td>
                            <button class="generate-btn" data-lrn="<?php echo $row['lrn']; ?>">Generate</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div id="idModal" class="modal">
    <div class="modal-content">
        <div class="modal-body">
            <div class="close-container">
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <iframe id="idFrame" style="width:100%; height:500px; border:none;"></iframe>
        </div>
    </div>
</div>

    <script>
document.addEventListener("DOMContentLoaded", function () {
    const generateButtons = document.querySelectorAll(".generate-btn");
    const modal = document.getElementById("idModal");
    const idFrame = document.getElementById("idFrame");
    const closeBtn = document.querySelector(".close");

    generateButtons.forEach(button => {
        button.addEventListener("click", function () {
            let studentLRN = button.getAttribute("data-lrn");
            if (studentLRN) {
                idFrame.src = "id_template.php?lrn=" + studentLRN;
                modal.style.display = "flex"; // Ensure the modal is visible
            } else {
                alert("LRN not found. Please try again.");
            }
        });
    });

    // Close modal when clicking "X"
    closeBtn.addEventListener("click", function () {
        modal.style.display = "none";
    });

    // Close modal when clicking outside of modal-content
    window.addEventListener("click", function (event) {
        if (event.target === modal) {
            modal.style.display = "none";
        }
    });
});

        // Search Functionality
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

    <!-- Scripts -->
    <script src="assets/js/main.js"></script>      
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

</body>
</html>
