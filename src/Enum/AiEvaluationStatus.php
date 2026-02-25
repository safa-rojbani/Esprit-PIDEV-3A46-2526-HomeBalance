<?php

namespace App\Enum;

enum AiEvaluationStatus: string
{
    case PENDING = 'PENDING';
    case SUCCESS = 'SUCCESS';
    case FAILED = 'FAILED';
}

