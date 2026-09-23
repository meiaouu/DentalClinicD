<?php

namespace App\Controllers\Dentist;

use App\Core\Auth;
use App\Core\Csrf;
use App\Repositories\NotificationRepository;

class NotificationController
{
    private NotificationRepository $notifications;

    public function __construct()
    {
        $this->notifications = new NotificationRepository();
    }

    public function index(): void
    {
        Auth::requireRole('dentist');

        $user = Auth::user();
        $userId = (int) ($user['user_id'] ?? 0);
        $this->notifications->createOrUpdateTodayAppointmentSummaryForDentistUser($userId);

        header('Content-Type: application/json');

        echo json_encode([
            'unread_count' => $this->notifications->unreadCountForUser($userId),
            'notifications' => $this->notifications->latestForUser($userId, 10),
            
        ]);
    }

    public function markOneRead(): void
    {
        Auth::requireRole('dentist');

        header('Content-Type: application/json');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid CSRF token.',
            ]);
            return;
        }

        $user = Auth::user();
        $userId = (int) ($user['user_id'] ?? 0);
        $notificationId = (int) ($_POST['notification_id'] ?? 0);

        if ($notificationId <= 0) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid notification.',
            ]);
            return;
        }

        $marked = $this->notifications->markOneReadForUser($userId, $notificationId);

        echo json_encode([
            'success' => true,
            'marked' => $marked,
            'notification_id' => $notificationId,
            'unread_count' => $this->notifications->unreadCountForUser($userId),
        ]);
    }
}