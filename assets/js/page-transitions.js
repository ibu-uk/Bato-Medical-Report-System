/**
 * Page Transitions and Loading States
 */

document.addEventListener('DOMContentLoaded', function() {
    // Create loading overlay
    const loadingOverlay = document.createElement('div');
    loadingOverlay.className = 'page-loading';
    loadingOverlay.innerHTML = `
        <div class="spinner"></div>
        <div class="mt-3 text-primary fw-bold">Loading...</div>
    `;
    document.body.appendChild(loadingOverlay);

    // Show loading state when clicking links
    document.addEventListener('click', function(e) {
        const target = e.target.closest('a');
        
        // Only handle internal links
        if (target && target.href && 
            !target.hasAttribute('data-no-loading') && 
            !target.target && 
            target.href.startsWith(window.location.origin) &&
            !target.href.includes('#')) {
            
            e.preventDefault();
            const destination = target.href;
            
            // Show loading overlay
            loadingOverlay.classList.add('active');
            
            // Add a small delay to show the loading state
            setTimeout(() => {
                window.location.href = destination;
            }, 300);
        }
    });

    // Handle form submissions
    document.addEventListener('submit', function(e) {
        const form = e.target;
        
        // Only handle regular form submissions (not AJAX)
        if (form && !form.hasAttribute('data-ajax')) {
            loadingOverlay.classList.add('active');
            
            // Add a small delay to show the loading state
            setTimeout(() => {
                form.submit();
            }, 300);
        }
    });

    // Handle browser back/forward buttons
    window.addEventListener('beforeunload', function() {
        loadingOverlay.classList.add('active');
    });

    // Hide loading overlay when page is fully loaded
    window.addEventListener('load', function() {
        // Add a small delay for a smoother transition
        setTimeout(() => {
            loadingOverlay.classList.remove('active');
            
            // Add fade-in effect to the main content
            const content = document.querySelector('.content-wrapper') || document.querySelector('main') || document.body;
            content.style.opacity = 0;
            content.style.transition = 'opacity 0.5s ease-in-out';
            
            // Trigger reflow
            void content.offsetWidth;
            
            // Fade in
            content.style.opacity = 1;
        }, 300);
    });

    // Add smooth scrolling to anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                e.preventDefault();
                window.scrollTo({
                    top: targetElement.offsetTop - 20,
                    behavior: 'smooth'
                });
            }
        });
    });
});
