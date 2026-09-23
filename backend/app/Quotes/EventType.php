<?php

namespace App\Quotes;

/** Entries on a quote's timeline. */
enum EventType: string
{
    case Created = 'CREATED';
    case Edited = 'EDITED';
    case Sent = 'SENT';
    case Resent = 'RESENT';
    case Viewed = 'VIEWED';
    case NudgeDrafted = 'NUDGE_DRAFTED';
    case NudgeSent = 'NUDGE_SENT';
    case NudgeDismissed = 'NUDGE_DISMISSED';
    case Accepted = 'ACCEPTED';
    case Declined = 'DECLINED';
    case Question = 'QUESTION';
    case Silent = 'SILENT';
    case Expired = 'EXPIRED';
    case Won = 'WON';
    case Lost = 'LOST';
}
