<?php
include 'db.php'; // Include your DB connection

require 'vendor/autoload.php';

// Set page title
$page_title = "vehicle Form";
// Include header
include 'header.php';
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
</head>
<body>
<div class="container mt-5">
    <h4 class="mb-4"><i class="fas fa-car me-2"></i>Add New Vehicle</h4>
    <div id="alertsContainer"></div>

    <form id="vehicleForm">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Vehicle Number <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="vehicle_no" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Vehicle Make <span class="text-danger">*</span></label>
                <select name="make" class="form-control" required>
                    <option value="">-- Select Make --</option>
                    <option value="bajaj">BAJAJ</option>
                    <option value="honda">Honda</option>
                    <option value="hero">HERO</option>
                    <option value="yamaha">YAMAHA</option>
                    <option value="hero_honda">HERO HONDA</option>
                    <option value="ranamoto">RANAMOTO</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Vehicle Type <span class="text-danger">*</span></label>
                <select name="type" class="form-control" required>
                    <option value="">-- Select Type --</option>
                    <option value="motor_cycle">MOTOR CYCLE</option>
                    <option value="three_wheeler">THREE WHEELER</option>
                    <option value="car">CAR</option>
                    <option value="lorry">LORRY</option>
                    <option value="van">VAN</option>
                    <option value="bus">BUS</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Vehicle Model <span class="text-danger">*</span></label>
                <select class="form-control" name="model" required>
                    <option value="">-- Select Model --</option>
                    <option value="Platina">Platina</option>
                    <option value="CT-100">CT-100</option>
                    <option value="Scooter">Scooter</option>
                    <option value="Discover">Discover</option>
                    <option value="Pulsar">Pulsar</option>
                    <option value="Avenger">Avenger</option>
                    <option value="Activa">Activa</option>
                    <option value="Dio">Dio</option>
                    <option value="TVS Apache">TVS Apache</option>
                    <option value="TVS XL">TVS XL</option>
                    <option value="Yamaha FZ">Yamaha FZ</option>
                    <option value="Yamaha Ray">Yamaha Ray</option>
                    <option value="Hero Splendor">Hero Splendor</option>
                    <option value="Hero HF Deluxe">Hero HF Deluxe</option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Year of Make <span class="text-danger">*</span></label>
                <select class="form-control" name="year_of_make" required>
                    <option value="">-- Select Year --</option>
                    <?php
                    $current_year = date('Y');
                    for ($year = $current_year; $year >= 1960; $year--) {
                        echo "<option value=\"$year\">$year</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Engine No <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="engine_no" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Chassis No <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="chassis_no" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Current Mileage</label>
                <input type="text" class="form-control" name="current_mileage">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Market Value (LKR) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" name="market_value" min="0" step="0.01" required>
            </div>
        </div>
        <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> Save Vehicle</button>
    </form>
</div>

<!-- JS for handling submission -->
<script>
$('#vehicleForm').submit(function(e) {
    e.preventDefault();
    let formData = $(this).serialize();
    $.ajax({
        url: 'save_vehicle.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                showAlert('success', 'Vehicle saved successfully!');
                $('#vehicleForm')[0].reset();
            } else {
                showAlert('danger', response.message || 'Error saving vehicle');
            }
        },
        error: function(xhr) {
            showAlert('danger', 'Something went wrong. Please try again.');
        }
    });
});

function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    $('#alertsContainer').html(alertHtml);
}
</script>

<!-- FontAwesome for icons -->
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>
