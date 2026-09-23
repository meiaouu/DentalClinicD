<?php

use App\Core\Csrf;

$conversation = $conversation ?? [];
$messages = $messages ?? [];
$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

ob_start();
?>
<div class="card">
    <h1 style="margin-top:0;">Conversation</h1>

    <?php if ($flash_success): ?>
        <p style="color:green;"><?= htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if ($flash_error): ?>
        <p style="color:#b91c1c;"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <p><strong>Patient:</strong> <?= htmlspecialchars(trim(($conversation['patient_first_name'] ?? '') . ' ' . ($conversation['patient_middle_name'] ?? '') . ' ' . ($conversation['patient_last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Contact:</strong> <?= htmlspecialchars((string) ($conversation['contact_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars((string) ($conversation['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Status:</strong> <?= htmlspecialchars((string) ($conversation['conversation_status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>

    <hr>

    <h2>Thread</h2>
    <?php if (empty($messages)): ?>
        <p>No messages yet.</p>
    <?php else: ?>
        <div style="display:grid; gap:10px;">
            <?php foreach ($messages as $message): ?>
                <div style="border:1px solid #e5e7eb; border-radius:10px; padding:12px;">
                    <p><strong><?= htmlspecialchars((string) ($message['sender_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong> at <?= htmlspecialchars((string) ($message['sent_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <p><?= nl2br(htmlspecialchars((string) ($message['message_text'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <hr>

    <h2>Reply as Staff</h2>
    <form method="POST" action="/staff/messages/reply">
        <?= Csrf::inputField(); ?>
        <input type="hidden" name="conversation_id" value="<?= (int) ($conversation['conversation_id'] ?? 0) ?>">

        <div class="form-group">
            <label>Message</label>
            <textarea name="message_body" rows="4" required></textarea>
        </div>

        <button type="submit">Send Reply</button>
    </form>

    <p style="margin-top:20px;">
        <a href="/staff/messages">Back to inbox</a>
    </p>
</div>
<?php
$content = ob_get_clean();
$title = 'Conversation';
require __DIR__ . '/../../layouts/main.php';