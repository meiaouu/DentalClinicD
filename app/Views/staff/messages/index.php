<?php

use App\Core\Csrf;

$conversations = isset($conversations) && is_array($conversations) ? $conversations : [];
$patients = isset($patients) && is_array($patients) ? $patients : [];
$messages = isset($messages) && is_array($messages) ? $messages : [];
$mediaFiles = isset($mediaFiles) && is_array($mediaFiles) ? $mediaFiles : [];

$activeConversation = isset($activeConversation) && is_array($activeConversation)
    ? $activeConversation
    : (
        isset($selectedConversation) && is_array($selectedConversation)
            ? $selectedConversation
            : (isset($conversation) && is_array($conversation) ? $conversation : null)
    );

$total = isset($total) ? (int) $total : count($conversations);
$page = isset($page) ? max(1, (int) $page) : 1;
$perPage = isset($perPage) ? max(1, (int) $perPage) : 15;
$activeTab = isset($activeTab) ? strtolower(trim((string) $activeTab)) : 'all';

$allowedTabs = ['all', 'unread', 'patients', 'guests', 'archive'];

if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'all';
}

$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

$botSuggestion = trim((string) ($botSuggestion ?? ''));

$botQuickReplies = isset($botQuickReplies) && is_array($botQuickReplies)
    ? $botQuickReplies
    : [];

if (empty($botQuickReplies)) {
    $botQuickReplies = [
        'Hello, how may we help you with your appointment?',
        'Thank you for messaging the clinic. Please wait while our staff checks your concern.',
        'Please provide your full name and contact number so we can assist you.',
        'You may book or check your appointment through our clinic appointment page.',
    ];
}

$composerSuggestions = [];

if ($botSuggestion !== '') {
    $composerSuggestions[] = $botSuggestion;
}

foreach ($botQuickReplies as $quickReply) {
    $quickReply = trim((string) $quickReply);

    if ($quickReply !== '' && !in_array($quickReply, $composerSuggestions, true)) {
        $composerSuggestions[] = $quickReply;
    }
}

$totalPages = max(1, (int) ceil($total / $perPage));
$baseUrl = '/DentalClinic/public';

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('safeStrStartsWith')) {
    function safeStrStartsWith(string $haystack, string $needle): bool
    {
        if (function_exists('str_starts_with')) {
            return str_starts_with($haystack, $needle);
        }
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}

if (!function_exists('messageFullName')) {
    function messageFullName(array $row, string $prefix): string
    {
        return trim(
            (string) (($row[$prefix . '_first_name'] ?? '') . ' ' .
            ($row[$prefix . '_middle_name'] ?? '') . ' ' .
            ($row[$prefix . '_last_name'] ?? ''))
        );
    }
}

if (!function_exists('messageDisplayName')) {
    function messageDisplayName(?array $conversation): string
    {
        $conversation = $conversation ?? [];
        $patientName = messageFullName($conversation, 'patient');
        if ($patientName !== '') return $patientName;
        $guestName = messageFullName($conversation, 'guest');
        if ($guestName !== '') return $guestName;
        return 'Unknown Patient';
    }
}

if (!function_exists('messageInitials')) {
    function messageInitials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = strtoupper(substr($parts[0] ?? 'P', 0, 1));
        $last = strtoupper(substr($parts[count($parts) - 1] ?? '', 0, 1));
        return $first . ($last !== '' && $last !== $first ? $last : '');
    }
}

if (!function_exists('messageConversationType')) {
    function messageConversationType(?array $conversation): string
    {
        $conversation = $conversation ?? [];
        return (int) ($conversation['patient_id'] ?? 0) > 0 ? 'patient' : 'guest';
    }
}

if (!function_exists('messagePreviewText')) {
    function messagePreviewText(array $conversation): string
    {
        $preview = trim((string) (
            $conversation['last_message']
            ?? $conversation['latest_message']
            ?? $conversation['message_text']
            ?? $conversation['last_message_text']
            ?? ''
        ));
        if ($preview === '') return 'No messages yet.';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($preview) > 58 ? mb_substr($preview, 0, 58) . '...' : $preview;
        }
        return strlen($preview) > 58 ? substr($preview, 0, 58) . '...' : $preview;
    }
}

if (!function_exists('messageTimeShort')) {
    function messageTimeShort($value): string
    {
        $value = trim((string) $value);
        if ($value === '') return '';
        $timestamp = strtotime($value);
        if ($timestamp === false) return $value;
        $diff = time() - $timestamp;
        if ($diff < 60) return 'now';
        if ($diff < 3600) return floor($diff / 60) . 'm';
        if ($diff < 86400) return floor($diff / 3600) . 'h';
        if ($diff < 604800) return floor($diff / 86400) . 'd';
        return date('M d', $timestamp);
    }
}

if (!function_exists('messageDateText')) {
    function messageDateText($value): string
    {
        $value = trim((string) $value);
        if ($value === '') return '—';
        $timestamp = strtotime($value);
        return $timestamp !== false ? date('M d, Y h:i A', $timestamp) : $value;
    }
}

if (!function_exists('messageDayText')) {
    function messageDayText($value): string
    {
        $value = trim((string) $value);
        if ($value === '') return '';
        $timestamp = strtotime($value);
        return $timestamp !== false ? date('F d, Y', $timestamp) : '';
    }
}

if (!function_exists('messageStatusText')) {
    function messageStatusText($status): string
    {
        $status = trim((string) $status);
        return $status !== '' ? ucwords(str_replace('_', ' ', $status)) : 'Open';
    }
}

if (!function_exists('messageIsUnread')) {
    function messageIsUnread(array $conversation): bool
    {
        if ((int) ($conversation['unread_count'] ?? 0) > 0) return true;
        if ((int) ($conversation['has_unread'] ?? 0) === 1) return true;
        if (isset($conversation['is_read'])) return (int) $conversation['is_read'] === 0;
        $lastSenderType = strtolower(trim((string) ($conversation['last_sender_type'] ?? '')));
        return in_array($lastSenderType, ['patient', 'guest'], true);
    }
}

if (!function_exists('messageIsArchived')) {
    function messageIsArchived(array $conversation): bool
    {
        $status = strtolower(trim((string) ($conversation['conversation_status'] ?? '')));
        return $status === 'archived' || !empty($conversation['archived_at']);
    }
}

if (!function_exists('messageText')) {
    function messageText(array $message): string
    {
        return trim((string) (
            $message['message_text']
            ?? $message['message_body']
            ?? $message['body']
            ?? $message['content']
            ?? ''
        ));
    }
}

if (!function_exists('messageSenderName')) {
    function messageSenderName(array $message): string
    {
        $senderType = strtolower(trim((string) ($message['sender_type'] ?? '')));
        if ($senderType === 'bot' || (int) ($message['is_bot_reply'] ?? 0) === 1) return 'Clinic Bot';
        $directName = trim((string) ($message['sender_name'] ?? ''));
        if ($directName !== '') return $directName;
        $name = trim(
            (string) (($message['sender_first_name'] ?? '') . ' ' .
            ($message['sender_middle_name'] ?? '') . ' ' .
            ($message['sender_last_name'] ?? ''))
        );
        if ($name !== '') return $name;
        return $senderType !== '' ? ucwords(str_replace('_', ' ', $senderType)) : 'Sender';
    }
}

if (!function_exists('messageIsOutgoing')) {
    function messageIsOutgoing(array $message): bool
    {
        $senderType = strtolower(trim((string) ($message['sender_type'] ?? '')));
        return in_array($senderType, ['staff', 'admin', 'clinic'], true);
    }
}

if (!function_exists('messageIsBot')) {
    function messageIsBot(array $message): bool
    {
        return strtolower(trim((string) ($message['sender_type'] ?? ''))) === 'bot'
            || (int) ($message['is_bot_reply'] ?? 0) === 1;
    }
}

if (!function_exists('conversationContact')) {
    function conversationContact(array $conversation): string
    {
        return trim((string) (
            $conversation['contact_number']
            ?? $conversation['patient_contact_number']
            ?? $conversation['guest_contact_number']
            ?? $conversation['phone']
            ?? ''
        ));
    }
}

if (!function_exists('conversationEmail')) {
    function conversationEmail(array $conversation): string
    {
        return trim((string) (
            $conversation['email']
            ?? $conversation['patient_email']
            ?? $conversation['guest_email']
            ?? ''
        ));
    }
}

if (!function_exists('messageAttachmentName')) {
    function messageAttachmentName(array $message): string
    {
        return trim((string) (
            $message['attachment_original_name']
            ?? $message['original_file_name']
            ?? $message['file_name']
            ?? ''
        ));
    }
}

if (!function_exists('messageAttachmentUrl')) {
    function messageAttachmentUrl(array $message, string $baseUrl): string
    {
        $path = trim((string) (
            $message['attachment_path']
            ?? $message['file_path']
            ?? ''
        ));
        if ($path === '') return '';
        if (safeStrStartsWith($path, 'http://') || safeStrStartsWith($path, 'https://')) return $path;
        if (safeStrStartsWith($path, '/')) return $baseUrl . $path;
        return $baseUrl . '/' . ltrim($path, '/');
    }
}

if (!function_exists('messageAttachmentIsImage')) {
    function messageAttachmentIsImage(array $message): bool
    {
        $mime = strtolower(trim((string) ($message['attachment_mime_type'] ?? $message['mime_type'] ?? '')));
        return safeStrStartsWith($mime, 'image/');
    }
}

$activeConversationId = 0;
if (is_array($activeConversation)) {
    $activeConversationId = (int) ($activeConversation['conversation_id'] ?? 0);
}
if ($activeConversationId <= 0) {
    $activeConversationId = (int) ($_GET['conversation_id'] ?? $_GET['id'] ?? 0);
}

$activeConversationRow = is_array($activeConversation) ? $activeConversation : [];
if ($activeConversationId > 0 && empty($activeConversationRow)) {
    foreach ($conversations as $conversationOption) {
        if (!is_array($conversationOption)) continue;
        if ((int) ($conversationOption['conversation_id'] ?? 0) === $activeConversationId) {
            $activeConversationRow = $conversationOption;
            break;
        }
    }
}

$hasActiveConversation = $activeConversationId > 0 && !empty($activeConversationRow);

$activeName = $hasActiveConversation ? messageDisplayName($activeConversationRow) : '';
$activeType = $hasActiveConversation ? messageConversationType($activeConversationRow) : '';
$activeRawStatus = $hasActiveConversation
    ? strtolower(trim((string) ($activeConversationRow['conversation_status'] ?? 'open')))
    : 'open';

$isActiveArchived = $activeRawStatus === 'archived';
$activeStatus = $hasActiveConversation ? messageStatusText($activeRawStatus) : '';
$activeContact = $hasActiveConversation ? conversationContact($activeConversationRow) : '';
$activeEmail = $hasActiveConversation ? conversationEmail($activeConversationRow) : '';

if (!isset($canUploadAttachment)) {
    $canUploadAttachment = $hasActiveConversation && $activeType === 'patient';
}
$canUploadAttachment = (bool) $canUploadAttachment;

$tabItems = [
    'all'     => 'All',
    'unread'  => 'Unread',
    'patients'=> 'Patients',
    'guests'  => 'Guests',
    'archive' => 'Archive',
];

ob_start();
?>


<style>
/* ─── Reset & tokens ──────────────────────────────────────────────────────── */
/*
  IMPORTANT:
  These variables are scoped to .ci-page only.
  Do not use :root here because it overrides the staff layout variables.
*/
.ci-page{
  --c-bg:#f0f2f7;
  --c-panel:#ffffff;
  --c-border:#e4e7ef;
  --c-border-soft:#edf0f6;
  --c-text:#0e1117;
  --c-muted:#7c8494;
  --c-subtle:#b0b8c8;
  --c-brand:#1a73e8;
  --c-brand-soft:#e8f0fd;
  --c-brand-dark:#1557b0;
  --c-teal:#0f9d8a;
  --c-teal-soft:#e6f7f5;
  --c-green:#16a34a;
  --c-green-soft:#dcfce7;
  --c-amber:#b45309;
  --c-amber-soft:#fef3c7;
  --c-out-bubble:#1a73e8;
  --c-out-text:#ffffff;
  --c-in-bubble:#f0f2f7;
  --c-in-text:#0e1117;
  --c-bot-bubble:#e6f7f5;
  --c-bot-text:#0a5449;

  --ci-sidebar-w:310px;
  --ci-rail-w:52px;
  --ci-details-w:296px;
  --ci-topbar-h:64px;

  --composer-h:auto;
  --radius-bubble:18px;
  --radius-ui:10px;
  --shadow-card:0 1px 3px rgba(14,17,23,.07),0 4px 16px rgba(14,17,23,.05);
  --shadow-float:0 8px 32px rgba(14,17,23,.14);
  --transition:.18s cubic-bezier(.4,0,.2,1);
}

.ci-page,
.ci-page *{box-sizing:border-box;font-family:var(--font-ui);}

.ci-page{
  height:calc(100vh - 84px);
  min-height:560px;
  padding:14px;
  background:var(--c-bg);
  overflow:hidden;
}

/* ─── Shell grid ─────────────────────────────────────────────────────────── */
.ci-shell{
  height:100%;
  display:grid;
  grid-template-columns:var(--ci-sidebar-w) 1fr var(--ci-rail-w);
  background:var(--c-panel);
  border-radius:var(--radius-ui);
  border:1px solid var(--c-border);
  box-shadow:var(--shadow-card);
  overflow:hidden;
  transition:grid-template-columns var(--transition);
}
.ci-shell.details-open{
  grid-template-columns:var(--ci-sidebar-w) 1fr var(--ci-rail-w) var(--ci-details-w);
}

/* ─── Sidebar ────────────────────────────────────────────────────────────── */
.ci-sidebar{
  min-width:0;
  border-right:1px solid var(--c-border-soft);
  background:#fff;
  display:flex;
  flex-direction:column;
  overflow:hidden;
}

.ci-sidebar-head{
  padding:20px 16px 12px;
  flex-shrink:0;
}

.ci-title-row{
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:14px;
}
.ci-title-row h1{
  margin:0;
  font-size:22px;
  font-weight:700;
  letter-spacing:-0.03em;
  color:var(--c-text);
}

.ci-plus-btn{
  width:32px;height:32px;
  border-radius:50%;
  border:1.5px solid var(--c-border);
  background:#fff;
  color:var(--c-brand);
  cursor:pointer;
  display:inline-flex;align-items:center;justify-content:center;
  font-size:20px;line-height:1;
  transition:background var(--transition),border-color var(--transition);
}
.ci-plus-btn:hover{background:var(--c-brand-soft);border-color:var(--c-brand);}

/* Search */
.ci-search{
  height:38px;
  background:#f7f9fc;
  border:1.5px solid var(--c-border-soft);
  border-radius:8px;
  display:flex;align-items:center;gap:8px;
  padding:0 12px;
  margin-bottom:10px;
  transition:border-color var(--transition),box-shadow var(--transition);
}
.ci-search:focus-within{
  border-color:var(--c-brand);
  box-shadow:0 0 0 3px rgba(26,115,232,.12);
  background:#fff;
}
.ci-search svg{width:15px;height:15px;fill:none;stroke:var(--c-subtle);stroke-width:2;flex-shrink:0;}
.ci-search input{
  flex:1;min-width:0;border:0;outline:0;background:transparent;
  font-size:13px;color:var(--c-text);
}
.ci-search input::placeholder{color:var(--c-subtle);}

/* Tabs */
.ci-tabs{
  display:flex;
  gap:2px;
  background:#f5f7fb;
  border-radius:8px;
  padding:3px;
  margin-bottom:4px;
}
.ci-tab{
  flex:1;min-width:0;
  padding:6px 4px;
  text-align:center;
  text-decoration:none;
  color:var(--c-muted);
  font-size:11px;
  font-weight:600;
  border-radius:6px;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
  transition:background var(--transition),color var(--transition);
}
.ci-tab.active{
  background:#fff;
  color:var(--c-text);
  font-weight:700;
  box-shadow:0 1px 4px rgba(14,17,23,.08);
}
.ci-tab:hover:not(.active){color:var(--c-text);}

/* Alerts */
.ci-alert{
  margin:0 0 8px;
  padding:9px 12px;
  border-radius:7px;
  font-size:12px;font-weight:600;line-height:1.45;
}
.ci-alert.success{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;}
.ci-alert.error{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;}

/* Thread list */
.ci-thread-list{
  flex:1;min-height:0;
  overflow-y:auto;
  padding:6px 8px 12px;
}
.ci-thread-list::-webkit-scrollbar{width:4px;}
.ci-thread-list::-webkit-scrollbar-track{background:transparent;}
.ci-thread-list::-webkit-scrollbar-thumb{background:var(--c-border);border-radius:4px;}

.ci-thread{
  display:flex;
  align-items:center;
  gap:11px;
  padding:10px 10px;
  text-decoration:none;
  color:var(--c-text);
  border-radius:10px;
  margin-bottom:2px;
  transition:background var(--transition);
  position:relative;
}
.ci-thread:hover,.ci-thread.active{background:#f5f7fb;}
.ci-thread.active{background:var(--c-brand-soft);}

/* Avatar */
.ci-avatar{
  width:44px;height:44px;
  border-radius:50%;
  background:linear-gradient(135deg,#c7d9fc 0%,#a5c4fb 100%);
  color:#1557b0;
  font-size:13px;font-weight:700;
  display:inline-flex;align-items:center;justify-content:center;
  flex-shrink:0;
  user-select:none;
}
.ci-avatar.guest{background:linear-gradient(135deg,#fde68a 0%,#fbbf24 100%);color:#78350f;}
.ci-avatar.bot{background:linear-gradient(135deg,#a7f3d0 0%,#34d399 100%);color:#065f46;}
.ci-avatar.sm{width:32px;height:32px;font-size:11px;}
.ci-avatar.lg{width:58px;height:58px;font-size:18px;}

.ci-thread-body{flex:1;min-width:0;}
.ci-thread-top{display:flex;align-items:center;gap:6px;margin-bottom:3px;}
.ci-thread-name{
  font-size:13.5px;font-weight:600;color:var(--c-text);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  flex:1;min-width:0;
}
.ci-thread.is-unread .ci-thread-name{font-weight:700;}
.ci-thread-time{font-size:10.5px;color:var(--c-subtle);white-space:nowrap;flex-shrink:0;}

.ci-thread-preview{
  font-size:12px;color:var(--c-muted);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  line-height:1.4;
}
.ci-thread.is-unread .ci-thread-preview{color:var(--c-text);font-weight:500;}

.ci-badge-row{display:flex;align-items:center;gap:5px;margin-top:4px;}
.ci-type-badge{
  font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;
  padding:2px 6px;border-radius:4px;
  background:var(--c-amber-soft);color:var(--c-amber);
}
.ci-type-badge.patient{background:var(--c-green-soft);color:var(--c-green);}
.ci-type-badge.archived{background:#f1f5f9;color:#475569;}

.ci-unread-dot{
  width:8px;height:8px;border-radius:50%;
  background:var(--c-brand);
  flex-shrink:0;
  margin-left:auto;
}
.ci-unread-count{
  min-width:18px;height:18px;border-radius:9px;
  background:var(--c-brand);color:#fff;
  font-size:10px;font-weight:700;
  display:inline-flex;align-items:center;justify-content:center;
  padding:0 5px;
}

.ci-empty{
  margin:10px 2px;
  padding:16px;
  border-radius:8px;
  border:1.5px dashed var(--c-border);
  color:var(--c-muted);
  font-size:13px;
  background:#fafbfd;
  text-align:center;
}

/* Pagination */
.ci-pagination{
  display:flex;gap:4px;
  padding:10px 12px;
  border-top:1px solid var(--c-border-soft);
  flex-shrink:0;
}
.ci-page-link{
  min-width:28px;height:28px;
  display:inline-flex;align-items:center;justify-content:center;
  border-radius:6px;
  border:1.5px solid var(--c-border);
  color:var(--c-muted);
  text-decoration:none;
  font-size:12px;font-weight:600;
  transition:background var(--transition),color var(--transition);
}
.ci-page-link.active{background:var(--c-brand);color:#fff;border-color:var(--c-brand);}
.ci-page-link:hover:not(.active){background:#f0f2f7;color:var(--c-text);}

/* ─── Chat panel ─────────────────────────────────────────────────────────── */
.ci-chat{
  min-width:0;min-height:0;
  display:flex;flex-direction:column;
  background:#fff;
  overflow:hidden;
}

/* Topbar */
.ci-topbar{
  flex-shrink:0;
  height:var(--ci-topbar-h);
  border-bottom:1px solid var(--c-border-soft);
  display:flex;align-items:center;gap:12px;
  padding:0 20px 0 18px;
  background:#fff;
}
.ci-topbar-person{
  display:flex;align-items:center;gap:10px;
  flex:1;min-width:0;
}
.ci-topbar-text{min-width:0;}
.ci-topbar-name{
  display:block;
  font-size:15px;font-weight:700;color:var(--c-text);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.ci-topbar-sub{
  display:block;
  font-size:11.5px;color:var(--c-muted);margin-top:1px;
}
.ci-online-dot{
  display:inline-block;
  width:8px;height:8px;border-radius:50%;
  background:#22c55e;margin-right:4px;
  box-shadow:0 0 0 2px #dcfce7;
}

.ci-topbar-search{
  height:34px;
  background:#f7f9fc;
  border:1.5px solid var(--c-border-soft);
  border-radius:8px;
  display:flex;align-items:center;gap:7px;
  padding:0 10px;
  width:200px;
  transition:border-color var(--transition);
}
.ci-topbar-search:focus-within{border-color:var(--c-brand);}
.ci-topbar-search svg{width:13px;height:13px;fill:none;stroke:var(--c-subtle);stroke-width:2;flex-shrink:0;}
.ci-topbar-search input{
  flex:1;min-width:0;border:0;outline:0;background:transparent;
  font-size:12px;color:var(--c-text);
}
.ci-topbar-search input::placeholder{color:var(--c-subtle);}

.ci-topbar-actions{display:flex;gap:6px;}
.ci-icon-btn{
  width:34px;height:34px;border-radius:8px;
  border:1.5px solid var(--c-border);
  background:#fff;
  color:var(--c-muted);
  cursor:pointer;
  display:inline-flex;align-items:center;justify-content:center;
  transition:background var(--transition),color var(--transition),border-color var(--transition);
}
.ci-icon-btn svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round;}
.ci-icon-btn:hover{background:#f5f7fb;color:var(--c-text);border-color:var(--c-border);}

.ci-mobile-back{
  display:none;
  align-items:center;gap:4px;
  color:var(--c-brand);
  font-size:13px;font-weight:600;
  text-decoration:none;
  flex-shrink:0;
}
.ci-mobile-back svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}

/* ─── Messages body ──────────────────────────────────────────────────────── */
.ci-messages{
  flex:1;min-height:0;
  overflow-y:auto;
  padding:24px 20px;
  background:#fafbfd;
  display:flex;flex-direction:column;
  gap:2px;
  scroll-behavior:smooth;
}
.ci-messages::-webkit-scrollbar{width:4px;}
.ci-messages::-webkit-scrollbar-track{background:transparent;}
.ci-messages::-webkit-scrollbar-thumb{background:var(--c-border);border-radius:4px;}

/* Day divider */
.ci-day-divider{
  display:flex;align-items:center;gap:10px;
  margin:12px 0 14px;
}
.ci-day-divider::before,
.ci-day-divider::after{
  content:'';flex:1;
  height:1px;background:var(--c-border-soft);
}
.ci-day-divider span{
  font-size:11px;font-weight:600;color:var(--c-subtle);
  background:#fafbfd;
  padding:0 8px;
  white-space:nowrap;
}

/* Message rows */
.ci-msg-row{
  display:flex;
  align-items:flex-end;
  gap:8px;
  margin-bottom:2px;
}
.ci-msg-row.out{flex-direction:row-reverse;}
.ci-msg-row.in{flex-direction:row;}

/* spacing: consecutive messages from same sender */
.ci-msg-row+.ci-msg-row.out,
.ci-msg-row+.ci-msg-row.in{margin-top:2px;}

/* Bubble */
.ci-bubble{
  max-width:min(68%,560px);
  padding:10px 14px;
  line-height:1.55;
  font-size:14px;
  position:relative;
  word-break:break-word;
}

/* Incoming */
.ci-msg-row.in .ci-bubble{
  background:var(--c-in-bubble);
  color:var(--c-in-text);
  border-radius:var(--radius-bubble) var(--radius-bubble) var(--radius-bubble) 4px;
}

/* Outgoing */
.ci-msg-row.out .ci-bubble{
  background:var(--c-out-bubble);
  color:var(--c-out-text);
  border-radius:var(--radius-bubble) var(--radius-bubble) 4px var(--radius-bubble);
}

/* Bot */
.ci-msg-row.bot .ci-bubble{
  background:var(--c-bot-bubble);
  color:var(--c-bot-text);
  border-radius:var(--radius-bubble) var(--radius-bubble) var(--radius-bubble) 4px;
  border:1px solid #a7f3d0;
}

.ci-bubble-text{white-space:pre-wrap;}

/* Meta line */
.ci-bubble-meta{
  margin-top:5px;
  font-size:10.5px;
  opacity:.65;
  display:flex;align-items:center;gap:4px;
}
.ci-msg-row.out .ci-bubble-meta{justify-content:flex-end;}

/* Attachment */
.ci-attachment{
  display:inline-flex;align-items:center;gap:8px;
  margin-top:8px;
  padding:8px 10px;
  background:rgba(255,255,255,.55);
  border:1px solid rgba(255,255,255,.7);
  border-radius:8px;
  color:inherit;
  text-decoration:none;
  font-size:12px;font-weight:600;
  backdrop-filter:blur(4px);
}
.ci-msg-row.in .ci-attachment{
  background:rgba(255,255,255,.85);
  border:1px solid var(--c-border);
  color:var(--c-text);
}
.ci-attachment img{
  max-width:180px;max-height:130px;
  object-fit:cover;border-radius:6px;
}
.ci-attach-icon{
  width:28px;height:28px;border-radius:6px;
  background:rgba(255,255,255,.25);
  display:inline-flex;align-items:center;justify-content:center;
  font-size:9px;font-weight:800;letter-spacing:.04em;
  flex-shrink:0;
}
.ci-msg-row.in .ci-attach-icon{background:var(--c-brand-soft);color:var(--c-brand);}

/* Empty state */
.ci-chat-empty{
  flex:1;display:flex;align-items:center;justify-content:center;
  padding:30px;
}
.ci-chat-empty-card{
  max-width:320px;text-align:center;
}
.ci-chat-empty-icon{
  width:64px;height:64px;border-radius:50%;
  background:var(--c-brand-soft);
  margin:0 auto 16px;
  display:flex;align-items:center;justify-content:center;
}
.ci-chat-empty-icon svg{width:28px;height:28px;fill:none;stroke:var(--c-brand);stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;}
.ci-chat-empty-card h2{margin:0 0 8px;font-size:18px;font-weight:700;color:var(--c-text);}
.ci-chat-empty-card p{margin:0;font-size:13px;line-height:1.6;color:var(--c-muted);}

/* ─── Composer ───────────────────────────────────────────────────────────── */
.ci-composer{
  flex-shrink:0;
  padding:10px 16px 14px;
  background:#fff;
  border-top:1px solid var(--c-border-soft);
}

.ci-guest-note{
  display:flex;align-items:center;gap:7px;
  padding:8px 10px;border-radius:7px;
  background:#fffbeb;border:1px solid #fde68a;
  color:#92400e;font-size:11.5px;font-weight:500;
  margin-bottom:8px;
}
.ci-guest-note svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;}

.ci-suggestions{
  display:flex;gap:6px;
  overflow-x:auto;
  padding-bottom:8px;
  scrollbar-width:none;
}
.ci-suggestions::-webkit-scrollbar{display:none;}
.ci-suggestion-chip{
  flex-shrink:0;
  max-width:240px;
  padding:6px 12px;
  border-radius:999px;
  border:1.5px solid var(--c-teal-soft);
  background:var(--c-teal-soft);
  color:var(--c-teal);
  font-size:11.5px;font-weight:600;
  cursor:pointer;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  transition:background var(--transition),border-color var(--transition);
}
.ci-suggestion-chip:hover{background:#ccf5ee;border-color:#99ead9;}

/* Composer row */
.ci-composer-row{
  display:flex;align-items:flex-end;gap:8px;
  background:#f7f9fc;
  border:1.5px solid var(--c-border-soft);
  border-radius:12px;
  padding:6px 6px 6px 10px;
  transition:border-color var(--transition),box-shadow var(--transition);
}
.ci-composer-row:focus-within{
  border-color:var(--c-brand);
  box-shadow:0 0 0 3px rgba(26,115,232,.1);
  background:#fff;
}

.ci-composer-textarea{
  flex:1;min-width:0;
  border:0;outline:0;
  background:transparent;
  color:var(--c-text);
  font-size:14px;line-height:1.5;
  resize:none;
  min-height:34px;max-height:120px;
  padding:4px 0;
}
.ci-composer-textarea::placeholder{color:var(--c-subtle);}

.ci-attach-btn{
  width:34px;height:34px;border-radius:8px;
  border:0;background:transparent;
  color:var(--c-muted);cursor:pointer;
  display:inline-flex;align-items:center;justify-content:center;
  flex-shrink:0;
  transition:background var(--transition),color var(--transition);
}
.ci-attach-btn:hover{background:#eef2ff;color:var(--c-brand);}
.ci-attach-btn svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round;}

.ci-send-btn{
  width:36px;height:36px;border-radius:9px;
  border:0;background:var(--c-brand);color:#fff;
  cursor:pointer;
  display:inline-flex;align-items:center;justify-content:center;
  flex-shrink:0;
  transition:background var(--transition),transform var(--transition);
}
.ci-send-btn:hover{background:var(--c-brand-dark);}
.ci-send-btn:active{transform:scale(.93);}
.ci-send-btn svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}

.ci-file-preview{
  margin-top:6px;
  display:flex;align-items:center;gap:6px;
  padding:6px 10px;border-radius:7px;
  background:#f0f2f7;
  color:var(--c-muted);font-size:11.5px;font-weight:500;
}
.ci-file-preview svg{width:13px;height:13px;fill:none;stroke:var(--c-brand);stroke-width:2;flex-shrink:0;}

/* ─── Rail ───────────────────────────────────────────────────────────────── */
.ci-rail{
  border-left:1px solid var(--c-border-soft);
  background:#fff;
  display:flex;flex-direction:column;
  align-items:center;
  justify-content:flex-end;
  gap:10px;
  padding:14px 7px;
}
.ci-rail-btn{
  width:36px;height:36px;border-radius:9px;
  border:1.5px solid var(--c-border);
  background:#fff;
  color:var(--c-muted);
  cursor:pointer;
  display:inline-flex;align-items:center;justify-content:center;
  transition:background var(--transition),color var(--transition),border-color var(--transition);
}
.ci-rail-btn svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round;}
.ci-rail-btn:hover,.ci-rail-btn.active{background:var(--c-teal-soft);color:var(--c-teal);border-color:#99ead9;}
.ci-rail-btn.archive-active{background:var(--c-amber-soft);color:var(--c-amber);border-color:#fde68a;}
.ci-rail-form{margin:0;padding:0;}

/* ─── Details drawer ─────────────────────────────────────────────────────── */
.ci-details{
  min-width:0;min-height:0;
  border-left:1px solid var(--c-border-soft);
  background:#fff;
  display:none;flex-direction:column;overflow:hidden;
}
.ci-shell.details-open .ci-details{display:flex;}

.ci-details-head{
  padding:16px 16px 12px;
  border-bottom:1px solid var(--c-border-soft);
  display:flex;align-items:center;justify-content:space-between;
  gap:10px;
  flex-shrink:0;
}
.ci-details-head h2{margin:0;font-size:15px;font-weight:700;color:var(--c-text);}

.ci-details-close{
  width:28px;height:28px;border-radius:6px;
  border:1.5px solid var(--c-border);background:#fff;cursor:pointer;
  display:inline-flex;align-items:center;justify-content:center;
  color:var(--c-muted);font-size:18px;line-height:1;
  transition:background var(--transition);
}
.ci-details-close:hover{background:#f5f7fb;}

.ci-details-profile{text-align:center;padding:20px 16px 14px;}
.ci-details-name{display:block;font-size:16px;font-weight:700;color:var(--c-text);margin-top:8px;}
.ci-details-status-badge{
  display:inline-flex;margin-top:8px;
  padding:4px 10px;border-radius:999px;
  background:var(--c-amber-soft);color:var(--c-amber);
  font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;
}

.ci-details-body{flex:1;min-height:0;overflow-y:auto;padding:0 16px 20px;}
.ci-details-body::-webkit-scrollbar{width:4px;}
.ci-details-body::-webkit-scrollbar-thumb{background:var(--c-border);border-radius:4px;}

.ci-details-section{border-top:1px solid var(--c-border-soft);padding:14px 0;}
.ci-details-section h3{margin:0 0 10px;font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--c-subtle);}

.ci-details-row{display:grid;gap:2px;margin-bottom:10px;}
.ci-details-row span{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--c-subtle);}
.ci-details-row strong{font-size:13px;color:var(--c-text);word-break:break-word;line-height:1.45;}

.ci-media-list{display:grid;gap:7px;}
.ci-media-item{
  display:flex;gap:9px;align-items:center;
  padding:9px 10px;
  background:#fafbfd;border:1.5px solid var(--c-border-soft);
  border-radius:8px;
  color:var(--c-text);text-decoration:none;font-size:12px;font-weight:600;
  transition:background var(--transition);
}
.ci-media-item:hover{background:#f0f2f7;}
.ci-media-icon{
  width:32px;height:32px;border-radius:7px;
  background:var(--c-brand-soft);color:var(--c-brand);
  display:inline-flex;align-items:center;justify-content:center;
  font-size:9px;font-weight:800;flex-shrink:0;
}
.ci-media-name{display:block;}
.ci-media-note{display:block;color:var(--c-subtle);font-size:10px;margin-top:1px;}

.ci-muted-box{
  padding:11px 12px;
  border-radius:8px;
  background:#fafbfd;border:1.5px dashed var(--c-border);
  color:var(--c-muted);font-size:12px;line-height:1.6;
}

/* ─── Modal ──────────────────────────────────────────────────────────────── */
.ci-modal-overlay{
  position:fixed;inset:0;z-index:200;
  background:rgba(14,17,23,.4);
  display:none;align-items:center;justify-content:center;
  padding:20px;
  backdrop-filter:blur(3px);
}
.ci-modal-overlay.is-open{display:flex;}

.ci-modal{
  width:min(540px,100%);
  max-height:88vh;
  background:#fff;
  border-radius:14px;
  border:1px solid var(--c-border);
  box-shadow:var(--shadow-float);
  display:flex;flex-direction:column;overflow:hidden;
  animation:modalIn .18s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes modalIn{
  from{opacity:0;transform:scale(.94) translateY(10px);}
  to{opacity:1;transform:scale(1) translateY(0);}
}

.ci-modal-head{
  padding:18px 20px 14px;
  border-bottom:1px solid var(--c-border-soft);
  display:flex;align-items:center;justify-content:space-between;
}
.ci-modal-head h2{margin:0;font-size:17px;font-weight:700;color:var(--c-text);}

.ci-modal-close{
  width:30px;height:30px;border-radius:7px;
  border:1.5px solid var(--c-border);background:#fff;cursor:pointer;
  font-size:20px;color:var(--c-muted);
  display:inline-flex;align-items:center;justify-content:center;
  transition:background var(--transition);
}
.ci-modal-close:hover{background:#f5f7fb;}

.ci-modal-body{
  padding:16px 20px 18px;
  overflow-y:auto;
  display:grid;gap:12px;
}

.ci-form-field{display:grid;gap:5px;font-size:13px;font-weight:600;color:var(--c-text);}
.ci-form-input{
  width:100%;min-height:40px;
  border:1.5px solid var(--c-border);border-radius:8px;
  background:#fff;padding:9px 12px;outline:none;
  font-size:13px;color:var(--c-text);
  transition:border-color var(--transition),box-shadow var(--transition);
}
.ci-form-input:focus{border-color:var(--c-brand);box-shadow:0 0 0 3px rgba(26,115,232,.12);}
.ci-form-textarea{min-height:80px;resize:vertical;}

/* Patient picker */
.ci-patient-picker{
  border:1.5px solid var(--c-border);border-radius:9px;
  max-height:240px;overflow-y:auto;
  padding:6px;display:grid;gap:5px;
  background:#fafbfd;
}
.ci-patient-picker::-webkit-scrollbar{width:4px;}
.ci-patient-picker::-webkit-scrollbar-thumb{background:var(--c-border);border-radius:4px;}

.ci-patient-item{
  width:100%;
  border:1.5px solid transparent;border-radius:8px;
  background:#fff;padding:9px 11px;
  display:flex;align-items:center;gap:10px;
  cursor:pointer;text-align:left;
  transition:background var(--transition),border-color var(--transition);
}
.ci-patient-item:hover{background:#f5f7fb;border-color:var(--c-border);}
.ci-patient-item.selected{background:var(--c-brand-soft);border-color:var(--c-brand);}

.ci-patient-main{flex:1;min-width:0;}
.ci-patient-main strong{display:block;font-size:13px;color:var(--c-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ci-patient-main small{display:block;font-size:11px;color:var(--c-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px;}

.ci-patient-tag{
  font-size:10px;font-weight:700;padding:3px 7px;border-radius:5px;
  background:#f1f5f9;color:var(--c-muted);flex-shrink:0;
}
.ci-patient-tag.existing{background:var(--c-brand-soft);color:var(--c-brand);}

.ci-selected-patient-box{
  padding:10px 12px;border-radius:8px;
  background:var(--c-teal-soft);border:1.5px solid #99ead9;
  color:var(--c-teal);font-size:13px;font-weight:600;
}

.ci-modal-actions{display:flex;justify-content:flex-end;gap:8px;}
.ci-btn{
  min-height:38px;padding:0 16px;border-radius:8px;
  border:1.5px solid var(--c-border);background:#fff;
  cursor:pointer;font-size:13px;font-weight:600;color:var(--c-text);
  transition:background var(--transition);
}
.ci-btn:hover{background:#f5f7fb;}
.ci-btn.primary{background:var(--c-brand);border-color:var(--c-brand);color:#fff;}
.ci-btn.primary:hover{background:var(--c-brand-dark);}

/* ─── Responsive ─────────────────────────────────────────────────────────── */
@media(max-width:1060px){
  .ci-shell,.ci-shell.details-open{
    grid-template-columns:var(--ci-sidebar-w) 1fr var(--ci-rail-w);
  }
  .ci-details{
    position:fixed;top:0;right:0;bottom:0;
    width:min(320px,100vw);z-index:90;
    box-shadow:var(--shadow-float);
  }
}

@media(max-width:740px){
  .ci-page{height:calc(100vh - 70px);padding:0;}
  .ci-shell,.ci-shell.details-open{
    grid-template-columns:1fr;
    border-radius:0;border-left:0;border-right:0;
  }
  .ci-chat,.ci-rail{display:none;}
  .ci-shell.active-mode .ci-sidebar{display:none;}
  .ci-shell.active-mode .ci-chat{display:flex;grid-column:1/2;}
  .ci-shell.active-mode .ci-rail{display:none;}
  .ci-mobile-back{display:flex;}
  .ci-topbar{padding:0 12px;}
  .ci-topbar-search{display:none;}
  .ci-messages{padding:14px 12px;}
  .ci-bubble{max-width:86%;}
  .ci-composer{padding:8px 10px 12px;}
  .ci-details{width:100vw;}
}








/* ─── Full screen layout fix ─────────────────────────────────────────────── */

.ci-page {
    width: 100%;
    height: calc(100vh - 72px);
    min-height: calc(100vh - 72px);
    padding: 0;
    margin: 0;
    background: var(--c-bg);
    overflow: hidden;
}

.ci-shell {
    width: 100%;
    height: 100%;
    max-width: none;
    border-radius: 0;
    border-left: 0;
    border-right: 0;
    box-shadow: none;
    grid-template-columns: minmax(300px, 340px) minmax(0, 1fr) var(--ci-rail-w);
}

.ci-shell.details-open {
    grid-template-columns: minmax(300px, 340px) minmax(0, 1fr) var(--ci-rail-w) var(--ci-details-w);
}

.ci-sidebar,
.ci-chat,
.ci-messages,
.ci-thread-list {
    min-width: 0;
}

.ci-thread {
    overflow: hidden;
}

.ci-thread-body {
    min-width: 0;
    overflow: hidden;
}

.ci-topbar {
    min-width: 0;
}

.ci-topbar-person {
    min-width: 0;
}

.ci-composer {
    width: 100%;
}

/* If your app layout adds padding around page content, this helps messages fill it */
.main-content .ci-page,
.content .ci-page,
.staff-content .ci-page,
.app-content .ci-page {
    margin: 0;
}

/* ─── Topbar dropdown / mobile 3 dots ───────────────────────────────────── */

.ci-menu-wrap {
    position: relative;
    display: inline-flex;
}

.ci-actions-menu {
    position: absolute;
    top: 42px;
    right: 0;
    z-index: 80;
    width: 190px;
    display: none;
    padding: 6px;
    background: #ffffff;
    border: 1px solid var(--c-border);
    border-radius: 10px;
    box-shadow: var(--shadow-float);
}

.ci-actions-menu.is-open {
    display: grid;
    gap: 4px;
}

.ci-actions-menu button,
.ci-actions-menu .ci-menu-submit {
    width: 100%;
    min-height: 36px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: var(--c-text);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 0 10px;
    font-size: 13px;
    font-weight: 600;
    text-align: left;
}

.ci-actions-menu button:hover,
.ci-actions-menu .ci-menu-submit:hover {
    background: #f5f7fb;
}

.ci-actions-menu svg {
    width: 16px;
    height: 16px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.9;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.ci-menu-form {
    margin: 0;
    padding: 0;
}

.ci-menu-danger {
    color: #92400e !important;
}

/* ─── Better mobile full screen ─────────────────────────────────────────── */

@media (max-width: 740px) {
    .ci-page {
        height: calc(100vh - 64px);
        min-height: calc(100vh - 64px);
        padding: 0;
    }

    .ci-shell,
    .ci-shell.details-open {
        width: 100%;
        height: 100%;
        grid-template-columns: minmax(0, 1fr);
        border: 0;
    }

    .ci-sidebar {
        width: 100%;
    }

    .ci-thread-list {
        padding-bottom: 18px;
    }

    .ci-topbar-actions {
        position: relative;
    }

    .ci-actions-menu {
        top: 40px;
        right: 0;
    }
}




/* Hide 3-dot conversation menu on desktop */
.ci-menu-wrap {
    display: none;
}

/* Show 3-dot conversation menu only on mobile */
@media (max-width: 740px) {
    .ci-menu-wrap {
        display: inline-flex;
    }
}


</style>

<div class="ci-page">
  <div class="ci-shell <?= $hasActiveConversation ? 'active-mode' : 'list-mode' ?>" id="clinicInboxShell" data-active-conversation="<?= $hasActiveConversation ? '1' : '0' ?>">

    <!-- ── Sidebar ─────────────────────────────────────────────────── -->
    <aside class="ci-sidebar">
      <div class="ci-sidebar-head">
        <div class="ci-title-row">
          <button type="button" class="ci-plus-btn" id="openCreateMessageModal" aria-label="New conversation" title="New conversation">+</button>
        </div>

        <label class="ci-search" for="messageSearchInput">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <input type="search" id="messageSearchInput" placeholder="Search conversations…" autocomplete="off">
        </label>

        <nav class="ci-tabs" aria-label="Filters">
          <?php foreach ($tabItems as $tabKey => $label): ?>
            <a href="<?= e($baseUrl . '/staff/messages?tab=' . urlencode($tabKey)) ?>" class="ci-tab <?= $activeTab === $tabKey ? 'active' : '' ?>"><?= e($label) ?></a>
          <?php endforeach; ?>
        </nav>

        <?php if ($flash_success): ?><div class="ci-alert success"><?= e($flash_success) ?></div><?php endif; ?>
        <?php if ($flash_error): ?><div class="ci-alert error"><?= e($flash_error) ?></div><?php endif; ?>
      </div>

      <div class="ci-thread-list" id="conversationList">
        <?php if (empty($conversations)): ?>
          <div class="ci-empty">No conversations found.</div>
        <?php else: ?>
          <?php foreach ($conversations as $item): ?>
            <?php
              if (!is_array($item)) continue;
              $conversationId = (int) ($item['conversation_id'] ?? 0);
              if ($conversationId <= 0) continue;
              $displayName     = messageDisplayName($item);
              $conversationType= messageConversationType($item);
              $isUnread        = messageIsUnread($item);
              $isArchived      = messageIsArchived($item);
              $isActive        = $conversationId === $activeConversationId;
              $updatedAt       = $item['last_message_at'] ?? $item['sent_at'] ?? $item['updated_at'] ?? $item['created_at'] ?? '';
              $searchText      = strtolower($displayName . ' ' . messagePreviewText($item) . ' ' . $conversationType);
              $unreadCount     = max(0, (int) ($item['unread_count'] ?? ($isUnread ? 1 : 0)));
            ?>
            <a href="<?= e($baseUrl . '/staff/messages?conversation_id=' . $conversationId . '&tab=' . urlencode($activeTab)) ?>"
               class="ci-thread <?= $isActive ? 'active' : '' ?> <?= $isUnread ? 'is-unread' : '' ?>"
               data-thread-item
               data-search="<?= e($searchText) ?>">

              <span class="ci-avatar <?= e($conversationType) ?>"><?= e(messageInitials($displayName)) ?></span>

              <span class="ci-thread-body">
                <span class="ci-thread-top">
                  <span class="ci-thread-name"><?= e($displayName) ?></span>
                  <span class="ci-thread-time"><?= e(messageTimeShort($updatedAt)) ?></span>
                </span>
                <span class="ci-thread-preview"><?= e(messagePreviewText($item)) ?></span>
                <span class="ci-badge-row">
                  <span class="ci-type-badge <?= e($conversationType) ?>"><?= e($conversationType) ?></span>
                  <?php if ($isArchived): ?><span class="ci-type-badge archived">Archived</span><?php endif; ?>
                  <?php if ($unreadCount > 0): ?><span class="ci-unread-count" style="margin-left:auto"><?= e((string)$unreadCount) ?></span><?php endif; ?>
                </span>
              </span>
            </a>
          <?php endforeach; ?>
          <div class="ci-empty" id="messageNoFilterResult" hidden>No conversations match your search.</div>
        <?php endif; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <div class="ci-pagination">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a class="ci-page-link <?= $i === $page ? 'active' : '' ?>"
               href="<?= e($baseUrl . '/staff/messages?page=' . $i . '&tab=' . urlencode($activeTab)) ?>"><?= e((string)$i) ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    </aside>

    <!-- ── Chat panel ──────────────────────────────────────────────── -->
    <main class="ci-chat">
      <?php if ($hasActiveConversation): ?>
        <header class="ci-topbar">
          <div class="ci-topbar-person">
            <a href="<?= e($baseUrl . '/staff/messages?tab=' . urlencode($activeTab)) ?>" class="ci-mobile-back" id="conversationBackLink">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
              
            </a>
            <span class="ci-avatar <?= e($activeType) ?>"><?= e(messageInitials($activeName)) ?></span>
            <span class="ci-topbar-text">
              <span class="ci-topbar-name"><?= e($activeName) ?></span>
              <span class="ci-topbar-sub">
                <span class="ci-online-dot"></span>
                <?= e(ucfirst($activeType)) ?> conversation
                <?php if ($activeStatus !== ''): ?> · <?= e($activeStatus) ?><?php endif; ?>
              </span>
            </span>
          </div>

          <label class="ci-topbar-search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" placeholder="Search in chat…" id="chatMessageSearch">
          </label>

         <div class="ci-topbar-actions">
    <div class="ci-menu-wrap">
        <button
            type="button"
            class="ci-icon-btn"
            id="conversationMenuButton"
            title="Conversation actions"
            aria-label="Conversation actions"
            aria-expanded="false"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="5" cy="12" r="1"></circle>
                <circle cx="12" cy="12" r="1"></circle>
                <circle cx="19" cy="12" r="1"></circle>
            </svg>
        </button>

        <div class="ci-actions-menu" id="conversationActionsMenu" aria-hidden="true">
            <button type="button" id="menuOpenDetails">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M12 10v6"></path>
                    <path d="M12 7h.01"></path>
                </svg>
                Chat info
            </button>

            <form
                method="POST"
                action="<?= e($baseUrl . ($isActiveArchived ? '/staff/messages/unarchive' : '/staff/messages/archive')) ?>"
                class="ci-menu-form"
                onsubmit="return confirm('<?= $isActiveArchived ? 'Restore this conversation?' : 'Archive this conversation?' ?>');"
            >
                <?= Csrf::inputField(); ?>

                <input type="hidden" name="conversation_id" value="<?= (int) $activeConversationId ?>">
                <input type="hidden" name="active_tab" value="<?= e($activeTab) ?>">

                <button type="submit" class="ci-menu-submit <?= $isActiveArchived ? '' : 'ci-menu-danger' ?>">
                    <?php if ($isActiveArchived): ?>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 7h16"></path>
                            <path d="M6 7v13h12V7"></path>
                            <path d="M9 14h6"></path>
                            <path d="M12 10v8"></path>
                            <path d="M8 4h8l1 3H7l1-3z"></path>
                        </svg>
                        Restore conversation
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 7h16"></path>
                            <path d="M6 7v13h12V7"></path>
                            <path d="M9 11h6"></path>
                            <path d="M8 4h8l1 3H7l1-3z"></path>
                        </svg>
                        Archive conversation
                    <?php endif; ?>
                </button>
            </form>
        </div>
    </div>
</div>
        </header>

        <!-- Messages -->
        <section class="ci-messages" id="conversationMessages">
          <?php if (empty($messages)): ?>
            <div style="text-align:center;color:var(--c-subtle);font-size:13px;padding:30px 0;">No messages yet. Start the conversation!</div>
          <?php else: ?>
            <?php $lastDay = ''; ?>
            <?php foreach ($messages as $message): ?>
              <?php
                if (!is_array($message)) continue;
                $createdAt     = $message['sent_at'] ?? $message['created_at'] ?? '';
                $dayLabel      = messageDayText($createdAt);
                $isBot         = messageIsBot($message);
                $isOutgoing    = messageIsOutgoing($message) || $isBot;
                $senderName    = messageSenderName($message);
                $text          = messageText($message);
                $attachmentName= messageAttachmentName($message);
                $attachmentUrl = messageAttachmentUrl($message, $baseUrl);
                $attachmentIsImage = messageAttachmentIsImage($message);
                $rowClass      = $isBot ? 'bot' : ($isOutgoing ? 'out' : 'in');
              ?>
              <?php if ($dayLabel !== '' && $dayLabel !== $lastDay): ?>
                <div class="ci-day-divider"><span><?= e($dayLabel) ?></span></div>
                <?php $lastDay = $dayLabel; ?>
              <?php endif; ?>

              <div class="ci-msg-row <?= $rowClass ?>" data-message-row>
                <?php if (!$isOutgoing): ?>
                  <span class="ci-avatar sm <?= $isBot ? 'bot' : e($activeType) ?>"><?= e(messageInitials($senderName)) ?></span>
                <?php endif; ?>

                <div class="ci-bubble">
                  <?php if ($text !== ''): ?>
                    <div class="ci-bubble-text"><?= nl2br(e($text)) ?></div>
                  <?php endif; ?>

                  <?php if ($attachmentName !== '' && $attachmentUrl !== ''): ?>
                    <a class="ci-attachment" href="<?= e($attachmentUrl) ?>" target="_blank" rel="noopener">
                      <?php if ($attachmentIsImage): ?>
                        <img src="<?= e($attachmentUrl) ?>" alt="<?= e($attachmentName) ?>">
                      <?php else: ?>
                        <span class="ci-attach-icon">FILE</span>
                      <?php endif; ?>
                      <span><?= e($attachmentName) ?></span>
                    </a>
                  <?php endif; ?>

                  <div class="ci-bubble-meta">
                    <span><?= e($senderName) ?></span>
                    <?php if ($createdAt): ?><span>· <?= e(messageDateText($createdAt)) ?></span><?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>

        <!-- Composer -->
        <footer class="ci-composer">
          <?php if (!$canUploadAttachment && $activeType === 'guest'): ?>
            <div class="ci-guest-note">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              Guests can send text only — file upload is unavailable.
            </div>
          <?php endif; ?>

          <?php if (!empty($composerSuggestions)): ?>
            <div class="ci-suggestions" aria-label="Quick replies">
              <?php foreach (array_slice($composerSuggestions, 0, 4) as $reply): ?>
                <button type="button" class="ci-suggestion-chip" data-insert-text="<?= e($reply) ?>" title="<?= e($reply) ?>"><?= e($reply) ?></button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="<?= e($baseUrl . '/staff/messages/reply') ?>" id="staffReplyForm" enctype="multipart/form-data">
            <?= Csrf::inputField(); ?>
            <input type="hidden" name="conversation_id" value="<?= (int) $activeConversationId ?>">
            <input type="hidden" name="active_tab" value="<?= e($activeTab) ?>">
            <input type="hidden" name="message_body" id="staffMessageBodyMirror" value="">

            <div class="ci-composer-row">
              <?php if ($canUploadAttachment): ?>
                <label class="ci-attach-btn" title="Attach file">
                  <input type="file" name="attachment" id="messageAttachmentInput" accept=".jpg,.jpeg,.png,.webp,.pdf" hidden>
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.44 11.05 12.2 20.29a6 6 0 0 1-8.49-8.49l9.24-9.24a4 4 0 1 1 5.66 5.66l-9.24 9.24a2 2 0 0 1-2.83-2.83l8.49-8.49"/></svg>
                </label>
              <?php endif; ?>

              <textarea
                name="message_text"
                id="staffMessageComposer"
                class="ci-composer-textarea"
                placeholder="Write a message…"
                required
                rows="1"
              ></textarea>

              <button type="submit" class="ci-send-btn" title="Send" aria-label="Send message">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h13"/><path d="m13 6 6 6-6 6"/></svg>
              </button>
            </div>
          </form>

          <div class="ci-file-preview" id="selectedFileName" hidden>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
            <span id="selectedFileNameText"></span>
          </div>
        </footer>

      <?php else: ?>
        <div class="ci-chat-empty">
          <div class="ci-chat-empty-card">
            <div class="ci-chat-empty-icon">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </div>
            <h2>Select a conversation</h2>
            <p>Choose a patient or guest message from the inbox to open the conversation and reply.</p>
          </div>
        </div>
      <?php endif; ?>
    </main>

    <!-- ── Rail ───────────────────────────────────────────────────── -->
    <aside class="ci-rail" aria-label="Actions">
      <?php if ($hasActiveConversation): ?>
        <button type="button" class="ci-rail-btn" id="openDetailsDrawer" title="Chat info" aria-expanded="false">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 10v6"/><path d="M12 7h.01"/></svg>
        </button>

        <form method="POST"
              action="<?= e($baseUrl . ($isActiveArchived ? '/staff/messages/unarchive' : '/staff/messages/archive')) ?>"
              class="ci-rail-form"
              onsubmit="return confirm('<?= $isActiveArchived ? 'Restore this conversation?' : 'Archive this conversation?' ?>');">
          <?= Csrf::inputField(); ?>
          <input type="hidden" name="conversation_id" value="<?= (int) $activeConversationId ?>">
          <input type="hidden" name="active_tab" value="<?= e($activeTab) ?>">
          <button type="submit" class="ci-rail-btn <?= $isActiveArchived ? 'archive-active' : '' ?>"
                  title="<?= $isActiveArchived ? 'Restore' : 'Archive' ?>"
                  aria-label="<?= $isActiveArchived ? 'Restore conversation' : 'Archive conversation' ?>">
            <?php if ($isActiveArchived): ?>
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16"/><path d="M6 7v13h12V7"/><path d="M9 14h6"/><path d="M12 10v8"/><path d="M8 4h8l1 3H7l1-3z"/></svg>
            <?php else: ?>
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16"/><path d="M6 7v13h12V7"/><path d="M9 11h6"/><path d="M8 4h8l1 3H7l1-3z"/></svg>
            <?php endif; ?>
          </button>
        </form>
      <?php endif; ?>
    </aside>

    <!-- ── Details drawer ─────────────────────────────────────────── -->
    <?php if ($hasActiveConversation): ?>
      <aside class="ci-details" id="detailsDrawer" aria-hidden="true">
        <div class="ci-details-head">
          <h2>Chat info</h2>
          <button type="button" class="ci-details-close" id="closeDetailsDrawer" aria-label="Close">×</button>
        </div>

        <div class="ci-details-profile">
          <span class="ci-avatar lg <?= e($activeType) ?>"><?= e(messageInitials($activeName)) ?></span>
          <span class="ci-details-name"><?= e($activeName) ?></span>
          <span class="ci-details-status-badge"><?= e($activeStatus) ?></span>
        </div>

        <div class="ci-details-body">
          <section class="ci-details-section">
            <h3>Contact</h3>
            <div class="ci-details-row"><span>Type</span><strong><?= e(ucfirst($activeType)) ?></strong></div>
            <div class="ci-details-row"><span>Phone</span><strong><?= e($activeContact !== '' ? $activeContact : '—') ?></strong></div>
            <div class="ci-details-row"><span>Email</span><strong><?= e($activeEmail !== '' ? $activeEmail : '—') ?></strong></div>
            <div class="ci-details-row"><span>Created</span><strong><?= e(messageDateText($activeConversationRow['created_at'] ?? '')) ?></strong></div>
          </section>

          <section class="ci-details-section">
            <h3>Shared files</h3>
            <?php if (empty($mediaFiles)): ?>
              <div class="ci-muted-box">No shared media or files yet.</div>
            <?php else: ?>
              <div class="ci-media-list">
                <?php foreach ($mediaFiles as $file): ?>
                  <?php
                    if (!is_array($file)) continue;
                    $attachmentId = (int) ($file['attachment_id'] ?? 0);
                    $fileName     = trim((string) ($file['original_file_name'] ?? $file['stored_file_name'] ?? 'Attachment'));
                    $mimeType     = trim((string) ($file['mime_type'] ?? ''));
                    $fileSize     = (int) ($file['file_size'] ?? 0);
                    $fileUrl      = $attachmentId > 0 ? $baseUrl . '/staff/attachments/show?id=' . $attachmentId : '#';
                  ?>
                  <a class="ci-media-item" href="<?= e($fileUrl) ?>" target="_blank" rel="noopener">
                    <span class="ci-media-icon"><?= safeStrStartsWith(strtolower($mimeType), 'image/') ? 'IMG' : 'FILE' ?></span>
                    <span>
                      <span class="ci-media-name"><?= e($fileName) ?></span>
                      <span class="ci-media-note">
                        <?= e($mimeType !== '' ? $mimeType : 'Attachment') ?>
                        <?php if ($fileSize > 0): ?> · <?= e(number_format($fileSize / 1024, 1)) ?> KB<?php endif; ?>
                      </span>
                    </span>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>

          <section class="ci-details-section">
            <h3>Privacy</h3>
            <div class="ci-muted-box">Messages are for clinic communication only. Do not share passwords, payment details, or unrelated sensitive information.</div>
          </section>
        </div>
      </aside>
    <?php endif; ?>
  </div>

  <!-- ── Create conversation modal ──────────────────────────────── -->
  <div class="ci-modal-overlay" id="createMessageModal" aria-hidden="true">
    <div class="ci-modal" role="dialog" aria-modal="true" aria-labelledby="createMessageTitle">
      <div class="ci-modal-head">
        <h2 id="createMessageTitle">New Conversation</h2>
        <button type="button" class="ci-modal-close" id="closeCreateMessageModal" aria-label="Close">×</button>
      </div>

      <form method="POST" action="<?= e($baseUrl . '/staff/messages/create') ?>" id="createMessageForm" class="ci-modal-body">
        <?= Csrf::inputField(); ?>

        <div class="ci-muted-box">Only patients with registered login accounts are shown. Patient records without accounts cannot be messaged here.</div>

        <label class="ci-form-field">
          <span>Search Patient Account</span>
          <input type="search" id="patientSearchInput" class="ci-form-input" placeholder="Search by name, contact, or email…" autocomplete="off">
        </label>

        <input type="hidden" name="patient_id" id="selectedPatientId" value="">

        <div class="ci-patient-picker" id="patientPicker">
          <?php if (empty($patients)): ?>
            <div class="ci-empty">No patient accounts found.</div>
          <?php else: ?>
            <?php foreach ($patients as $patient): ?>
              <?php
                if (!is_array($patient)) continue;
                $patientId = (int) ($patient['patient_id'] ?? 0);
                $patientName = trim((string)(($patient['first_name'] ?? '').' '.($patient['middle_name'] ?? '').' '.($patient['last_name'] ?? '')));
                $patientContact = trim((string)($patient['contact_number'] ?? ''));
                $patientEmail   = trim((string)($patient['email'] ?? ''));
                $existingConversationId = (int)($patient['existing_conversation_id'] ?? 0);
                if ($patientId <= 0 || $patientName === '') continue;
                $patientSearch = strtolower($patientName . ' ' . $patientContact . ' ' . $patientEmail);
              ?>
              <button type="button" class="ci-patient-item"
                      data-patient-option
                      data-patient-id="<?= (int)$patientId ?>"
                      data-patient-name="<?= e($patientName) ?>"
                      data-existing-conversation-id="<?= (int)$existingConversationId ?>"
                      data-search="<?= e($patientSearch) ?>">
                <span class="ci-avatar sm patient"><?= e(messageInitials($patientName)) ?></span>
                <span class="ci-patient-main">
                  <strong><?= e($patientName) ?></strong>
                  <small><?= e($patientContact !== '' ? $patientContact : 'No contact') ?><?= $patientEmail !== '' ? ' · ' . e($patientEmail) : '' ?></small>
                </span>
                <span class="ci-patient-tag <?= $existingConversationId > 0 ? 'existing' : '' ?>"><?= $existingConversationId > 0 ? 'Existing' : 'New' ?></span>
              </button>
            <?php endforeach; ?>
            <div class="ci-empty" id="patientNoResult" hidden>No registered patient matches your search.</div>
          <?php endif; ?>
        </div>

        <div class="ci-selected-patient-box" id="selectedPatientBox" hidden>
          Selected: <strong id="selectedPatientName"></strong><span id="selectedPatientMode"></span>
        </div>

        <label class="ci-form-field">
          <span>Optional First Message</span>
          <textarea name="initial_message" class="ci-form-input ci-form-textarea" placeholder="Type an optional first message…"></textarea>
        </label>

        <div class="ci-modal-actions">
          <button type="button" class="ci-btn" id="cancelCreateMessageModal">Cancel</button>
          <button type="button" class="ci-btn primary" id="createMessageSubmitBtn">Open Conversation</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const shell         = document.getElementById('clinicInboxShell');
  const detailsDrawer = document.getElementById('detailsDrawer');
  const openDetailsBtn= document.getElementById('openDetailsDrawer');
  const closeDetailsBtn=document.getElementById('closeDetailsDrawer');
  const messagesBox   = document.getElementById('conversationMessages');





const conversationMenuButton = document.getElementById('conversationMenuButton');
const conversationActionsMenu = document.getElementById('conversationActionsMenu');
const menuOpenDetails = document.getElementById('menuOpenDetails');

function closeConversationMenu() {
    if (!conversationMenuButton || !conversationActionsMenu) {
        return;
    }

    conversationActionsMenu.classList.remove('is-open');
    conversationActionsMenu.setAttribute('aria-hidden', 'true');
    conversationMenuButton.setAttribute('aria-expanded', 'false');
}

function toggleConversationMenu(event) {
    if (!conversationMenuButton || !conversationActionsMenu) {
        return;
    }

    event.stopPropagation();

    const isOpen = conversationActionsMenu.classList.contains('is-open');

    if (isOpen) {
        closeConversationMenu();
        return;
    }

    conversationActionsMenu.classList.add('is-open');
    conversationActionsMenu.setAttribute('aria-hidden', 'false');
    conversationMenuButton.setAttribute('aria-expanded', 'true');
}

if (conversationMenuButton) {
    conversationMenuButton.addEventListener('click', toggleConversationMenu);
}

if (menuOpenDetails) {
    menuOpenDetails.addEventListener('click', function () {
        closeConversationMenu();
        openDetails();
    });
}

document.addEventListener('click', function (event) {
    if (
        conversationActionsMenu &&
        conversationMenuButton &&
        !conversationActionsMenu.contains(event.target) &&
        !conversationMenuButton.contains(event.target)
    ) {
        closeConversationMenu();
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeConversationMenu();
    }
});







  /* ── Details panel ── */
  function openDetails(){
    if(!shell||!detailsDrawer||!openDetailsBtn) return;
    shell.classList.add('details-open');
    detailsDrawer.setAttribute('aria-hidden','false');
    openDetailsBtn.classList.add('active');
    openDetailsBtn.setAttribute('aria-expanded','true');
  }
  function closeDetails(){
    if(!shell||!detailsDrawer||!openDetailsBtn) return;
    shell.classList.remove('details-open');
    detailsDrawer.setAttribute('aria-hidden','true');
    openDetailsBtn.classList.remove('active');
    openDetailsBtn.setAttribute('aria-expanded','false');
  }
  if(openDetailsBtn) openDetailsBtn.addEventListener('click',function(){
    shell&&shell.classList.contains('details-open') ? closeDetails() : openDetails();
  });
  if(closeDetailsBtn) closeDetailsBtn.addEventListener('click', closeDetails);

  /* ── Auto-scroll to bottom ── */
  if(messagesBox) messagesBox.scrollTop = messagesBox.scrollHeight;

  /* ── Thread search ── */
  const searchInput   = document.getElementById('messageSearchInput');
  const threadItems   = Array.from(document.querySelectorAll('[data-thread-item]'));
  const noFilterResult= document.getElementById('messageNoFilterResult');
  if(searchInput && threadItems.length){
    searchInput.addEventListener('input', function(){
      const q = searchInput.value.trim().toLowerCase();
      let visible = 0;
      threadItems.forEach(function(item){
        const match = q==='' || String(item.dataset.search||'').toLowerCase().includes(q);
        item.hidden = !match;
        if(match) visible++;
      });
      if(noFilterResult) noFilterResult.hidden = visible > 0;
    });
  }

  /* ── Thread click animation ── */
  document.querySelectorAll('[data-thread-item]').forEach(function(link){
    link.addEventListener('click', function(e){
      if(e.ctrlKey||e.metaKey||e.shiftKey||e.altKey||link.target==='_blank') return;
      e.preventDefault();
      if(shell) shell.classList.add('is-sliding-to-conversation');
      setTimeout(function(){ window.location.href = link.href; }, 150);
    });
  });

  /* ── Back link ── */
  const backLink = document.getElementById('conversationBackLink');
  if(backLink) backLink.addEventListener('click', function(e){ e.preventDefault(); window.location.href = backLink.href; });

  /* ── Composer auto-grow ── */
  const composer = document.getElementById('staffMessageComposer');
  const mirror   = document.getElementById('staffMessageBodyMirror');
  function growComposer(){
    if(!composer) return;
    composer.style.height='auto';
    composer.style.height = Math.min(composer.scrollHeight, 120) + 'px';
  }
  if(composer){ composer.addEventListener('input', growComposer); growComposer(); }

  /* ── Reply form ── */
  const replyForm = document.getElementById('staffReplyForm');
  if(replyForm && composer && mirror){
    replyForm.addEventListener('submit', function(e){
      const msg = composer.value.trim();
      if(msg===''){e.preventDefault();composer.focus();return;}
      mirror.value = msg;
    });
  }

  /* ── Quick replies ── */
  document.querySelectorAll('[data-insert-text]').forEach(function(btn){
    btn.addEventListener('click', function(){
      if(!composer) return;
      const text = String(btn.dataset.insertText||'').trim();
      if(text==='') return;
      composer.value = text;
      composer.focus();
      growComposer();
      if(mirror) mirror.value = text;
    });
  });

  /* ── File picker ── */
  const fileInput  = document.getElementById('messageAttachmentInput');
  const filePreview= document.getElementById('selectedFileName');
  const fileText   = document.getElementById('selectedFileNameText');
  if(fileInput && filePreview && fileText){
    fileInput.addEventListener('change', function(){
      const file = fileInput.files&&fileInput.files.length ? fileInput.files[0] : null;
      if(!file){ filePreview.hidden=true; fileText.textContent=''; return; }
      fileText.textContent = file.name;
      filePreview.hidden = false;
    });
  }

  /* ── Create modal ── */
  const createModal   = document.getElementById('createMessageModal');
  const openCreateBtn = document.getElementById('openCreateMessageModal');
  const closeCreateBtn= document.getElementById('closeCreateMessageModal');
  const cancelBtn     = document.getElementById('cancelCreateMessageModal');
  function openModal(){ if(createModal){createModal.classList.add('is-open');createModal.setAttribute('aria-hidden','false');} }
  function closeModal(){ if(createModal){createModal.classList.remove('is-open');createModal.setAttribute('aria-hidden','true');} }
  if(openCreateBtn) openCreateBtn.addEventListener('click', openModal);
  if(closeCreateBtn) closeCreateBtn.addEventListener('click', closeModal);
  if(cancelBtn) cancelBtn.addEventListener('click', closeModal);
  if(createModal) createModal.addEventListener('click', function(e){ if(e.target===createModal) closeModal(); });

  /* ── Patient picker ── */
  const patientSearchInput = document.getElementById('patientSearchInput');
  const patientOptions     = Array.from(document.querySelectorAll('[data-patient-option]'));
  const patientNoResult    = document.getElementById('patientNoResult');
  const selectedPatientId  = document.getElementById('selectedPatientId');
  const selectedPatientBox = document.getElementById('selectedPatientBox');
  const selectedPatientName= document.getElementById('selectedPatientName');
  const selectedPatientMode= document.getElementById('selectedPatientMode');

  if(patientSearchInput && patientOptions.length){
    patientSearchInput.addEventListener('input', function(){
      const q = patientSearchInput.value.trim().toLowerCase();
      let visible=0;
      patientOptions.forEach(function(opt){
        const match = q==='' || String(opt.dataset.search||'').toLowerCase().includes(q);
        opt.hidden=!match;
        if(match) visible++;
      });
      if(patientNoResult) patientNoResult.hidden = visible>0;
    });
  }

  patientOptions.forEach(function(opt){
    opt.addEventListener('click', function(){
      patientOptions.forEach(function(o){ o.classList.remove('selected'); });
      opt.classList.add('selected');
      const pid  = String(opt.dataset.patientId||'');
      const pname= String(opt.dataset.patientName||'');
      const existId = parseInt(opt.dataset.existingConversationId||'0',10);
      if(selectedPatientId) selectedPatientId.value = pid;
      if(selectedPatientName) selectedPatientName.textContent = pname;
      if(selectedPatientMode) selectedPatientMode.textContent = existId>0 ? ' · Existing conversation' : ' · New conversation';
      if(selectedPatientBox) selectedPatientBox.hidden = false;
    });
  });

  const createSubmitBtn  = document.getElementById('createMessageSubmitBtn');
  const createMessageForm= document.getElementById('createMessageForm');
  if(createSubmitBtn && createMessageForm && selectedPatientId){
    createSubmitBtn.addEventListener('click', function(){
      if(!document.querySelector('[data-patient-option].selected') || selectedPatientId.value.trim()===''){
        alert('Please select a patient with a registered account first.');
        return;
      }
      createMessageForm.submit();
    });
  }

  /* ── Chat message search ── */
  const chatSearch = document.getElementById('chatMessageSearch');
  if(chatSearch){
    chatSearch.addEventListener('input', function(){
      const q = chatSearch.value.trim().toLowerCase();
      document.querySelectorAll('[data-message-row]').forEach(function(row){
        row.hidden = q!=='' && !row.textContent.toLowerCase().includes(q);
      });
    });
  }
});
</script>

<?php
$staffContent = ob_get_clean();
$pageTitle = 'Messages';
$title = 'Messages';
require __DIR__ . '/../layouts/app.php';