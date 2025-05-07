// Check for new notifications every 30 seconds
function pollNotifications() {
    fetch('check_notifications.php')
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.notification-badge');
            if (data.count > 0) {
                badge.textContent = data.count;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        })
        .catch(error => console.error('Error:', error));
}

setInterval(pollNotifications, 30000); // 30-second polling
pollNotifications(); // Initial load

// Toggle dropdown
document.getElementById('notificationBell').addEventListener('click', function() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    
    // Fetch latest notifications when opened
    if (dropdown.style.display === 'block') {
        fetch('fetch_notifications.php')
            .then(response => response.json())
            .then(data => {
                const list = document.querySelector('.notification-list');
                list.innerHTML = data.notifications.length > 0 
                    ? data.notifications.map(notif => `
                        <a href="assets/mark_notification_read.php?id=${notif.id}&redirect=${encodeURIComponent(notif.link)}" 
                           class="notification-item ${notif.is_read ? 'read' : 'unread'}">
                            <div class="notification-message">${notif.message}</div>
                            <div class="notification-time">${notif.time}</div>
                        </a>
                      `).join('')
                    : '<div class="notification-item empty">No new notifications</div>';
            });
    }
});