<?php
session_start();

require_once 'config/timezone.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/staff_chat.php';

requireLogin();

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

if (!ensureStaffChatTables($conn)) {
    $conn->close();
    die('Could not initialize staff chat table.');
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentUserName = staffChatResolveSenderName($conn, $currentUserId);
$isAdmin = hasRole(['admin']);
$adminRecipients = $isAdmin ? staffChatGetNonAdminRecipients($conn, $currentUserId) : [];

$recipientNameMap = [];
foreach ($adminRecipients as $recipient) {
    $recipientId = (int)($recipient['id'] ?? 0);
    if ($recipientId <= 0) {
        continue;
    }

    $recipientNameMap[$recipientId] = trim((string)($recipient['full_name'] ?? ''));
}

$initialMessages = [];
if ($isAdmin) {
    $initialQuery = "SELECT id, user_id, sender_name, message_text, is_bot, attachment_path, attachment_name, attachment_type, thread_user_id, is_broadcast, created_at
                     FROM (
                        SELECT id, user_id, sender_name, message_text, is_bot, attachment_path, attachment_name, attachment_type, thread_user_id, is_broadcast, created_at
                        FROM staff_chat_messages
                        ORDER BY id DESC
                        LIMIT 80
                     ) latest
                     ORDER BY id ASC";
    $initialResult = $conn->query($initialQuery);
    if ($initialResult) {
        while ($row = $initialResult->fetch_assoc()) {
            $initialMessages[] = [
                'id' => (int)$row['id'],
                'user_id' => isset($row['user_id']) ? (int)$row['user_id'] : 0,
                'sender_name' => $row['sender_name'],
                'message_text' => $row['message_text'],
                'is_bot' => (int)$row['is_bot'],
                'attachment_path' => $row['attachment_path'] ?? null,
                'attachment_name' => $row['attachment_name'] ?? null,
                'attachment_type' => $row['attachment_type'] ?? null,
                'thread_user_id' => isset($row['thread_user_id']) ? (int)$row['thread_user_id'] : 0,
                'is_broadcast' => isset($row['is_broadcast']) ? (int)$row['is_broadcast'] : 0,
                'is_own' => isset($row['user_id']) && (int)$row['user_id'] === $currentUserId,
                'created_at' => $row['created_at']
            ];
        }
    }
} else {
    $initialQuery = "SELECT id, user_id, sender_name, message_text, is_bot, attachment_path, attachment_name, attachment_type, thread_user_id, is_broadcast, created_at
                     FROM (
                        SELECT id, user_id, sender_name, message_text, is_bot, attachment_path, attachment_name, attachment_type, thread_user_id, is_broadcast, created_at
                        FROM staff_chat_messages
                        WHERE is_broadcast = 1 OR thread_user_id = ?
                        ORDER BY id DESC
                        LIMIT 80
                     ) latest
                     ORDER BY id ASC";
    $stmt = $conn->prepare($initialQuery);
    if ($stmt) {
        $stmt->bind_param('i', $currentUserId);
        $stmt->execute();
        $initialResult = $stmt->get_result();
        while ($initialResult && $row = $initialResult->fetch_assoc()) {
            $initialMessages[] = [
                'id' => (int)$row['id'],
                'user_id' => isset($row['user_id']) ? (int)$row['user_id'] : 0,
                'sender_name' => $row['sender_name'],
                'message_text' => $row['message_text'],
                'is_bot' => (int)$row['is_bot'],
                'attachment_path' => $row['attachment_path'] ?? null,
                'attachment_name' => $row['attachment_name'] ?? null,
                'attachment_type' => $row['attachment_type'] ?? null,
                'thread_user_id' => isset($row['thread_user_id']) ? (int)$row['thread_user_id'] : 0,
                'is_broadcast' => isset($row['is_broadcast']) ? (int)$row['is_broadcast'] : 0,
                'is_own' => isset($row['user_id']) && (int)$row['user_id'] === $currentUserId,
                'created_at' => $row['created_at']
            ];
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Chat - Bato Medical Report System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .chat-shell {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 145px);
            min-height: 520px;
            border: 1px solid #d6e5f1;
            border-radius: 14px;
            background: linear-gradient(180deg, #f6fbff 0%, #ffffff 18%);
            box-shadow: 0 10px 24px rgba(15, 61, 94, 0.08);
            overflow: hidden;
        }

        .chat-header {
            border-bottom: 1px solid #d6e5f1;
            background: linear-gradient(90deg, #0f3d5e, #16648a);
            color: #fff;
            padding: 0.85rem 1rem;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background-image: radial-gradient(circle at 25px 25px, #eef6fd 2px, transparent 0);
            background-size: 18px 18px;
        }

        .message-row {
            display: flex;
            margin-bottom: 0.75rem;
        }

        .message-row.own {
            justify-content: flex-end;
        }

        .message-bubble {
            max-width: min(78%, 720px);
            border-radius: 12px;
            padding: 0.6rem 0.75rem;
            border: 1px solid #d9e6f2;
            background: #ffffff;
            box-shadow: 0 4px 10px rgba(16, 50, 76, 0.08);
            white-space: pre-wrap;
            word-break: break-word;
        }

        .message-row.own .message-bubble {
            background: #e8f5ff;
            border-color: #b7daf3;
        }

        .message-row.bot .message-bubble {
            background: #fff8e8;
            border-color: #f1d59a;
        }

        .message-meta {
            font-size: 0.76rem;
            color: #5f6f80;
            margin-bottom: 0.15rem;
            display: flex;
            gap: 0.45rem;
            align-items: center;
        }

        .message-tag {
            font-size: 0.68rem;
            background: #edf3f9;
            color: #36556f;
            border: 1px solid #c7d8e6;
            border-radius: 999px;
            padding: 0.05rem 0.4rem;
        }

        .chat-composer {
            border-top: 1px solid #d6e5f1;
            background: #fff;
            padding: 0.75rem;
        }

        .chat-hints {
            font-size: 0.82rem;
            color: #5d7185;
            margin-top: 0.45rem;
        }

        .chat-image-preview {
            display: block;
            margin-top: 0.5rem;
            max-width: min(420px, 100%);
            border-radius: 10px;
            border: 1px solid #cddfed;
            box-shadow: 0 5px 14px rgba(14, 45, 67, 0.12);
        }

        .chat-upload-row {
            margin-top: 0.55rem;
        }

        .recipient-panel {
            margin-top: 0.55rem;
            padding: 0.55rem 0.65rem;
            border: 1px solid #cde0f1;
            border-radius: 10px;
            background: #f4f9ff;
        }

        .recipient-panel .form-label {
            font-size: 0.76rem;
            font-weight: 600;
            color: #3d5f7c;
            margin-bottom: 0.25rem;
        }

        .chat-status {
            font-size: 0.8rem;
            color: #5d7185;
        }

        @media (max-width: 768px) {
            .chat-shell {
                height: calc(100vh - 130px);
                min-height: 460px;
            }

            .message-bubble {
                max-width: 92%;
            }
        }
    </style>
</head>
<body>
    <?php include_once 'includes/sidebar.php'; ?>

    <nav class="top-navbar">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between w-100">
                <button class="btn btn-link d-md-none" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h5 class="mb-0">Staff Chat</h5>
                <span class="badge bg-info text-dark"><i class="fas fa-robot me-1"></i>Clinic Assistant Bot</span>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="container-fluid py-4">
            <div class="chat-shell">
                <div class="chat-header d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold"><i class="fas fa-comments me-1"></i> Internal Team Chat</div>
                        <small>Logged in as <?php echo htmlspecialchars($currentUserName); ?></small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <?php if ($isAdmin): ?>
                            <select class="form-select form-select-sm" id="adminThreadFilter" style="min-width: 220px;">
                                <option value="0">All Conversations</option>
                                <?php foreach ($adminRecipients as $recipient): ?>
                                    <option value="<?php echo (int)$recipient['id']; ?>"><?php echo htmlspecialchars($recipient['full_name'] . ' (' . ucfirst($recipient['role']) . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <?php if ($isAdmin): ?>
                            <button type="button" class="btn btn-sm btn-outline-light" id="clearChatBtn" title="Clear all staff chat messages">
                                <i class="fas fa-trash-alt me-1"></i>Clear Chat
                            </button>
                        <?php endif; ?>
                        <div class="chat-status" id="chatStatus">Connected</div>
                    </div>
                </div>

                <div class="chat-messages" id="chatMessages"></div>

                <div class="chat-composer">
                    <form id="chatForm" class="d-flex gap-2">
                        <input type="text" id="chatMessageInput" class="form-control" maxlength="1000" placeholder="Type message for staff chat (use /bot for assistant)" autocomplete="off">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i>Send
                        </button>
                    </form>
                    <?php if ($isAdmin): ?>
                        <div class="recipient-panel">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label for="recipientMode" class="form-label">Send To</label>
                                    <select id="recipientMode" class="form-select form-select-sm">
                                        <option value="all">All Staff (Broadcast)</option>
                                        <option value="single">Single Staff</option>
                                        <option value="multiple">Multiple Staff</option>
                                    </select>
                                </div>
                                <div class="col-md-4" id="singleRecipientWrap" style="display:none;">
                                    <label for="recipientUserId" class="form-label">Staff User</label>
                                    <select id="recipientUserId" class="form-select form-select-sm">
                                        <?php foreach ($adminRecipients as $recipient): ?>
                                            <option value="<?php echo (int)$recipient['id']; ?>"><?php echo htmlspecialchars($recipient['full_name'] . ' (' . ucfirst($recipient['role']) . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5" id="multipleRecipientsWrap" style="display:none;">
                                    <label for="recipientUserIds" class="form-label">Select Staff (Ctrl/Cmd + Click)</label>
                                    <select id="recipientUserIds" class="form-select form-select-sm" multiple size="3">
                                        <?php foreach ($adminRecipients as $recipient): ?>
                                            <option value="<?php echo (int)$recipient['id']; ?>"><?php echo htmlspecialchars($recipient['full_name'] . ' (' . ucfirst($recipient['role']) . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="chat-upload-row">
                        <input type="file" id="chatAttachmentInput" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp,image/gif">
                        <small class="text-muted">Optional: upload screenshot/image (JPG, PNG, WEBP, GIF - max 10MB)</small>
                    </div>
                    <div class="chat-hints">
                        Bot guide: <strong>/bot help</strong> to see menu • choose by number <strong>/bot 1</strong> to <strong>/bot 11</strong> • or use direct command like <strong>/bot today summary</strong>
                        <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline" id="botHelpQuickBtn">Need help?</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sidebar.js"></script>
    <script>
    const currentUserId = <?php echo (int)$currentUserId; ?>;
    const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
    const initialMessages = <?php echo json_encode($initialMessages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const recipientNameMap = <?php echo json_encode($recipientNameMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    const chatMessagesEl = document.getElementById('chatMessages');
    const chatFormEl = document.getElementById('chatForm');
    const chatInputEl = document.getElementById('chatMessageInput');
    const chatAttachmentEl = document.getElementById('chatAttachmentInput');
    const chatStatusEl = document.getElementById('chatStatus');
    const botHelpQuickBtnEl = document.getElementById('botHelpQuickBtn');
    const clearChatBtnEl = document.getElementById('clearChatBtn');
    const recipientModeEl = document.getElementById('recipientMode');
    const singleRecipientWrapEl = document.getElementById('singleRecipientWrap');
    const multipleRecipientsWrapEl = document.getElementById('multipleRecipientsWrap');
    const recipientUserIdEl = document.getElementById('recipientUserId');
    const recipientUserIdsEl = document.getElementById('recipientUserIds');
    const adminThreadFilterEl = document.getElementById('adminThreadFilter');
    let lastMessageId = 0;
    let isSending = false;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatTime(dateValue) {
        if (!dateValue) return '';
        const date = new Date(dateValue.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return dateValue;
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function appendMessage(msg, shouldAutoScroll = true) {
        if (!msg || !msg.id) return;
        lastMessageId = Math.max(lastMessageId, Number(msg.id));

        const row = document.createElement('div');
        row.className = 'message-row';
        if (Number(msg.user_id) === currentUserId) {
            row.classList.add('own');
        }
        if (Number(msg.is_bot) === 1) {
            row.classList.add('bot');
        }

        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';

        const meta = document.createElement('div');
        meta.className = 'message-meta';

        const sender = document.createElement('span');
        sender.innerHTML = Number(msg.is_bot) === 1
            ? '<i class="fas fa-robot"></i> ' + escapeHtml(msg.sender_name)
            : '<i class="fas fa-user"></i> ' + escapeHtml(msg.sender_name);

        const time = document.createElement('span');
        time.textContent = formatTime(msg.created_at);

        meta.appendChild(sender);
        meta.appendChild(time);

        const isBroadcast = Number(msg.is_broadcast || 0) === 1;
        const threadUserId = Number(msg.thread_user_id || 0);
        if (isBroadcast) {
            const scopeTag = document.createElement('span');
            scopeTag.className = 'message-tag';
            scopeTag.textContent = isAdmin ? 'Broadcast' : 'Announcement';
            meta.appendChild(scopeTag);
        } else if (isAdmin && threadUserId > 0) {
            const scopeTag = document.createElement('span');
            scopeTag.className = 'message-tag';
            scopeTag.textContent = 'To ' + (recipientNameMap[String(threadUserId)] || ('User #' + threadUserId));
            meta.appendChild(scopeTag);
        }

        const text = document.createElement('div');
        text.textContent = msg.message_text || '';

        const attachmentPath = msg.attachment_path ? String(msg.attachment_path) : '';
        const isImageAttachment = attachmentPath !== '' && /^uploads\/staff_chat\//.test(attachmentPath);

        bubble.appendChild(meta);
        if (text.textContent.trim() !== '') {
            bubble.appendChild(text);
        }

        if (isImageAttachment) {
            const image = document.createElement('img');
            image.src = attachmentPath;
            image.alt = msg.attachment_name || 'Chat attachment';
            image.className = 'chat-image-preview';
            image.loading = 'lazy';
            bubble.appendChild(image);
        }

        row.appendChild(bubble);
        chatMessagesEl.appendChild(row);

        if (shouldAutoScroll) {
            chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
        }
    }

    function loadInitialMessages() {
        initialMessages.forEach((msg) => appendMessage(msg, false));
        chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
    }

    function setStatus(text, isError = false) {
        chatStatusEl.textContent = text;
        chatStatusEl.style.color = isError ? '#ffc9c9' : '#d7ecff';
    }

    function getActiveThreadFilterUserId() {
        if (!isAdmin || !adminThreadFilterEl) {
            return 0;
        }

        const value = Number(adminThreadFilterEl.value || 0);
        return Number.isFinite(value) && value > 0 ? value : 0;
    }

    async function fetchMessages(reset = false) {
        try {
            if (reset) {
                lastMessageId = 0;
                chatMessagesEl.innerHTML = '';
            }

            const query = new URLSearchParams();
            query.set('since_id', String(lastMessageId));
            const threadFilterUserId = getActiveThreadFilterUserId();
            if (threadFilterUserId > 0) {
                query.set('thread_user_id', String(threadFilterUserId));
            }

            const response = await fetch('staff_chat_fetch.php?' + query.toString(), {
                headers: { 'Accept': 'application/json' }
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || 'Failed to fetch messages');
            }

            if (Array.isArray(data.messages)) {
                data.messages.forEach((msg) => appendMessage(msg, true));
            }

            setStatus('Connected');
        } catch (error) {
            setStatus('Reconnecting...', true);
        }
    }

    async function sendMessage(message, attachmentFile) {
        if (isSending) return;
        isSending = true;

        try {
            const payload = new FormData();
            payload.append('message', message);
            if (attachmentFile) {
                payload.append('attachment', attachmentFile);
            }

            if (isAdmin && recipientModeEl) {
                const recipientMode = recipientModeEl.value || 'all';
                payload.append('recipient_mode', recipientMode);

                if (recipientMode === 'single' && recipientUserIdEl) {
                    payload.append('recipient_user_id', recipientUserIdEl.value || '0');
                }

                if (recipientMode === 'multiple' && recipientUserIdsEl) {
                    const selectedRecipientIds = Array.from(recipientUserIdsEl.selectedOptions || []).map((option) => option.value);
                    selectedRecipientIds.forEach((id) => payload.append('recipient_user_ids[]', id));
                }
            }

            const response = await fetch('staff_chat_send.php', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json'
                },
                body: payload
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || 'Could not send message');
            }

            await fetchMessages();
        } catch (error) {
            alert(error.message);
        } finally {
            isSending = false;
        }
    }

    function updateRecipientModeUi() {
        if (!isAdmin || !recipientModeEl) {
            return;
        }

        const mode = recipientModeEl.value;
        if (singleRecipientWrapEl) {
            singleRecipientWrapEl.style.display = mode === 'single' ? '' : 'none';
        }
        if (multipleRecipientsWrapEl) {
            multipleRecipientsWrapEl.style.display = mode === 'multiple' ? '' : 'none';
        }
    }

    function validateRecipientsBeforeSend() {
        if (!isAdmin || !recipientModeEl) {
            return true;
        }

        const mode = recipientModeEl.value;
        if (mode === 'single') {
            const selectedId = Number(recipientUserIdEl?.value || 0);
            if (selectedId <= 0) {
                alert('Please select one staff recipient.');
                return false;
            }
        }

        if (mode === 'multiple') {
            const selectedCount = Array.from(recipientUserIdsEl?.selectedOptions || []).length;
            if (selectedCount === 0) {
                alert('Please select at least one staff recipient.');
                return false;
            }
        }

        return true;
    }

    chatFormEl.addEventListener('submit', async function(event) {
        event.preventDefault();
        const message = chatInputEl.value.trim();
        const attachmentFile = chatAttachmentEl && chatAttachmentEl.files && chatAttachmentEl.files.length > 0
            ? chatAttachmentEl.files[0]
            : null;
        if (!message && !attachmentFile) return;
        if (!validateRecipientsBeforeSend()) return;

        chatInputEl.value = '';
        if (chatAttachmentEl) {
            chatAttachmentEl.value = '';
        }
        await sendMessage(message, attachmentFile);
        chatInputEl.focus();
    });

    clearChatBtnEl?.addEventListener('click', async function() {
        const confirmed = window.confirm('Are you sure you want to clear all staff chat messages? This cannot be undone.');
        if (!confirmed) {
            return;
        }

        try {
            clearChatBtnEl.disabled = true;
            const response = await fetch('clear_staff_chat.php', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || 'Failed to clear chat');
            }

            chatMessagesEl.innerHTML = '';
            lastMessageId = 0;
            alert('Chat cleared successfully.');
        } catch (error) {
            alert(error.message || 'Could not clear chat.');
        } finally {
            clearChatBtnEl.disabled = false;
        }
    });

    botHelpQuickBtnEl?.addEventListener('click', function() {
        chatInputEl.value = '/bot help';
        chatInputEl.focus();
    });

    recipientModeEl?.addEventListener('change', updateRecipientModeUi);
    updateRecipientModeUi();

    adminThreadFilterEl?.addEventListener('change', function() {
        fetchMessages(true);
    });

    document.getElementById('mobileMenuToggle')?.addEventListener('click', function() {
        document.body.classList.toggle('sidebar-collapsed');
    });

    loadInitialMessages();
    setInterval(fetchMessages, 3000);
    </script>
</body>
</html>
