<?php
// Enable strict error reporting
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Include database connection
require_once "db.php";

// Set proper content type
header('Content-Type: text/html; charset=utf-8');

try {
    // Validate and sanitize input
    $account_id = filter_input(INPUT_POST, 'account_id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);

    // Start with default option
    $options = '<option value="">None</option>';

    if ($account_id) {
        // Use prepared statement with error handling
        $stmt = $conn->prepare("
            SELECT id, sub_account_code, sub_account_name 
            FROM sub_accounts 
            WHERE parent_account_id = ? AND is_active = 1
            ORDER BY sub_account_code
        ");
        
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }

        $stmt->bind_param("i", $account_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        
        // Build options string
        while ($sub = $result->fetch_assoc()) {
            $options .= sprintf(
                '<option value="%d">%s - %s</option>',
                $sub['id'],
                htmlspecialchars($sub['sub_account_code'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($sub['sub_account_name'], ENT_QUOTES, 'UTF-8')
            );
        }
        
        $stmt->close();
    }

    echo $options;

} catch (Exception $e) {
    // Log error (in production, log to file instead of outputting)
    error_log($e->getMessage());
    
    // Return just the default option on error
    echo '<option value="">None</option>';
}

// Close connection if needed
if (isset($conn)) {
    $conn->close();
}