<?php

namespace App\Enum;

enum DocumentActivityEvent: string
{
    case DOCUMENT_UPLOADED = 'document_uploaded';
    case DOCUMENT_VIEWED = 'document_viewed';
    case DOCUMENT_DOWNLOADED = 'document_downloaded';
    case DOCUMENT_SHARED = 'document_shared';
    case DOCUMENT_SHARE_BLOCKED = 'document_share_blocked';
}

