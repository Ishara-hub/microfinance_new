<?php
include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 🔹 Sanitize Inputs
    $full_name = htmlspecialchars(trim($_POST['full_name']));
    $initials = htmlspecialchars(trim($_POST['initials']));
    $nic = strtoupper(trim($_POST['nic'])); // Convert NIC to uppercase
    $phone = preg_replace('/[^0-9]/', '', $_POST['phone']); // Remove non-numeric chars
    $address = htmlspecialchars(trim($_POST['address']));
    $dob = $_POST['dob'];
    $gender = htmlspecialchars(trim($_POST['gender']));

    // 🔹 Validation Checks
    $errors = [];

    // Name Validation (Letters, spaces, and some special chars)
    if (!preg_match("/^[a-zA-Z .'-]+$/", $full_name)) {
        $errors[] = "Invalid name format";
    }

    // NIC Validation (Sri Lankan formats)
    if ($nic !== "N/A") {
        if (!(preg_match("/^[0-9]{9}[VXvx]$/", $nic) || preg_match("/^[0-9]{12}$/", $nic))) {
            $errors[] = "Invalid NIC format (Use 123456789V or 199012345678)";
        } else {
            // 🔹 Check if NIC already exists in database
            $checkQuery = "SELECT id FROM members WHERE nic = ? LIMIT 1";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("s", $nic);
            $checkStmt->execute();
            $checkStmt->store_result();
            
            if ($checkStmt->num_rows > 0) {
                $errors[] = "This NIC number is already registered!";
            }
            $checkStmt->close();
        }
    }

    // Phone Validation (Sri Lankan numbers)
    if (!preg_match("/^(0|94|\+94)?[1-9][0-9]{8}$/", $phone)) {
        $errors[] = "Invalid phone number (e.g., 0712345678 or +94712345678)";
    }

    // Date of Birth Validation
    $min_age_date = date('Y-m-d', strtotime('-18 years')); // Minimum 18 years old
    if ($dob > date('Y-m-d') || $dob > $min_age_date) {
        $errors[] = "Invalid date of birth (Must be at least 18 years old)";
    }

    // 🔹 If no errors, proceed with database insertion
    if (empty($errors)) {
        $status = "Active"; // Set default status here
        $query = "INSERT INTO members (full_name, initials, nic, phone, address, dob, gender, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssssss", $full_name, $initials, $nic, $phone, $address, $dob, $gender, $status);

        if ($stmt->execute()) {
            echo "<script>alert('✅ Member Added Successfully!'); window.location.href='members.php';</script>";
        } else {
            echo "<script>alert('❌ Error: " . addslashes($stmt->error) . "');</script>";
        }
    } else {
        // Display all validation errors
        echo "<script>alert('❌ " . implode("\\n", $errors) . "');</script>";
    }
}
// Include header
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add Member</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css1/components/member-form.css">
    <script>
        // 🔹 Generate Auto NIC
        function generateNIC() {
            let randomNIC = "9000" + Math.floor(100000 + Math.random() * 900000) + "v"; // 9000XXXXXX Format
            document.getElementById("nic").value = randomNIC;
            autoFillDOBGender(randomNIC);
        }

        // 🔹 Generate Initials from Full Name
        function generateInitials() {
            let fullName = document.getElementById("full_name").value.trim();
            if (fullName === "") return;

            let words = fullName.split(" ");
            let lastName = words.pop();
            let initials = words.map(word => word.charAt(0).toUpperCase()).join(".") + ". " + lastName;
            document.getElementById("initials").value = initials;
        }

        // 🔹 Auto-Fill DOB & Gender from NIC (Old & New Formats)
        function autoFillDOBGender(nic) {
            let year, days, gender;
            
            if (nic.length === 10 && (nic.endsWith("V") || nic.endsWith("v") || nic.endsWith("X") || nic.endsWith("x"))) {
                // Old NIC (e.g., 790123456V)
                year = "19" + nic.substring(0, 2);
                days = parseInt(nic.substring(2, 5));
            } else if (nic.length === 12) {
                // New NIC (e.g., 199001234567)
                year = nic.substring(0, 4);
                days = parseInt(nic.substring(4, 7));
            } else {
                document.getElementById("dob").value = "";
                document.getElementById("gender").value = "";
                return;
            }

            // Determine Gender
            if (days > 500) {
                gender = "Female";
                days -= 500;
            } else {
                gender = "Male";
            }

            // Convert day-of-year to date
            let birthDate = new Date(year, 0, days);
            let formattedDOB = birthDate.toISOString().split("T")[0];

            document.getElementById("dob").value = formattedDOB;
            document.getElementById("gender").value = gender;
        }
        
        // Toggle NIC field when "No NIC" checkbox is checked
        function toggleNICField() {
            const nicField = document.getElementById("nic");
            const noNICCheckbox = document.getElementById("no_nic");
            
            if (noNICCheckbox.checked) {
                nicField.disabled = true;
                nicField.required = false;
                nicField.value = "N/A";
            } else {
                nicField.disabled = false;
                nicField.required = true;
                nicField.value = "";
            }
        }
    </script>
</head>
<body>
    <div class="container mt-4">
        <div class="form-card">
            <h2 class="mb-4">Add New Member</h2>
            <form method="POST">
                <div class="row">
                    <!-- Left Column (Core Details) -->
                    <div class="col-md-6 form-section">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <select name="title" class="form-select" required>
                                <option value="">-- Select --</option>
                                <option value="Mr">Mr</option>
                                <option value="Mrs">Mrs</option>
                                <option value="Miss">Miss</option>
                                <option value="Dr">Dr</option>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="no_nic" onclick="toggleNICField()">
                            <label class="form-check-label">No NIC</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" id="full_name" class="form-control"
                             onkeyup="generateInitials()" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Initials</label>
                            <input type="text" name="initials" id="initials" class="form-control" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">NIC</label>
                            <input type="text" name="nic" id="nic" class="form-control" onkeyup="autoFillDOBGender(this.value)" required>
                            <button type="button" class="btn btn-warning mt-2" onclick="generateNIC()">Generate NIC</button>
                        </div>
                    </div>  
                    <div class="col-md-6 form-section">
                        <div class="mb-3">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="dob" id="dob" class="form-control" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Gender</label>
                            <input type="text" name="gender" id="gender" class="form-control" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Civil Status</label>
                            <select name="civil_status" class="form-select">
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Divorced">Divorced</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary">Add Member</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // 🔹 Validate Form Before Submission
        document.querySelector("form").addEventListener("submit", function(event) {
            let isValid = true;
            let errorMessages = [];

            // Phone Validation
            const phone = document.querySelector("[name='phone']").value;
            if (!/^(0|94|\+94)?[1-9][0-9]{8}$/.test(phone)) {
                errorMessages.push("❌ Invalid phone number (e.g., 0712345678)");
                isValid = false;
            }

            // NIC Validation (if not "N/A")
            const nic = document.getElementById("nic").value;
            if (nic !== "N/A" && !/^([0-9]{9}[VXvx]|[0-9]{12})$/.test(nic)) {
                errorMessages.push("❌ Invalid NIC (Use 123456789V or 199012345678)");
                isValid = false;
            }

            // Full Name Validation
            const fullName = document.getElementById("full_name").value;
            if (!/^[a-zA-Z .'-]+$/.test(fullName)) {
                errorMessages.push("❌ Name can only contain letters, spaces, and hyphens");
                isValid = false;
            }

            // If validation fails, show errors and prevent submission
            if (!isValid) {
                event.preventDefault();
                alert(errorMessages.join("\n"));
            }
        });
    </script>
</body>
</html>
