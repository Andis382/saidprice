<?php

use Illuminate\Support\Facades\Schedule;

// Drafts follow-ups for unanswered quotes (never sends them) and closes silent or expired ones.
Schedule::command('quotes:follow-up')->hourly()->withoutOverlapping();
