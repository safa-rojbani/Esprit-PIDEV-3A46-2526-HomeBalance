<?php
namespace App\Enum;

enum EtatGallery: string {
    case ACTIF = 'actif';
    case HIDDEN = 'hidden';
    case DELETED = 'deleted';
}
