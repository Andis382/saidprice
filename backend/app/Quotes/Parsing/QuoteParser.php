<?php

namespace App\Quotes\Parsing;

use App\Quotes\ItemKind;
use App\Support\Phones;

/**
 * Reads a quote the way a tradesperson says it at the door, in Albanian or English:
 *
 *   "Znj. Hoxha, Rruga Myslym Shyri 24, ndërrim rubineti kuzhine dhe dy zorra,
 *    85 pjesë, 60 punë, të enjten në mëngjes"
 *
 * with or without the punctuation speech recognition leaves out. Names, addresses and
 * dates are claimed first; what is left is cut into priced pieces around the amounts.
 * It does not have to be perfect: the result prefills the editor, where every field is
 * checked before anything is sent.
 */
final class QuoteParser
{
    private const DOT = "\u{E000}";

    private const COMMA = "\u{E001}";

    public function __construct(private readonly string $countryCode = '355') {}

    public function parse(string $text, string $localeHint = 'sq'): ParsedQuote
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $quote = new ParsedQuote(locale: Vocabulary::detectLocale($text, $localeHint));
        if ($text === '') {
            return $quote;
        }
        $text = $this->takePhone($text, $quote);
        // Sentence case alone ("Replace the tap...") says nothing about names.
        $capitalised = preg_match_all('/(?<![\p{L}\p{N}])\p{Lu}/u', $text) > 1;

        $clauses = $this->clauses($text);
        $blocks = [];
        foreach ($clauses as $i => $clause) {
            $tokens = $this->tokens($clause);
            $this->takeName($tokens, $quote, $capitalised, $i === 0 && count($clauses) > 1);
            $this->takeAddress($tokens, $quote, $capitalised);
            $this->takeWhen($tokens, $quote);
            foreach ($this->runs($tokens) as $run) {
                array_push($blocks, ...$this->blocks($run, $quote));
            }
        }
        $this->assemble($blocks, $quote);

        return $quote;
    }

    private function takePhone(string $text, ParsedQuote $quote): string
    {
        $label = '(?:\b(?:phone|tel|cel|cell|mobile|number|numri|nr|telefon|telefoni|celulari)\b[:.]?\s*)?';
        $albanian = '(?<![\d+])(?:(?:\+|00)355[\s.-]?|0)?6[5-9](?:[\s.-]?\d){7}(?!\d)';
        $international = '(?<![\d+])\+\d{1,3}(?:[\s.-]?\d){6,12}(?!\d)';
        if (preg_match("/{$label}({$albanian}|{$international})/iu", $text, $m, PREG_OFFSET_CAPTURE)) {
            $quote->customerPhone = Phones::normalize($m[1][0], $this->countryCode);
            $text = trim(substr_replace($text, ',', $m[0][1], strlen($m[0][0])), " ,");
        }

        return $text;
    }

    /** @return list<string> */
    private function clauses(string $text): array
    {
        // Decimal and thousands separators, and the dot of "Znj." or "Rr.", do not end a clause.
        $text = (string) preg_replace('/(?<=\d)\.(?=\d)/u', self::DOT, $text);
        $text = (string) preg_replace('/(?<=\d),(?=\d)/u', self::COMMA, $text);
        $text = (string) preg_replace('/\b(znj|zn|z|mrs|mr|ms|dr|rr|blv|nr|no|ap|st|pall|shk)\./iu', '$1'.self::DOT, $text);
        $parts = preg_split('/\s*(?:[,;.!?\n]+|\s[-–]\s)\s*/u', $text) ?: [];

        $clauses = [];
        foreach ($parts as $part) {
            $part = trim(strtr($part, [self::DOT => '.', self::COMMA => ',']));
            if ($part !== '') {
                $clauses[] = $part;
            }
        }

        return $clauses;
    }

    /** @return list<Token> */
    private function tokens(string $clause): array
    {
        $clause = (string) preg_replace('/([€$£])/u', ' $1 ', $clause);
        $clause = (string) preg_replace('/(?<=\d)(l|lt|w|kw|mm|cm|m|h|hrs?)\b/iu', ' $1', $clause);

        return array_map(fn (string $w) => new Token($w), preg_split('/\s+/u', trim($clause), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /** @param list<Token> $tokens */
    private function takeName(array $tokens, ParsedQuote $quote, bool $capitalised, bool $firstClause): void
    {
        if ($quote->customerName !== null) {
            return;
        }
        foreach ($tokens as $i => $token) {
            $title = Vocabulary::TITLES[$token->key] ?? null;
            $byPreposition = $capitalised && in_array($token->key, ['for', 'per'], true);
            if ($token->used || (! $title && ! $byPreposition) || ($token->key === 'z' && ! str_starts_with($token->text, 'Z'))) {
                continue;
            }
            $words = $this->nameWords($tokens, $i + 1, $capitalised ? 3 : 1, $capitalised);
            if ($words === []) {
                continue;
            }
            $quote->customerName = trim(($title ?? '').' '.$this->text($words, titleCase: ! $capitalised));
            $token->used = true;
            array_walk($words, fn (Token $t) => $t->used = true);
            $before = $tokens[$i - 1] ?? null;
            if ($title && $before && ! $before->used && in_array($before->key, Vocabulary::NAME_PREPOSITIONS, true)) {
                $before->used = true;
            }

            return;
        }
        // "Arben Kola, 069 234 5678, ..." - a first clause that is only a name.
        if ($firstClause && $capitalised && count($tokens) <= 3) {
            foreach ($tokens as $t) {
                if (! $this->isNameWord($t, true)) {
                    return;
                }
            }
            $quote->customerName = $this->text($tokens);
            array_walk($tokens, fn (Token $t) => $t->used = true);
        }
    }

    /**
     * @param  list<Token>  $tokens
     * @return list<Token>
     */
    private function nameWords(array $tokens, int $from, int $max, bool $capitalised): array
    {
        $taken = [];
        for ($j = $from; $j < count($tokens) && count($taken) < $max && $this->isNameWord($tokens[$j], $capitalised); $j++) {
            $taken[] = $tokens[$j];
        }

        return $taken;
    }

    private function isNameWord(?Token $t, bool $mustBeCapitalised): bool
    {
        return $t !== null && ! $t->used && $t->isWord() && ! Vocabulary::isKeyword($t->key) && (! $mustBeCapitalised || $t->isCapitalised());
    }

    /** @param list<Token> $tokens */
    private function takeAddress(array $tokens, ParsedQuote $quote, bool $capitalised): void
    {
        if ($quote->customerAddress !== null) {
            return;
        }
        foreach ($tokens as $i => $token) {
            if ($token->used || ! in_array($token->key, Vocabulary::STREETS, true)) {
                continue;
            }
            $taken = [$token];
            $words = 0;
            for ($j = $i + 1; $j < count($tokens); $j++) {
                $t = $tokens[$j];
                $next = $tokens[$j + 1] ?? null;
                if ($t->used) {
                    break;
                }
                if (preg_match('#^\d{1,4}[a-z]?(/\d{1,3})?$#', $t->key) && ! $this->startsMoney($next)) {
                    $taken[] = $t;
                    if ($next && in_array($next->key, Vocabulary::ADDRESS_MORE, true)) {
                        continue;
                    }
                    break;
                }
                if (in_array($t->key, Vocabulary::ADDRESS_MORE, true) && $words > 0) {
                    $taken[] = $t;
                } elseif (in_array($t->key, Vocabulary::ADDRESS_LINKS, true) && $this->isNameWord($next, $capitalised)) {
                    $taken[] = $t;
                } elseif ($words < ($capitalised ? 4 : 3) && $this->isNameWord($t, $capitalised)) {
                    $taken[] = $t;
                    $words++;
                } else {
                    break;
                }
            }
            if (count($taken) < 2) {
                continue;
            }
            array_walk($taken, fn (Token $t) => $t->used = true);
            $quote->customerAddress = $this->text($taken, titleCase: ! $capitalised);

            return;
        }
    }

    private function startsMoney(?Token $t): bool
    {
        return $t !== null && (isset(Vocabulary::CURRENCIES[$t->key]) || in_array($t->key, Vocabulary::PARTS, true)
            || in_array($t->key, Vocabulary::LABOUR, true) || in_array($t->key, Vocabulary::EACH_AFTER, true)
            || in_array($t->key, Vocabulary::TOTALS, true) || isset(Vocabulary::MULTIPLIERS[$t->key]));
    }

    /** @param list<Token> $tokens */
    private function takeWhen(array $tokens, ParsedQuote $quote): void
    {
        if ($quote->proposedFor !== null) {
            return;
        }
        foreach ($tokens as $i => $token) {
            if ($token->used || ! in_array($token->key, Vocabulary::WHEN, true)) {
                continue;
            }
            $start = $i;
            while ($start > 0 && ! $tokens[$start - 1]->used && in_array($tokens[$start - 1]->key, Vocabulary::WHEN_GLUE, true)) {
                $start--;
            }
            $end = $i;
            $previous = $token;
            for ($j = $i + 1; $j < count($tokens) && ! $tokens[$j]->used; $j++) {
                $t = $tokens[$j];
                $isTime = $this->isTime($t) && (in_array($previous->key, Vocabulary::TIME_LEAD, true) || $this->isTime($previous));
                $isTail = $t->key === 'tjeter' && in_array($previous->key, Vocabulary::WHEN, true);
                if (in_array($t->key, Vocabulary::WHEN, true) || $isTime || $isTail) {
                    $end = $j;
                } elseif (! in_array($t->key, Vocabulary::WHEN_GLUE, true)) {
                    break;
                }
                $previous = $t;
            }
            $span = array_slice($tokens, $start, $end - $start + 1);
            array_walk($span, fn (Token $t) => $t->used = true);
            $words = array_map(fn (Token $t) => in_array($t->key, Vocabulary::EN_DAYS, true) ? ucfirst($t->key) : $t->text, $span);
            $quote->proposedFor = $this->capitalise(implode(' ', $words));

            return;
        }
    }

    private function isTime(Token $t): bool
    {
        return preg_match('/^\d{1,2}([:.]\d{2})?(am|pm)?$/', $t->key) === 1 || in_array($t->key, ['am', 'pm', 'gjysme', 'half'], true);
    }

    /**
     * Contiguous runs of words nothing has claimed yet.
     *
     * @param  list<Token>  $tokens
     * @return list<list<Token>>
     */
    private function runs(array $tokens): array
    {
        $runs = [];
        $current = [];
        foreach ($tokens as $t) {
            if ($t->used) {
                if ($current) {
                    $runs[] = $current;
                }
                $current = [];
            } else {
                $current[] = $t;
            }
        }
        if ($current) {
            $runs[] = $current;
        }

        return $runs;
    }

    /**
     * Cuts a run into priced pieces ("water heater 180", "85 parts") and plain description.
     *
     * @param  list<Token>  $run
     * @return list<array{0: string, 1: mixed}>
     */
    private function blocks(array $run, ParsedQuote $quote): array
    {
        [$roles, $values] = $this->roles($run);
        $amounts = array_keys(array_filter($roles, fn (string $r) => $r === 'amount'));
        if ($amounts === []) {
            return [['desc', $run]];
        }
        $ends = $this->pieceEnds($run, $roles, $amounts);

        $blocks = [];
        $start = 0;
        foreach ($ends as $n => $end) {
            $length = $end - $start + 1;
            $blocks[] = ['piece', $this->piece(array_slice($run, $start, $length), array_slice($roles, $start, $length), $values[$amounts[$n]], $quote)];
            $start = $end + 1;
        }
        if ($start < count($run)) {
            $blocks[] = ['desc', array_slice($run, $start)];
        }

        return $blocks;
    }

    /**
     * What each word does: a price, a count, a unit, a currency, a kind ("parts"), or plain description.
     *
     * @param  list<Token>  $run
     * @return array{0: list<string>, 1: array<int, float>}
     */
    private function roles(array $run): array
    {
        $roles = [];
        $values = [];
        $n = count($run);
        for ($k = 0; $k < $n; $k++) {
            $t = $run[$k];
            $next = $run[$k + 1] ?? null;
            $previous = $run[$k - 1] ?? null;
            $number = $t->number();
            $count = Vocabulary::COUNTS[$t->key] ?? null;

            $roles[$k] = match (true) {
                isset(Vocabulary::CURRENCIES[$t->key]) => 'currency',
                in_array($t->key, Vocabulary::PARTS, true) => 'parts',
                in_array($t->key, Vocabulary::LABOUR, true) => 'labour',
                in_array($t->key, Vocabulary::EACH_AFTER, true) => 'each',
                in_array($t->key, Vocabulary::EACH_BEFORE, true) && $next?->number() !== null => 'each',
                in_array($t->key, Vocabulary::TOTALS, true), $t->key === 'all' && $next?->key === 'in', $t->key === 'in' && $previous?->key === 'all' => 'total',
                default => 'word',
            };
            if ($roles[$k] !== 'word' || ($number === null && $count === null)) {
                continue;
            }
            if ($next && in_array($next->key, Vocabulary::MEASURES, true)) {
                $roles[++$k] = 'word';
            } elseif ($next && isset(Vocabulary::QTY_UNITS[$next->key])) {
                $roles[$k] = 'qty';
                $values[$k] = $number ?? (float) $count;
                $roles[++$k] = 'unit';
            } elseif ($count !== null) {
                $roles[$k] = 'count';
                $values[$k] = (float) $count;
            } elseif ($next && isset(Vocabulary::MULTIPLIERS[$next->key])) {
                $roles[$k] = 'amount';
                $values[$k] = $number * Vocabulary::MULTIPLIERS[$next->key];
                $roles[++$k] = 'currency';
            } else {
                $roles[$k] = $this->isAmount($number, $next, $previous) ? 'amount' : 'count';
                $values[$k] = $number;
            }
        }

        return [$roles, $values];
    }

    private function isAmount(float $number, ?Token $next, ?Token $previous): bool
    {
        if ($next === null || $this->startsMoney($next) || ($previous && isset(Vocabulary::CURRENCIES[$previous->key]))) {
            return true;
        }
        // "2 hoses" is a count; "65 hoses" is the price of the thing before, then the next item.
        $looksLikeCount = $number <= 20 && floor($number) === $number;

        return ! ($looksLikeCount && $next->isWord() && ! Vocabulary::isKeyword($next->key));
    }

    /**
     * Where each priced piece ends. A kind word between two amounts ("85 parts 60 labour" vs
     * "heater 180 labour 70") belongs either to the amount before or after it; both readings
     * are tried and the one that leaves no stray kind words or empty pieces wins.
     *
     * @param  list<Token>  $run
     * @param  list<string>  $roles
     * @param  list<int>  $amounts
     * @return list<int>
     */
    private function pieceEnds(array $run, array $roles, array $amounts): array
    {
        $best = null;
        $bestScore = PHP_INT_MAX;
        foreach ([true, false] as $kindAfterAmount) {
            $ends = [];
            foreach ($amounts as $n => $a) {
                $limit = $amounts[$n + 1] ?? count($run);
                $ends[] = $this->pieceEnd($run, $roles, $a, $limit, $kindAfterAmount);
            }
            $score = $this->score($run, $roles, $ends);
            if ($score < $bestScore) {
                [$best, $bestScore] = [$ends, $score];
            }
        }

        return $best;
    }

    /**
     * @param  list<Token>  $run
     * @param  list<string>  $roles
     */
    private function pieceEnd(array $run, array $roles, int $amount, int $limit, bool $kindAfterAmount): int
    {
        $end = $amount;
        $kindTaken = false;
        for ($j = $amount + 1; $j < $limit; $j++) {
            $role = $roles[$j];
            if (in_array($role, ['currency', 'total'], true) || ($role === 'each' && in_array($run[$j]->key, Vocabulary::EACH_AFTER, true))) {
                $end = $j;

                continue;
            }
            if ($kindAfterAmount && ! $kindTaken && in_array($role, ['parts', 'labour'], true)) {
                $end = $j;
                $kindTaken = true;

                continue;
            }
            if ($kindAfterAmount && ! $kindTaken && in_array($run[$j]->key, Vocabulary::MONEY_FILLERS, true)) {
                $k = $j;
                while ($k < $limit && in_array($run[$k]->key, Vocabulary::MONEY_FILLERS, true)) {
                    $k++;
                }
                if ($k < $limit && in_array($roles[$k], ['parts', 'labour'], true)) {
                    $j = $k - 1;

                    continue;
                }
            }
            break;
        }

        return $end;
    }

    /**
     * @param  list<Token>  $run
     * @param  list<string>  $roles
     * @param  list<int>  $ends
     */
    private function score(array $run, array $roles, array $ends): int
    {
        $score = 0;
        $start = 0;
        foreach ($ends as $end) {
            $kinds = 0;
            $substance = 0;
            for ($j = $start; $j <= $end; $j++) {
                if (in_array($roles[$j], ['parts', 'labour'], true)) {
                    $kinds++;
                } elseif (in_array($roles[$j], ['count', 'qty'], true) || ($roles[$j] === 'word' && ! in_array($run[$j]->key, Vocabulary::FILLERS, true))) {
                    $substance++;
                }
            }
            $score += ($kinds > 1 ? 2 : 0) + ($kinds === 0 && $substance === 0 ? 1 : 0);
            $start = $end + 1;
        }
        for ($j = $start; $j < count($run); $j++) {
            $score += in_array($roles[$j], ['parts', 'labour'], true) ? 2 : 0;
        }

        return $score;
    }

    /**
     * @param  list<Token>  $tokens
     * @param  list<string>  $roles
     * @return array{words: list<Token>, said: list<Token>, kind: ?ItemKind, qty: ?float, unit: ?string, each: bool, total: bool, amount: float}
     */
    private function piece(array $tokens, array $roles, float $amount, ParsedQuote $quote): array
    {
        $piece = ['words' => [], 'said' => [], 'kind' => null, 'qty' => null, 'unit' => null, 'each' => false, 'total' => false, 'amount' => $amount];
        $skipUnitAt = null;
        foreach ($tokens as $i => $t) {
            $role = $roles[$i];
            if (! in_array($role, ['amount', 'currency', 'each', 'total'], true)) {
                $piece['said'][] = $t;
            }
            if ($role === 'currency') {
                $quote->currency ??= Vocabulary::CURRENCIES[$t->key] ?? null;
            } elseif ($role === 'each') {
                $piece['each'] = true;
            } elseif ($role === 'total') {
                $piece['total'] = true;
            } elseif (($role === 'qty' || $role === 'count') && $piece['qty'] === null && $this->isOpening($piece['words'])) {
                // A quantity only counts at the start of the thing it counts: "two hoses", "2 orë punë".
                $piece['qty'] = $t->number() ?? (float) Vocabulary::COUNTS[$t->key];
                $piece['unit'] = $role === 'qty' ? Vocabulary::QTY_UNITS[$tokens[$i + 1]->key] : 'piece';
                $skipUnitAt = $role === 'qty' ? $i + 1 : null;
            } elseif ($role !== 'amount' && $i !== $skipUnitAt) {
                if ($role === 'parts' || $role === 'labour') {
                    $piece['kind'] ??= $role === 'parts' ? ItemKind::Parts : ItemKind::Labour;
                }
                $piece['words'][] = $t;
            }
        }

        return $piece;
    }

    /** @param list<Token> $words */
    private function isOpening(array $words): bool
    {
        foreach ($words as $w) {
            if (! $this->isFiller($w) && ! $this->isVerb($w) && ! $this->isKindWord($w)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Turns the blocks into items: a description said on its own ("replace the kitchen tap")
     * names the first priced piece after it that has no words of its own ("85 parts").
     *
     * @param  list<array{0: string, 1: mixed}>  $blocks
     */
    private function assemble(array $blocks, ParsedQuote $quote): void
    {
        $units = [];
        $open = null;
        $spoken = [];
        foreach ($blocks as [$type, $data]) {
            if ($type === 'desc') {
                $words = $this->core($data);
                if ($words === []) {
                    continue;
                }
                if ($open === null) {
                    $units[] = ['words' => $words, 'attached' => false];
                    $open = array_key_last($units);
                } else {
                    $units[$open]['words'] = [...$units[$open]['words'], new Token(','), ...$words];
                }

                continue;
            }
            $own = $this->core($data['words']) !== [];
            $words = $data['words'];
            if (! $own && $open !== null) {
                $words = $units[$open]['words'];
                $units[$open]['attached'] = true;
            }
            $open = null;
            if ($own) {
                $spoken[] = $spoken ? $this->lowerFirst($this->phrase($data['said'])) : $this->phrase($data['said']);
            }
            $quote->items[] = $this->item($data, $words, $own, $quote->locale);
        }

        $quote->title = isset($units[0]) ? $this->phrase($units[0]['words']) : (implode(', ', array_slice($spoken, 0, 2)) ?: null);
        $notes = [];
        foreach (array_slice($units, 1) as $unit) {
            if (! $unit['attached']) {
                $notes[] = $this->phrase($unit['words']);
            }
        }
        $quote->notes = $notes ? implode('. ', $notes) : null;

        // A bare total ("120 all in") is the job itself.
        $generic = Vocabulary::GENERIC[$quote->locale]['OTHER'];
        foreach ($quote->items as $item) {
            if ($item->description === $generic && $quote->title) {
                $item->description = $quote->title;
            }
        }
    }

    /**
     * @param  array{words: list<Token>, said: list<Token>, kind: ?ItemKind, qty: ?float, unit: ?string, each: bool, total: bool, amount: float}  $piece
     * @param  list<Token>  $words
     */
    private function item(array $piece, array $words, bool $own, string $locale): ParsedItem
    {
        $kind = $piece['kind'] ?? match (true) {
            $piece['total'] => ItemKind::Other,
            $piece['unit'] === 'hour' => ItemKind::Labour,
            ! $own => ItemKind::Other,
            $this->onlyLabourWords($words) => ItemKind::Labour,
            default => ItemKind::Parts,
        };
        $description = $this->phrase($words, stripVerbs: $kind === ItemKind::Parts);

        $amountCents = (int) round($piece['amount'] * 100);
        $qty = $piece['qty'] ?? 1.0;
        $unit = $piece['unit'] && ($piece['unit'] !== 'piece' || $qty > 1) ? Vocabulary::UNIT_LABELS[$piece['unit']][$locale] : null;
        $unitPrice = $piece['each'] ? $amountCents : $amountCents / $qty;
        if (abs($unitPrice - round($unitPrice)) > 0.0001) {
            // "three sockets 20" does not split into whole cents: keep the total as said.
            $prefix = $this->number($qty).($piece['unit'] === 'piece' ? '' : ' '.$unit);
            $description = $prefix.' '.$this->lowerFirst($description);
            [$qty, $unit, $unitPrice] = [1.0, null, $amountCents];
        }

        return new ParsedItem(
            description: $description !== '' ? mb_substr($description, 0, 255) : Vocabulary::GENERIC[$locale][$kind->value],
            qty: $qty,
            unit: $unit,
            unitPriceCents: (int) round($unitPrice),
            kind: $kind,
        );
    }

    /** @param list<Token> $words */
    private function onlyLabourWords(array $words): bool
    {
        $core = $this->core($words);
        foreach ($core as $w) {
            if (! $this->isVerb($w) && ! $this->isKindWord($w) && ! $this->isFiller($w)) {
                return false;
            }
        }

        return $core !== [];
    }

    /**
     * The words that name the thing: no filler at either end, no kind marker trailing it
     * ("… two hoses 85 parts"), and for parts lines no opening verb ("replace", "ndërrim").
     *
     * @param  list<Token>  $words
     * @return list<Token>
     */
    private function core(array $words, bool $stripVerbs = false): array
    {
        while ($words && ($this->isFiller($words[0]) || ($stripVerbs && $this->isVerb($words[0])))) {
            array_shift($words);
        }
        while ($words && ($this->isFiller($words[count($words) - 1]) || $this->isKindWord($words[count($words) - 1]))) {
            array_pop($words);
        }

        return array_values($words);
    }

    private function isFiller(Token $t): bool
    {
        return $t->key === '' || $t->text === ',' || in_array($t->key, Vocabulary::FILLERS, true);
    }

    private function isVerb(Token $t): bool
    {
        return in_array($t->key, Vocabulary::VERBS, true);
    }

    private function isKindWord(Token $t): bool
    {
        return in_array($t->key, Vocabulary::PARTS, true) || in_array($t->key, Vocabulary::LABOUR, true);
    }

    /** @param list<Token> $words */
    private function phrase(array $words, bool $stripVerbs = false): string
    {
        $parts = array_map(
            fn (Token $t) => isset(Vocabulary::COUNTS[$t->key]) && $t->key !== 'nje' ? (string) Vocabulary::COUNTS[$t->key] : $t->text,
            $this->core($words, $stripVerbs),
        );

        return $this->capitalise(str_replace(' ,', ',', implode(' ', $parts)));
    }

    /** @param list<Token> $tokens */
    private function text(array $tokens, bool $titleCase = false): string
    {
        $words = array_map(
            fn (Token $t) => $titleCase && ! in_array($t->key, Vocabulary::ADDRESS_LINKS, true) ? mb_convert_case($t->text, MB_CASE_TITLE) : $t->text,
            $tokens,
        );

        return implode(' ', $words);
    }

    private function capitalise(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    private function lowerFirst(string $text): string
    {
        return mb_strtolower(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    private function number(float $n): string
    {
        return floor($n) === $n ? (string) (int) $n : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
