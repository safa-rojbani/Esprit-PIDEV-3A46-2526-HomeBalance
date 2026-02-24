<?php
namespace App\Enum;

enum EtatDocument: string {
    case ACTIF = 'actif';
    case HIDDEN = 'hidden';
    case CORBEILLE = 'corbeille';
    case DELETED = 'deleted';

}
