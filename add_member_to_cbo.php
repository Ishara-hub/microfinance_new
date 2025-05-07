<?php
// cbo.php - CBO Management System with Group Functionality
require_once 'db.php';
include 'header.php';

// Initialize variables
$message = '';
$member = null;
$cbos = [];

// Only proceed if database connection is established
if ($conn) {
    // Search member by NIC
    if (isset($_GET['search'])) {
        $nic = trim($conn->real_escape_string($_GET['nic']));
        
        if (empty($nic)) {
            $message = '<div class="alert alert-warning">Please enter a NIC number</div>';
        } else {
            $query = "SELECT * FROM members WHERE nic = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $nic);
            
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $member = $result->fetch_assoc();
                
                if (!$member) {
                    $message = '<div class="alert alert-warning">No member found with NIC: ' . htmlspecialchars($nic) . '</div>';
                }
            } else {
                $message = '<div class="alert alert-danger">Error searching member: ' . htmlspecialchars($conn->error) . '</div>';
            }
            $stmt->close();
        }
    }

    // Add member to CBO
    if (isset($_POST['add_to_cbo'])) {
        $cbo_id = (int)$_POST['cbo_id'];
        $member_id = (int)$_POST['member_id'];
        $position = trim($conn->real_escape_string($_POST['position']));
        $group_number = isset($_POST['group_number']) ? $_POST['group_number'] : null;
        
        if (empty($position)) {
            $message = '<div class="alert alert-warning">Please specify a position/role</div>';
        } else {
            // Check if member already exists in CBO
            $checkQuery = "SELECT id FROM cbo_members WHERE cbo_id = ? AND member_id = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("ii", $cbo_id, $member_id);
            
            if ($checkStmt->execute()) {
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows > 0) {
                    $message = '<div class="alert alert-warning">This member is already part of the selected CBO!</div>';
                } else {
                    // Check group capacity if group number is provided
                    if ($group_number) {
                        $groupCheck = "SELECT COUNT(*) as count FROM cbo_members 
                                     WHERE cbo_id = ? AND group_number = ?";
                        $groupStmt = $conn->prepare($groupCheck);
                        $groupStmt->bind_param("is", $cbo_id, $group_number);
                        $groupStmt->execute();
                        $groupResult = $groupStmt->get_result();
                        $groupCount = $groupResult->fetch_assoc()['count'];
                        $groupStmt->close();
                        
                        if ($groupCount >= 4) {
                            $message = '<div class="alert alert-warning">Group '.htmlspecialchars($group_number).' already has 4 members!</div>';
                            $checkStmt->close();
                            goto render_page;
                        }
                    }
                    
                    // Insert new CBO member with group number
                    $insertQuery = "INSERT INTO cbo_members (cbo_id, member_id, position, group_number, join_date) 
                                   VALUES (?, ?, ?, ?, NOW())";
                    $insertStmt = $conn->prepare($insertQuery);
                    $insertStmt->bind_param("iiss", $cbo_id, $member_id, $position, $group_number);
                    
                    if ($insertStmt->execute()) {
                        $message = '<div class="alert alert-success">Member successfully added to CBO' . 
                                  ($group_number ? ' Group '.htmlspecialchars($group_number) : '') . '!</div>';
                        $member = null; // Clear member data
                    } else {
                        $message = '<div class="alert alert-danger">Error adding member: ' . htmlspecialchars($conn->error) . '</div>';
                    }
                    $insertStmt->close();
                }
            } else {
                $message = '<div class="alert alert-danger">Error checking membership: ' . htmlspecialchars($conn->error) . '</div>';
            }
            $checkStmt->close();
        }
    }

    // Change member's group
    if (isset($_POST['change_group'])) {
        $cbo_member_id = (int)$_POST['cbo_member_id'];
        $new_group = !empty($_POST['new_group']) ? $_POST['new_group'] : null;
        
        // Get current member info for validation
        $currentQuery = "SELECT cm.*, m.full_name, c.cbo_name 
                        FROM cbo_members cm
                        JOIN members m ON cm.member_id = m.id
                        JOIN cbo c ON cm.cbo_id = c.cbo_id
                        WHERE cm.id = ?";
        $currentStmt = $conn->prepare($currentQuery);
        $currentStmt->bind_param("i", $cbo_member_id);
        
        if ($currentStmt->execute()) {
            $currentResult = $currentStmt->get_result();
            $currentMember = $currentResult->fetch_assoc();
            
            if ($currentMember) {
                // Check new group capacity if changing to a group
                if ($new_group) {
                    $groupCheck = "SELECT COUNT(*) as count FROM cbo_members 
                                 WHERE cbo_id = ? AND group_number = ? AND id != ?";
                    $groupStmt = $conn->prepare($groupCheck);
                    $groupStmt->bind_param("isi", $currentMember['cbo_id'], $new_group, $cbo_member_id);
                    $groupStmt->execute();
                    $groupResult = $groupStmt->get_result();
                    $groupCount = $groupResult->fetch_assoc()['count'];
                    $groupStmt->close();
                    
                    if ($groupCount >= 4) {
                        $message = '<div class="alert alert-warning">Group '.htmlspecialchars($new_group).' already has 4 members!</div>';
                        $currentStmt->close();
                        goto render_page;
                    }
                }
                
                // Update the group number
                $updateQuery = "UPDATE cbo_members SET group_number = ? WHERE id = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("si", $new_group, $cbo_member_id);
                
                if ($updateStmt->execute()) {
                    $message = '<div class="alert alert-success">' . 
                              htmlspecialchars($currentMember['full_name']) . 
                              ' has been moved to ' . 
                              ($new_group ? 'Group ' . htmlspecialchars($new_group) : 'no group') . 
                              ' in ' . htmlspecialchars($currentMember['cbo_name']) . '</div>';
                } else {
                    $message = '<div class="alert alert-danger">Error changing group: ' . 
                              htmlspecialchars($conn->error) . '</div>';
                }
                $updateStmt->close();
            } else {
                $message = '<div class="alert alert-warning">Member not found!</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Error retrieving member info: ' . 
                      htmlspecialchars($conn->error) . '</div>';
        }
        $currentStmt->close();
    }

    // Get list of CBOs for dropdown
    $cboQuery = "SELECT cbo_id, cbo_name FROM cbo ORDER BY cbo_name";
    $cboResult = $conn->query($cboQuery);
    
    if ($cboResult) {
        $cbos = $cboResult->fetch_all(MYSQLI_ASSOC);
    } else {
        $message = '<div class="alert alert-danger">Error loading CBOs: ' . htmlspecialchars($conn->error) . '</div>';
    }
} else {
    $message = '<div class="alert alert-danger">Database connection failed. Please try again later.</div>';
}

// Function to display CBO groups
function displayCboGroups($conn, $cbo_id) {
    $query = "SELECT 
                cm.group_number,
                COUNT(cm.member_id) as member_count,
                GROUP_CONCAT(m.full_name SEPARATOR '<br>') as members
              FROM cbo_members cm
              JOIN members m ON cm.member_id = m.id
              WHERE cm.cbo_id = ? AND cm.group_number IS NOT NULL
              GROUP BY cm.group_number
              ORDER BY cm.group_number";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $cbo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo '<div class="mt-4">';
        echo '<h4><i class="bi bi-people-fill"></i> Group Members</h4>';
        echo '<div class="row">';
        
        while ($row = $result->fetch_assoc()) {
            echo '<div class="col-md-3 mb-3">';
            echo '<div class="card h-100">';
            echo '<div class="card-header">Group ' . htmlspecialchars($row['group_number']) . '</div>';
            echo '<div class="card-body">';
            echo '<p class="card-text">Members: ' . $row['member_count'] . '/4</p>';
            echo '<p class="card-text">' . $row['members'] . '</p>';
            echo '</div></div></div>';
        }
        
        echo '</div></div>';
    }
    $stmt->close();
}

render_page:
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Member to CBO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .container {
            max-width: 800px;
            margin-top: 30px;
            margin-bottom: 30px;
        }
        .search-box, .member-details, .group-management {
            background-color: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .member-details {
            background-color: #e9ecef;
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 5px;
        }
        .btn-action {
            padding: 8px 15px;
        }
        .info-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .group-card {
            transition: all 0.3s ease;
        }
        .group-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4 text-center"><i class="bi bi-people-fill"></i> CBO Group Management</h1>
        
        <?php if (!empty($message)) echo $message; ?>
        
        <?php if ($conn): ?>
            <div class="search-box">
                <h3 class="mb-3"><i class="bi bi-search"></i> Search Member</h3>
                <form method="GET" action="">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-9">
                            <label for="nic" class="form-label">Member NIC Number</label>
                            <input type="text" class="form-control" id="nic" name="nic" 
                                   value="<?= isset($_GET['nic']) ? htmlspecialchars($_GET['nic']) : '' ?>" 
                                   required pattern="[0-9]{9}[vVxX]?" 
                                   title="Enter valid NIC (e.g., 123456789V or 123456789X)">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" name="search" class="btn btn-primary w-100 btn-action">
                                <i class="bi bi-search"></i> Search
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <?php if ($member): ?>
            <div class="member-details">
                <h3 class="mb-3"><i class="bi bi-person-badge"></i> Member Details</h3>
                <div class="info-card">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><i class="bi bi-person"></i> Name:</strong> <?= htmlspecialchars($member['full_name']) ?></p>
                            <p><strong><i class="bi bi-telephone"></i> Phone:</strong> <?= htmlspecialchars($member['phone'] ?? 'N/A') ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><i class="bi bi-credit-card"></i> NIC:</strong> <?= htmlspecialchars($member['nic']) ?></p>
                            <p><strong><i class="bi bi-geo-alt"></i> Address:</strong> <?= htmlspecialchars($member['address'] ?? 'N/A') ?></p>
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="cbo_id" class="form-label"><i class="bi bi-building"></i> Select CBO</label>
                            <select class="form-select" id="cbo_id" name="cbo_id" required>
                                <option value="">-- Select CBO --</option>
                                <?php foreach ($cbos as $cbo): ?>
                                    <option value="<?= $cbo['cbo_id'] ?>" <?= isset($_POST['cbo_id']) && $_POST['cbo_id'] == $cbo['cbo_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cbo['cbo_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="position" class="form-label"><i class="bi bi-person-gear"></i> Position/Role</label>
                            <select name="position" class="form-select" required>
                                <option value="">-- Select Position/Role --</option>
                                <option value="Center_leader" <?php echo (isset($_POST['position']) && $_POST['position'] == 'Center_leader') ? 'selected' : ''; ?>>Center Leader</option>
                                <option value="Member" <?= (isset($_POST['position']) && $_POST['position'] == 'Member') ? 'selected' : '' ?>>Member</option>
                                <option value="Secretary" <?= (isset($_POST['position']) && $_POST['position'] == 'Secretary') ? 'selected' : '' ?>>Secretary</option>
                                <option value="Treasurer" <?= (isset($_POST['position']) && $_POST['position'] == 'Treasurer') ? 'selected' : '' ?>>Treasurer</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="group_number" class="form-label"><i class="bi bi-people-fill"></i> Group Number</label>
                            <select class="form-select" id="group_number" name="group_number">
                                <option value="">-- No Group --</option>
                                <option value="01" <?= (isset($_POST['group_number']) && $_POST['group_number'] == '01') ? 'selected' : '' ?>>Group 01</option>
                                <option value="02" <?= (isset($_POST['group_number']) && $_POST['group_number'] == '02') ? 'selected' : '' ?>>Group 02</option>
                                <option value="03" <?= (isset($_POST['group_number']) && $_POST['group_number'] == '03') ? 'selected' : '' ?>>Group 03</option>
                                <option value="04" <?= (isset($_POST['group_number']) && $_POST['group_number'] == '04') ? 'selected' : '' ?>>Group 04</option>
                            </select>
                            <small class="text-muted">Max 4 members per group</small>
                        </div>
                        
                        <div class="col-12 mt-3">
                            <button type="submit" name="add_to_cbo" class="btn btn-success px-4">
                                <i class="bi bi-person-plus"></i> Add to CBO
                            </button>
                            <a href="cbo.php" class="btn btn-outline-secondary ms-2">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
                
                <?php if (isset($_POST['cbo_id'])): ?>
                    <?php displayCboGroups($conn, (int)$_POST['cbo_id']); ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="group-management">
                <h3><i class="bi bi-arrow-repeat"></i> Change Member's Group</h3>
                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label for="cbo_member_id" class="form-label">Select Member</label>
                                    <select class="form-select" id="cbo_member_id" name="cbo_member_id" required>
                                        <option value="">-- Select Member --</option>
                                        <?php 
                                        $membersQuery = "SELECT cm.id, m.full_name, c.cbo_name, cm.group_number
                                                       FROM cbo_members cm
                                                       JOIN members m ON cm.member_id = m.id
                                                       JOIN cbo c ON cm.cbo_id = c.cbo_id
                                                       ORDER BY c.cbo_name, m.full_name";
                                        $membersResult = $conn->query($membersQuery);
                                        while ($row = $membersResult->fetch_assoc()): ?>
                                            <option value="<?= $row['id'] ?>">
                                                <?= htmlspecialchars($row['cbo_name']) ?> - 
                                                <?= htmlspecialchars($row['full_name']) ?>
                                                (Current: <?= $row['group_number'] ? 'Group '.$row['group_number'] : 'No group' ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="new_group" class="form-label">New Group</label>
                                    <select class="form-select" id="new_group" name="new_group">
                                        <option value="">-- No Group --</option>
                                        <option value="01">Group 01</option>
                                        <option value="02">Group 02</option>
                                        <option value="03">Group 03</option>
                                        <option value="04">Group 04</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" name="change_group" class="btn btn-warning w-100">
                                        <i class="bi bi-arrow-repeat"></i> Change Group
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Additional JavaScript for better UX -->
    <script>
        // Client-side NIC validation
        document.getElementById('nic').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });
        
        // Simple client-side validation
        document.querySelector('form[method="POST"]').addEventListener('submit', function(e) {
            const position = document.getElementById('position');
            if (position && !position.value) {
                alert('Please select a position/role');
                e.preventDefault();
            }
        });
        
        // Show group capacity when selecting a group
        document.getElementById('group_number')?.addEventListener('change', function() {
            const cboSelect = document.getElementById('cbo_id');
            if (this.value && cboSelect?.value) {
                // In a real implementation, you would fetch current group count via AJAX
                console.log(`Checking capacity for Group ${this.value} in CBO ${cboSelect.value}`);
            }
        });
    </script>
</body>
</html>