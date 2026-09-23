<?php

namespace Tests\Unit;

use App\Quotes\ItemKind;
use App\Quotes\Parsing\ParsedItem;
use App\Quotes\Parsing\ParsedQuote;
use App\Quotes\Parsing\QuoteParser;
use PHPUnit\Framework\TestCase;

class QuoteParserTest extends TestCase
{
    private QuoteParser $parser;

    protected function setUp(): void
    {
        $this->parser = new QuoteParser;
    }

    public function test_the_doorstep_quote_in_english(): void
    {
        $q = $this->parser->parse('Mrs. Hoxha, Rruga Myslym Shyri 24, replace kitchen mixer tap and two hoses, 85 parts, 60 labour, Thursday morning.', 'sq');

        $this->assertSame('en', $q->locale);
        $this->assertSame('Mrs Hoxha', $q->customerName);
        $this->assertSame('Rruga Myslym Shyri 24', $q->customerAddress);
        $this->assertSame('Replace kitchen mixer tap and 2 hoses', $q->title);
        $this->assertSame('Thursday morning', $q->proposedFor);
        $this->assertItems([
            ['Kitchen mixer tap and 2 hoses', 1, null, 8500, ItemKind::Parts],
            ['Labour', 1, null, 6000, ItemKind::Labour],
        ], $q);
        $this->assertSame(14500, $q->totalCents());
    }

    public function test_the_doorstep_quote_in_albanian(): void
    {
        $q = $this->parser->parse('Znj. Hoxha, Rruga Myslym Shyri 24, ndërrim rubineti kuzhine dhe dy zorra, 85 pjesë, 60 punë, të enjten në mëngjes.', 'en');

        $this->assertSame('sq', $q->locale);
        $this->assertSame('Znj. Hoxha', $q->customerName);
        $this->assertSame('Rruga Myslym Shyri 24', $q->customerAddress);
        $this->assertSame('Ndërrim rubineti kuzhine dhe 2 zorra', $q->title);
        $this->assertSame('Të enjten në mëngjes', $q->proposedFor);
        $this->assertItems([
            ['Rubineti kuzhine dhe 2 zorra', 1, null, 8500, ItemKind::Parts],
            ['Punëdore', 1, null, 6000, ItemKind::Labour],
        ], $q);
    }

    public function test_speech_without_punctuation_or_capitals_reads_the_same(): void
    {
        $q = $this->parser->parse('mrs hoxha rruga myslym shyri 24 replace kitchen mixer tap and two hoses 85 parts 60 labour thursday morning');

        $this->assertSame('Mrs Hoxha', $q->customerName);
        $this->assertSame('Rruga Myslym Shyri 24', $q->customerAddress);
        $this->assertSame('Replace kitchen mixer tap and 2 hoses', $q->title);
        $this->assertSame('Thursday morning', $q->proposedFor);
        $this->assertItems([
            ['Kitchen mixer tap and 2 hoses', 1, null, 8500, ItemKind::Parts],
            ['Labour', 1, null, 6000, ItemKind::Labour],
        ], $q);
    }

    public function test_albanian_speech_with_currency_between_amount_and_kind(): void
    {
        $q = $this->parser->parse('zonja hoxha rruga myslym shyri 24 ndërrim rubineti kuzhine dhe dy zorra 85 euro pjesë 60 euro punë të enjten në mëngjes');

        $this->assertSame('Znj. Hoxha', $q->customerName);
        $this->assertSame('Rruga Myslym Shyri 24', $q->customerAddress);
        $this->assertSame('Të enjten në mëngjes', $q->proposedFor);
        $this->assertSame('EUR', $q->currency);
        $this->assertItems([
            ['Rubineti kuzhine dhe 2 zorra', 1, null, 8500, ItemKind::Parts],
            ['Punëdore', 1, null, 6000, ItemKind::Labour],
        ], $q);
    }

    public function test_prices_per_item_with_counts_each_prices_and_sizes(): void
    {
        $q = $this->parser->parse('Arben Kola, 069 234 5678, install 80 litre water heater 180 euro, two flexible hoses at 8 each, labour 70, tomorrow afternoon', 'en');

        $this->assertSame('Arben Kola', $q->customerName);
        $this->assertSame('355692345678', $q->customerPhone);
        $this->assertSame('Tomorrow afternoon', $q->proposedFor);
        $this->assertSame('Install 80 litre water heater, 2 flexible hoses', $q->title);
        $this->assertItems([
            ['80 litre water heater', 1, null, 18000, ItemKind::Parts],
            ['Flexible hoses', 2, 'pcs', 800, ItemKind::Parts],
            ['Labour', 1, null, 7000, ItemKind::Labour],
        ], $q);
        $this->assertSame(26600, $q->totalCents());
    }

    public function test_the_same_in_albanian_with_nga_for_each(): void
    {
        $q = $this->parser->parse('Arben Kola, 069 234 5678, montim bojleri 80 litra 180 euro, dy zorra fleksibël nga 8 euro, punëdore 70, nesër pasdite');

        $this->assertSame('Nesër pasdite', $q->proposedFor);
        $this->assertItems([
            ['Bojleri 80 litra', 1, null, 18000, ItemKind::Parts],
            ['Zorra fleksibël', 2, 'copë', 800, ItemKind::Parts],
            ['Punëdore', 1, null, 7000, ItemKind::Labour],
        ], $q);
    }

    public function test_hours_of_labour_take_the_job_description_said_before_them(): void
    {
        $q = $this->parser->parse('fix leaking pipe under the sink, 2 hours labour at 25, parts 15, Monday at 10', 'en');

        $this->assertSame('Fix leaking pipe under the sink', $q->title);
        $this->assertSame('Monday at 10', $q->proposedFor);
        $this->assertItems([
            ['Fix leaking pipe under the sink', 2, 'h', 2500, ItemKind::Labour],
            ['Parts and materials', 1, null, 1500, ItemKind::Parts],
        ], $q);
    }

    public function test_one_price_for_everything_and_a_name_after_for(): void
    {
        $q = $this->parser->parse('Replace bathroom tap for Mr Leka, 120 euro all in, Saturday', 'en');

        $this->assertSame('Mr Leka', $q->customerName);
        $this->assertSame('Replace bathroom tap', $q->title);
        $this->assertSame('Saturday', $q->proposedFor);
        $this->assertItems([['Replace bathroom tap', 1, null, 12000, ItemKind::Other]], $q);
    }

    public function test_albanian_total_with_title_in_the_accusative(): void
    {
        $q = $this->parser->parse('Ndërrim çezme banjoje për zotin Leka, 120 euro gjithsej, të shtunën');

        $this->assertSame('Z. Leka', $q->customerName);
        $this->assertSame('Të shtunën', $q->proposedFor);
        $this->assertItems([['Ndërrim çezme banjoje', 1, null, 12000, ItemKind::Other]], $q);
    }

    public function test_kind_words_said_before_the_amount(): void
    {
        $q = $this->parser->parse('water heater 180 labour 70', 'en');

        $this->assertSame('Water heater', $q->title);
        $this->assertItems([
            ['Water heater', 1, null, 18000, ItemKind::Parts],
            ['Labour', 1, null, 7000, ItemKind::Labour],
        ], $q);

        $q = $this->parser->parse('pjesë 85 euro punë 60 euro');
        $this->assertItems([
            ['Pjesë dhe materiale', 1, null, 8500, ItemKind::Parts],
            ['Punëdore', 1, null, 6000, ItemKind::Labour],
        ], $q);
    }

    public function test_lek_thousands_and_decimal_amounts(): void
    {
        $q = $this->parser->parse('prizë e re në kuzhinë 3.500 lekë pjesë 2 mijë lekë punë');
        $this->assertSame('ALL', $q->currency);
        $this->assertItems([
            ['Prizë e re në kuzhinë', 1, null, 350000, ItemKind::Parts],
            ['Punëdore', 1, null, 200000, ItemKind::Labour],
        ], $q);

        $q = $this->parser->parse('sifon 12,50 euro, bojler 80 L 185 euro');
        $this->assertItems([
            ['Sifon', 1, null, 1250, ItemKind::Parts],
            ['Bojler 80 L', 1, null, 18500, ItemKind::Parts],
        ], $q);
    }

    public function test_labour_words_inside_a_name_stay_and_labour_only_lines_are_labour(): void
    {
        $q = $this->parser->parse('punë elektrike 200, montim 40');

        $this->assertItems([
            ['Punë elektrike', 1, null, 20000, ItemKind::Labour],
            ['Montim', 1, null, 4000, ItemKind::Labour],
        ], $q);
    }

    public function test_a_count_that_does_not_split_into_cents_keeps_the_total(): void
    {
        $q = $this->parser->parse('three sockets 20, two hoses 16', 'en');

        $this->assertItems([
            ['3 sockets', 1, null, 2000, ItemKind::Parts],
            ['Hoses', 2, 'pcs', 800, ItemKind::Parts],
        ], $q);
    }

    public function test_phone_numbers_in_either_format_and_street_names_with_links(): void
    {
        $q = $this->parser->parse('Arta Kola +355 68 123 4567 Rruga e Kavajës 12 ndërrim sifoni 35 euro');

        $this->assertSame('355681234567', $q->customerPhone);
        $this->assertSame('Rruga e Kavajës 12', $q->customerAddress);
        $this->assertItems([['Sifoni', 1, null, 3500, ItemKind::Parts]], $q);

        $this->assertSame('355691234567', $this->parser->parse('tel 0691234567, riparim 40')->customerPhone);
    }

    public function test_extra_remarks_become_notes(): void
    {
        $q = $this->parser->parse('replace tap, 85 parts, 40 labour, customer buys the tiles herself', 'en');

        $this->assertSame('Replace tap', $q->title);
        $this->assertSame('Customer buys the tiles herself', $q->notes);
        $this->assertCount(2, $q->items);
    }

    public function test_words_without_prices_give_a_title_and_no_items(): void
    {
        $this->assertEquals(new ParsedQuote(locale: 'sq'), $this->parser->parse('   '));

        $q = $this->parser->parse('call her back about the bathroom', 'en');
        $this->assertSame('Call her back about the bathroom', $q->title);
        $this->assertSame([], $q->items);
    }

    public function test_language_is_read_from_the_words_with_the_hint_as_tie_breaker(): void
    {
        $this->assertSame('en', $this->parser->parse('85 parts 60 labour', 'sq')->locale);
        $this->assertSame('sq', $this->parser->parse('85 pjese 60 pune', 'en')->locale);
        $this->assertSame('en', $this->parser->parse('Hoxha 120', 'en')->locale);
        $this->assertSame('sq', $this->parser->parse('Hoxha 120', 'sq')->locale);
    }

    /** @param list<array{0: string, 1: float|int, 2: ?string, 3: int, 4: ItemKind}> $expected */
    private function assertItems(array $expected, ParsedQuote $quote): void
    {
        $actual = array_map(fn (ParsedItem $i) => [$i->description, $i->qty, $i->unit, $i->unitPriceCents, $i->kind], $quote->items);
        $expected = array_map(fn (array $e) => [$e[0], (float) $e[1], $e[2], $e[3], $e[4]], $expected);
        $this->assertEquals($expected, $actual);
    }
}
