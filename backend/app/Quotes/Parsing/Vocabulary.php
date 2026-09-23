<?php

namespace App\Quotes\Parsing;

/**
 * The words QuoteParser listens for, in Albanian and English. Keys are lower-case and
 * without diacritics (see Token::keyOf), so "punë", "pune" and "Punë" are the same word.
 */
final class Vocabulary
{
    /** How a title is written on the quote. */
    public const TITLES = [
        'mrs' => 'Mrs', 'mr' => 'Mr', 'ms' => 'Ms', 'miss' => 'Miss', 'dr' => 'Dr',
        'znj' => 'Znj.', 'zonja' => 'Znj.', 'zonjes' => 'Znj.', 'zonjen' => 'Znj.', 'zonjusha' => 'Znsh.',
        'z' => 'Z.', 'zoti' => 'Z.', 'zotit' => 'Z.', 'zotin' => 'Z.', 'zoteri' => 'Z.',
    ];

    public const NAME_PREPOSITIONS = ['for', 'per', 'to', 'te', 'tek'];

    public const STREETS = [
        'rruga', 'rruges', 'rrugen', 'rr', 'rrugica', 'bulevardi', 'bulevard', 'blv', 'lagjja', 'lagja', 'lagjen',
        'lagjes', 'sheshi', 'pallati', 'pall', 'kompleksi', 'blloku', 'street', 'st', 'road', 'rd', 'avenue', 'ave', 'lane',
    ];

    /** Small words inside street names: "Rruga e Kavajës". */
    public const ADDRESS_LINKS = ['e', 'i', 'te', 'of', 'de'];

    /** Words that may follow a house number: "Pallati 5 shkalla 2 ap 12". */
    public const ADDRESS_MORE = ['shkalla', 'shk', 'hyrja', 'ap', 'apt', 'apartamenti', 'kati', 'floor', 'flat', 'nr', 'no', 'numri', 'pallati', 'pall', 'blloku'];

    /** Doing-words that open a job ("replace", "ndërrim"); stripped from parts lines. */
    public const VERBS = [
        'replace', 'replacing', 'replacement', 'install', 'installing', 'installation', 'fit', 'fitting', 'mount',
        'mounting', 'fix', 'fixing', 'repair', 'repairing', 'change', 'changing', 'unblock', 'unblocking', 'unclog',
        'clean', 'cleaning', 'service', 'servicing', 'check', 'checking', 'inspect', 'inspection', 'supply', 'supplying',
        'connect', 'connecting', 'move', 'moving', 'remove', 'removing', 'removal', 'lay', 'laying', 'wire', 'wiring',
        'rewire', 'rewiring', 'seal', 'sealing', 'test', 'testing', 'upgrade', 'upgrading',
        'nderrim', 'nderrimi', 'nderrimin', 'nderroj', 'nderrohet', 'zevendesim', 'zevendesimi', 'zevendesimin', 'montim',
        'montimi', 'montimin', 'instalim', 'instalimi', 'instalimin', 'vendosje', 'vendosja', 'vendosjen', 'riparim',
        'riparimi', 'riparimin', 'rregullim', 'rregullimi', 'rregullimin', 'zhbllokim', 'zhbllokimi', 'pastrim',
        'pastrimi', 'kontroll', 'kontrolli', 'servis', 'servisim', 'servisimi', 'lidhje', 'lidhja', 'furnizim',
        'furnizimi', 'heqje', 'heqja', 'levizje', 'izolim', 'izolimi', 'testim', 'shtrim', 'shtrimi', 'hapje',
    ];

    public const PARTS = ['parts', 'part', 'materials', 'material', 'supplies', 'pjese', 'pjeset', 'pjesesh', 'materiale', 'materialet', 'materiali'];

    public const LABOUR = ['labour', 'labor', 'work', 'workmanship', 'pune', 'puna', 'punen', 'punes', 'punedore', 'punedora', 'dore', 'dora', 'doren'];

    public const CURRENCIES = [
        '€' => 'EUR', 'euro' => 'EUR', 'euros' => 'EUR', 'eur' => 'EUR', 'leke' => 'ALL', 'lek' => 'ALL', 'lekesh' => 'ALL',
        '$' => 'USD', 'dollars' => 'USD', 'usd' => 'USD', '£' => 'GBP', 'pounds' => 'GBP', 'gbp' => 'GBP', 'chf' => 'CHF',
    ];

    public const MULTIPLIERS = ['mije' => 1000, 'thousand' => 1000];

    /** "8 each", "8 euro copa". */
    public const EACH_AFTER = ['each', 'apiece', 'copa', 'secila', 'secili'];

    /** "at 8", "nga 8 euro". */
    public const EACH_BEFORE = ['at', 'nga'];

    /** "120 all in", "gjithsej 120" ("all in" is matched as a pair). */
    public const TOTALS = ['total', 'totali', 'gjithsej', 'komplet', 'altogether', 'overall', 'bashke'];

    /** Glue that may sit between an amount and its kind: "85 euro for parts". */
    public const MONEY_FILLERS = ['for', 'per', 'e', 'te', 'of', 'the', 'in'];

    /** Trimmed from the edges of a description, kept inside it. */
    public const FILLERS = [
        'for', 'per', 'the', 'a', 'an', 'and', 'dhe', 'plus', 'e', 'te', 'of', 'me', 'with', 'then', 'pastaj', 'also',
        'edhe', 'si', 'about', 'rreth', 'around', 'approx', 'approximately', 'afersisht', 'is', 'eshte', 'will', 'be',
        'it', 'its', 'that', 'qe', 'at', 'nga', 'in', 'ne', 'i', 'on',
    ];

    public const COUNTS = [
        'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8,
        'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12, 'couple' => 2,
        'nje' => 1, 'dy' => 2, 'tre' => 3, 'tri' => 3, 'kater' => 4, 'pese' => 5, 'gjashte' => 6, 'shtate' => 7,
        'tete' => 8, 'nente' => 9, 'dhjete' => 10,
    ];

    /** A number followed by these describes the thing ("80 L", "12 mm"): not a price, not a count. */
    public const MEASURES = ['l', 'lt', 'litre', 'litres', 'liter', 'liters', 'litra', 'litrash', 'w', 'watt', 'watts', 'kw', 'kva', 'mm', 'cm', 'mm2', 'inch', 'inches', 'bar', 'btu', 'amp', 'amps', 'v', 'volt'];

    /** A number followed by these is a quantity with a unit ("2 hours", "3 m"). */
    public const QTY_UNITS = [
        'h' => 'hour', 'hr' => 'hour', 'hrs' => 'hour', 'hour' => 'hour', 'hours' => 'hour', 'ore' => 'hour', 'oret' => 'hour',
        'oresh' => 'hour', 'm' => 'metre', 'metre' => 'metre', 'metres' => 'metre', 'meter' => 'metre', 'meters' => 'metre',
        'metra' => 'metre', 'metrash' => 'metre', 'pcs' => 'piece', 'pc' => 'piece', 'piece' => 'piece', 'pieces' => 'piece', 'cope' => 'piece',
    ];

    public const UNIT_LABELS = [
        'hour' => ['en' => 'h', 'sq' => 'orë'],
        'metre' => ['en' => 'm', 'sq' => 'm'],
        'piece' => ['en' => 'pcs', 'sq' => 'copë'],
    ];

    /** Words that make a phrase a date: days, "tomorrow", "morning", "next week". */
    public const WHEN = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'today', 'tonight', 'tomorrow',
        'week', 'weekend', 'morning', 'afternoon', 'evening', 'noon', 'midday', 'lunchtime', 'asap',
        'hene', 'henen', 'marte', 'marten', 'merkure', 'merkuren', 'enjte', 'enjten', 'premte', 'premten', 'shtune',
        'shtunen', 'diel', 'dielen', 'sot', 'sonte', 'neser', 'pasneser', 'jave', 'javen', 'java', 'fundjave', 'fundjaven',
        'mengjes', 'mengjesin', 'paradite', 'pasdite', 'dreke', 'drekes', 'mbremje', 'mbremjen', 'menjehere',
    ];

    public const EN_DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /** Small words that belong to a date phrase when next to one: "të enjten në mëngjes", "on Monday at 10". */
    public const WHEN_GLUE = [
        'te', 'ne', 'e', 'on', 'in', 'at', 'the', 'next', 'this', 'coming', 'around', 'rreth', 'ora', 'oren', 'from',
        'deri', 'to', 'early', 'late', 'heret', 'vone', 'kete', 'or', 'ose', 'before', 'after', 'para', 'pas', 'by',
    ];

    /** Words after which a bare number is a time of day. */
    public const TIME_LEAD = ['at', 'ora', 'oren', 'ne', 'rreth', 'around', 'from', 'deri', 'to', 'before', 'after', 'para', 'pas', 'by'];

    public const GENERIC = [
        'en' => ['PARTS' => 'Parts and materials', 'LABOUR' => 'Labour', 'OTHER' => 'Work as described'],
        'sq' => ['PARTS' => 'Pjesë dhe materiale', 'LABOUR' => 'Punëdore', 'OTHER' => 'Punë sipas përshkrimit'],
    ];

    private const SQ_MARKERS = [
        'dhe', 'per', 'pjese', 'pjeset', 'pune', 'puna', 'punedore', 'te', 'ne', 'neser', 'sot', 'mengjes', 'pasdite',
        'nje', 'dy', 'tre', 'nga', 'zonja', 'znj', 'zoti', 'enjten', 'henen', 'marten', 'merkuren', 'premten', 'shtunen',
        'dielen', 'nderrim', 'montim', 'riparim', 'leke', 'gjithsej', 'materiale',
    ];

    private const EN_MARKERS = [
        'and', 'for', 'parts', 'labour', 'labor', 'the', 'at', 'tomorrow', 'today', 'morning', 'afternoon', 'evening',
        'two', 'three', 'each', 'replace', 'install', 'fix', 'repair', 'mrs', 'mr', 'monday', 'tuesday', 'wednesday',
        'thursday', 'friday', 'saturday', 'sunday', 'hours', 'total', 'materials', 'new',
    ];

    public static function isKeyword(string $key): bool
    {
        static $all = null;
        $all ??= array_fill_keys(array_merge(
            array_keys(self::TITLES), self::STREETS, self::VERBS, self::PARTS, self::LABOUR, array_keys(self::CURRENCIES),
            self::EACH_AFTER, self::EACH_BEFORE, self::TOTALS, self::FILLERS, array_keys(self::COUNTS), self::MEASURES,
            array_keys(self::QTY_UNITS), self::WHEN, array_keys(self::MULTIPLIERS),
        ), true);

        return isset($all[$key]);
    }

    /** Albanian or English, from the words used; the hint breaks a tie. */
    public static function detectLocale(string $text, string $hint): string
    {
        if (preg_match('/[ëçËÇ]/u', $text)) {
            return 'sq';
        }
        $words = array_map(fn ($w) => Token::keyOf($w), preg_split('/[^\p{L}]+/u', $text) ?: []);
        $sq = count(array_intersect($words, self::SQ_MARKERS));
        $en = count(array_intersect($words, self::EN_MARKERS));
        if ($sq === $en) {
            return $hint === 'en' ? 'en' : 'sq';
        }

        return $sq > $en ? 'sq' : 'en';
    }
}
