<?php

namespace App\Enum;

enum AiEvaluationDecision: string
{
    case PASS = 'PASS';
    case FAIL = 'FAIL';
    case REVIEW = 'REVIEW';
}

