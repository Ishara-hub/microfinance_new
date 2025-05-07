</div> <!-- Close container-fluid -->

<script>
    // Sidebar toggle functionality
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('active');
    });
    
    // Auto-close sidebar on mobile when clicking a link
    document.querySelectorAll('.sidebar a').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 768) {
                document.querySelector('.sidebar').classList.remove('active');
            }
        });
    });
</script>
</body>
</html>