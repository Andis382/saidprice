<?php

namespace App\Quotes\Parsing;

/** One spoken word. The key is lower-case without diacritics, so "Pjesë" and "pjese" match. */
final class Token
{
    public readonly string $key;

    /** Claimed by a name, address or date, so the money pass skips it. */
    public bool $used = false;

    public function __construct(public readonly string $text)
    {
        $this->key = self::keyOf($text);
    }

    public static function keyOf(string $text): string
    {
        $plain = strtr(mb_strtolower($text), ['ë' => 'e', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'á' => 'a', 'à' => 'a', 'ó' => 'o', 'ö' => 'o', 'ü' => 'u', '’' => "'"]);

        return trim($plain, " \t\"'()[]{}:.!?");
    }

    /** "85", "12,50", "1.200" (thousands) as a number; null for words. */
    public function number(): ?float
    {
        if (preg_match('/^\d{1,3}(?:[.,]\d{3})+$/', $this->key)) {
            return (float) preg_replace('/[.,]/', '', $this->key);
        }
        if (preg_match('/^\d+(?:[.,]\d{1,2})?$/', $this->key)) {
            return (float) str_replace(',', '.', $this->key);
        }

        return null;
    }

    public function isWord(): bool
    {
        return preg_match("/^\p{L}[\p{L}'-]*$/u", $this->key) === 1;
    }

    public function isCapitalised(): bool
    {
        return preg_match('/^\p{Lu}/u', $this->text) === 1;
    }
}
