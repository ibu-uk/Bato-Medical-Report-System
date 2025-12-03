/**
 * Common JavaScript functions for Bato Medical Report System
 */

/**
 * Shows a confirmation dialog before performing an action
 * @param {Object} options - Configuration options
 * @param {string} options.title - Dialog title
 * @param {string} options.text - Dialog message
 * @param {string} [options.icon='warning'] - SweetAlert2 icon
 * @param {string} [options.confirmButtonText='Yes, proceed'] - Confirm button text
 * @param {string} [options.cancelButtonText='Cancel'] - Cancel button text
 * @param {string} [options.confirmButtonClass='btn btn-danger'] - Confirm button class
 * @param {string} [options.cancelButtonClass='btn btn-secondary me-2'] - Cancel button class
 * @param {Function} [options.onConfirm] - Function to execute on confirm
 * @param {string} [options.redirectUrl] - URL to redirect to after confirmation
 * @param {string} [options.successMessage] - Success message to show after action
 */
function showConfirmationDialog(options) {
    const defaultOptions = {
        title: 'Are you sure?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, proceed',
        cancelButtonText: 'Cancel',
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-secondary me-2',
            actions: 'mt-4'
        },
        buttonsStyling: false
    };

    const settings = { ...defaultOptions, ...options };

    Swal.fire(settings).then((result) => {
        if (result.isConfirmed) {
            if (settings.onConfirm) {
                settings.onConfirm();
            } else if (settings.redirectUrl) {
                // Show loading state
                Swal.fire({
                    title: 'Processing...',
                    text: 'Please wait',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Add small delay to show loading state
                setTimeout(() => {
                    if (settings.successMessage) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: settings.successMessage,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = settings.redirectUrl;
                        });
                    } else {
                        window.location.href = settings.redirectUrl;
                    }
                }, 500);
            }
        }
    });
}

/**
 * Initialize all confirmation dialogs for links with data-confirm attribute
 */
function initConfirmDialogs() {
    document.querySelectorAll('a[data-confirm]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const message = this.getAttribute('data-confirm');
            const href = this.getAttribute('href');
            const method = this.getAttribute('data-method') || 'get';
            const successMessage = this.getAttribute('data-success-message');
            
            showConfirmationDialog({
                title: 'Confirm Action',
                text: message,
                confirmButtonText: 'Yes, continue',
                redirectUrl: href,
                successMessage: successMessage
            });
        });
    });

    // Initialize form confirmations
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', function(e) {
            const message = this.getAttribute('data-confirm');
            if (!message) return;
            
            e.preventDefault();
            
            showConfirmationDialog({
                title: 'Confirm Action',
                text: message,
                confirmButtonText: 'Yes, submit',
                onConfirm: () => {
                    // Show loading state
                    Swal.fire({
                        title: 'Processing...',
                        text: 'Please wait',
                        allowOutsideClick: false,
                        didOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                            // Submit the form after showing loading
                            form.submit();
                        }
                    });
                }
            });
        });
    });
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    initConfirmDialogs();
});
