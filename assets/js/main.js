// Notification System
document.addEventListener('DOMContentLoaded', function() {
    const notificationBell = document.getElementById('notificationBell');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    // Toggle dropdown
    if (notificationBell && notificationDropdown) {
        notificationBell.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('active');
        });
        
        // Close when clicking outside
        document.addEventListener('click', function() {
            notificationDropdown.classList.remove('active');
        });
    }
    
    // Real-time updates
    // userId is now defined in the global scope from the inline script in the HTML
    if (typeof(EventSource) !== "undefined" && userId) {
        const eventSource = new EventSource("notification_stream.php?user_id=<?= $_SESSION['user_id'] ?? 0 ?>");
        
        eventSource.onmessage = function(e) {
            const data = JSON.parse(e.data);
            updateNotificationBadge(data.count);
            
            if (data.latest) {
                showToast(data.latest.message, data.latest.link);
            }
        };
    }
});

function updateNotificationBadge(count) {
    const badge = document.querySelector('.notification-badge');
    
    if (count > 0) {
        if (!badge) {
            const newBadge = document.createElement('span');
            newBadge.className = 'notification-badge';
            newBadge.textContent = count;
            document.querySelector('.notification-icon').appendChild(newBadge);
        } else {
            badge.textContent = count;
        }
    } else if (badge) {
        badge.remove();
    }
}

function showToast(message, link) {
    // Toast implementation here
}
