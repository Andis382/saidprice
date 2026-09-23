<?php

namespace App\Quotes\FollowUps;

use App\Models\Nudge;
use App\Models\OutboundMessage;
use App\Models\QuoteEvent;
use App\Models\User;
use App\Quotes\EventType;
use App\Quotes\NudgeStatus;
use App\Quotes\QuoteMessages;
use App\Quotes\QuoteStateException;
use Illuminate\Support\Facades\DB;

/** What the tradesperson does with a drafted follow-up: send it (as is or edited), or dismiss it. */
class Nudges
{
    public function __construct(private readonly QuoteMessages $messages) {}

    public function edit(Nudge $nudge, string $body): Nudge
    {
        $this->assertPending($nudge);
        $nudge->update(['body' => trim($body)]);

        return $nudge;
    }

    public function send(Nudge $nudge, User $by, ?string $body = null): OutboundMessage
    {
        $this->assertPending($nudge);
        $quote = $nudge->quote;
        if (! $quote->status->isAnswerable()) {
            throw new QuoteStateException(__('quotes.not_answerable'));
        }

        return DB::transaction(function () use ($nudge, $quote, $by, $body) {
            if ($body !== null && trim($body) !== '') {
                $nudge->body = trim($body);
            }
            $message = $this->messages->nudge($quote, $nudge->body);
            $nudge->fill(['status' => NudgeStatus::Sent, 'sent_at' => now(), 'acted_by' => $by->id, 'outbound_message_id' => $message->id])->save();
            $quote->fill(['nudge_count' => $quote->nudge_count + 1, 'last_nudged_at' => now()])->save();
            $quote->record(EventType::NudgeSent, QuoteEvent::USER, $by, QuoteEvent::VIA_WHATSAPP, $nudge->body, ['sequence' => $nudge->sequence, 'messageId' => $message->id]);

            return $message;
        });
    }

    public function dismiss(Nudge $nudge, User $by): void
    {
        $this->assertPending($nudge);
        DB::transaction(function () use ($nudge, $by) {
            $nudge->fill(['status' => NudgeStatus::Dismissed, 'dismissed_at' => now(), 'acted_by' => $by->id])->save();
            $nudge->quote->record(EventType::NudgeDismissed, QuoteEvent::USER, $by, QuoteEvent::VIA_APP, meta: ['sequence' => $nudge->sequence]);
        });
    }

    private function assertPending(Nudge $nudge): void
    {
        if ($nudge->status !== NudgeStatus::Drafted) {
            throw new QuoteStateException(__('quotes.nudge_not_pending'));
        }
    }
}
