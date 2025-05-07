<?php
// cbo.php - CBO Management System (No Authentication)
require_once 'db.php';

// Include header
include 'header.php';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $branch_id = intval($_POST['branch_id']);
    $credit_officer_id = intval($_POST['credit_officer_id']);
    $cbo_name = trim($_POST['cbo_name']);
    $cbo_code = trim($_POST['cbo_code']);
    $formation_date = $_POST['formation_date'];
    
    try {
        $stmt = $conn->prepare("INSERT INTO cbo (
            branch_id, credit_officer_id, cbo_name, cbo_code, formation_date,
            meeting_day, meeting_time, meeting_frequency, address,
            province, district, ds_division, gs_division, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        
        $stmt->bind_param("iisssssssssss", 
            $branch_id,
            $credit_officer_id,
            $cbo_name,
            $cbo_code,
            $formation_date,
            $_POST['meeting_day'],
            $_POST['meeting_time'],
            $_POST['meeting_frequency'],
            $_POST['address'],
            $_POST['province'],
            $_POST['district'],
            $_POST['ds_division'],
            $_POST['gs_division']
        );
        
        if ($stmt->execute()) {
            $success_message = "CBO created successfully!";
            // Clear form after successful submission
            $_POST = array();
        }
    } catch (mysqli_sql_exception $e) {
        $error_message = "Error creating CBO: " . $e->getMessage();
    }
}

// Handle status changes
if (isset($_GET['change_status'])) {
    $cbo_id = intval($_GET['id']);
    $new_status = $_GET['change_status'] === 'activate' ? 'active' : 'inactive';
    
    $stmt = $conn->prepare("UPDATE cbo SET status = ? WHERE cbo_id = ?");
    $stmt->bind_param("si", $new_status, $cbo_id);
    $stmt->execute();
    
    $success_message = "CBO status updated!";
    // Refresh the page to show updated status
    header("Location: cbo.php");
    exit();
}

// Fetch data
$branches = $conn->query("SELECT id, name FROM branches WHERE status='active'");
$officers = $conn->query("SELECT id, username FROM users WHERE role='admin'");
$cbos = $conn->query("
    SELECT c.*, b.name as branch_name, u.username as officer_name 
    FROM cbo c
    JOIN branches b ON c.branch_id = b.id
    JOIN users u ON c.credit_officer_id = u.id
    ORDER BY c.status DESC, c.cbo_name
");

// Sri Lankan provinces and districts data
$provinces = array(
    "Western" => array("Colombo", "Gampaha", "Kalutara"),
    "Central" => array("Kandy", "Matale", "Nuwara Eliya"),
    "Southern" => array("Galle", "Matara", "Hambantota"),
    "Northern" => array("Jaffna", "Kilinochchi", "Mannar", "Mullaitivu", "Vavuniya"),
    "Eastern" => array("Batticaloa", "Ampara", "Trincomalee"),
    "North Western" => array("Kurunegala", "Puttalam"),
    "North Central" => array("Anuradhapura", "Polonnaruwa"),
    "Uva" => array("Badulla", "Monaragala"),
    "Sabaragamuwa" => array("Ratnapura", "Kegalle")
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBO Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css1/components/cbo.css">
    
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Alert Messages -->
        <?php if(isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>
                <?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- First Row with two cards -->
        <div class="row mb-4">
            <!-- Create CBO Card -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="bi bi-people-fill me-2"></i> Create New CBO</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-section">
                                <h5>Basic Information</h5>
                                <div class="mb-3">
                                    <label class="form-label">Branch</label>
                                    <select name="branch_id" class="form-select" required>
                                        <option value="">-- Select Branch --</option>
                                        <?php while($branch = $branches->fetch_assoc()): ?>
                                            <option value="<?= $branch['id'] ?>" <?= (isset($_POST['branch_id']) && $_POST['branch_id'] == $branch['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($branch['name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Credit Officer</label>
                                    <select name="credit_officer_id" class="form-select" required>
                                        <option value="">-- Select Credit Officer --</option>
                                        <?php while($officer = $officers->fetch_assoc()): ?>
                                            <option value="<?= $officer['id'] ?>" <?= (isset($_POST['credit_officer_id']) && $_POST['credit_officer_id'] == $officer['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($officer['username']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">CBO Name</label>
                                        <input type="text" name="cbo_name" class="form-control" value="<?= isset($_POST['cbo_name']) ? htmlspecialchars($_POST['cbo_name']) : '' ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">CBO Code</label>
                                        <input type="text" name="cbo_code" class="form-control" value="<?= isset($_POST['cbo_code']) ? htmlspecialchars($_POST['cbo_code']) : '' ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Formation Date</label>
                                    <input type="date" name="formation_date" class="form-control" value="<?= isset($_POST['formation_date']) ? htmlspecialchars($_POST['formation_date']) : '' ?>" required>
                                </div>
                            </div>
                            
                            
                            
                            
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-primary me-md-2">
                                    <i class="bi bi-save"></i> Save CBO
                                </button>
                                <button type="reset" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Second Card in First Row -->
            <div class="col-lg-6">
                <div class="form-section">
                    <h5>Location Information</h5>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2"><?= isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '' ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Province</label>
                            <select name="province" class="form-select" id="provinceSelect">
                                <option value="">-- Select Province --</option>
                                <?php foreach($provinces as $province => $districts): ?>
                                    <option value="<?= $province ?>" <?= (isset($_POST['province']) && $_POST['province'] == $province) ? 'selected' : '' ?>>
                                        <?= $province ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">District</label>
                            <select name="district" class="form-select" id="districtSelect">
                                <option value="">-- Select District --</option>
                                <?php 
                                if (isset($_POST['province']) && array_key_exists($_POST['province'], $provinces)) {
                                    foreach($provinces[$_POST['province']] as $district): ?>
                                        <option value="<?= $district ?>" <?= (isset($_POST['district']) && $_POST['district'] == $district) ? 'selected' : '' ?>>
                                            <?= $district ?>
                                        </option>
                                    <?php endforeach;
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">DS Division</label>
                            <input type="text" name="ds_division" class="form-control" value="<?= isset($_POST['ds_division']) ? htmlspecialchars($_POST['ds_division']) : '' ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">GS Division</label>
                            <input type="text" name="gs_division" class="form-control" value="<?= isset($_POST['gs_division']) ? htmlspecialchars($_POST['gs_division']) : '' ?>">
                        </div>
                    </div>
                        
                    <div class="row">
                    <h5>Meeting Information</h5>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Meeting Day</label>
                            <select name="meeting_day" class="form-select">
                                <option value="">-- Select Day --</option>
                                <?php 
                                $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                                foreach($days as $day): ?>
                                    <option value="<?= $day ?>" <?= (isset($_POST['meeting_day']) && $_POST['meeting_day'] == $day) ? 'selected' : '' ?>>
                                        <?= $day ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Meeting Time</label>
                            <input type="time" name="meeting_time" class="form-control" value="<?= isset($_POST['meeting_time']) ? htmlspecialchars($_POST['meeting_time']) : '' ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Frequency</label>
                            <select name="meeting_frequency" class="form-select">
                                <option value="">-- Select Frequency --</option>
                                <option value="Weekly" <?= (isset($_POST['meeting_frequency']) && $_POST['meeting_frequency'] == 'Weekly') ? 'selected' : '' ?>>Weekly</option>
                                <option value="Bi-weekly" <?= (isset($_POST['meeting_frequency']) && $_POST['meeting_frequency'] == 'Bi-weekly') ? 'selected' : '' ?>>Bi-weekly</option>
                                <option value="Monthly" <?= (isset($_POST['meeting_frequency']) && $_POST['meeting_frequency'] == 'Monthly') ? 'selected' : '' ?>>Monthly</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Second Row with Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h3 class="mb-0"><i class="bi bi-list-ul me-2"></i> CBO List</h3>
                        <div class="input-group" style="width: 250px;">
                            <input type="text" class="form-control" placeholder="Search CBOs..." id="searchInput">
                            <button class="btn btn-light" type="button" id="searchButton">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Branch</th>
                                        <th>Officer</th>
                                        <th>Formation Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="cboTableBody">
                                    <?php 
                                    // Reset pointer to start of result set
                                    $cbos->data_seek(0);
                                    while($cbo = $cbos->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($cbo['cbo_code']) ?></td>
                                        <td><?= htmlspecialchars($cbo['cbo_name']) ?></td>
                                        <td><?= htmlspecialchars($cbo['branch_name']) ?></td>
                                        <td><?= htmlspecialchars($cbo['officer_name']) ?></td>
                                        <td><?= date('Y-m-d', strtotime($cbo['formation_date'])) ?></td>
                                        <td>
                                            <span class="badge rounded-pill <?= $cbo['status'] == 'active' ? 'badge-active' : 'badge-inactive' ?>">
                                                <?= ucfirst($cbo['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="cbo_view.php?id=<?= $cbo['cbo_id'] ?>" class="btn btn-info" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="cbo_edit.php?id=<?= $cbo['cbo_id'] ?>" class="btn btn-warning" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if($cbo['status'] == 'active'): ?>
                                                    <a href="?change_status=deactivate&id=<?= $cbo['cbo_id'] ?>" class="btn btn-danger" title="Deactivate">
                                                        <i class="bi bi-toggle-off"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="?change_status=activate&id=<?= $cbo['cbo_id'] ?>" class="btn btn-success" title="Activate">
                                                        <i class="bi bi-toggle-on"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-close alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                new bootstrap.Alert(alert).close();
            });
        }, 5000);

        // Simple search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#cboTableBody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Dynamic district loading based on province selection
        const provinces = <?php echo json_encode($provinces); ?>;
        const provinceSelect = document.getElementById('provinceSelect');
        const districtSelect = document.getElementById('districtSelect');
        
        provinceSelect.addEventListener('change', function() {
            const selectedProvince = this.value;
            districtSelect.innerHTML = '<option value="">-- Select District --</option>';
            
            if (selectedProvince && provinces[selectedProvince]) {
                provinces[selectedProvince].forEach(district => {
                    const option = document.createElement('option');
                    option.value = district;
                    option.textContent = district;
                    districtSelect.appendChild(option);
                });
            }
        });
    </script>
</body>
</html>