<?php

namespace App\Enum;

enum TaskAssignmentStatus: string
{
    case ASSIGNED = 'assigned';
    case ACCEPTED = 'accepted';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
