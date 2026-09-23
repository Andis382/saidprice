<?php

namespace App\Quotes;

use App\Models\BusinessSettings;
use App\Models\Quote;
use App\Support\Money;
use App\Support\Phones;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/** The quote as an A4 PDF in the customer's language: the same document as the link. */
class QuotePdf
{
    public function download(Quote $quote): Response
    {
        $quote->loadMissing(['items', 'customer', 'organization']);
        $locale = $quote->customerLocale();
        $currency = $quote->organization->currency;
        $money = fn (int $cents) => Money::format($cents, $currency, $locale);
        $totals = QuoteTotals::of($quote->items->map(fn ($i) => ['qty' => $i->qty, 'unitPriceCents' => $i->unit_price_cents, 'kind' => $i->kind]));

        $pdf = Pdf::loadView('pdf.quote', [
            'quote' => $quote,
            'business' => $quote->organization,
            'trade' => BusinessSettings::for($quote->organization)->trade,
            't' => fn (string $key, array $params = []) => trans('pdf.'.$key, $params, $locale),
            'money' => $money,
            'date' => fn ($date) => Phrases::date($date, $locale),
            'phone' => fn (?string $phone) => Phones::display($phone),
            'totals' => $totals,
            'qty' => fn (float $qty) => rtrim(rtrim(number_format($qty, 2, $locale === 'sq' ? ',' : '.', ''), '0'), '.,'),
        ])->setPaper('a4');

        $name = $quote->number.'-'.Str::slug($quote->customer?->name ?? 'quote').'.pdf';

        return $pdf->download($name);
    }
}
