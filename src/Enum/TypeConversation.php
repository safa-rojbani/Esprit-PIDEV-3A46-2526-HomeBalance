<?php
namespace App\Enum;

enum TypeConversation: string {
    case CHAT = 'chat';
    case SUPPORT = 'support';
    case PRIVATE = 'private';
    case GROUP = 'group';
}
