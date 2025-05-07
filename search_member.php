<?php
include "db.php";

if (isset($_POST['nic'])) {
    $nic = trim($_POST['nic']);

    // Prepare SQL statement to prevent SQL injection
    $query = $conn->prepare("SELECT id, full_name FROM members WHERE nic = ?");
    $query->bind_param("s", $nic);
    $query->execute();
    $result = $query->get_result();

    if ($result->num_rows > 0) {
        $member = $result->fetch_assoc();
        echo json_encode([
            "status" => "success",
            "member_id" => $member['id'],
            "member_name" => $member['full_name']
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "No member found with this NIC."]);
    }
}
?>
