<?php

namespace App\Quotes;

enum NudgeStatus: string
{
    case Drafted = 'DRAFTED';
    case Sent = 'SENT';
    case Dismissed = 'DISMISSED';
}
