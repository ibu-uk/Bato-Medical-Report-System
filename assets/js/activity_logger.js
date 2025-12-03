/**
 * Activity Logger for Bato Medical Report System
 * This script handles automatic logging of user activities on the client side
 */

// Initialize the activity logger
const ActivityLogger = {
    /**
     * Log a user activity
     * @param {string} type - The activity type
     * @param {number|string|null} id - The entity ID (optional)
     * @param {string|null} details - Additional details about the activity (optional)
     * @param {string|null} name - The entity name (optional)
     * @returns {Promise} - A promise that resolves with the server response
     */
    log: function(type, id = null, details = null, name = null) {
        return new Promise((resolve, reject) => {
            // Create form data
            const formData = new FormData();
            formData.append('type', type);
            
            if (id !== null) {
                formData.append('id', id);
            }
            
            if (details !== null) {
                formData.append('details', details);
            }
            
            if (name !== null) {
                formData.append('name', name);
            }
            
            // Send the log request
            fetch('log_activity.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                resolve(data);
            })
            .catch(error => {
                console.error('Error logging activity:', error);
                reject(error);
            });
        });
    },
    
    /**
     * Log page view activity
     * @param {string} pageName - The name of the page being viewed
     */
    logPageView: function(pageName) {
        const path = window.location.pathname;
        const fileName = path.substring(path.lastIndexOf('/') + 1);
        const details = `Viewed page: ${pageName || fileName}`;
        
        // Extract ID from URL if present
        const urlParams = new URLSearchParams(window.location.search);
        const id = urlParams.get('id');
        
        let activityType = 'page_view';
        let entityName = null;
        
        // Determine specific activity type based on the page
        if (fileName === 'index.php') {
            activityType = 'view_dashboard';
        } else if (fileName === 'reports.php') {
            activityType = 'view_reports';
        } else if (fileName === 'view_report.php' && id) {
            activityType = 'view_report';
            entityName = `Report #${id}`;
        } else if (fileName === 'edit_report.php' && id) {
            activityType = 'edit_report';
            entityName = `Report #${id}`;
        } else if (fileName === 'manage_test_types.php') {
            activityType = 'view_test_types';
        } else if (fileName === 'manage_doctors.php') {
            activityType = 'view_doctors';
        } else if (fileName === 'manage_users.php') {
            activityType = 'view_users';
        } else if (fileName === 'activity_logs.php') {
            activityType = 'view_logs';
        } else if (fileName === 'add_patient.php') {
            activityType = 'view_add_patient';
        } else if (fileName.includes('patient') && id) {
            activityType = 'view_patient';
            entityName = `Patient #${id}`;
        }
        
        this.log(activityType, id, details, entityName);
    },
    
    /**
     * Log form submission activity
     * @param {HTMLFormElement} form - The form being submitted
     * @param {string} actionType - The type of action (create, update, delete)
     * @param {string} entityType - The type of entity (patient, report, etc.)
     */
    logFormSubmission: function(form, actionType, entityType) {
        const formId = form.id || 'unknown_form';
        const formAction = form.action || window.location.href;
        const details = `${actionType} ${entityType} via form submission: ${formId}`;
        
        // Extract ID from form if present
        const idField = form.querySelector('[name="id"]');
        const id = idField ? idField.value : null;
        
        // Determine activity type
        let activityType = `${actionType}_${entityType}`;
        
        this.log(activityType, id, details);
    },
    
    /**
     * Log button click activity
     * @param {HTMLElement} button - The button being clicked
     * @param {string} actionType - The type of action (view, print, delete, etc.)
     * @param {string} entityType - The type of entity (patient, report, etc.)
     * @param {number|string|null} entityId - The entity ID (optional)
     * @param {string|null} entityName - The entity name (optional)
     */
    logButtonClick: function(button, actionType, entityType, entityId = null, entityName = null) {
        const buttonText = button.innerText || button.value || 'unknown';
        const details = `Clicked ${buttonText} button to ${actionType} ${entityType}`;
        
        // Determine activity type
        let activityType = `${actionType}_${entityType}`;
        
        this.log(activityType, entityId, details, entityName);
    },
    
    /**
     * Log search activity
     * @param {string} searchTerm - The search term
     * @param {string} entityType - The type of entity being searched (patient, report, etc.)
     */
    logSearch: function(searchTerm, entityType) {
        const details = `Searched for "${searchTerm}" in ${entityType}`;
        const activityType = `search_${entityType}`;
        
        this.log(activityType, null, details);
    },
    
    /**
     * Log data export activity
     * @param {string} exportType - The type of export (PDF, Excel, etc.)
     * @param {string} entityType - The type of entity being exported (patient, report, etc.)
     * @param {number|string|null} entityId - The entity ID (optional)
     */
    logExport: function(exportType, entityType, entityId = null) {
        const details = `Exported ${entityType} as ${exportType}`;
        const activityType = `export_${entityType}`;
        
        this.log(activityType, entityId, details);
    },
    
    /**
     * Log data import activity
     * @param {string} importType - The type of import (CSV, Excel, etc.)
     * @param {string} entityType - The type of entity being imported (patient, report, etc.)
     * @param {number} count - The number of records imported
     */
    logImport: function(importType, entityType, count) {
        const details = `Imported ${count} ${entityType} records from ${importType}`;
        const activityType = `import_${entityType}`;
        
        this.log(activityType, null, details);
    },
    
    /**
     * Log data deletion activity
     * @param {string} entityType - The type of entity being deleted (patient, report, etc.)
     * @param {number|string} entityId - The entity ID
     * @param {string|null} entityName - The entity name (optional)
     */
    logDeletion: function(entityType, entityId, entityName = null) {
        const details = `Deleted ${entityType} ${entityName ? `(${entityName})` : ''}`;
        const activityType = `delete_${entityType}`;
        
        this.log(activityType, entityId, details, entityName);
    }
};

// Automatically log page views when the page loads
document.addEventListener('DOMContentLoaded', function() {
    // Get page title if available
    const pageTitle = document.title.replace(' - Bato Medical Report System', '');
    ActivityLogger.logPageView(pageTitle);
    
    // Add event listeners for common actions
    setupFormLogging();
    setupButtonLogging();
    setupSearchLogging();
});

/**
 * Set up logging for form submissions
 */
function setupFormLogging() {
    // Find all forms and add submit event listeners
    document.querySelectorAll('form').forEach(form => {
        // Skip the search and filter forms
        if (form.id === 'searchForm' || form.id === 'filterForm') {
            return;
        }
        
        form.addEventListener('submit', function(event) {
            // Determine form purpose based on form action or ID
            const formAction = form.action || '';
            const formId = form.id || '';
            
            let actionType = 'create';
            let entityType = 'unknown';
            
            // Check if this is an edit form (has id field or edit in the URL)
            const hasIdField = form.querySelector('[name="id"][value]');
            if (hasIdField || formAction.includes('edit') || formId.includes('edit')) {
                actionType = 'edit';
            }
            
            // Determine entity type based on form action or ID
            if (formAction.includes('patient') || formId.includes('patient')) {
                entityType = 'patient';
            } else if (formAction.includes('report') || formId.includes('report')) {
                entityType = 'report';
            } else if (formAction.includes('doctor') || formId.includes('doctor')) {
                entityType = 'doctor';
            } else if (formAction.includes('user') || formId.includes('user')) {
                entityType = 'user';
            } else if (formAction.includes('test_type') || formId.includes('test_type')) {
                entityType = 'test_type';
            }
            
            ActivityLogger.logFormSubmission(form, actionType, entityType);
        });
    });
}

/**
 * Set up logging for button clicks
 */
function setupButtonLogging() {
    // Log print button clicks
    document.querySelectorAll('.btn-print, [data-action="print"]').forEach(button => {
        button.addEventListener('click', function() {
            const entityId = this.dataset.id || null;
            const entityName = this.dataset.name || null;
            const entityType = this.dataset.type || 'report';
            
            ActivityLogger.logButtonClick(this, 'print', entityType, entityId, entityName);
        });
    });
    
    // Log delete button clicks
    document.querySelectorAll('.btn-delete, [data-action="delete"]').forEach(button => {
        button.addEventListener('click', function() {
            const entityId = this.dataset.id || null;
            const entityName = this.dataset.name || null;
            const entityType = this.dataset.type || 'record';
            
            ActivityLogger.logButtonClick(this, 'delete', entityType, entityId, entityName);
        });
    });
    
    // Log export button clicks
    document.querySelectorAll('.btn-export, [data-action="export"]').forEach(button => {
        button.addEventListener('click', function() {
            const entityId = this.dataset.id || null;
            const entityType = this.dataset.type || 'report';
            const exportType = this.dataset.format || 'PDF';
            
            ActivityLogger.logExport(exportType, entityType, entityId);
        });
    });
}

/**
 * Set up logging for search actions
 */
function setupSearchLogging() {
    // Find all search forms and add submit event listeners
    document.querySelectorAll('form#searchForm, .search-form').forEach(form => {
        form.addEventListener('submit', function(event) {
            const searchInput = this.querySelector('input[type="search"], input[name*="search"]');
            if (searchInput && searchInput.value.trim()) {
                // Determine what's being searched
                let entityType = 'records';
                
                // Try to determine entity type from form or page context
                const formAction = form.action || window.location.href;
                if (formAction.includes('patient')) {
                    entityType = 'patient';
                } else if (formAction.includes('report')) {
                    entityType = 'report';
                } else if (formAction.includes('doctor')) {
                    entityType = 'doctor';
                } else if (formAction.includes('user')) {
                    entityType = 'user';
                } else if (formAction.includes('test_type')) {
                    entityType = 'test_type';
                }
                
                ActivityLogger.logSearch(searchInput.value.trim(), entityType);
            }
        });
    });
    
    // Also log search input with keyup events (for live search)
    document.querySelectorAll('input[type="search"], input[name*="search"]').forEach(input => {
        let timeout = null;
        input.addEventListener('keyup', function() {
            clearTimeout(timeout);
            const searchTerm = this.value.trim();
            
            if (searchTerm.length >= 3) {
                // Debounce to avoid excessive logging
                timeout = setTimeout(() => {
                    // Determine what's being searched
                    let entityType = 'records';
                    
                    // Try to determine entity type from input or page context
                    const inputName = input.name || '';
                    const currentPage = window.location.pathname;
                    
                    if (inputName.includes('patient') || currentPage.includes('patient')) {
                        entityType = 'patient';
                    } else if (inputName.includes('report') || currentPage.includes('report')) {
                        entityType = 'report';
                    } else if (inputName.includes('doctor') || currentPage.includes('doctor')) {
                        entityType = 'doctor';
                    } else if (inputName.includes('user') || currentPage.includes('user')) {
                        entityType = 'user';
                    } else if (inputName.includes('test') || currentPage.includes('test')) {
                        entityType = 'test_type';
                    }
                    
                    ActivityLogger.logSearch(searchTerm, entityType);
                }, 1000); // Wait 1 second after typing stops
            }
        });
    });
}
