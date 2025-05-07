// Tab switching functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.tab');
    const contentSections = document.querySelectorAll('.content > section');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('active'));
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Hide all content sections
            contentSections.forEach(section => {
                section.style.display = 'none';
            });
            
            // Show corresponding content section
            const tabName = this.textContent.trim().toLowerCase().replace(' ', '-');
            const targetSection = document.querySelector(`.${tabName}-section`);
            if (targetSection) {
                targetSection.style.display = 'block';
            }
        });
    });
    
    // Payment schedule row click handler
    const scheduleRows = document.querySelectorAll('.schedule-table tbody tr');
    scheduleRows.forEach(row => {
        row.addEventListener('click', function() {
            // This would typically open a modal with payment details
            console.log('Payment details for installment:', this.cells[0].textContent);
        });
    });
});

// Modal functions
function showSettleModal() {
    document.getElementById('settleModal').style.display = 'block';
}

function showCashRequestModal() {
    alert('Cash request modal would appear here');
}

function showNoteModal() {
    alert('Note modal would appear here');
}

function generateReport() {
    alert('Report generation would be implemented here');
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    if (event.target.className === 'modal') {
        event.target.style.display = 'none';
    }
}