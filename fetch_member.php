<?php
include "db.php"; // Include your database connection file

if (isset($_POST['nic'])) {
    $nic = $_POST['nic'];

    // Fetch member details by NIC
    $query = $conn->prepare("SELECT id, full_name, mobile FROM members WHERE nic = ?");
    $query->bind_param("s", $nic);
    $query->execute();
    $result = $query->get_result();

    if ($result->num_rows > 0) {
        $member = $result->fetch_assoc();

        // Return JSON response
        echo json_encode([
            "status" => "success",
            "member_id" => $member['id'],
            "name" => $member['full_name'],
            "mobile" => $member['mobile']
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "❌ No member found with this NIC."]);
    }
}
?>
