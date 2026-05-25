// assets/js/main.js
$(document).ready(function () {
    // Sidebar toggle
    $('#sidebarCollapse').on('click', function (e) {
        e.stopPropagation();
        $('#sidebar').toggleClass('active');
    });

    // Close sidebar on click outside on mobile
    $(document).on('click', function (e) {
        if ($(window).width() <= 768) {
            var sidebar = $('#sidebar');
            var collapseBtn = $('#sidebarCollapse');
            
            // If click was outside sidebar and toggle button, close sidebar
            if (!sidebar.is(e.target) && sidebar.has(e.target).length === 0 &&
                !collapseBtn.is(e.target) && collapseBtn.has(e.target).length === 0) {
                sidebar.removeClass('active');
            }
        }
    });

    // Auto dismiss alerts after 3 seconds
    setTimeout(function() {
        $(".alert").alert('close');
    }, 3000);
});
