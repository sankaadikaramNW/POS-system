// assets/js/main.js
$(document).ready(function () {
    // Sidebar toggle
    $('#sidebarCollapse').on('click', function () {
        $('#sidebar').toggleClass('active');
    });

    // Auto dismiss alerts after 3 seconds
    setTimeout(function() {
        $(".alert").alert('close');
    }, 3000);
});
