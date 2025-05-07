<?php
include "db.php";
require 'vendor/autoload.php';

$page_title = "Member Report";
include 'header.php';

// Get filter parameters
$branch_id = $_GET['branch_id'] ?? '';
$cbo_id = $_GET['cbo_id'] ?? '';
$status = $_GET['status'] ?? 'active';

// Base query for members
$query = "SELECT 
            m.id,
            m.full_name,
            m.nic,
            m.phone,
            m.address,
            m.join_date,
            m.status,
            b.name AS branch_name,
            c.name AS cbo_name,
            (SELECT COUNT(*) FROM loan_applications WHERE member_id = m.id) AS total_loans,
            (SELECT SUM(loan_amount) FROM loan_applications WHERE member_id = m.id AND status = 'Disbursed') AS total_borrowed,
            (SELECT SUM(amount) FROM payments WHERE loan_id IN (SELECT id FROM loan_applications WHERE member_id = m.id)) AS total_repaid
          FROM members m
          LEFT JOIN branches b ON m.branch_id = b.id
          LEFT JOIN cbos c ON m.cbo_id = c.id
          WHERE 1=1";

// Add conditions based on filters
$conditions = [];
$params = [];
$types = '';

if (!empty($branch_id)) {
    $conditions[] = "m.branch_id = ?";
    $params[] = $branch_id;
    $types .= 'i';
}

if (!empty($cbo_id)) {
    $conditions[] = "m.cbo_id = ?";
    $params[] = $cbo_id;
    $types .= 'i';
}

if (!empty($status)) {
    $conditions[] = "m.status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($conditions)) {
    $query .= " AND " . implode(" AND ", $conditions);
}

$query .= " ORDER BY m.full_name ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get CBOs for filter dropdown
$cbos = [];
if ($branch_id) {
    $cbo_query = $conn->prepare("SELECT id, name FROM cbos WHERE branch_id = ?");
    $cbo_query->bind_param("i", $branch_id);
    $cbo_query->execute();
    $cbo_result = $cbo_query->get_result();
    $cbos = $cbo_result->fetch_all(MYSQLI_ASSOC);
}

// Calculate totals
$total_members = $result->num_rows;
$rows = $result->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-info text-white">
            <h2 class="mb-0"><i class="fas fa-users me-2"></i> Member Report</h2>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <form method="GET" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Branch</label>
                        <select name="branch_id" id="branchSelect" class="form-select">
                            <option value="">All Branches</option>
                            <?php
                            $branches = $conn->query("SELECT * FROM branches WHERE status = 'active'");
                            while ($branch = $branches->fetch_assoc()) {
                                $selected = $branch['id'] == $branch_id ? 'selected' : '';
                                echo "<option value='{$branch['id']}' $selected>{$branch['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">CBO</label>
                        <select name="cbo_id" id="cboSelect" class="form-select">
                            <option value="">All CBOs</option>
                            <?php foreach ($cbos as $cbo): ?>
                                <option value="<?= $cbo['id'] ?>" <?= $cbo['id'] == $cbo_id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cbo['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= $status == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="" <?= empty($status) ? 'selected' : '' ?>>All Statuses</option>
                        </select>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <a href="member_report.php" class="btn btn-outline-secondary">
                            <i class="fas fa-sync-alt me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>

            <!-- Summary -->
            <div class="alert alert-info mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Total Members:</strong> <?= $total_members ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Active Members:</strong> 
                        <?php
                        $active_count = $conn->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetch_row()[0];
                        echo $active_count;
                        ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Avg. Loans per Member:</strong> 
                        <?= $total_members > 0 ? number_format(array_sum(array_column($rows, 'total_loans')) / $total_members, 1) : '0' ?>
                    </div>
                </div>
            </div>

            <!-- Report Table -->
            <div class="table-responsive">
                <table class="table table-striped table-bordered" id="memberTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Member ID</th>
                            <th>Full Name</th>
                            <th>NIC</th>
                            <th>Phone</th>
                            <th>Branch</th>
                            <th>CBO</th>
                            <th>Join Date</th>
                            <th>Status</th>
                            <th>Total Loans</th>
                            <th>Total Borrowed</th>
                            <th>Total Repaid</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id']) ?></td>
                            <td><?= htmlspecialchars($row['full_name']) ?></td>
                            <td><?= htmlspecialchars($row['nic']) ?></td>
                            <td><?= htmlspecialchars($row['phone']) ?></td>
                            <td><?= htmlspecialchars($row['branch_name']) ?></td>
                            <td><?= htmlspecialchars($row['cbo_name']) ?></td>
                            <td><?= date('Y-m-d', strtotime($row['join_date'])) ?></td>
                            <td>
                                <span class="badge <?= $row['status'] == 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                            <td class="text-center"><?= $row['total_loans'] ?></td>
                            <td class="text-end"><?= number_format($row['total_borrowed'] ?? 0, 2) ?></td>
                            <td class="text-end"><?= number_format($row['total_repaid'] ?? 0, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Load CBOs when branch is selected
    $('#branchSelect').change(function() {
        const branchId = $(this).val();
        $('#cboSelect').html('<option value="">All CBOs</option>');
        
        if (branchId) {
            $.ajax({
                url: 'get_cbos.php',
                type: 'POST',
                data: { branch_id: branchId },
                success: function(response) {
                    $('#cboSelect').append(response);
                }
            });
        }
    });
    
    // Initialize DataTable
    $('#memberTable').DataTable({
        pageLength: 25,
        order: [[1, 'asc']]
    });
});
</script>

<?php include 'footer.php'; ?>