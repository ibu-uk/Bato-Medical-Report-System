// Main JavaScript for Bato Medical Report System

// Function to load recent patients
function loadRecentPatients() {
    const statusElement = $('#search_status');
    statusElement.text('Loading recent patients...');
    
    $.ajax({
        url: 'includes/search_patients.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            // Clear existing options except the first one
            $('#patient option:not(:first)').remove();
            
            if (response.success && response.patients.length > 0) {
                // Add patient options
                $.each(response.patients, function(index, patient) {
                    const option = `<option value="${patient.id}" 
                        data-civil-id="${patient.civil_id}" 
                        data-mobile="${patient.mobile}" 
                        data-file-number="${patient.file_number}">
                        ${patient.name}
                    </option>`;
                    $('#patient').append(option);
                });
                
                // Update status message
                statusElement.text(response.message || `Showing ${response.count} recent patients`);
            } else {
                // No results found
                statusElement.text('No patients found');
            }
        },
        error: function() {
            statusElement.text('Error loading patients. Please try again.');
        }
    });
}

$(document).ready(function() {
    // Load recent patients on page load
    loadRecentPatients();
    // Patient search functionality - optimized for large databases
    let searchTimeout;
    $('#patient_search').on('input', function() {
        const searchTerm = $(this).val().trim();
        const statusElement = $('#search_status');
        
        // Clear any pending timeout
        clearTimeout(searchTimeout);
        
        // Reset patient dropdown
        $('#patient').val('');
        $('#civil_id').val('');
        $('#mobile').val('');
        
        if (searchTerm.length === 0) {
            // If search is cleared, show initial message
            statusElement.text('Type at least 3 characters to search');
            loadRecentPatients();
            return;
        }
        
        if (searchTerm.length < 3) {
            // Require at least 3 characters
            statusElement.text('Type at least 3 characters to search');
            return;
        }
        
        // Show searching message
        statusElement.text('Searching...');
        
        // Set a timeout to avoid making requests on every keystroke
        searchTimeout = setTimeout(function() {
            // Make AJAX request to search patients
            $.ajax({
                url: 'includes/search_patients.php',
                type: 'GET',
                data: { search: searchTerm },
                dataType: 'json',
                success: function(response) {
                    // Clear existing options except the first one
                    $('#patient option:not(:first)').remove();
                    
                    if (response.success && response.patients.length > 0) {
                        // Add patient options
                        $.each(response.patients, function(index, patient) {
                            const option = `<option value="${patient.id}" 
                                data-civil-id="${patient.civil_id}" 
                                data-mobile="${patient.mobile}" 
                                data-file-number="${patient.file_number}">
                                ${patient.name}
                            </option>`;
                            $('#patient').append(option);
                        });
                        
                        // Update status message
                        if (response.count >= response.limit) {
                            statusElement.text(`Showing first ${response.count} results. Refine your search for more specific results.`);
                        } else {
                            statusElement.text(`Found ${response.count} matching patients`);
                        }
                        
                        // If there's only one result, select it automatically
                        if (response.patients.length === 1) {
                            $('#patient').val(response.patients[0].id).trigger('change');
                        }
                    } else {
                        // No results found
                        statusElement.text(`No patients found matching "${searchTerm}"`);
                    }
                },
                error: function() {
                    statusElement.text('Error searching patients. Please try again.');
                }
            });
        }, 300); // 300ms delay to reduce server load
    });
    
    // Clear search button
    $('#clear_search').click(function() {
        $('#patient_search').val('');
        $('#search_status').text('Type at least 3 characters to search');
        loadRecentPatients();
    });
    
    // Patient selection - auto-fill patient details
    $('#patient').change(function() {
        const patientId = $(this).val();
        if (patientId) {
            // Get Civil ID from data attribute
            const civilId = $('option:selected', this).data('civil-id');
            $('#civil_id').val(civilId);
            
            // Fetch additional patient details via AJAX
            $.ajax({
                url: 'includes/get_patient_details.php',
                type: 'POST',
                data: { patient_id: patientId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#mobile').val(response.mobile);
                    }
                }
            });
        } else {
            // Clear fields if no patient selected
            $('#civil_id').val('');
            $('#mobile').val('');
        }
    });

    // Doctor selection - auto-fill doctor position
    $('#doctor').change(function() {
        const position = $('option:selected', this).data('position');
        $('#doctor_position').val(position || '');
    });

    // Add Test button functionality
    $('#addTestBtn').click(function() {
        addTestRow();
    });

    // Initial test row
    addTestRow();

    // Form validation before submission
    $('#reportForm').submit(function(e) {
        // Check if at least one test is added
        if ($('.test-row').length === 0) {
            alert('Please add at least one test result.');
            e.preventDefault();
            return false;
        }
        
        // Check if all test values are filled
        let isValid = true;
        $('.test-value').each(function() {
            if ($(this).val() === '') {
                isValid = false;
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        if (!isValid) {
            alert('Please fill in all test values.');
            e.preventDefault();
            return false;
        }
    });
});

// Function to add a new test row
function addTestRow() {
    // Create a unique ID for this test row
    const rowId = 'test-' + Date.now();
    
    // Create the HTML for the test row
    const html = `
        <div class="test-row" id="${rowId}">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Test Type</label>
                    <div class="input-group mb-2">
                        <input type="text" class="form-control test-search" placeholder="Search test by name">
                        <button class="btn btn-outline-secondary clear-test-search" type="button">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <select class="form-select test-type" name="test_type_id[]" required>
                        <option value="">-- Select Test --</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Value</label>
                    <div class="input-group">
                        <input type="text" class="form-control test-value" name="test_value[]" required>
                        <select class="form-select flag-select" name="test_flag[]" style="max-width: 100px;">
                            <option value="">--</option>
                            <option value="HIGH" style="color: red;">HIGH</option>
                            <option value="LOW" style="color: red;">LOW</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Unit</label>
                    <input type="text" class="form-control test-unit" name="test_unit[]" readonly>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Normal Range</label>
                    <input type="text" class="form-control test-range" name="test_range[]" readonly>
                </div>
                <div class="col-md-1 mb-3 d-flex align-items-end">
                    <button type="button" class="btn btn-danger remove-test" onclick="removeTestRow('${rowId}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label">Remarks</label>
                    <textarea class="form-control test-remarks" name="test_remarks[]" rows="2" placeholder="Enter any remarks or notes here"></textarea>
                </div>
            </div>
        </div>
    `;
    
    // Append the row to the container
    $('#testsContainer').append(html);
    
    // Load test types for the new row
    loadTestTypes($(`#${rowId} .test-type`));
    
    // Set up test search functionality
    $(`#${rowId} .test-search`).on('input', function() {
        const searchTerm = $(this).val().trim();
        const testSelect = $(this).closest('.test-row').find('.test-type');
        
        if (searchTerm.length > 0) {
            // Load filtered test types
            loadTestTypes(testSelect, searchTerm);
        } else {
            // Load all test types if search is cleared
            loadTestTypes(testSelect);
        }
    });
    
    // Clear test search button
    $(`#${rowId} .clear-test-search`).click(function() {
        const searchInput = $(this).closest('.input-group').find('.test-search');
        searchInput.val('');
        const testSelect = $(this).closest('.test-row').find('.test-type');
        loadTestTypes(testSelect);
    });
    
    // Set up change event for the test type dropdown
    $(`#${rowId} .test-type`).change(function() {
        const testId = $(this).val();
        const row = $(this).closest('.test-row');
        
        if (testId) {
            // Fetch test details via AJAX
            $.ajax({
                url: 'includes/get_test_details.php',
                type: 'POST',
                data: { test_id: testId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Store the original values for comparison later
                        row.find('.test-unit')
                            .val(response.unit)
                            .data('original-value', response.unit)
                            .prop('readonly', false);
                        
                        row.find('.test-range')
                            .val(response.normal_range)
                            .data('original-value', response.normal_range)
                            .prop('readonly', false);
                            
                        // Add save button if it doesn't exist
                        if (row.find('.save-test-details').length === 0) {
                            const saveBtn = $('<button>', {
                                type: 'button',
                                class: 'btn btn-sm btn-primary save-test-details ms-2',
                                html: '<i class="fas fa-save"></i> Save',
                                'data-test-id': testId
                            });
                            row.find('.test-range').after(saveBtn);
                            
                            // Initially hide the save button
                            saveBtn.hide();
                        } else {
                            // Update the test ID on the existing save button
                            row.find('.save-test-details').attr('data-test-id', testId).hide();
                        }
                    }
                }
            });
        } else {
            // Clear fields if no test selected
            row.find('.test-unit').val('').prop('readonly', true);
            row.find('.test-range').val('').prop('readonly', true);
            row.find('.save-test-details').hide();
        }
    });
    
    // Set up input event for unit and range fields to show save button when changed
    $(`#${rowId}`).on('input', '.test-unit, .test-range', function() {
        const row = $(this).closest('.test-row');
        const unitField = row.find('.test-unit');
        const rangeField = row.find('.test-range');
        const saveBtn = row.find('.save-test-details');
        
        // Check if either field has changed from its original value
        const unitChanged = unitField.val() !== unitField.data('original-value');
        const rangeChanged = rangeField.val() !== rangeField.data('original-value');
        
        // Show or hide save button based on changes
        if (unitChanged || rangeChanged) {
            saveBtn.show();
        } else {
            saveBtn.hide();
        }
    });
    
    // Set up click event for save button
    $(`#${rowId}`).on('click', '.save-test-details', function() {
        const row = $(this).closest('.test-row');
        const testId = $(this).data('test-id');
        const unitField = row.find('.test-unit');
        const rangeField = row.find('.test-range');
        const saveBtn = $(this);
        
        // Get the new values
        const unit = unitField.val().trim();
        const normalRange = rangeField.val().trim();
        
        // Validate fields
        if (!unit) {
            alert('Unit field cannot be empty');
            unitField.focus();
            return;
        }
        
        // Show loading state
        saveBtn.html('<i class="fas fa-spinner fa-spin"></i> Saving...');
        saveBtn.prop('disabled', true);
        
        // Save the changes via AJAX
        $.ajax({
            url: 'includes/update_test_details.php',
            type: 'POST',
            data: {
                test_id: testId,
                unit: unit,
                normal_range: normalRange
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update the original values
                    unitField.data('original-value', unit);
                    rangeField.data('original-value', normalRange);
                    
                    // Show success message
                    const alertHtml = `<div class="alert alert-success mt-2 mb-2 test-update-alert">Test details updated successfully!</div>`;
                    row.find('.save-test-details').after(alertHtml);
                    
                    // Hide the alert after 3 seconds
                    setTimeout(function() {
                        row.find('.test-update-alert').fadeOut(function() {
                            $(this).remove();
                        });
                    }, 3000);
                    
                    // Hide the save button
                    saveBtn.hide();
                } else {
                    // Show error message
                    alert('Failed to update test details: ' + (response.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('Network error occurred while saving test details');
            },
            complete: function() {
                // Reset button state
                saveBtn.html('<i class="fas fa-save"></i> Save');
                saveBtn.prop('disabled', false);
            }
        });
    });
}

// Function to remove a test row
function removeTestRow(rowId) {
    $(`#${rowId}`).remove();
}

// Function to load test types
function loadTestTypes(selectElement, searchTerm = '') {
    // Show loading indicator in the parent row
    const testRow = selectElement.closest('.test-row');
    const searchInput = testRow.find('.test-search');
    const notFoundMsg = testRow.find('.test-not-found');
    
    // Remove any existing not found message
    if (notFoundMsg.length) {
        notFoundMsg.remove();
    }
    
    $.ajax({
        url: 'includes/get_test_types.php',
        type: 'GET',
        data: { search: searchTerm },
        dataType: 'json',
        success: function(response) {
            // Clear existing options except the first one
            selectElement.find('option:not(:first)').remove();
            
            if (response.success && response.tests.length > 0) {
                // Add new options
                $.each(response.tests, function(index, test) {
                    selectElement.append(`<option value="${test.id}" data-name="${test.name.toLowerCase()}">${test.name}</option>`);
                });
                
                // If there's only one result and we're searching, select it automatically
                if (searchTerm && response.tests.length === 1) {
                    selectElement.val(response.tests[0].id).trigger('change');
                }
                
                // Make sure select is enabled
                selectElement.prop('disabled', false);
            } else if (searchTerm) {
                // If search term provided but no results found
                // Add a not found message
                const notFoundHtml = `<div class="alert alert-warning test-not-found mt-1 mb-2">Test "${searchTerm}" not found</div>`;
                searchInput.after(notFoundHtml);
                
                // Disable the select since there are no options
                selectElement.prop('disabled', true);
            } else {
                // No search term and no results
                const notFoundHtml = `<div class="alert alert-warning test-not-found mt-1 mb-2">No test types available</div>`;
                searchInput.after(notFoundHtml);
                
                // Disable the select since there are no options
                selectElement.prop('disabled', true);
            }
        },
        error: function() {
            // Show error message
            const errorHtml = `<div class="alert alert-danger test-not-found mt-1 mb-2">Error loading test types</div>`;
            searchInput.after(errorHtml);
        }
    });
}
