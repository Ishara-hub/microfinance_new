<?php
include "db.php";

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $query = "SELECT * FROM members WHERE id=?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $member = $result->fetch_assoc();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];

    $query = "UPDATE members SET full_name=?, phone=?, address=? WHERE id=?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssi", $full_name, $phone, $address, $id);

    if ($stmt->execute()) {
        echo "<script>alert('✅ Member Updated Successfully!'); window.location.href='members.php';</script>";
    } else {
        echo "<script>alert('❌ Error: " . $stmt->error . "');</script>";
    }
}
// Include header
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Member</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Edit Member</h2>
        <form method="POST">
            <input type="hidden" name="id" value="<?= $member['id']; ?>">
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?= $member['full_name']; ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= $member['phone']; ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" required><?= $member['address']; ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Update Member</button>
        </form>
    </div>
</body>
</html>
