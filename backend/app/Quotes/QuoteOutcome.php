<?php

namespace App\Quotes;

/** What the tradesperson says happened in the end, independent of the customer's click. */
enum QuoteOutcome: string
{
    case Won = 'WON';
    case Lost = 'LOST';
}
