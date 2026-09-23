<?php

return [
    'otp' => [
        'expires_minutes' => 10,
        'max_attempts' => 5,
        'resend_cooldown_seconds' => 60,
    ],

    'sms' => [
        'enabled' => true,
        'provider' => '',
        'api_key' => '',
        'sender_name' => '',
    ],

    'email' => [
        'enabled' => true,
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'encryption' => 'tls',

        'username' => 'dr.brendalynwansicalacat@gmail.com',
        'password' => 'smzxvwxxtidkzlmv',

        'from_email' => 'dr.brendalynwansicalacat@gmail.com',
        'from_name' => 'Dr. Brendalyn Wansi Calacat Dental Clinic',
    ],
];