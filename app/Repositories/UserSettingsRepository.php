<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

class UserSettingsRepository
{
    private PDO $db;

    private const PRIVACY_FIELDS = [
        'show_profile_name',
        'allow_appointment_notifications',
        'allow_message_notifications',
        'allow_document_notifications',
        'hide_contact_from_non_staff',
    ];

    private const ALLOWED_LANGUAGE = ['english', 'filipino'];

    private const ALLOWED_APPEARANCE = [
        'theme_mode' => ['light', 'dark', 'system'],
        'accent_color' => ['teal', 'blue', 'green', 'purple', 'gray'],
        'font_size' => ['small', 'normal', 'large'],
        'layout_density' => ['comfortable', 'compact'],
        'border_radius' => ['none', 'small', 'medium'],
        'sidebar_mode' => ['expanded', 'compact'],
    ];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?: self::resolveConnection();
    }

    public function findByUserId(int $userId): array
    {
        if ($userId <= 0) {
            return $this->defaultSettings(0);
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM user_settings
            WHERE user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $this->createDefaultForUser($userId);

            $stmt->execute([
                ':user_id' => $userId,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $this->normalizeSettings($row ?: $this->defaultSettings($userId));
    }

    public function createDefaultForUser(int $userId): int
    {
        if ($userId <= 0) {
            throw new RuntimeException('Invalid user ID.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO user_settings (
                user_id,
                show_profile_name,
                allow_appointment_notifications,
                allow_message_notifications,
                allow_document_notifications,
                hide_contact_from_non_staff,
                language,
                theme_mode,
                accent_color,
                font_size,
                layout_density,
                border_radius,
                sidebar_mode,
                created_at
            ) VALUES (
                :user_id,
                1,
                1,
                1,
                1,
                0,
                'english',
                'light',
                'teal',
                'normal',
                'comfortable',
                'small',
                'expanded',
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id)
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateAccount(int $userId, array $data): bool
    {
        if ($userId <= 0) {
            throw new RuntimeException('Invalid user ID.');
        }

        $columns = $this->getTableColumns('users');

        $sets = [];
        $params = [
            ':user_id' => $userId,
        ];

        $allowedUserFields = [
    'username',
    'first_name',
    'last_name',
    'email',
    'contact_number',
];

        foreach ($allowedUserFields as $field) {
            if (in_array($field, $columns, true) && array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (!empty($data['password_hash'])) {
            $passwordColumn = $this->detectPasswordColumn($columns);

            if ($passwordColumn === '') {
                throw new RuntimeException('Password column was not found in users table.');
            }

            $sets[] = "{$passwordColumn} = :password_hash";
            $params[':password_hash'] = $data['password_hash'];
        }

        if (in_array('updated_at', $columns, true)) {
            $sets[] = 'updated_at = NOW()';
        }

        if (empty($sets)) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE users
            SET " . implode(', ', $sets) . "
            WHERE user_id = :user_id
            LIMIT 1
        ");

        return $stmt->execute($params);
    }


public function usernameExistsForOtherUser(string $username, int $userId): bool
{
    $stmt = $this->db->prepare("
        SELECT user_id
        FROM users
        WHERE username = :username
          AND user_id <> :user_id
        LIMIT 1
    ");

    $stmt->execute([
        ':username' => $username,
        ':user_id' => $userId,
    ]);

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}




    public function updatePrivacy(int $userId, array $data): bool
    {
        $this->createDefaultForUser($userId);

        $clean = $this->sanitizePrivacy($data);

        $stmt = $this->db->prepare("
            UPDATE user_settings
            SET show_profile_name = :show_profile_name,
                allow_appointment_notifications = :allow_appointment_notifications,
                allow_message_notifications = :allow_message_notifications,
                allow_document_notifications = :allow_document_notifications,
                hide_contact_from_non_staff = :hide_contact_from_non_staff,
                updated_at = NOW()
            WHERE user_id = :user_id
            LIMIT 1
        ");

        return $stmt->execute([
            ':show_profile_name' => $clean['show_profile_name'],
            ':allow_appointment_notifications' => $clean['allow_appointment_notifications'],
            ':allow_message_notifications' => $clean['allow_message_notifications'],
            ':allow_document_notifications' => $clean['allow_document_notifications'],
            ':hide_contact_from_non_staff' => $clean['hide_contact_from_non_staff'],
            ':user_id' => $userId,
        ]);
    }

    public function updateLanguage(int $userId, string $language): bool
    {
        $this->createDefaultForUser($userId);

        $language = $this->allowedValue($language, self::ALLOWED_LANGUAGE, 'english');

        $stmt = $this->db->prepare("
            UPDATE user_settings
            SET language = :language,
                updated_at = NOW()
            WHERE user_id = :user_id
            LIMIT 1
        ");

        return $stmt->execute([
            ':language' => $language,
            ':user_id' => $userId,
        ]);
    }

    public function updateAppearance(int $userId, array $data): bool
    {
        $this->createDefaultForUser($userId);

        $clean = $this->sanitizeAppearance($data);

        $stmt = $this->db->prepare("
            UPDATE user_settings
            SET theme_mode = :theme_mode,
                accent_color = :accent_color,
                font_size = :font_size,
                layout_density = :layout_density,
                border_radius = :border_radius,
                sidebar_mode = :sidebar_mode,
                updated_at = NOW()
            WHERE user_id = :user_id
            LIMIT 1
        ");

        return $stmt->execute([
            ':theme_mode' => $clean['theme_mode'],
            ':accent_color' => $clean['accent_color'],
            ':font_size' => $clean['font_size'],
            ':layout_density' => $clean['layout_density'],
            ':border_radius' => $clean['border_radius'],
            ':sidebar_mode' => $clean['sidebar_mode'],
            ':user_id' => $userId,
        ]);
    }

    public function resetAppearance(int $userId): bool
    {
        $this->createDefaultForUser($userId);

        $stmt = $this->db->prepare("
            UPDATE user_settings
            SET theme_mode = 'light',
                accent_color = 'teal',
                font_size = 'normal',
                layout_density = 'comfortable',
                border_radius = 'small',
                sidebar_mode = 'expanded',
                updated_at = NOW()
            WHERE user_id = :user_id
            LIMIT 1
        ");

        return $stmt->execute([
            ':user_id' => $userId,
        ]);
    }

    public function sanitizePrivacy(array $input): array
    {
        $clean = [];

        foreach (self::PRIVACY_FIELDS as $field) {
            $clean[$field] = $this->toBool($input[$field] ?? 0);
        }

        return $clean;
    }

    public function sanitizeAppearance(array $input): array
    {
        $clean = [];

        foreach (self::ALLOWED_APPEARANCE as $field => $allowed) {
            $clean[$field] = $this->allowedValue(
                $input[$field] ?? $this->defaultAppearanceValue($field),
                $allowed,
                $this->defaultAppearanceValue($field)
            );
        }

        return $clean;
    }

    public function bodyClasses(array $settings): string
    {
        $settings = $this->normalizeSettings($settings);

        $classes = [
            'theme-' . $settings['theme_mode'],
            'accent-' . $settings['accent_color'],
            'font-' . $settings['font_size'],
            'density-' . $settings['layout_density'],
            'radius-' . $settings['border_radius'],
            'sidebar-' . $settings['sidebar_mode'],
        ];

        return implode(' ', array_map(static function (string $class): string {
            return preg_replace('/[^a-z0-9_-]/i', '', $class);
        }, $classes));
    }

    public function findUserById(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM users
            WHERE user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function emailExistsForOtherUser(string $email, int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT user_id
            FROM users
            WHERE email = :email
              AND user_id <> :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':email' => $email,
            ':user_id' => $userId,
        ]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPasswordHashForUser(int $userId): string
    {
        $columns = $this->getTableColumns('users');
        $passwordColumn = $this->detectPasswordColumn($columns);

        if ($passwordColumn === '') {
            return '';
        }

        $stmt = $this->db->prepare("
            SELECT {$passwordColumn}
            FROM users
            WHERE user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        return (string) $stmt->fetchColumn();
    }

    private function normalizeSettings(array $settings): array
    {
        $defaults = $this->defaultSettings((int) ($settings['user_id'] ?? 0));
        $settings = array_merge($defaults, $settings);

        foreach (self::PRIVACY_FIELDS as $field) {
            $settings[$field] = (int) $settings[$field] === 1 ? 1 : 0;
        }

        $settings['language'] = $this->allowedValue(
            $settings['language'] ?? 'english',
            self::ALLOWED_LANGUAGE,
            'english'
        );

        $settings['theme_mode'] = $this->allowedValue($settings['theme_mode'] ?? 'light', self::ALLOWED_APPEARANCE['theme_mode'], 'light');
        $settings['accent_color'] = $this->allowedValue($settings['accent_color'] ?? 'teal', self::ALLOWED_APPEARANCE['accent_color'], 'teal');
        $settings['font_size'] = $this->allowedValue($settings['font_size'] ?? 'normal', self::ALLOWED_APPEARANCE['font_size'], 'normal');
        $settings['layout_density'] = $this->allowedValue($settings['layout_density'] ?? 'comfortable', self::ALLOWED_APPEARANCE['layout_density'], 'comfortable');
        $settings['border_radius'] = $this->allowedValue($settings['border_radius'] ?? 'small', self::ALLOWED_APPEARANCE['border_radius'], 'small');
        $settings['sidebar_mode'] = $this->allowedValue($settings['sidebar_mode'] ?? 'expanded', self::ALLOWED_APPEARANCE['sidebar_mode'], 'expanded');

        return $settings;
    }

    private function defaultSettings(int $userId): array
    {
        return [
            'setting_id' => 0,
            'user_id' => $userId,

            'show_profile_name' => 1,
            'allow_appointment_notifications' => 1,
            'allow_message_notifications' => 1,
            'allow_document_notifications' => 1,
            'hide_contact_from_non_staff' => 0,

            'language' => 'english',

            'theme_mode' => 'light',
            'accent_color' => 'teal',
            'font_size' => 'normal',
            'layout_density' => 'comfortable',
            'border_radius' => 'small',
            'sidebar_mode' => 'expanded',

            'created_at' => null,
            'updated_at' => null,
        ];
    }

    private function defaultAppearanceValue(string $field): string
    {
        return match ($field) {
            'theme_mode' => 'light',
            'accent_color' => 'teal',
            'font_size' => 'normal',
            'layout_density' => 'comfortable',
            'border_radius' => 'small',
            'sidebar_mode' => 'expanded',
            default => '',
        };
    }

    private function toBool(mixed $value): int
    {
        return in_array((string) $value, ['1', 'on', 'true', 'yes'], true) ? 1 : 0;
    }

    private function allowedValue(mixed $value, array $allowed, string $default): string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function getTableColumns(string $table): array
    {
        if ($table !== 'users') {
            throw new RuntimeException('Invalid table name.');
        }

        $stmt = $this->db->query("SHOW COLUMNS FROM users");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static function (array $row): string {
            return (string) $row['Field'];
        }, $rows);
    }

    private function detectPasswordColumn(array $columns): string
    {
        if (in_array('password_hash', $columns, true)) {
            return 'password_hash';
        }

        if (in_array('password', $columns, true)) {
            return 'password';
        }

        return '';
    }

    private static function resolveConnection(): PDO
    {
        if (method_exists(Database::class, 'getConnection')) {
            return Database::getConnection();
        }

        if (method_exists(Database::class, 'connection')) {
            return Database::connection();
        }

        if (method_exists(Database::class, 'connect')) {
            return Database::connect();
        }

        if (method_exists(Database::class, 'getInstance')) {
            $instance = Database::getInstance();

            if ($instance instanceof PDO) {
                return $instance;
            }

            if (method_exists($instance, 'getConnection')) {
                return $instance->getConnection();
            }
        }

        throw new RuntimeException('No valid database connection method found.');
    }
}