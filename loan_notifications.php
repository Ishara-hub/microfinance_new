<?php
/**
 * Loan Application Notification System
 */

require_once 'config/database.php';

class LoanNotificationSystem {
    private $db;
    
    public function __construct() {
        $this->db = new PDO('mysql:host=localhost;dbname=microfinance', 'username', 'password');
    }
    
    /**
     * Get count of pending loan applications
     */
    public function getPendingLoanCount() {
        $query = "SELECT COUNT(*) as count FROM loan_applications WHERE status = 'Pending'";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
    
    /**
     * Get count of pending lease applications
     */
    public function getPendingLeaseCount() {
        $query = "SELECT COUNT(*) as count FROM lease_applications WHERE status = 'Pending'";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
    
    /**
     * Get count of pending micro loan applications
     */
    public function getPendingMicroLoanCount() {
        $query = "SELECT COUNT(*) as count FROM micro_loan_applications WHERE status = 'Pending'";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
    
    /**
     * Send notification about pending applications
     */
    public function sendPendingApplicationsNotification($userId) {
        $loanCount = $this->getPendingLoanCount();
        $leaseCount = $this->getPendingLeaseCount();
        $microCount = $this->getPendingMicroLoanCount();
        $totalCount = $loanCount + $leaseCount + $microCount;
        
        if ($totalCount > 0) {
            $message = "You have $totalCount pending applications (";
            $parts = [];
            if ($loanCount > 0) $parts[] = "$loanCount regular loans";
            if ($leaseCount > 0) $parts[] = "$leaseCount leases";
            if ($microCount > 0) $parts[] = "$microCount micro loans";
            $message .= implode(", ", $parts) . ")";
            
            $link = "loan_approval1.php";
            
            $query = "INSERT INTO notifications (user_id, message, link, is_read) 
                      VALUES (?, ?, ?, 0)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$userId, $message, $link]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get all unread loan-related notifications for a user
     */
    public function getUnreadLoanNotifications($userId) {
        $query = "SELECT * FROM notifications 
                  WHERE user_id = ? AND is_read = 0 
                  AND (message LIKE '%Loan%' OR message LIKE '%Lease%')
                  ORDER BY created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mark notifications as read
     */
    public function markAsRead($notificationIds) {
        if (empty($notificationIds)) return false;
        
        $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
        $query = "UPDATE notifications SET is_read = 1 WHERE id IN ($placeholders)";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute($notificationIds);
    }
}
?>