<!DOCTYPE html>
<html lang="{{ $quote->customerLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ $quote->number }}</title>
<style>
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #1c1c1f; line-height: 1.45; }
    .band { background: #111113; color: #fff; padding: 30px 44px 26px; }
    .band table { width: 100%; border-collapse: collapse; }
    .business { font-size: 19px; font-weight: bold; letter-spacing: -0.3px; }
    .trade { color: #b9b9bf; font-size: 10px; margin-top: 3px; }
    .doc { text-align: right; }
    .doc-label { color: #facc15; font-size: 10px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
    .doc-number { font-size: 17px; font-weight: bold; margin-top: 2px; }
    .stripe { height: 5px; background: #facc15; }
    .content { padding: 26px 44px 0; }
    .meta { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .meta td { vertical-align: top; padding: 0 16px 0 0; width: 33%; }
    .label { color: #75757d; font-size: 8.5px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 3px; }
    .value { font-size: 11px; font-weight: bold; }
    .muted { color: #5b5b63; }
    .title { font-size: 16px; font-weight: bold; margin: 4px 0 14px; letter-spacing: -0.2px; }
    table.items { width: 100%; border-collapse: collapse; }
    table.items th { text-align: left; color: #75757d; font-size: 8.5px; letter-spacing: 1px; text-transform: uppercase; padding: 7px 8px; border-bottom: 1.5px solid #1c1c1f; }
    table.items td { padding: 9px 8px; border-bottom: 1px solid #e4e4e0; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .kind { display: inline-block; font-size: 8px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; padding: 1px 6px; border-radius: 8px; background: #f1f1ee; color: #5b5b63; }
    .kind-PARTS { background: #fef6c3; color: #6b4e0a; }
    .kind-LABOUR { background: #e6f7f0; color: #0b6b4c; }
    .sums { width: 46%; margin: 14px 0 0 54%; border-collapse: collapse; }
    .sums td { padding: 4px 8px; }
    .grand td { padding: 10px 8px; font-size: 15px; font-weight: bold; background: #facc15; color: #111113; }
    .block { margin-top: 20px; }
    .box { border: 1px solid #e4e4e0; border-radius: 8px; padding: 10px 12px; }
    .status { margin-top: 20px; padding: 10px 12px; border-radius: 8px; font-weight: bold; }
    .status-ok { background: #e6f7f0; color: #0b6b4c; }
    .status-no { background: #fdeceb; color: #a52626; }
    .status-old { background: #f1f1ee; color: #5b5b63; }
    .link { color: #111113; word-break: break-all; }
    .footer { position: fixed; bottom: 0; left: 0; right: 0; padding: 12px 44px 18px; color: #8a8a92; font-size: 8.5px; border-top: 1px solid #ececea; }
</style>
</head>
<body>
    <div class="band">
        <table>
            <tr>
                <td>
                    <div class="business">{{ $business->name }}</div>
                    <div class="trade">{{ collect([$trade, $business->phone ? '+'.$business->phone : null])->filter()->implode(' · ') }}</div>
                </td>
                <td class="doc">
                    <div class="doc-label">{{ $t('quote') }}</div>
                    <div class="doc-number">{{ $quote->number }}</div>
                </td>
            </tr>
        </table>
    </div>
    <div class="stripe"></div>

    <div class="content">
        <table class="meta">
            <tr>
                <td>
                    <div class="label">{{ $t('for') }}</div>
                    <div class="value">{{ $quote->customer?->name ?? '—' }}</div>
                    @if ($quote->customer?->address)<div class="muted">{{ $quote->customer->address }}</div>@endif
                    @if ($quote->customer?->phone)<div class="muted">+{{ $quote->customer->phone }}</div>@endif
                </td>
                <td>
                    <div class="label">{{ $t('date') }}</div>
                    <div class="value">{{ $date($quote->sent_at ?? $quote->created_at) }}</div>
                    @if ($quote->valid_until)
                        <div class="label" style="margin-top: 8px">{{ $t('valid_until') }}</div>
                        <div class="value">{{ $date($quote->valid_until) }}</div>
                    @endif
                </td>
                <td>
                    <div class="label">{{ $t('proposed') }}</div>
                    <div class="value">{{ $quote->proposed_for ?: '—' }}</div>
                </td>
            </tr>
        </table>

        <div class="label">{{ $t('job') }}</div>
        <div class="title">{{ $quote->displayTitle() }}</div>

        <table class="items">
            <thead>
                <tr>
                    <th>{{ $t('description') }}</th>
                    <th class="num">{{ $t('qty') }}</th>
                    <th class="num">{{ $t('unit_price') }}</th>
                    <th class="num">{{ $t('amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quote->items as $item)
                    <tr>
                        <td>
                            {{ $item->description }}<br>
                            <span class="kind kind-{{ $item->kind->value }}">{{ $t('kinds.'.$item->kind->value) }}</span>
                        </td>
                        <td class="num">{{ $qty($item->qty) }}{{ $item->unit ? ' '.$item->unit : '' }}</td>
                        <td class="num">{{ $money($item->unit_price_cents) }}</td>
                        <td class="num"><strong>{{ $money($item->total_cents) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="sums">
            @if ($totals['parts'] > 0 && $totals['labour'] > 0)
                <tr><td class="muted">{{ $t('subtotal_parts') }}</td><td class="num">{{ $money($totals['parts']) }}</td></tr>
                <tr><td class="muted">{{ $t('subtotal_labour') }}</td><td class="num">{{ $money($totals['labour']) }}</td></tr>
            @endif
            <tr class="grand"><td>{{ $t('total') }}</td><td class="num">{{ $money($quote->total_cents) }}</td></tr>
        </table>

        @if ($quote->status === \App\Quotes\QuoteStatus::Accepted)
            <div class="status status-ok">{{ $t('accepted_on', ['date' => $date($quote->responded_at)]) }}</div>
        @elseif ($quote->status === \App\Quotes\QuoteStatus::Declined)
            <div class="status status-no">{{ $t('declined_on', ['date' => $date($quote->responded_at)]) }}</div>
        @elseif ($quote->status === \App\Quotes\QuoteStatus::Expired)
            <div class="status status-old">{{ $t('expired', ['date' => $date($quote->valid_until)]) }}</div>
        @endif

        @if ($quote->notes)
            <div class="block">
                <div class="label">{{ $t('notes') }}</div>
                <div class="box">{!! nl2br(e($quote->notes)) !!}</div>
            </div>
        @endif
        @if ($quote->terms)
            <div class="block">
                <div class="label">{{ $t('terms') }}</div>
                <div class="muted">{!! nl2br(e($quote->terms)) !!}</div>
            </div>
        @endif
        @if ($quote->status->isAnswerable())
            <div class="block">
                <div class="label">{{ $t('answer_online') }}</div>
                <a class="link" href="{{ $quote->publicUrl() }}">{{ $quote->publicUrl() }}</a>
            </div>
        @endif
    </div>

    <div class="footer">{{ $t('footer', ['business' => $business->name, 'currency' => $business->currency]) }}</div>
</body>
</html>
