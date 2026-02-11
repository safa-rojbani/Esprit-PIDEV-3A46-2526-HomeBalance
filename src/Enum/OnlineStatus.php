<?php
namespace App\Enum;

enum OnlineStatus: string
{
    case ONLINE = 'online';
    case AWAY = 'away';
    case DO_NOT_DISTURB = 'do_not_disturb';
    case OFFLINE = 'offline';
}
