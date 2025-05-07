<?php
include "db.php";
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => ''];

try {
    $id = $_POST['id'] ?? 0;
    
    if (empty($id)) {
        throw new Exception("Vehicle ID is required");
    }

    $stmt = $conn->prepare("SELECT * FROM vehicles WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $vehicle = $result->fetch_assoc();
        
        // Format make and type for display
        $vehicle['make_display'] = ucfirst(str_replace('_', ' ', $vehicle['make']));
        $vehicle['type_display'] = ucfirst(str_replace('_', ' ', $vehicle['type']));
        
        $response = [
            'status' => 'success',
            'data' => $vehicle
        ];
    } else {
        throw new Exception("Vehicle not found");
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>