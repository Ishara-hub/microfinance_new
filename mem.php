<?php
include "db.php";

include 'header.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $last_name = $_POST['last_name'];
    $initials = $_POST['initials'];
    $nic = $_POST['nic'];
    $mobile_no = $_POST['mobile_no'];
    $house_no = $_POST['house_no'];
    $street_address = $_POST['street_address'];
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $civil_status = $_POST['civil_status'];
    $land_phone = $_POST['land_phone'];

    $query = "INSERT INTO members (
        title, last_name, initials, nic, mobile_no, house_no, 
        street_address, dob, gender, civil_status, land_phone
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "sssssssssss",
        $title, $last_name, $initials, $nic, $mobile_no, $house_no,
        $street_address, $dob, $gender, $civil_status, $land_phone
    );

    if ($stmt->execute()) {
        echo "<script>alert('✅ Member Added Successfully!'); window.location.href='add_member.php';</script>";
    } else {
        echo "<script>alert('❌ Error: " . $stmt->error . "');</script>";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Vehicle</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css1/components/vehicle.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .form-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        .form-section {
            border-right: 1px dashed #ddd;
            padding-right: 20px;
        }
        .form-label {
            font-weight: 600;
            color: #555;
        }
        .btn-generate {
            background: #f8f9fa;
            border: 1px solid #ddd;
        }
    </style>
    <script>
        // 🔹 Auto-generate NIC (if "No NIC" is checked)
        function toggleNICField() {
            const noNICChecked = document.getElementById("no_nic").checked;
            const nicField = document.getElementById("nic");
            
            if (noNICChecked) {
                nicField.value = "N/A";
                nicField.readOnly = true;
            } else {
                nicField.value = "";
                nicField.readOnly = false;
            }
        }

        // 🔹 Auto-fill DOB & Gender from NIC (Sri Lankan format)
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
            document.getElementById("gender").value = gender;.
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
                            <label class="form-label">NIC</label>
                            <input type="text" name="nic" id="nic" class="form-control" 
                                   onkeyup="autoFillDOBGender(this.value)" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Initials</label>
                            <input type="text" name="initials" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Gender</label>
                            <input type="text" name="gender" id="gender" class="form-control" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mobile No</label>
                            <input type="tel" name="mobile_no" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">House No</label>
                            <input type="text" name="house_no" class="form-control" required>
                        </div>
                    </div>

                    <!-- Right Column (Additional Info) -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Repeat NIC</label>
                            <input type="text" class="form-control" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Names Denoted By Initials</label>
                            <input type="text" class="form-control" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="dob" id="dob" class="form-control" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Civil Status</label>
                            <select name="civil_status" class="form-select">
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Divorced">Divorced</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Land Phone No</label>
                            <input type="tel" name="land_phone" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Street Address</label>
                            <textarea name="street_address" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="button" class="btn btn-secondary me-2">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Client</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>