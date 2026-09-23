<?php

namespace App\Quotes;

use App\Models\Nudge;
use App\Models\OutboundMessage;
use App\Models\Quote;
use App\Models\QuoteEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Everything that moves a quote from one status to another. Each step checks the
 * transition table, stamps the times, writes the timeline and sends what it should.
 */
class QuoteWorkflow
{
    public function __construct(private readonly QuoteMessages $messages) {}

    public function send(Quote $quote, User $by): OutboundMessage
    {
        return $this->deliver($quote, $by, EventType::Sent);
    }

    /** Sends the quote again; an expired or silent quote gets a fresh validity and follow-up clock. */
    public function resend(Quote $quote, User $by): OutboundMessage
    {
        return $this->deliver($quote, $by, EventType::Resent);
    }

    /** First open of the link by someone other than the business. */
    public function viewed(Quote $quote): void
    {
        if ($quote->viewed_at !== null || ! $quote->status->isSent()) {
            return;
        }
        $quote->viewed_at = now();
        if ($quote->status === QuoteStatus::Sent) {
            $quote->status = QuoteStatus::Viewed;
        }
        $quote->save();
        $quote->record(EventType::Viewed, QuoteEvent::CUSTOMER, channel: QuoteEvent::VIA_LINK);
    }

    public function accept(Quote $quote, string $channel, ?string $note = null): void
    {
        $this->assertAnswerable($quote);
        DB::transaction(function () use ($quote, $channel, $note) {
            $this->move($quote, QuoteStatus::Accepted);
            $quote->fill(['responded_at' => now(), 'response_note' => $note ?: null]);
            if ($quote->outcome === QuoteOutcome::Lost) {
                // Written off, then the customer said yes after all.
                $quote->fill(['outcome' => null, 'lost_reason' => null, 'outcome_at' => null]);
            }
            $quote->save();
            $this->dismissDrafts($quote);
            $quote->record(EventType::Accepted, QuoteEvent::CUSTOMER, channel: $channel, body: $note ?: null);
        });
        $this->messages->accepted($quote);
        $this->messages->thanks($quote);
    }

    public function decline(Quote $quote, string $channel, ?string $reason = null): void
    {
        $this->assertAnswerable($quote);
        DB::transaction(function () use ($quote, $channel, $reason) {
            $this->move($quote, QuoteStatus::Declined);
            $quote->fill(['responded_at' => now(), 'response_note' => $reason ?: null]);
            $quote->save();
            $this->dismissDrafts($quote);
            $quote->record(EventType::Declined, QuoteEvent::CUSTOMER, channel: $channel, body: $reason ?: null);
        });
        $this->messages->declined($quote, $reason);
    }

    /** A question does not change the status; it tells the business and pauses the follow-ups. */
    public function question(Quote $quote, string $channel, string $text): void
    {
        if (! $quote->status->isSent()) {
            throw new QuoteStateException(__('quotes.not_answerable'));
        }
        $quote->record(EventType::Question, QuoteEvent::CUSTOMER, channel: $channel, body: $text);
        $this->messages->question($quote, $text);
    }

    public function silent(Quote $quote): void
    {
        $this->move($quote, QuoteStatus::Silent);
        $quote->save();
        $quote->record(EventType::Silent, QuoteEvent::SYSTEM);
    }

    public function expire(Quote $quote): void
    {
        DB::transaction(function () use ($quote) {
            $this->move($quote, QuoteStatus::Expired);
            $quote->save();
            $this->dismissDrafts($quote);
            $quote->record(EventType::Expired, QuoteEvent::SYSTEM);
        });
    }

    /**
     * What really happened. "Won" on an open quote means the customer said yes by phone or
     * in person; "lost" on an open one closes it so nobody chases it any more.
     */
    public function outcome(Quote $quote, QuoteOutcome $outcome, User $by, ?string $reason = null): void
    {
        if (! $quote->status->isSent()) {
            throw QuoteStateException::cannotBecome($quote->status, QuoteStatus::Accepted);
        }
        DB::transaction(function () use ($quote, $outcome, $by, $reason) {
            $statusBefore = $quote->status;
            if ($outcome === QuoteOutcome::Won && $quote->status !== QuoteStatus::Accepted) {
                $this->move($quote, QuoteStatus::Accepted);
                $quote->responded_at ??= now();
            }
            if ($outcome === QuoteOutcome::Lost && $quote->status->isAwaitingAnswer()) {
                $this->move($quote, QuoteStatus::Declined);
                $quote->responded_at ??= now();
            }
            $quote->fill([
                'outcome' => $outcome,
                'outcome_at' => now(),
                'lost_reason' => $outcome === QuoteOutcome::Lost ? ($reason ?: null) : null,
            ]);
            $quote->save();
            $this->dismissDrafts($quote);
            $quote->record(
                $outcome === QuoteOutcome::Won ? EventType::Won : EventType::Lost,
                QuoteEvent::USER,
                $by,
                QuoteEvent::VIA_APP,
                $outcome === QuoteOutcome::Lost ? ($reason ?: null) : null,
                ['statusBefore' => $statusBefore->value],
            );
        });
    }

    private function deliver(Quote $quote, User $by, EventType $event): OutboundMessage
    {
        $this->assertReadyToSend($quote);
        $message = DB::transaction(function () use ($quote, $by, $event) {
            $this->move($quote, QuoteStatus::Sent);
            $quote->sent_at = now();
            $quote->valid_until = now()->tz($quote->organization->timezone)->addDays($quote->validity_days)->toDateString();
            $quote->save();
            $this->dismissDrafts($quote, $by);
            $message = $this->messages->quote($quote);
            $quote->record($event, QuoteEvent::USER, $by, QuoteEvent::VIA_WHATSAPP, meta: ['messageId' => $message->id]);

            return $message;
        });

        return $message;
    }

    private function assertReadyToSend(Quote $quote): void
    {
        $errors = [];
        if (! $quote->customer) {
            $errors['customer.name'] = [__('quotes.needs_customer')];
        } elseif (! $quote->customer->phone) {
            $errors['customer.phone'] = [__('quotes.needs_customer_phone')];
        }
        if ($quote->items()->count() === 0 || $quote->total_cents <= 0) {
            $errors['items'] = [__('quotes.needs_items')];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function assertAnswerable(Quote $quote): void
    {
        if (! $quote->status->isAnswerable()) {
            throw new QuoteStateException(__('quotes.not_answerable'));
        }
    }

    private function move(Quote $quote, QuoteStatus $to): void
    {
        if (! $quote->status->canBecome($to)) {
            throw QuoteStateException::cannotBecome($quote->status, $to);
        }
        $quote->status = $to;
    }

    /** Drafted follow-ups make no sense once the quote was answered or sent again. */
    private function dismissDrafts(Quote $quote, ?User $by = null): void
    {
        Nudge::where('quote_id', $quote->id)
            ->where('status', NudgeStatus::Drafted)
            ->update(['status' => NudgeStatus::Dismissed, 'dismissed_at' => now(), 'acted_by' => $by?->id]);
    }
}
