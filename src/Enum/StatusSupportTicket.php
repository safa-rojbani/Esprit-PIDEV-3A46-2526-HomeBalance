<?php
namespace App\Enum;

enum StatusSupportTicket: string {
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case CLOSED = 'closed';
}
