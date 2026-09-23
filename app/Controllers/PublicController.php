<?php

namespace App\Controllers;

use App\Core\View;

class PublicController
{
    public function privacyNotice(): void
    {
        View::render('public.privacy_notice');
    }
}