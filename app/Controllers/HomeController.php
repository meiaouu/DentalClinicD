<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\View;
use PDO;
use Throwable;

class HomeController
{
    private PDO $db;
    private string $baseUrl = '/DentalClinic/public';

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function index(): void
    {
        $settings = $this->systemSettings();

        $clinicProfile = $settings['clinic_profile'] ?? [];
        $publicWebsite = $settings['public_website'] ?? [];
        $appointmentRules = $settings['appointment_rules'] ?? [];
        $messagingSettings = $settings['messaging'] ?? [];
        $appearanceSettings = $settings['appearance'] ?? [];
        $maintenanceSettings = $settings['maintenance'] ?? [];

        $clinicHours = $this->clinicHours();
        $services = $this->activeServices();

        View::render('public.home', [
            'title' => $this->settingValue($clinicProfile, 'clinic_name', 'Home'),
            'pageTitle' => $this->settingValue($clinicProfile, 'clinic_name', 'Home'),

            'settings' => $settings,
            'clinicProfile' => $clinicProfile,
            'publicWebsite' => $publicWebsite,
            'appointmentRules' => $appointmentRules,
            'messagingSettings' => $messagingSettings,
            'appearanceSettings' => $appearanceSettings,
            'maintenanceSettings' => $maintenanceSettings,

            'clinicHours' => $clinicHours,
            'services' => $services,

            'baseUrl' => $this->baseUrl,
        ]);
    }

    private function systemSettings(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT
                    setting_id,
                    setting_group,
                    setting_key,
                    setting_value,
                    setting_type,
                    value_type,
                    is_public,
                    updated_by,
                    created_at,
                    updated_at
                FROM system_settings
                ORDER BY setting_group ASC, setting_key ASC
            ");

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }

        $settings = [];

        foreach ($rows as $row) {
            $group = (string) ($row['setting_group'] ?? '');
            $key = (string) ($row['setting_key'] ?? '');

            if ($group === '' || $key === '') {
                continue;
            }

            $settings[$group][$key] = $row;
        }

        return $settings;
    }

    private function clinicHours(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT
                    clinic_hour_id,
                    day_of_week,
                    is_open,
                    opening_time,
                    closing_time,
                    break_start,
                    break_end,
                    updated_by,
                    created_at,
                    updated_at
                FROM clinic_hours
                ORDER BY FIELD(day_of_week, 1, 2, 3, 4, 5, 6, 0)
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    private function activeServices(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT
                    service_id,
                    service_name,
                    description,
                    estimated_duration_minutes,
                    estimated_price,
                    service_image,
                    display_order,
                    is_active,
                    created_at,
                    updated_at
                FROM services
                WHERE is_active = 1
                ORDER BY display_order ASC, service_name ASC, service_id ASC
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    private function settingValue(array $group, string $key, string $default = ''): string
    {
        $value = $group[$key] ?? null;

        if (is_array($value)) {
            $value = $value['setting_value']
                ?? $value['value']
                ?? $value['setting_text']
                ?? null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : $default;
    }
}