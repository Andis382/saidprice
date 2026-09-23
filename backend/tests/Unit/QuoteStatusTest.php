<?php

namespace Tests\Unit;

use App\Quotes\QuoteStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QuoteStatusTest extends TestCase
{
    /** Every pair the product allows; everything else must be refused. */
    private const ALLOWED = [
        'DRAFT' => ['SENT'],
        'SENT' => ['SENT', 'VIEWED', 'ACCEPTED', 'DECLINED', 'SILENT', 'EXPIRED'],
        'VIEWED' => ['SENT', 'ACCEPTED', 'DECLINED', 'SILENT', 'EXPIRED'],
        'SILENT' => ['SENT', 'ACCEPTED', 'DECLINED', 'EXPIRED'],
        'EXPIRED' => ['SENT', 'ACCEPTED'],
        'DECLINED' => ['ACCEPTED'],
        'ACCEPTED' => [],
    ];

    public static function pairs(): iterable
    {
        foreach (QuoteStatus::cases() as $from) {
            foreach (QuoteStatus::cases() as $to) {
                yield "{$from->value} -> {$to->value}" => [$from, $to];
            }
        }
    }

    #[DataProvider('pairs')]
    public function test_transition_table(QuoteStatus $from, QuoteStatus $to): void
    {
        $this->assertSame(in_array($to->value, self::ALLOWED[$from->value], true), $from->canBecome($to));
    }

    public function test_a_draft_cannot_be_answered_and_an_accepted_quote_is_final(): void
    {
        $this->assertFalse(QuoteStatus::Draft->isAnswerable());
        $this->assertFalse(QuoteStatus::Draft->canBecome(QuoteStatus::Accepted));
        $this->assertSame([], QuoteStatus::Accepted->allowedNext());
        $this->assertFalse(QuoteStatus::Accepted->isEditable());
        $this->assertFalse(QuoteStatus::Declined->isEditable());
    }

    public function test_which_states_wait_for_the_customer(): void
    {
        $this->assertTrue(QuoteStatus::Sent->isAwaitingAnswer());
        $this->assertTrue(QuoteStatus::Viewed->isAwaitingAnswer());
        $this->assertFalse(QuoteStatus::Silent->isAwaitingAnswer());
        $this->assertTrue(QuoteStatus::Silent->isAnswerable());
        $this->assertFalse(QuoteStatus::Expired->isAnswerable());
    }
}
