<?php
namespace App\Enum;
enum TaskAssignmentStatus: string {
    case ASSIGNED = 'assigned';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
