<?php

namespace App\Enums;

enum SettingsTab: string
{
    case Branding = 'branding';
    case Models = 'models';
    case Chat = 'chat';
    case Recap = 'recap';
    case Email = 'email';
}
