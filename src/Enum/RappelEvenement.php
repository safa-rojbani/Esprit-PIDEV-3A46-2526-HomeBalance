<?php
namespace App\Enum;

enum RappelEvenement: string {
    case POPUP = 'popup';
    case EMAIL = 'email';
    case SMS = 'sms';
}
