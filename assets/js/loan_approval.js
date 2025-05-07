$(document).ready(function() {
    // Auto-reload functionality
    let autoReload = false;
    let reloadInterval;
    
    $('#autoReloadBtn').click(function() {
        autoReload = !autoReload;
        
        if (autoReload) {
            $(this).addClass('btn-success').removeClass('btn-primary');
            $(this).html('<i class="fas fa-sync-alt fa-spin"></i> Auto Refresh ON');
            reloadInterval = setInterval(function() {
                location.reload();
            }, 30000); // Reload every 30 seconds
        } else {
            $(this).addClass('btn-primary').removeClass('btn-success');
            $(this).html('<i class="fas fa-sync-alt"></i> Auto Refresh');
            clearInterval(reloadInterval);
        }
    });

    // Check for new applications periodically
    function checkNewApplications() {
        $.ajax({
            url: 'assets/check_new_applications.php',
            method: 'GET',
            data: { loan_type: '<?= $loan_type_filter ?>' },
            success: function(response) {
                if (response.count > 0) {
                    showNewApplicationNotification(response.count);
                }
            },
            complete: function() {
                setTimeout(checkNewApplications, 60000);
            }
        });
    }

    function showNewApplicationNotification(count) {
        let notificationBadge = $('#newLoanBadge');
        if (notificationBadge.length === 0) {
            $('h2').append(` <span class="badge bg-danger" id="newLoanBadge">${count} New</span>`);
        } else {
            notificationBadge.text(`${count} New`);
        }
        
        const toast = $(`
            <div class="toast show position-fixed bottom-0 end-0 m-3" style="z-index: 9999">
                <div class="toast-header bg-primary text-white">
                    <strong class="me-auto">New Application</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    There ${count === 1 ? 'is' : 'are'} ${count} new application${count === 1 ? '' : 's'} waiting for review.
                    <a href="loan_approval1.php" class="text-white fw-bold">Click to view</a>
                </div>
            </div>
        `);
        
        $('body').append(toast);
        setTimeout(function() { toast.remove(); }, 5000);
    }

    checkNewApplications();
});