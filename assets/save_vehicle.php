<?php
include "db.php";
header('Content-Type: application/json');



$response = ['status' => 'error', 'message' => ''];

try {
    // Validate required fields
    $required = ['vehicle_no', 'make', 'type', 'model', 'year_of_make', 'engine_no', 'chassis_no', 'market_value'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception(ucfirst(str_replace('_', ' ', $field)) . " is required");
        }
    }

    $vehicle_no = trim($_POST['vehicle_no']);
    $make = trim($_POST['make']);
    $type = trim($_POST['type']);
    $model = trim($_POST['model']);
    $year_of_make = (int)$_POST['year_of_make'];
    $engine_no = trim($_POST['engine_no']);
    $chassis_no = trim($_POST['chassis_no']);
    $current_mileage = trim($_POST['current_mileage'] ?? '');
    $market_value = (float)$_POST['market_value'];

    // Check if vehicle already exists
    $check = $conn->prepare("SELECT id FROM vehicles WHERE vehicle_no = ?");
    $check->bind_param("s", $vehicle_no);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        throw new Exception("Vehicle with this number already exists");
    }

    // Insert new vehicle
    $stmt = $conn->prepare("INSERT INTO vehicles (
        vehicle_no, make, type, model, year_of_make, 
        engine_no, chassis_no, current_mileage, market_value
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "ssssisssd", 
        $vehicle_no, $make, $type, $model, $year_of_make,
        $engine_no, $chassis_no, $current_mileage, $market_value
    );

    if ($stmt->execute()) {
        $response = [
            'status' => 'success',
            'id' => $stmt->insert_id,
            'vehicle_no' => $vehicle_no,
            'make' => $make,
            'type' => $type,
            'model' => $model,
            'year_of_make' => $year_of_make,
            'engine_no' => $engine_no,
            'chassis_no' => $chassis_no,
            'current_mileage' => $current_mileage,
            'market_value' => $market_value
        ];
    } else {
        throw new Exception("Error saving vehicle: " . $stmt->error);
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>