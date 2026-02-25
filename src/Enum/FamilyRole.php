<?php
namespace App\Enum;

enum FamilyRole: string
{
    case PARENT = 'PARENT';
    case CHILD = 'CHILD';
    case SOLO = 'SOLO';
}
