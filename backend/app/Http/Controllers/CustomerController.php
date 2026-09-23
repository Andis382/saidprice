<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Quote;
use App\Quotes\QuotePresenter;
use App\Quotes\QuoteStatus;
use App\Support\Phones;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        $like = '%'.addcslashes($search, '%_\\').'%';
        $digits = preg_replace('/\D/', '', $search);

        $customers = $this->withHistory(Customer::query())
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'ilike', $like)
                ->orWhere('address', 'ilike', $like)
                ->when(strlen($digits) >= 3, fn ($w) => $w->orWhere('phone', 'like', '%'.$digits.'%'))))
            ->orderByRaw('last_quote_at desc nulls last')
            ->orderBy('name')
            ->limit(300)
            ->get();

        return response()->json($customers->map(fn (Customer $c) => $this->row($c)));
    }

    public function show(int $id, QuotePresenter $presenter): JsonResponse
    {
        $customer = $this->withHistory(Customer::query())->findOrFail($id);
        $quotes = Quote::with('customer')->where('customer_id', $customer->id)->latest('created_at')->latest('id')->get();

        return response()->json([
            ...$this->row($customer),
            'quotes' => $quotes->map(fn (Quote $q) => $presenter->summary($q))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        return response()->json($this->row(Customer::create($data)), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $customer->update($this->validated($request, $customer));

        return response()->json($this->row($this->withHistory(Customer::query())->findOrFail($id)));
    }

    /** @return array{name: string, phone: ?string, address: ?string, locale: string} */
    private function validated(Request $request, ?Customer $current = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'locale' => ['required', Rule::in(['en', 'sq'])],
        ]);
        $phone = Phones::normalize($data['phone'] ?? null);
        if (($data['phone'] ?? '') !== '' && ! Phones::isPlausible($phone)) {
            throw ValidationException::withMessages(['phone' => [__('errors.phone')]]);
        }
        $taken = $phone ? Customer::where('phone', $phone)->when($current, fn ($q) => $q->whereKeyNot($current->id))->first() : null;
        if ($taken) {
            throw ValidationException::withMessages(['phone' => [__('errors.phone_taken', ['name' => $taken->name])]]);
        }

        return [
            'name' => trim($data['name']),
            'phone' => $phone,
            'address' => trim((string) ($data['address'] ?? '')) ?: null,
            'locale' => $data['locale'],
        ];
    }

    /** Each customer's quote history in one query: counts, value quoted and won, last quote. */
    private function withHistory(Builder $query): Builder
    {
        return $query
            ->withCount(['quotes as quotes_count' => fn ($q) => $q->where('status', '!=', QuoteStatus::Draft)])
            ->withCount(['quotes as won_count' => fn ($q) => $q->won()])
            ->withSum(['quotes as won_cents' => fn ($q) => $q->won()], 'total_cents')
            ->withSum(['quotes as quoted_cents' => fn ($q) => $q->where('status', '!=', QuoteStatus::Draft)], 'total_cents')
            ->withMax('quotes as last_quote_at', 'created_at');
    }

    private function row(Customer $c): array
    {
        return [
            ...$c->toApi(),
            'quotesCount' => (int) $c->quotes_count,
            'wonCount' => (int) $c->won_count,
            'wonCents' => (int) $c->won_cents,
            'quotedCents' => (int) $c->quoted_cents,
            'lastQuoteAt' => $c->last_quote_at ? Carbon::parse($c->last_quote_at)->toIso8601String() : null,
        ];
    }
}
