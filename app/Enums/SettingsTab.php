<?php

namespace App\Enums;

enum SettingsTab: string
{
    case Branding = 'branding';
    case Chat = 'chat';
    case Recap = 'recap';
    case Email = 'email';
}
