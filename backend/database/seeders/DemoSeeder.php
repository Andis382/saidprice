<?php

namespace Database\Seeders;

use App\Messaging\InboundDispatcher;
use App\Models\BusinessSettings;
use App\Models\Customer;
use App\Models\InboundMessage;
use App\Models\Nudge;
use App\Models\Organization;
use App\Models\Quote;
use App\Models\QuoteEvent;
use App\Models\User;
use App\Quotes\EventType;
use App\Quotes\FollowUps\FollowUps;
use App\Quotes\FollowUps\FollowUpSchedule;
use App\Quotes\FollowUps\Nudges;
use App\Quotes\NudgeStatus;
use App\Quotes\QuoteEditor;
use App\Quotes\QuoteNumbers;
use App\Quotes\QuoteOutcome;
use App\Quotes\QuoteStatus;
use App\Quotes\QuoteWorkflow;
use App\Support\Tenant;
use App\Support\Tokens;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Twelve weeks in the life of Elektro-Hidro Beqiri, a plumber-electrician in Tirana. Every
 * quote is played through the real workflow with the clock moved to when it happened:
 * sent, opened, followed up, answered on the link or on WhatsApp, marked won or lost.
 * The timeline, outbox and follow-up drafts are therefore exactly what the app produces.
 */
class DemoSeeder extends Seeder
{
    private const BUSINESS_PHONE = '355692124480';

    private Organization $org;

    private User $owner;

    private User $assistant;

    private CarbonImmutable $now;

    /** @var list<Customer> */
    private array $customers = [];

    public function __construct(
        private readonly QuoteNumbers $numbers,
        private readonly QuoteEditor $editor,
        private readonly QuoteWorkflow $workflow,
        private readonly FollowUps $followUps,
        private readonly Nudges $nudges,
        private readonly InboundDispatcher $inbound,
    ) {}

    public function run(): void
    {
        if (User::where('email', config('product.demo_email'))->exists()) {
            return;
        }
        mt_srand(4480);
        $this->now = CarbonImmutable::now();
        $this->org = Organization::create([
            'name' => 'Elektro-Hidro Beqiri', 'phone' => self::BUSINESS_PHONE, 'country' => 'AL',
            'locale' => 'sq', 'timezone' => 'Europe/Tirane', 'currency' => 'EUR',
        ]);
        $this->owner = User::create([
            'organization_id' => $this->org->id, 'name' => 'Gentian Beqiri', 'email' => config('product.demo_email'),
            'password' => config('product.demo_password'), 'role' => User::OWNER, 'locale' => 'sq', 'last_login_at' => $this->now->subHours(3),
        ]);
        $this->assistant = User::create([
            'organization_id' => $this->org->id, 'name' => 'Elira Beqiri', 'email' => 'assistant@saidprice.test',
            'password' => config('product.demo_password'), 'role' => User::ASSISTANT, 'locale' => 'sq', 'last_login_at' => $this->now->subDay(),
        ]);

        try {
            Tenant::run($this->org->id, function () {
                BusinessSettings::for($this->org)->update(['trade' => 'Hidraulik & elektricist · Tiranë']);
                $this->customers = $this->customers();
                foreach ($this->plan() as [$at, $story]) {
                    $story === 'regular' ? $this->regular($at) : $this->{$story}($at);
                }
            });
        } finally {
            Carbon::setTestNow();
        }

        $this->command?->info('Demo data ready. Sign in with '.config('product.demo_email').' / '.config('product.demo_password').' (assistant@saidprice.test for the assistant).');
    }

    /** @return list<Customer> */
    private function customers(): array
    {
        $names = [];
        foreach (DemoCatalog::SURNAMES as $i => $surname) {
            $names[] = [DemoCatalog::WOMEN[$i % count(DemoCatalog::WOMEN)].' '.$surname, 'sq'];
            $names[] = [DemoCatalog::MEN[($i * 7) % count(DemoCatalog::MEN)].' '.DemoCatalog::SURNAMES[($i + 11) % count(DemoCatalog::SURNAMES)], 'sq'];
        }
        foreach (DemoCatalog::TITLED as $name) {
            $names[] = [$name, 'sq'];
        }
        foreach (DemoCatalog::ENGLISH as $name) {
            $names[] = [$name, 'en'];
        }

        return array_map(fn (array $n) => $this->customer($n[0], $n[1]), $names);
    }

    private function customer(string $name, string $locale, ?string $address = null): Customer
    {
        do {
            $phone = '3556'.[7, 8, 9][mt_rand(0, 2)].mt_rand(1000000, 9999999);
        } while (Customer::where('phone', $phone)->exists());
        $address ??= DemoCatalog::STREETS[mt_rand(0, count(DemoCatalog::STREETS) - 1)].' '.mt_rand(3, 180)
            .(mt_rand(0, 3) === 0 ? ', ap. '.mt_rand(2, 40) : '');

        return Customer::create(['name' => $name, 'phone' => $phone, 'address' => $address, 'locale' => $locale]);
    }

    /**
     * When each quote was made: one or two most working days, now and then a Saturday,
     * over twelve weeks up to today, plus the few special ones the demo shows off.
     *
     * @return list<array{0: CarbonImmutable, 1: string}>
     */
    private function plan(): array
    {
        $tz = $this->org->timezone;
        $day = $this->now->tz($tz)->startOfWeek()->subWeeks(11);
        $plan = [];
        while ($day->lessThanOrEqualTo($this->now)) {
            $perDay = match ($day->dayOfWeekIso) {
                6 => mt_rand(0, 1),
                7 => 0,
                default => [0, 1, 1, 1, 2, 2, 2, 2, 3][mt_rand(0, 8)],
            };
            for ($i = 0; $i < $perDay; $i++) {
                $at = $day->setTime(mt_rand(8, 17), mt_rand(0, 59))->utc();
                if ($at->lessThan($this->now->subHours(30))) {
                    $plan[] = [$at, 'regular'];
                }
            }
            $day = $day->addDay();
        }
        $plan[] = [$this->local('-3 days 11:20'), 'quiet'];
        $plan[] = [$this->local('yesterday 09:10'), 'hoxha'];
        $plan[] = [$this->local('yesterday 14:30'), 'quickYes'];
        $plan[] = [$this->local('yesterday 17:45'), 'typedDraft'];
        $plan[] = [$this->recent('07:20', 'yesterday 18:50'), 'whatsAppVoiceNote'];
        $plan[] = [$this->recent('08:05', 'yesterday 19:05'), 'freshVoiceDraft'];
        usort($plan, fn (array $a, array $b) => $a[0] <=> $b[0]);

        return $plan;
    }

    /**
     * A clock time in Tirana relative to the real now ("yesterday 09:10"), as UTC. Not
     * CarbonImmutable::parse(): that reads the moved test clock of the story being played.
     */
    private function local(string $when): CarbonImmutable
    {
        return $this->now->setTimezone($this->org->timezone)->modify($when)->utc();
    }

    /** This morning if it is already past, else the evening before: seeding at 4 AM still looks like a working day. */
    private function recent(string $todayAt, string $otherwise): CarbonImmutable
    {
        $today = $this->local('today '.$todayAt);

        return $today->addMinutes(15)->lessThan($this->now) ? $today : $this->local($otherwise);
    }

    /** A quote given at a door, then whatever the customer did with it. */
    private function regular(CarbonImmutable $createdAt): void
    {
        $customer = $this->customers[mt_rand(0, count($this->customers) - 1)];
        $by = mt_rand(1, 5) === 1 ? $this->assistant : $this->owner;
        $quote = $this->draft($customer, DemoCatalog::JOBS[mt_rand(0, count(DemoCatalog::JOBS) - 1)], $createdAt, $by, voice: mt_rand(1, 100) <= 38);

        $sentAt = $createdAt->addMinutes(mt_rand(2, 15));
        $this->at($sentAt, fn () => $this->workflow->send($quote, $by));
        $viewedAt = mt_rand(1, 100) <= 88 ? $sentAt->addMinutes(mt_rand(3, 360)) : null;

        $roll = mt_rand(1, 100);
        $answer = match (true) {
            $roll <= 31 => ['accept', $sentAt->addMinutes(mt_rand(20, 30 * 60))],
            $roll <= 43 => ['accept', $sentAt->addHours(mt_rand(50, 100))],
            $roll <= 48 => ['accept', $sentAt->addHours(mt_rand(122, 150))],
            $roll <= 61 => ['decline', $sentAt->addHours(mt_rand(2, 60))],
            $roll <= 66 => ['decline', $sentAt->addHours(mt_rand(122, 140))],
            default => null,
        };
        $question = mt_rand(1, 100) <= 6 ? $sentAt->addHours(mt_rand(3, 20)) : null;

        $this->live($quote, $viewedAt, $answer, $question);
        $this->settle($quote);
    }

    /**
     * Moves the clock through the quote's life, doing whatever falls due next: the customer
     * opens it, asks, answers; the follow-up job drafts; the tradesperson sends or dismisses.
     *
     * @param  array{0: string, 1: CarbonImmutable}|null  $answer
     */
    private function live(Quote $quote, ?CarbonImmutable $viewedAt, ?array $answer, ?CarbonImmutable $question): void
    {
        $expiresAt = CarbonImmutable::parse($quote->valid_until->toDateString().' 23:59:59', $this->org->timezone)->utc()->addSecond();
        $t = CarbonImmutable::instance($quote->sent_at);
        $action = null;
        for ($guard = 0; $guard < 40; $guard++) {
            $quote = $this->reload($quote);
            $plan = $this->followUps->plan($quote, $this->org, $t);
            $due = $plan['action'] !== FollowUpSchedule::WAIT ? $t : $plan['at'];
            $next = collect([
                $viewedAt && ! $quote->viewed_at ? $viewedAt : null,
                $question,
                $answer && $quote->status->isAnswerable() ? $answer[1] : null,
                $action['at'] ?? null,
                $due,
                $quote->status->isAnswerable() ? $expiresAt : null,
            ])->filter()->min();
            if (! $next || $next->greaterThan($this->now)) {
                return;
            }
            $t = $next;
            Carbon::setTestNow($t);

            if ($viewedAt && $viewedAt->lessThanOrEqualTo($t) && ! $quote->viewed_at) {
                $this->workflow->viewed($quote);
            }
            if ($question && $question->lessThanOrEqualTo($t)) {
                $this->whatsApp($quote, $quote->customer->locale === 'en' ? 'Could you come a bit earlier in the day?' : 'A përfshihet edhe heqja e së vjetrës?');
                $question = null;
            }
            if ($action && $action['at']->lessThanOrEqualTo($t)) {
                $nudge = Nudge::find($action['nudge']);
                if ($nudge->status === NudgeStatus::Drafted) {
                    $action['send'] ? $this->nudges->send($nudge, $action['by']) : $this->nudges->dismiss($nudge, $action['by']);
                }
                $action = null;
            }
            if ($answer && $answer[1]->lessThanOrEqualTo($t) && $quote->fresh()->status->isAnswerable()) {
                $this->answer($this->reload($quote), $answer[0]);
                $answer = null;

                continue;
            }
            $quote = $this->reload($quote);
            switch ($this->followUps->plan($quote, $this->org, $t)['action']) {
                case FollowUpSchedule::FIRST_NUDGE:
                    $action = $this->nudge($quote, 1, $t);
                    break;
                case FollowUpSchedule::SECOND_NUDGE:
                    $action = $this->nudge($quote, 2, $t);
                    break;
                case FollowUpSchedule::SILENT:
                    $this->workflow->silent($quote);
                    break;
                case FollowUpSchedule::EXPIRE:
                    $this->workflow->expire($quote);
                    break;
            }
        }
    }

    /**
     * Drafts a follow-up; the tradesperson gets to it a few hours later, except for the
     * last three days' drafts, which wait in the Follow-ups inbox.
     */
    private function nudge(Quote $quote, int $sequence, CarbonImmutable $t): ?array
    {
        $this->followUps->draftNudge($quote, $sequence);
        $nudge = Nudge::where('quote_id', $quote->id)->latest('id')->first();
        $actAt = $t->addMinutes(mt_rand(60, 14 * 60));
        if ($t->greaterThan($this->now->subHours(72)) || $actAt->greaterThan($this->now)) {
            return null;
        }

        return ['at' => $actAt, 'nudge' => $nudge->id, 'send' => mt_rand(1, 100) <= 80, 'by' => mt_rand(1, 5) === 1 ? $this->assistant : $this->owner];
    }

    private function answer(Quote $quote, string $kind): void
    {
        $en = $quote->customer->locale === 'en';
        if (! $quote->viewed_at || mt_rand(1, 100) <= 35) {
            $words = $kind === 'accept' ? ($en ? ['Yes', 'yes', '1'] : ['Po', 'po', '1', 'Po!']) : ($en ? ['No', '2'] : ['Jo', '2', 'jo']);
            $this->whatsApp($quote, $words[mt_rand(0, count($words) - 1)]);

            return;
        }
        if ($kind === 'accept') {
            $notes = $en ? ['Great, see you then.', null, null] : ['Po, jam në shtëpi gjithë ditën.', 'Dakord, ju pres.', null, null, null];
            $this->workflow->accept($quote, QuoteEvent::VIA_LINK, $notes[mt_rand(0, count($notes) - 1)]);
        } else {
            $reasons = DemoCatalog::DECLINE_REASONS[$en ? 'en' : 'sq'];
            $this->workflow->decline($quote, QuoteEvent::VIA_LINK, $reasons[mt_rand(0, count($reasons) - 1)]);
        }
    }

    /** After the answer: accepted jobs get done and marked won; some silent ones are written off. */
    private function settle(Quote $quote): void
    {
        $quote = $this->reload($quote);
        if ($quote->status === QuoteStatus::Accepted) {
            $markAt = CarbonImmutable::instance($quote->responded_at)->addHours(mt_rand(20, 120));
            $roll = mt_rand(1, 100);
            if ($markAt->lessThan($this->now) && $roll <= 96) {
                $this->at($markAt, fn () => $this->workflow->outcome($quote, $roll <= 93 ? QuoteOutcome::Won : QuoteOutcome::Lost, $this->owner, $roll <= 93 ? null : DemoCatalog::LOST_REASONS[2]));
            }
        } elseif (in_array($quote->status, [QuoteStatus::Silent, QuoteStatus::Expired], true) && mt_rand(1, 100) <= 45) {
            $closedAt = CarbonImmutable::instance($quote->events->last()->created_at)->addHours(mt_rand(10, 90));
            if ($closedAt->lessThan($this->now)) {
                $this->at($closedAt, fn () => $this->workflow->outcome($quote, QuoteOutcome::Lost, $this->owner, DemoCatalog::LOST_REASONS[mt_rand(0, 1)]));
            }
        }
    }

    /** The quote from the pitch, opened, with a question waiting for an answer. */
    private function hoxha(CarbonImmutable $createdAt): void
    {
        $customer = $this->customer('Znj. Hoxha', 'sq', 'Rruga Myslym Shyri 24');
        $quote = $this->draft($customer, [
            'sq' => 'Ndërrim rubineti kuzhine', 'en' => 'Replace kitchen mixer tap',
            'lines' => [
                ['PARTS', 'Rubinet kuzhine', '', 1, null, [69, 69]],
                ['PARTS', 'Zorra fleksibël', '', 2, 'pcs', [8, 8]],
                ['LABOUR', 'Punëdore', '', 1, null, [60, 60]],
            ],
        ], $createdAt, $this->owner, voice: true, when: 'Të enjten në mëngjes');
        $sentAt = $createdAt->addMinutes(3);
        $this->at($sentAt, fn () => $this->workflow->send($quote, $this->owner));
        $this->at($sentAt->addMinutes(41), fn () => $this->workflow->viewed($quote));
        $this->at($this->recent('07:40', 'yesterday 20:10'), fn () => $this->whatsApp($quote, 'A mund të vini të premten në vend të së enjtes? Të enjten jam në punë.'));
    }

    /** Opened three days ago and not a word since: its first follow-up waits in the inbox. */
    private function quiet(CarbonImmutable $createdAt): void
    {
        $customer = $this->customer('Ilir Dervishi', 'sq', 'Rruga e Kavajës 118, ap. 6');
        $quote = $this->draft($customer, DemoCatalog::JOBS[2], $createdAt, $this->owner, voice: true, when: 'Të premten pasdite');
        $sentAt = $createdAt->addMinutes(6);
        $this->at($sentAt, fn () => $this->workflow->send($quote, $this->owner));
        $this->live($quote, $sentAt->addMinutes(35), null, null);
    }

    /** Opened within minutes and accepted on the link the same afternoon. */
    private function quickYes(CarbonImmutable $createdAt): void
    {
        $customer = $this->customer('Arta Kola', 'sq', 'Rruga Sami Frashëri 41, ap. 9');
        $quote = $this->draft($customer, DemoCatalog::JOBS[8], $createdAt, $this->owner, voice: true, when: 'Të premten në mëngjes');
        $sentAt = $createdAt->addMinutes(4);
        $this->at($sentAt, fn () => $this->workflow->send($quote, $this->owner));
        $this->at($sentAt->addMinutes(18), fn () => $this->workflow->viewed($quote));
        $this->at($sentAt->addMinutes(95), fn () => $this->workflow->accept($quote, QuoteEvent::VIA_LINK, 'Dakord, ju pres të premten.'));
    }

    private function typedDraft(CarbonImmutable $createdAt): void
    {
        $this->draft($this->customers[7], DemoCatalog::JOBS[10], $createdAt, $this->assistant, voice: false);
    }

    /** Forwarded from the van to the platform number: a draft waiting for its words. */
    private function whatsAppVoiceNote(CarbonImmutable $at): void
    {
        $this->at($at, fn () => $this->inbound->receive(new InboundMessage([
            'from_phone' => self::BUSINESS_PHONE,
            'kind' => 'audio',
            'media_id' => 'demo-voice-note',
            'media_type' => 'audio/ogg',
            'provider_message_id' => 'demo-'.Str::uuid(),
        ])));
    }

    private function freshVoiceDraft(CarbonImmutable $createdAt): void
    {
        $customer = $this->customer('Klodiana Muça', 'sq', 'Rruga Abdi Toptani 7, ap. 12');
        $this->draft($customer, DemoCatalog::JOBS[1], $createdAt, $this->owner, voice: true, when: 'Nesër në mëngjes');
    }

    /** A draft with priced lines, as it looks after the tradesperson checked the voice note. */
    private function draft(Customer $customer, array $job, CarbonImmutable $at, User $by, bool $voice, ?string $when = null): Quote
    {
        Carbon::setTestNow($at);
        $locale = $customer->locale;
        $when ??= DemoCatalog::WHEN[$locale][mt_rand(0, count(DemoCatalog::WHEN[$locale]) - 1)];
        $items = array_map(fn (array $line) => [
            'description' => $locale === 'en' && $line[2] !== '' ? $line[2] : $line[1],
            'qty' => $line[3],
            'unit' => $line[4] ? DemoCatalog::UNITS[$line[4]][$locale] : null,
            'unitPriceCents' => $this->price($line[5]),
            'kind' => $line[0],
        ], $job['lines']);

        $quote = new Quote([
            'customer_id' => $customer->id,
            'created_by' => $by->id,
            'number' => $this->numbers->next($this->org->id, $at),
            'title' => $job[$locale],
            'status' => QuoteStatus::Draft,
            'source' => $voice ? Quote::SOURCE_VOICE : Quote::SOURCE_TYPED,
            'proposed_for' => $when,
            'validity_days' => 14,
            'terms' => trans('quotes.default_terms', [], $locale),
            'public_token' => Tokens::urlToken(),
        ]);
        $quote->save();
        $this->editor->replaceItems($quote, $items);
        $quote->transcript = $voice ? $this->transcript($quote, $customer) : null;
        $quote->save();
        $quote->record(EventType::Created, QuoteEvent::USER, $by, QuoteEvent::VIA_APP, meta: array_filter([
            'source' => $quote->source,
            'engine' => $voice ? 'parser' : null,
        ]));

        return $quote;
    }

    /** How he would have said it at the door. */
    private function transcript(Quote $quote, Customer $customer): string
    {
        $en = $customer->locale === 'en';
        $amount = fn (int $cents) => $cents % 100 === 0 ? (string) intdiv($cents, 100) : number_format($cents / 100, 2, $en ? '.' : ',', '');
        $totals = ['PARTS' => 0, 'LABOUR' => 0];
        foreach ($quote->items as $item) {
            $totals[$item->kind->value] = ($totals[$item->kind->value] ?? 0) + $item->total_cents;
        }
        $money = $totals['PARTS'] > 0
            ? $amount($totals['PARTS']).($en ? ' parts, ' : ' pjesë, ').$amount($totals['LABOUR']).($en ? ' labour' : ' punë')
            : $amount($totals['LABOUR']).($en ? ' euro all in' : ' euro gjithsej');

        return implode(', ', [$customer->name, $customer->address, Str::lcfirst($quote->title), $money, Str::lcfirst($quote->proposed_for)]);
    }

    /** A customer's WhatsApp message, through the same handlers as the real webhook. */
    private function whatsApp(Quote $quote, string $text): void
    {
        $this->inbound->receive(new InboundMessage([
            'from_phone' => $quote->customer->phone,
            'body' => $text,
            'kind' => 'text',
            'provider_message_id' => 'demo-'.Str::uuid(),
        ]));
    }

    /** @param array{0: int|float, 1: int|float} $range euros */
    private function price(array $range): int
    {
        $cents = mt_rand((int) ($range[0] * 100), (int) ($range[1] * 100));

        return match (true) {
            $cents >= 10000 => (int) round($cents / 500) * 500,
            $cents >= 2000 => (int) round($cents / 100) * 100,
            default => (int) round($cents / 50) * 50,
        };
    }

    private function at(CarbonImmutable $at, callable $action): void
    {
        Carbon::setTestNow($at);
        $action();
    }

    private function reload(Quote $quote): Quote
    {
        return Quote::with(['customer', 'nudges', 'events', 'items'])
            ->withMax(['events as last_question_at' => fn ($q) => $q->where('type', EventType::Question)], 'created_at')
            ->findOrFail($quote->id);
    }
}
