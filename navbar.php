<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and utilities
include_once "db.php";
include_once "utilities.php";

// Get notification count if user is logged in
$notification_count = 0;
$notifications = [];
if (isset($_SESSION['user_id'])) {
    // Get unread notification count
    $count_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param("i", $_SESSION['user_id']);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $notification_count = $count_result->fetch_assoc()['count'];
    
    // Get recent notifications
    $notif_query = "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5";
    $notif_stmt = $conn->prepare($notif_query);
    $notif_stmt->bind_param("i", $_SESSION['user_id']);
    $notif_stmt->execute();
    $notifications = $notif_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Microfinance Dashboard</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css1/components/_navbar.css">
    
    <style>
        /* Dropdown styles */
        .has-dropdown {
            position: relative;
        }
        
        .has-dropdown > a::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 15px;
            top: 12px;
            font-size: 12px;
            transition: transform 0.3s;
        }
        
        .has-dropdown.open > a::after {
            transform: rotate(180deg);
        }
        
        .dropdown-content {
            display: none;
            padding-left: 20px;
            background-color: rgba(0,0,0,0.05);
        }
        
        .dropdown-content a {
            display: block;
            padding: 10px 15px;
            color: #777;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .dropdown-content a:hover {
            background-color: rgba(0,0,0,0.1);
        }
        
        .dropdown-content a.active {
            background-color: rgba(0,0,0,0.1);
            font-weight: bold;
        }
        
        /* Fix for unclosed div in leasing dropdown */
        .sidebar-menu > div {
            margin-bottom: 5px;
        }
    </style>
</head>

<body>
    <!-- Top Navigation Bar -->
    <nav class="top-navbar">
        <div class="navbar-brand">
            <h4><i class="fas fa-university"></i> Microfinance</h4>
        </div>
        
        <!-- Notification System -->
        <div class="notification-wrapper">
            <div class="notification-icon" id="notificationBell">
                <i class="fas fa-bell"></i>
                <span class="notification-text">Pending Loans</span>
                <?php if($notification_count > 0): ?>
                    <span class="notification-badge"><?= $notification_count ?></span>
                <?php endif; ?>
            </div>
            <div class="notification-dropdown" id="notificationDropdown">
                <div class="notification-header">
                    <h5>Notifications</h5>
                    <a href="all_notifications.php" class="view-all">View All</a>
                </div>
                <div class="notification-list">
                    <?php if(empty($notifications)): ?>
                        <div class="notification-item empty">
                            No new notifications
                        </div>
                    <?php else: ?>
                        <?php foreach($notifications as $notification): ?>
                            <a href="mark_notification_read.php?redirect=<?= urlencode($notification['link']) ?>&id=<?= $notification['id'] ?>" class="notification-item">
                                <div class="notification-message"><?= htmlspecialchars($notification['message']) ?></div>
                                <div class="notification-time"><?= time_elapsed_string($notification['created_at']) ?></div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-menu">
            <a href="dash_board_new.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dash_board_new.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="has-dropdown">
                <a href="javascript:void(0)">
                    <i class="fas fa-users"></i>
                    <span>Client Management</span>
                </a>
                <div class="dropdown-content">
                    <a href="members.php" class="<?= basename($_SERVER['PHP_SELF']) == 'members.php' ? 'active' : '' ?>">
                        <i class="fas fa-user-friends"></i>
                        <span>Clients</span>
                    </a>
                    <a href="add_member_new.php" class="<?= basename($_SERVER['PHP_SELF']) == 'add_member_new.php' ? 'active' : '' ?>">
                        <i class="fas fa-user-plus"></i>
                        <span>Add Client</span>
                    </a>
                </div>
            </div>
            
            <div class="has-dropdown">
                <a href="javascript:void(0)">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span>Loan Management</span>
                </a>
                <div class="dropdown-content">
                    <a href="loan_appl.php" class="<?= basename($_SERVER['PHP_SELF']) == 'loan_appl.php' ? 'active' : '' ?>">
                        <i class="fas fa-hand-holding-usd"></i>
                        <span>Loan Applications Daily & Business</span>
                    </a>
                    <a href="loan_approval.php" class="<?= basename($_SERVER['PHP_SELF']) == 'loan_approval.php' ? 'active' : '' ?>">
                        <i class="fas fa-check-circle"></i>
                        <span>Loan Approvals</span>
                    </a>
                    <a href="manage_payments.php" class="<?= basename($_SERVER['PHP_SELF']) == 'manage_payments.php' ? 'active' : '' ?>">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Manage Payments</span>
                    </a>
                </div>
            </div>
            
            <div class="has-dropdown">
                <a href="javascript:void(0)">
                    <i class="fas fa-chart-line"></i>
                    <span>Micro Loan Management</span>
                </a>
                <div class="dropdown-content">
                    <a href="cbo.php" class="<?= basename($_SERVER['PHP_SELF']) == 'cbo.php' ? 'active' : '' ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span>CBO Management</span>
                    </a>
                    <a href="add_member_to_cbo.php" class="<?= basename($_SERVER['PHP_SELF']) == 'add_member_to_cbo.php' ? 'active' : '' ?>">
                        <i class="fas fa-chart-pie"></i>
                        <span>Add member to CBO</span>
                    </a>
                    <a href="micro_loan_application.php" class="<?= basename($_SERVER['PHP_SELF']) == 'micro_loan_application.php' ? 'active' : '' ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span>New Micro Loan</span>
                    </a>
                    <a href="micro_loan_approval.php" class="<?= basename($_SERVER['PHP_SELF']) == 'micro_loan_approval.php' ? 'active' : '' ?>">
                        <i class="fas fa-check-circle"></i>
                        <span>Micro Loan Approvals</span>
                    </a>
                    <a href="pro_pay_m_new.php" class="<?= basename($_SERVER['PHP_SELF']) == 'pro_pay_m_new.php' ? 'active' : '' ?>">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Micro Repayment</span>
                    </a>
                </div>
            </div>
            
            <div class="has-dropdown">
                <a href="javascript:void(0)">
                    <i class="fas fa-car"></i>
                    <span>Leasing</span>
                </a>
                <div class="dropdown-content">
                    <a href="add_vehicle.php" class="<?= basename($_SERVER['PHP_SELF']) == 'add_vehicle.php' ? 'active' : '' ?>">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add Vehicle</span>
                    </a>
                    <a href="leasing.php" class="<?= basename($_SERVER['PHP_SELF']) == 'leasing.php' ? 'active' : '' ?>">
                        <i class="fas fa-file-contract"></i>
                        <span>New Lease</span>
                    </a>
                    <a href="lease_approval.php" class="<?= basename($_SERVER['PHP_SELF']) == 'lease_approval.php' ? 'active' : '' ?>">
                        <i class="fas fa-check-square"></i>
                        <span>Lease Approvals</span>
                    </a>
                    <a href="p_payment.php" class="<?= basename($_SERVER['PHP_SELF']) == 'p_payment.php' ? 'active' : '' ?>">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Lease Repayment</span>
                    </a>
                </div>
            </div>
            <div class="has-dropdown">
                <a href="javascript:void(0)">
                    <i class="fas fa-car"></i>
                    <span>Admin</span>
                </a>
                <div class="dropdown-content">
                    <a href="product.php" class="<?= basename($_SERVER['PHP_SELF']) == 'product.php' ? 'active' : '' ?>">
                        <i class="fas fa-plus-circle"></i>
                        <span>Loan Products</span>
                    </a>
                    <a href="branches.php" class="<?= basename($_SERVER['PHP_SELF']) == 'branches.php' ? 'active' : '' ?>">
                        <i class="fas fa-file-contract"></i>
                        <span>New Branch</span>
                    </a>
                    <a href="early_settlement.php" class="<?= basename($_SERVER['PHP_SELF']) == 'early_settlement.php' ? 'active' : '' ?>">
                        <i class="fas fa-file-contract"></i>
                        <span>Early Settlement</span>
                    </a>
                    <a href="new_user.php" class="<?= basename($_SERVER['PHP_SELF']) == 'new_user.php' ? 'active' : '' ?>">
                        <i class="fas fa-check-square"></i>
                        <span>User Management</span>
                    </a>
                </div>
            </div>
            <div class="has-dropdown">
                <a href="javascript:void(0)">
                    <i class="fas fa-car"></i>
                    <span>Accounts</span>
                </a>
                <div class="dropdown-content">
                    <a href="account_categories.php" class="<?= basename($_SERVER['PHP_SELF']) == 'account_categories.php' ? 'active' : '' ?>">
                        <i class="fas fa-plus-circle"></i>
                        <span>Account Categaries</span>
                    </a>
                    <a href="chart_of_accounts.php" class="<?= basename($_SERVER['PHP_SELF']) == 'chart_of_accounts.php' ? 'active' : '' ?>">
                        <i class="fas fa-file-contract"></i>
                        <span>Chart of Accounts</span>
                    </a>
                    <a href="sub_accounts.php" class="<?= basename($_SERVER['PHP_SELF']) == 'sub_accounts.php' ? 'active' : '' ?>">
                        <i class="fas fa-check-square"></i>
                        <span>Sub Accounts</span>
                    </a>
                    <a href="journal_entry.php" class="<?= basename($_SERVER['PHP_SELF']) == 'journal_entry.php' ? 'active' : '' ?>">
                        <i class="fas fa-check-square"></i>
                        <span>Journal Entry</span>
                    </a>
                    <a href="account_payment.php" class="<?= basename($_SERVER['PHP_SELF']) == 'account_payment.php' ? 'active' : '' ?>">
                        <i class="fas fa-check-square"></i>
                        <span>Account payment</span>
                    </a>
                    <a href="balance_sheet.php" class="<?= basename($_SERVER['PHP_SELF']) == 'balance_sheet.php' ? 'active' : '' ?>">
                        <i class="fas fa-check-square"></i>
                        <span>Balance sheet</span>
                    </a>
                    <a href="income_statement.php" class="<?= basename($_SERVER['PHP_SELF']) == 'income_statement.php' ? 'active' : '' ?>">
                        <i class="fas fa-check-square"></i>
                        <span>Income Statement</span>
                    </a>
                </div>
            </div>
                    <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
                        <i class="fas fa-list"></i>
                        <span>Reports</span>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="mt-4 sidebar-footer">
            <a href="settings.php" class="<?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>

    <script>
        // Dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggles = document.querySelectorAll('.has-dropdown > a');
            
            dropdownToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const parent = this.parentElement;
                    const dropdown = this.nextElementSibling;
                    
                    // Toggle current dropdown
                    if (dropdown.style.display === 'block') {
                        dropdown.style.display = 'none';
                        parent.classList.remove('open');
                    } else {
                        // Close all other dropdowns first
                        document.querySelectorAll('.dropdown-content').forEach(d => {
                            if (d !== dropdown) {
                                d.style.display = 'none';
                                d.parentElement.classList.remove('open');
                            }
                        });
                        
                        dropdown.style.display = 'block';
                        parent.classList.add('open');
                    }
                });
            });
            
            // Open dropdown if current page is in it
            const currentPage = '<?= basename($_SERVER['PHP_SELF']) ?>';
            document.querySelectorAll('.dropdown-content a').forEach(link => {
                if (link.classList.contains('active')) {
                    link.parentElement.style.display = 'block';
                    link.parentElement.parentElement.classList.add('open');
                }
            });
            
            // Notification dropdown
            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            
            if (notificationBell && notificationDropdown) {
                notificationBell.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationDropdown.style.display = notificationDropdown.style.display === 'block' ? 'none' : 'block';
                });
                
                // Close when clicking outside
                document.addEventListener('click', function() {
                    notificationDropdown.style.display = 'none';
                });
            }
        });
    </script>
</body>
</html>