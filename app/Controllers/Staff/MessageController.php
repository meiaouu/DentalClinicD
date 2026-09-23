<?php

declare(strict_types=1);

namespace App\Controllers\Staff;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Services\MessagingService;
use PDO;
use RuntimeException;
use Throwable;

class MessageController
{
    private MessagingService $messaging;
    private PDO $db;
    private string $baseUrl = '/DentalClinic/public';
    private array $columnCache = [];

    public function __construct()
    {
        $this->messaging = new MessagingService();
        $this->db = Database::getConnection();
    }

    public function index(): void
    {
        Auth::requireRole('staff');

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 15;
        $activeTab = $this->allowedTab($_GET['tab'] ?? 'all');

        $conversationId = (int) (
            $_GET['id']
            ?? $_GET['conversation_id']
            ?? 0
        );

        $data = $this->loadInbox($page, $perPage, $activeTab);

        $selectedConversation = null;
        $messages = [];

        $flashSuccess = Session::get('flash_success');
        $flashError = Session::get('flash_error');

        if ($conversationId > 0) {
            try {
                $thread = $this->messaging->getConversationThread($conversationId);

                $selectedConversation = isset($thread['conversation']) && is_array($thread['conversation'])
                    ? $thread['conversation']
                    : null;

                $messages = isset($thread['messages']) && is_array($thread['messages'])
                    ? $thread['messages']
                    : [];

                if (method_exists($this->messaging, 'markConversationAsRead')) {
                    $staffUser = Auth::user();
                    $staffUserId = (int) ($staffUser['user_id'] ?? 0);

                    if ($staffUserId > 0) {
                        $this->messaging->markConversationAsRead($conversationId, $staffUserId);
                    }
                }
            } catch (Throwable $e) {
                $selectedConversation = null;
                $messages = [];
                $flashError = $e->getMessage();
            }
        }

        View::render('staff.messages.index', [
            'conversations' => $data['items'],
            'patients' => $this->getPatientsForCreate(),

            'total' => $data['total'],
            'page' => $data['page'],
            'perPage' => $data['perPage'],

            'activeTab' => $activeTab,
            'allowedTabs' => $this->messageTabs(),

            'selectedConversation' => $selectedConversation,
            'activeConversation' => $selectedConversation,
            'conversation' => $selectedConversation,
            'messages' => $messages,
            'selectedConversationId' => $conversationId,

            'flash_success' => $flashSuccess,
            'flash_error' => $flashError,
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function show(): void
    {
        Auth::requireRole('staff');

        $conversationId = (int) ($_GET['id'] ?? $_GET['conversation_id'] ?? 0);

        if ($conversationId <= 0) {
            Session::set('flash_error', 'Invalid conversation.');
            $this->redirect('/staff/messages');
        }

        $this->redirect('/staff/messages?conversation_id=' . $conversationId);
    }

    public function create(): void
    {
        Auth::requireRole('staff');

        if (!$this->validCsrf()) {
            Session::set('flash_error', 'Invalid request token.');
            $this->redirect('/staff/messages');
        }

        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $initialMessage = trim((string) ($_POST['initial_message'] ?? ''));

        if ($patientId <= 0) {
            Session::set('flash_error', 'Please select a patient account.');
            $this->redirect('/staff/messages');
        }

        if ($this->textLength($initialMessage) > 2000) {
            Session::set('flash_error', 'Initial message is too long. Maximum is 2000 characters.');
            $this->redirect('/staff/messages');
        }

        try {
            $patientAccount = $this->findPatientAccountByPatientId($patientId);

            if (!$patientAccount) {
                Session::set('flash_error', 'This patient record cannot be messaged because it does not have a registered account.');
                $this->redirect('/staff/messages');
            }

            $this->db->beginTransaction();

            $conversationId = $this->findExistingPatientConversationId($patientId);

            if ($conversationId <= 0) {
                $conversationId = $this->createPatientConversation($patientId);
            }

            if ($initialMessage !== '') {
                $this->insertStaffMessage($conversationId, $initialMessage);
            }

            $this->touchConversation($conversationId);

            $this->db->commit();

            Session::set('flash_success', 'Conversation opened successfully.');
            $this->redirect('/staff/messages?conversation_id=' . $conversationId . '&tab=patients');
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            Session::set('flash_error', 'Unable to create conversation: ' . $e->getMessage());
            $this->redirect('/staff/messages');
        }
    }

    public function reply(): void
    {
        Auth::requireRole('staff');

        if (!$this->validCsrf()) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $staffUser = Auth::user();
        $staffUserId = (int) ($staffUser['user_id'] ?? 0);

        $conversationId = (int) ($_POST['conversation_id'] ?? 0);
        $messageBody = trim((string) ($_POST['message_body'] ?? $_POST['message_text'] ?? ''));
        $activeTab = $this->allowedTab($_POST['active_tab'] ?? $_GET['tab'] ?? 'all');

        if ($conversationId <= 0) {
            Session::set('flash_error', 'Invalid conversation.');
            $this->redirect('/staff/messages?tab=' . urlencode($activeTab));
        }

        if ($staffUserId <= 0) {
            Session::set('flash_error', 'Invalid staff account.');
            $this->redirect('/staff/messages?conversation_id=' . $conversationId . '&tab=' . urlencode($activeTab));
        }

        if ($messageBody === '') {
            Session::set('flash_error', 'Reply message is required.');
            $this->redirect('/staff/messages?conversation_id=' . $conversationId . '&tab=' . urlencode($activeTab));
        }

        if ($this->textLength($messageBody) > 2000) {
            Session::set('flash_error', 'Reply message is too long. Maximum is 2000 characters.');
            $this->redirect('/staff/messages?conversation_id=' . $conversationId . '&tab=' . urlencode($activeTab));
        }

        try {
            /*
                Important:
                This keeps guest chat working.
                Guests can message staff, and staff can reply to guests.
                Only the create() method is restricted to patient accounts.
            */
            $this->messaging->replyAsStaff(
                $conversationId,
                $staffUserId,
                $messageBody
            );

            Session::set('flash_success', 'Reply sent successfully.');
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        $this->redirect('/staff/messages?conversation_id=' . $conversationId . '&tab=' . urlencode($activeTab));
    }

    public function archive(): void
    {
        $this->updateArchiveStatus('archived');
    }

    public function unarchive(): void
    {
        $this->updateArchiveStatus('open');
    }

    private function updateArchiveStatus(string $status): void
    {
        Auth::requireRole('staff');

        if (!$this->validCsrf()) {
            Session::set('flash_error', 'Invalid request token.');
            $this->redirect('/staff/messages');
        }

        $conversationId = (int) ($_POST['conversation_id'] ?? 0);
        $activeTab = $this->allowedTab($_POST['active_tab'] ?? 'all');

        if ($conversationId <= 0) {
            Session::set('flash_error', 'Invalid conversation.');
            $this->redirect('/staff/messages?tab=' . urlencode($activeTab));
        }

        try {
            $data = [];

            if ($this->columnExists('conversations', 'conversation_status')) {
                $data['conversation_status'] = $status;
            }

            if ($this->columnExists('conversations', 'archived_at')) {
                $data['archived_at'] = $status === 'archived'
                    ? date('Y-m-d H:i:s')
                    : null;
            }

            if ($this->columnExists('conversations', 'updated_at')) {
                $data['updated_at'] = date('Y-m-d H:i:s');
            }

            if (empty($data)) {
                throw new RuntimeException('Archive columns are missing in conversations table.');
            }

            $this->updateRowById('conversations', 'conversation_id', $conversationId, $data);

            Session::set(
                'flash_success',
                $status === 'archived'
                    ? 'Conversation archived.'
                    : 'Conversation restored.'
            );

            if ($status === 'archived') {
                $this->redirect('/staff/messages?tab=archive');
            }

            $this->redirect('/staff/messages?conversation_id=' . $conversationId . '&tab=' . urlencode($activeTab));
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
            $this->redirect('/staff/messages?conversation_id=' . $conversationId . '&tab=' . urlencode($activeTab));
        }
    }

   private function loadInbox(int $page, int $perPage, string $activeTab): array
{
    if (method_exists($this->messaging, 'getStaffInboxByType')) {
        $data = $this->messaging->getStaffInboxByType($page, $perPage, $activeTab);
        $data = $this->normalizeInboxData($data, $page, $perPage);

        $data['items'] = $this->filterConversationsByTab($data['items'], $activeTab);
        $data['total'] = count($data['items']);

        return $data;
    }

    $data = $this->messaging->getStaffInbox($page, $perPage);
    $data = $this->normalizeInboxData($data, $page, $perPage);

    $data['items'] = $this->filterConversationsByTab($data['items'], $activeTab);
    $data['total'] = count($data['items']);

    if ($activeTab !== 'all') {
        $data['page'] = 1;
    }

    return $data;
}

    private function normalizeInboxData(array $data, int $page, int $perPage): array
    {
        $items = $data['items'] ?? [];

        if (!is_array($items)) {
            $items = [];
        }

        $items = array_values(array_filter($items, static function ($item): bool {
            return is_array($item);
        }));

        $total = isset($data['total']) ? (int) $data['total'] : count($items);

        return [
            'items' => $items,
            'total' => max(0, $total),
            'page' => max(1, (int) ($data['page'] ?? $page)),
            'perPage' => max(1, (int) ($data['perPage'] ?? $perPage)),
        ];
    }

   private function filterConversationsByTab(array $items, string $activeTab): array
{
    return array_values(array_filter($items, function (array $conversation) use ($activeTab): bool {
        $isArchived = $this->isArchivedConversation($conversation);

        if ($activeTab === 'archive') {
            return $isArchived;
        }

        if ($isArchived) {
            return false;
        }

        if ($activeTab === 'patients') {
            return $this->isPatientConversation($conversation);
        }

        if ($activeTab === 'guests') {
            return $this->isGuestConversation($conversation);
        }

        if ($activeTab === 'unread') {
            return $this->isUnreadConversation($conversation);
        }

        return true;
    }));
}

    private function isPatientConversation(array $conversation): bool
    {
        $patientId = (int) ($conversation['patient_id'] ?? 0);

        if ($patientId > 0) {
            return true;
        }

        $patientName = trim(
            (string) (($conversation['patient_first_name'] ?? '') . ' ' .
            ($conversation['patient_middle_name'] ?? '') . ' ' .
            ($conversation['patient_last_name'] ?? ''))
        );

        return $patientName !== '';
    }

    private function isGuestConversation(array $conversation): bool
    {
        if ($this->isPatientConversation($conversation)) {
            return false;
        }

        $guestName = trim(
            (string) (($conversation['guest_first_name'] ?? '') . ' ' .
            ($conversation['guest_middle_name'] ?? '') . ' ' .
            ($conversation['guest_last_name'] ?? ''))
        );

        if ($guestName !== '') {
            return true;
        }

        $senderType = strtolower(trim((string) ($conversation['sender_type'] ?? '')));
        $lastSenderType = strtolower(trim((string) ($conversation['last_sender_type'] ?? '')));
        $conversationType = strtolower(trim((string) ($conversation['conversation_type'] ?? '')));

        return $senderType === 'guest'
            || $lastSenderType === 'guest'
            || $conversationType === 'guest';
    }

    private function isUnreadConversation(array $conversation): bool
    {
        if ((int) ($conversation['unread_count'] ?? 0) > 0) {
            return true;
        }

        if ((int) ($conversation['has_unread'] ?? 0) === 1) {
            return true;
        }

        if (isset($conversation['is_read']) && (int) $conversation['is_read'] === 0) {
            return true;
        }

        $lastSenderType = strtolower(trim((string) ($conversation['last_sender_type'] ?? '')));

        return in_array($lastSenderType, ['patient', 'guest'], true);
    }

  private function isArchivedConversation(array $conversation): bool
{
    $status = strtolower(trim((string) ($conversation['conversation_status'] ?? '')));

    if ($status === 'archived') {
        return true;
    }

    if (!empty($conversation['archived_at'])) {
        return true;
    }

    return false;
}

    private function getPatientsForCreate(): array
    {
        if (!$this->columnExists('patients', 'user_id')) {
            return [];
        }

        $firstNameSelect = $this->columnExists('patients', 'first_name')
            ? 'p.first_name'
            : "'' AS first_name";

        $middleNameSelect = $this->columnExists('patients', 'middle_name')
            ? 'p.middle_name'
            : "'' AS middle_name";

        $lastNameSelect = $this->columnExists('patients', 'last_name')
            ? 'p.last_name'
            : "'' AS last_name";

        $contactSelect = $this->columnExists('patients', 'contact_number')
            ? 'p.contact_number'
            : "'' AS contact_number";

        $emailSelect = $this->columnExists('patients', 'email')
            ? 'p.email'
            : "'' AS email";

        $usernameSelect = $this->columnExists('users', 'username')
            ? 'u.username'
            : "'' AS username";

        $activeWhere = $this->columnExists('users', 'is_active')
            ? 'AND COALESCE(u.is_active, 1) = 1'
            : '';

        $conversationStatusFilter = $this->columnExists('conversations', 'conversation_status')
            ? "AND LOWER(COALESCE(c.conversation_status, 'open')) <> 'archived'"
            : '';

        $archivedFilter = $this->columnExists('conversations', 'archived_at')
            ? 'AND c.archived_at IS NULL'
            : '';

        $orderExpression = $this->conversationOrderExpression('c');

        $stmt = $this->db->query("
            SELECT
                p.patient_id,
                p.user_id,
                {$firstNameSelect},
                {$middleNameSelect},
                {$lastNameSelect},
                {$contactSelect},
                {$emailSelect},
                {$usernameSelect},
                (
                    SELECT c.conversation_id
                    FROM conversations c
                    WHERE c.patient_id = p.patient_id
                      {$conversationStatusFilter}
                      {$archivedFilter}
                    ORDER BY {$orderExpression} DESC
                    LIMIT 1
                ) AS existing_conversation_id
            FROM patients p
            INNER JOIN users u ON u.user_id = p.user_id
            WHERE p.user_id IS NOT NULL
              AND p.user_id > 0
              {$activeWhere}
            ORDER BY p.last_name ASC, p.first_name ASC
            LIMIT 300
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function findPatientAccountByPatientId(int $patientId): ?array
    {
        if (!$this->columnExists('patients', 'user_id')) {
            return null;
        }

        $conditions = [
            'p.patient_id = :patient_id',
            'p.user_id IS NOT NULL',
            'p.user_id > 0',
        ];

        if ($this->columnExists('users', 'is_active')) {
            $conditions[] = 'COALESCE(u.is_active, 1) = 1';
        }

        $firstNameSelect = $this->columnExists('patients', 'first_name')
            ? 'p.first_name'
            : "'' AS first_name";

        $middleNameSelect = $this->columnExists('patients', 'middle_name')
            ? 'p.middle_name'
            : "'' AS middle_name";

        $lastNameSelect = $this->columnExists('patients', 'last_name')
            ? 'p.last_name'
            : "'' AS last_name";

        $contactSelect = $this->columnExists('patients', 'contact_number')
            ? 'p.contact_number'
            : "'' AS contact_number";

        $emailSelect = $this->columnExists('patients', 'email')
            ? 'p.email'
            : "'' AS email";

        $sql = "
            SELECT
                p.patient_id,
                p.user_id,
                {$firstNameSelect},
                {$middleNameSelect},
                {$lastNameSelect},
                {$contactSelect},
                {$emailSelect}
            FROM patients p
            INNER JOIN users u ON u.user_id = p.user_id
            WHERE " . implode(' AND ', $conditions) . "
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':patient_id' => $patientId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function findExistingPatientConversationId(int $patientId): int
    {
        $conditions = [
            'patient_id = :patient_id',
        ];

        if ($this->columnExists('conversations', 'conversation_status')) {
            $conditions[] = "LOWER(COALESCE(conversation_status, 'open')) <> 'archived'";
        }

        if ($this->columnExists('conversations', 'archived_at')) {
            $conditions[] = 'archived_at IS NULL';
        }

        $orderExpression = $this->conversationOrderExpression();

        $stmt = $this->db->prepare("
            SELECT conversation_id
            FROM conversations
            WHERE " . implode(' AND ', $conditions) . "
            ORDER BY {$orderExpression} DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
        ]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function createPatientConversation(int $patientId): int
    {
        $now = date('Y-m-d H:i:s');

        $data = [
            'patient_id' => $patientId,
            'handled_by' => $this->currentUserId(),
            'conversation_type' => 'patient',
            'conversation_status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        return $this->insertRow('conversations', $data);
    }

    private function insertStaffMessage(int $conversationId, string $messageText): int
    {
        $now = date('Y-m-d H:i:s');

        $data = [
            'conversation_id' => $conversationId,
            'sender_user_id' => $this->currentUserId(),
            'sender_type' => 'staff',
            'is_bot_reply' => 0,
            'sent_at' => $now,
            'created_at' => $now,
        ];

        $this->applyMessageTextColumn($data, $messageText);

        return $this->insertRow('messages', $data);
    }

    private function applyMessageTextColumn(array &$data, string $messageText): void
    {
        if ($this->columnExists('messages', 'message_text')) {
            $data['message_text'] = $messageText;
            return;
        }

        if ($this->columnExists('messages', 'message_body')) {
            $data['message_body'] = $messageText;
            return;
        }

        if ($this->columnExists('messages', 'body')) {
            $data['body'] = $messageText;
            return;
        }

        if ($this->columnExists('messages', 'content')) {
            $data['content'] = $messageText;
            return;
        }

        throw new RuntimeException('No message text column found in messages table.');
    }

    private function touchConversation(int $conversationId): void
    {
        $data = [
            'handled_by' => $this->currentUserId(),
            'conversation_status' => 'open',
            'archived_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->updateRowById('conversations', 'conversation_id', $conversationId, $data);
    }

    private function conversationOrderExpression(string $alias = ''): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';

        $hasUpdatedAt = $this->columnExists('conversations', 'updated_at');
        $hasCreatedAt = $this->columnExists('conversations', 'created_at');

        if ($hasUpdatedAt && $hasCreatedAt) {
            return "COALESCE({$prefix}updated_at, {$prefix}created_at)";
        }

        if ($hasUpdatedAt) {
            return "{$prefix}updated_at";
        }

        if ($hasCreatedAt) {
            return "{$prefix}created_at";
        }

        return "{$prefix}conversation_id";
    }

    private function filterExistingColumns(string $table, array $data): array
    {
        $filtered = [];

        foreach ($data as $column => $value) {
            if ($this->columnExists($table, $column)) {
                $filtered[$column] = $value;
            }
        }

        return $filtered;
    }

    private function insertRow(string $table, array $data): int
    {
        $data = $this->filterExistingColumns($table, $data);

        if (empty($data)) {
            throw new RuntimeException('No valid columns found for insert into ' . $table . '.');
        }

        $columns = array_keys($data);
        $quotedColumns = array_map(static fn ($column) => "`{$column}`", $columns);
        $placeholders = array_map(static fn ($column) => ':' . $column, $columns);

        $sql = "
            INSERT INTO `{$table}` (" . implode(', ', $quotedColumns) . ")
            VALUES (" . implode(', ', $placeholders) . ")
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($data as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }

        $stmt->execute();

        return (int) $this->db->lastInsertId();
    }

    private function updateRowById(string $table, string $idColumn, int $id, array $data): void
    {
        $data = $this->filterExistingColumns($table, $data);

        if (empty($data)) {
            return;
        }

        $sets = [];

        foreach (array_keys($data) as $column) {
            $sets[] = "`{$column}` = :{$column}";
        }

        $sql = "
            UPDATE `{$table}`
            SET " . implode(', ', $sets) . "
            WHERE `{$idColumn}` = :row_id
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($data as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }

        $stmt->bindValue(':row_id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;

        if (array_key_exists($key, $this->columnCache)) {
            return $this->columnCache[$key];
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
        ");

        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);

        $exists = (int) $stmt->fetchColumn() > 0;

        $this->columnCache[$key] = $exists;

        return $exists;
    }

  private function allowedTab($value): string
{
    $value = strtolower(trim((string) $value));

    $allowed = array_keys($this->messageTabs());

    return in_array($value, $allowed, true) ? $value : 'all';
}

    private function messageTabs(): array
{
    return [
        'all' => 'All',
        'unread' => 'Unread',
        'patients' => 'Patient',
        'guests' => 'Guest',
        'archive' => 'Archive',
    ];
}

    private function validCsrf(): bool
    {
        return Csrf::verify($_POST['_csrf_token'] ?? $_POST['csrf_token'] ?? null);
    }

    private function currentUserId(): int
    {
        $user = Auth::user();

        return (int) ($user['user_id'] ?? 0);
    }

    private function textLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }

        return strlen($value);
    }

    private function redirect(string $path): void
    {
        header('Location: ' . $this->baseUrl . $path);
        exit;
    }
}