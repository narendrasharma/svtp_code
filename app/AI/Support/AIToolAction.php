<?php

namespace App\AI\Support;

enum AIToolAction: string
{
    case Read = 'read';
    case Draft = 'draft';
    case Write = 'write';
}
