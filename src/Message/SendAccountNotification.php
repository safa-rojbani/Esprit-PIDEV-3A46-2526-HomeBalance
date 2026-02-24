<?php

namespace App\Message;

final class SendAccountNotification
{
    public function __construct(
        public readonly int $notificationId,
    ) {
    }
}
