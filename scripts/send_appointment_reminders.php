<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

$projectRoot = dirname(__DIR__);

$autoload = $projectRoot . '/vendor/autoload.php';

if (is_file($autoload)) {
    require_once $autoload;
} else {
    spl_autoload_register(function (string $class) use ($projectRoot): void {
        $prefix = 'App\\';

        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $path = $projectRoot . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($path)) {
            require_once $path;
        }
    });
}

use App\Services\AppointmentReminderService;

try {
    $service = new AppointmentReminderService();
    $summary = $service->sendAutomaticDueReminders();

    echo "Appointment Reminder Summary\n";
    echo "Date: " . date('Y-m-d H:i:s') . "\n";
    echo "Total found: " . (int) ($summary['total_found'] ?? 0) . "\n";
    echo "Sent: " . (int) ($summary['sent'] ?? 0) . "\n";
    echo "Skipped: " . (int) ($summary['skipped'] ?? 0) . "\n";
    echo "Failed: " . (int) ($summary['failed'] ?? 0) . "\n";

    exit(0);
} catch (Throwable $e) {
    echo "Appointment reminder script failed.\n";
    echo $e->getMessage() . "\n";

    exit(1);
}