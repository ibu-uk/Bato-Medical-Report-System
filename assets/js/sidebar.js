document.addEventListener('DOMContentLoaded', function() {
    // Toggle sidebar collapse
    const menuToggle = document.querySelector('.menu-toggle');
    const body = document.body;
    
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            body.classList.toggle('sidebar-collapsed');
            // Save state in localStorage
            if (body.classList.contains('sidebar-collapsed')) {
                localStorage.setItem('sidebarCollapsed', 'true');
            } else {
                localStorage.removeItem('sidebarCollapsed');
            }
        });
    }

    // Initialize submenu toggles
    const submenuToggles = document.querySelectorAll('.has-submenu');
    submenuToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            if (e.target === this || this.contains(e.target)) {
                const submenu = this.querySelector('.submenu');
                if (submenu) {
                    submenu.classList.toggle('show');
                    const icon = this.querySelector('.dropdown-icon');
                    if (icon) {
                        icon.style.transform = submenu.classList.contains('show') 
                            ? 'rotate(90deg)' 
                            : 'rotate(0)';
                    }
                }
            }
        });
    });

    // Check for saved sidebar state
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        body.classList.add('sidebar-collapsed');
    }

    // Make sidebar links with submenus work with Bootstrap collapse
    const hasSubmenuLinks = document.querySelectorAll('.has-submenu > a');
    hasSubmenuLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (window.innerWidth > 768) { // Only prevent default on desktop
                e.preventDefault();
                const parent = this.parentElement;
                const submenu = this.nextElementSibling;
                if (submenu && submenu.classList.contains('submenu')) {
                    const isExpanded = submenu.classList.contains('show');
                    submenu.classList.toggle('show');
                    const icon = this.querySelector('.dropdown-icon');
                    if (icon) {
                        icon.style.transform = !isExpanded ? 'rotate(90deg)' : 'rotate(0)';
                    }
                }
            }
        });
    });

    // Handle responsive behavior
    function handleResize() {
        if (window.innerWidth <= 768) {
            body.classList.add('sidebar-collapsed');
        } else if (!localStorage.getItem('sidebarCollapsed')) {
            body.classList.remove('sidebar-collapsed');
        }
    }

    // Initial check
    handleResize();
    
    // Add event listener for window resize
    window.addEventListener('resize', handleResize);
});
