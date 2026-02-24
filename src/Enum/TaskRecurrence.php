<?php
namespace App\Enum;
enum TaskRecurrence: string {
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
}
