<?php
include "db.php";
header('Content-Type: text/html');

if (isset($_POST['cbo_id'])) {
    $cbo_id = (int)$_POST['cbo_id'];
    $members = $conn->query("
        SELECT m.id, m.full_name, m.nic, m.phone, cm.position, cm.group_number 
        FROM cbo_members cm
        JOIN members m ON cm.member_id = m.id
        WHERE cm.cbo_id = $cbo_id
    ");
    
    $html = '';
    while ($member = $members->fetch_assoc()) {
        $html .= '
        <div class="col-md-6">
            <div class="card member-card" data-member-id="'.$member['id'].'">
                <div class="card-body">
                    <h5 class="card-title">'.$member['full_name'].'</h5>
                    <p class="card-text mb-1">
                        <small class="text-muted">NIC: '.$member['nic'].'</small>
                    </p>
                    <p class="card-text mb-1">
                        <small class="text-muted">Phone: '.$member['phone'].'</small>
                    </p>
                    <p class="card-text">
                        <small class="text-muted">
                            Position: '.$member['position'].', 
                            Group: '.($member['group_number'] ? 'Group '.$member['group_number'] : 'None').'
                        </small>
                    </p>
                </div>
            </div>
        </div>';
    }
    echo $html;
}
?>