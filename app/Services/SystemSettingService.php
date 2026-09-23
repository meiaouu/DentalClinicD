<?php

namespace App\Services;

use App\Repositories\SystemSettingsRepository;
use Throwable;

class SystemSettingService
{
    private static ?SystemSettingsRepository $repository = null;

    private static function repository(): SystemSettingsRepository
    {
        if (self::$repository === null) {
            self::$repository = new SystemSettingsRepository();
        }

        return self::$repository;
    }

    /*
    |--------------------------------------------------------------------------
    | Main Getters
    |--------------------------------------------------------------------------
    */

    public static function get(string $group, string $key, string $default = ''): string
    {
        try {
            return self::repository()->get($group, $key, $default);
        } catch (Throwable $e) {
            error_log('[SystemSettingService::get] ' . $e->getMessage());
            return $default;
        }
    }

    public static function value(string $group, string $key, string $default = ''): string
    {
        return self::get($group, $key, $default);
    }

    public static function raw(string $group, string $key, string $default = ''): string
    {
        return self::get($group, $key, $default);
    }

    /*
    |--------------------------------------------------------------------------
    | Boolean Settings
    |--------------------------------------------------------------------------
    */

    public static function bool(string $group, string $key, bool $default = false): bool
    {
        try {
            return self::repository()->bool($group, $key, $default);
        } catch (Throwable $e) {
            error_log('[SystemSettingService::bool] ' . $e->getMessage());

            $value = self::get($group, $key, $default ? '1' : '0');

            return self::toBool($value, $default);
        }
    }

    public static function enabled(string $group, string $key, bool $default = false): bool
    {
        return self::bool($group, $key, $default);
    }

    public static function isEnabled(string $group, string $key, bool $default = false): bool
    {
        return self::bool($group, $key, $default);
    }

    public static function disabled(string $group, string $key, bool $default = false): bool
    {
        return !self::bool($group, $key, $default);
    }

    private static function toBool(string $value, bool $default = false): bool
    {
        $value = strtolower(trim($value));

        if ($value === '') {
            return $default;
        }

        return in_array($value, [
            '1',
            'yes',
            'true',
            'on',
            'enabled',
        ], true);
    }

    /*
    |--------------------------------------------------------------------------
    | Integer Settings
    |--------------------------------------------------------------------------
    */

    public static function int(string $group, string $key, int $default = 0): int
    {
        try {
            return self::repository()->int($group, $key, $default);
        } catch (Throwable $e) {
            error_log('[SystemSettingService::int] ' . $e->getMessage());

            $value = self::get($group, $key, (string) $default);

            return is_numeric($value) ? (int) $value : $default;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Float Settings
    |--------------------------------------------------------------------------
    */

    public static function float(string $group, string $key, float $default = 0.0): float
    {
        $value = self::get($group, $key, (string) $default);

        return is_numeric($value) ? (float) $value : $default;
    }

    /*
    |--------------------------------------------------------------------------
    | Grouped Settings
    |--------------------------------------------------------------------------
    */

    public static function all(): array
    {
        try {
            return self::repository()->getAllGrouped();
        } catch (Throwable $e) {
            error_log('[SystemSettingService::all] ' . $e->getMessage());
            return [];
        }
    }

    public static function group(string $group): array
    {
        $all = self::all();

        return isset($all[$group]) && is_array($all[$group])
            ? $all[$group]
            : [];
    }

    /*
    |--------------------------------------------------------------------------
    | Common System Shortcuts
    |--------------------------------------------------------------------------
    */

    public static function onlineBookingEnabled(): bool
    {
        return self::bool('appointment_rules', 'enable_online_booking', true);
    }

    public static function guestBookingEnabled(): bool
    {
        return self::bool('appointment_rules', 'enable_guest_booking', true);
    }

    public static function chatbotEnabled(): bool
    {
        return self::bool('messaging', 'enable_chatbot', true);
    }

    public static function guestChatbotEnabled(): bool
    {
        return self::bool('messaging', 'enable_guest_chatbot', true);
    }

    public static function billingEnabled(): bool
    {
        return self::bool('billing', 'enable_billing_module', true);
    }

    public static function documentUploadsEnabled(): bool
    {
        return self::bool('documents', 'enable_document_uploads', true);
    }

    public static function maintenanceModeEnabled(): bool
    {
        return self::bool('maintenance', 'maintenance_mode', false);
    }

    public static function allowOwnerAdminDuringMaintenance(): bool
    {
        return self::bool('maintenance', 'allow_owner_admin_login', true);
    }

    public static function maintenanceMessage(): string
    {
        return self::get(
            'maintenance',
            'maintenance_message',
            'The system is currently under maintenance. Please check back later.'
        );
    }

    public static function clinicName(): string
    {
        return self::get(
            'clinic_profile',
            'clinic_name',
            'Dental Clinic'
        );
    }

    public static function reset(): void
    {
        self::$repository = null;
    }
} 