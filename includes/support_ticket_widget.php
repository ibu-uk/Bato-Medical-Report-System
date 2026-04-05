<style>
    .helpdesk-fab {
        position: fixed;
        top: 72px;
        right: 18px;
        z-index: 1200;
        border: none;
        border-radius: 999px;
        padding: 0.5rem 0.9rem;
        background: linear-gradient(135deg, #0f4f79 0%, #1f6f9f 100%);
        color: #ffffff;
        font-weight: 600;
        font-size: 0.82rem;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        box-shadow: 0 8px 18px rgba(10, 43, 69, 0.28);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .helpdesk-fab:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 22px rgba(10, 43, 69, 0.34);
    }

    .helpdesk-fab i {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    @media (max-width: 768px) {
        .helpdesk-fab {
            top: auto;
            bottom: 18px;
            right: 14px;
            padding: 0.55rem 0.75rem;
        }

        .helpdesk-fab span {
            display: none;
        }
    }
</style>

<button type="button" class="helpdesk-fab" id="helpDeskFloatingBtn" title="Do you need help?">
    <i class="fas fa-headset"></i>
    <span>DO YOU NEED HELP!</span>
</button>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    function openSupportTicketModal() {
        const issueFormHtml = `
            <div class="text-start">
                <div class="mb-2">
                    <label class="form-label">Subject</label>
                    <input type="text" id="supportSubject" class="form-control" maxlength="255" placeholder="Short title of issue">
                </div>
                <div class="mb-2">
                    <label class="form-label">Issue Type</label>
                    <select id="supportIssueType" class="form-select">
                        <option value="general">General</option>
                        <option value="bug">Bug/Error</option>
                        <option value="access">Access/Permission</option>
                        <option value="performance">Slow Performance</option>
                        <option value="training">Need Guidance/Training</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Priority</label>
                    <select id="supportPriority" class="form-select">
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <div class="mb-0">
                    <label class="form-label">Details</label>
                    <textarea id="supportDetails" class="form-control" rows="4" placeholder="Explain what happened and what you need help with"></textarea>
                </div>
                <div class="mt-2">
                    <label class="form-label">Attachment (Optional: screenshot/image/video, max 20MB)</label>
                    <input type="file" id="supportAttachment" class="form-control" accept="image/*,video/*">
                </div>
                <div class="mt-3 pt-2 border-top rounded px-2 py-2" style="background:#eef3f8;border:1px solid #c9d7e6;">
                    <small class="fw-semibold" style="color:#1b3954;">For assistance, contact IT Support - Mohamed Ibrahim | Mobile/WhatsApp: 96566680241 | Email: it@batoclinic.com</small>
                </div>
            </div>
        `;

        Swal.fire({
            title: 'Create Support Ticket',
            html: issueFormHtml,
            width: 650,
            showCancelButton: true,
            confirmButtonText: 'Submit Ticket',
            cancelButtonText: 'Cancel',
            focusConfirm: false,
            preConfirm: () => {
                const subject = document.getElementById('supportSubject').value.trim();
                const details = document.getElementById('supportDetails').value.trim();
                const attachmentInput = document.getElementById('supportAttachment');
                const attachment = attachmentInput && attachmentInput.files ? attachmentInput.files[0] : null;

                if (!subject || !details) {
                    Swal.showValidationMessage('Subject and details are required');
                    return false;
                }

                if (attachment && attachment.size > 20 * 1024 * 1024) {
                    Swal.showValidationMessage('Attachment exceeds 20MB.');
                    return false;
                }

                return {
                    subject,
                    details,
                    issue_type: document.getElementById('supportIssueType').value,
                    priority: document.getElementById('supportPriority').value,
                    current_page: window.location.pathname + window.location.search,
                    attachment
                };
            }
        }).then((result) => {
            if (!result.isConfirmed || !result.value) {
                return;
            }

            const payload = new FormData();
            payload.append('subject', result.value.subject);
            payload.append('details', result.value.details);
            payload.append('issue_type', result.value.issue_type);
            payload.append('priority', result.value.priority);
            payload.append('current_page', result.value.current_page);
            if (result.value.attachment) {
                payload.append('attachment', result.value.attachment);
            }

            Swal.fire({
                title: 'Submitting...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch('create_support_ticket.php', {
                method: 'POST',
                body: payload
            })
            .then((response) => response.json())
            .then((data) => {
                if (!data.success) {
                    throw new Error(data.message || 'Failed to create support ticket');
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Ticket Created',
                    text: `Support ticket #${data.ticket_id} created successfully.`,
                    confirmButtonText: 'Open Support Center'
                }).then(() => {
                    window.location.href = 'support_center.php';
                });
            })
            .catch((err) => {
                Swal.fire({
                    icon: 'error',
                    title: 'Request Failed',
                    text: err.message
                });
            });
        });
    }

    window.openSupportTicketModal = window.openSupportTicketModal || openSupportTicketModal;

    const helpDeskFloatingBtn = document.getElementById('helpDeskFloatingBtn');
    if (helpDeskFloatingBtn) {
        helpDeskFloatingBtn.addEventListener('click', function (e) {
            e.preventDefault();
            window.location.href = 'staff_chat.php';
        });
    }
})();
</script>
