<?php
header('Content-Type: application/json');
include 'db.php'; // Your DB connection file

// Function to sanitize input
function sanitize($conn, $data) {
    return mysqli_real_escape_string($conn, trim($data));
}

// Check required fields
$required_fields = ['vehicle_no', 'make', 'type', 'model', 'year_of_make', 'engine_no', 'chassis_no', 'market_value'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['status' => 'error', 'message' => "Missing field: $field"]);
        exit;
    }
}

// Sanitize all inputs
$vehicle_no     = sanitize($conn, $_POST['vehicle_no']);
$make           = sanitize($conn, $_POST['make']);
$type           = sanitize($conn, $_POST['type']);
$model          = sanitize($conn, $_POST['model']);
$year_of_make   = sanitize($conn, $_POST['year_of_make']);
$engine_no      = sanitize($conn, $_POST['engine_no']);
$chassis_no     = sanitize($conn, $_POST['chassis_no']);
$current_mileage = isset($_POST['current_mileage']) ? sanitize($conn, $_POST['current_mileage']) : '';
$market_value   = sanitize($conn, $_POST['market_value']);

// Check for duplicate vehicle_no or chassis_no
$check = $conn->query("SELECT id FROM vehicles WHERE vehicle_no = '$vehicle_no' OR chassis_no = '$chassis_no'");
if ($check->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Vehicle with this number or chassis already exists.']);
    exit;
}

// Insert into vehicles table
$sql = "INSERT INTO vehicles (vehicle_no, make, type, model, year_of_make, engine_no, chassis_no, current_mileage, market_value)
        VALUES ('$vehicle_no', '$make', '$type', '$model', '$year_of_make', '$engine_no', '$chassis_no', '$current_mileage', '$market_value')";

if ($conn->query($sql)) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
}
?>
