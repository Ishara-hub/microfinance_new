<?php
include "db.php";
header('Content-Type: text/html');

if (isset($_POST['branch_id'])) {
    $branch_id = (int)$_POST['branch_id'];
    $cbos = $conn->query("SELECT cbo_id, cbo_name FROM cbo WHERE branch_id = $branch_id");
    
    $options = '';
    while ($cbo = $cbos->fetch_assoc()) {
        $options .= "<option value='{$cbo['cbo_id']}'>{$cbo['cbo_name']}</option>";
    }
    echo $options;
}
?>