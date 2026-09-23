<?php

namespace App\Http\Controllers;

use App\Files\FileStorage;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\StoredFile;
use App\Quotes\EventType;
use App\Quotes\FollowUps\FollowUps;
use App\Quotes\ItemKind;
use App\Quotes\NudgeStatus;
use App\Quotes\QuoteCapture;
use App\Quotes\QuoteEditor;
use App\Quotes\QuoteExtractor;
use App\Quotes\QuotePdf;
use App\Quotes\QuotePresenter;
use App\Quotes\QuoteStateException;
use App\Quotes\QuoteStatus;
use App\Support\Phones;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuoteController extends Controller
{
    /** Board columns; "Silent" also holds quotes that expired unanswered. */
    private const COLUMNS = [
        'DRAFT' => ['DRAFT'],
        'SENT' => ['SENT'],
        'VIEWED' => ['VIEWED'],
        'ACCEPTED' => ['ACCEPTED'],
        'DECLINED' => ['DECLINED'],
        'SILENT' => ['SILENT', 'EXPIRED'],
    ];

    private const PER_COLUMN = 40;

    private const AUDIO_TYPES = ['audio/x-wav' => 'audio/wav', 'video/webm' => 'audio/webm', 'video/mp4' => 'audio/mp4'];

    public function __construct(private readonly QuotePresenter $presenter) {}

    /** The pipeline: each column's count and value, and its most recent quotes. */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        $totals = $this->filtered($search)->toBase()
            ->selectRaw('status, count(*) as n, coalesce(sum(total_cents), 0) as cents')
            ->groupBy('status')->get()->keyBy('status');

        $columns = [];
        foreach (self::COLUMNS as $key => $statuses) {
            $quotes = $this->filtered($search)
                ->with('customer')
                ->withExists(['nudges as pending_nudge' => fn ($q) => $q->where('status', NudgeStatus::Drafted)])
                ->withMax(['events as last_question_at' => fn ($q) => $q->where('type', EventType::Question)], 'created_at')
                ->whereIn('status', $statuses)
                ->orderByRaw($key === 'DRAFT' ? 'updated_at desc' : 'coalesce(responded_at, sent_at) desc')
                ->orderByDesc('id')
                ->limit(self::PER_COLUMN)
                ->get();
            $columns[] = [
                'key' => $key,
                'count' => (int) collect($statuses)->sum(fn ($s) => $totals[$s]->n ?? 0),
                'totalCents' => (int) collect($statuses)->sum(fn ($s) => $totals[$s]->cents ?? 0),
                'quotes' => $quotes->map(fn (Quote $q) => $this->presenter->summary($q))->all(),
            ];
        }

        return response()->json(['columns' => $columns]);
    }

    /** New draft from a recording and/or its transcript (or blank, to type from scratch). */
    public function store(Request $request, QuoteCapture $capture, FileStorage $files): JsonResponse
    {
        $data = $request->validate([
            'transcript' => ['nullable', 'string', 'max:5000'],
            'audio' => ['nullable', 'file', 'max:20480', 'mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/mp4,audio/wav,audio/x-wav,audio/x-m4a,audio/aac,video/webm,video/mp4'],
            'customerId' => ['nullable', 'integer'],
            'source' => ['nullable', Rule::in([Quote::SOURCE_VOICE, Quote::SOURCE_TYPED])],
            'locale' => ['nullable', Rule::in(['en', 'sq'])],
        ]);
        $customer = isset($data['customerId']) ? Customer::findOrFail($data['customerId']) : null;
        $voice = $request->hasFile('audio') ? $this->storeAudio($request->file('audio'), $files) : null;

        $quote = $capture->draft(
            $request->user()->organization,
            $request->user(),
            $data['transcript'] ?? null,
            $voice,
            $voice ? Quote::SOURCE_VOICE : ($data['source'] ?? Quote::SOURCE_TYPED),
            $customer,
            $data['locale'] ?? null,
        );

        return response()->json($this->presenter->detail($quote->fresh()), 201);
    }

    public function show(int $id, FollowUps $followUps): JsonResponse
    {
        $quote = $this->find($id);
        $plan = $quote->status->isAwaitingAnswer() ? $followUps->plan($quote, $quote->organization, now()) : null;

        return response()->json($this->presenter->detail($quote, $plan));
    }

    public function update(Request $request, int $id, QuoteEditor $editor, FollowUps $followUps): JsonResponse
    {
        $quote = $this->find($id);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'customer' => ['nullable', 'array'],
            'customer.id' => ['nullable', 'integer'],
            'customer.name' => ['required_with:customer', 'nullable', 'string', 'max:160'],
            'customer.phone' => ['nullable', 'string', 'max:40'],
            'customer.address' => ['nullable', 'string', 'max:255'],
            'customer.locale' => ['nullable', Rule::in(['en', 'sq'])],
            'items' => ['sometimes', 'array', 'max:60'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'items.*.unit' => ['nullable', 'string', 'max:16'],
            'items.*.unitPriceCents' => ['required', 'integer', 'min:0', 'max:100000000'],
            'items.*.kind' => ['required', Rule::enum(ItemKind::class)],
            'proposedFor' => ['nullable', 'string', 'max:160'],
            'validityDays' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:4000'],
            'transcript' => ['nullable', 'string', 'max:5000'],
        ]);
        $phone = $data['customer']['phone'] ?? null;
        if ($phone !== null && trim($phone) !== '' && ! Phones::isPlausible(Phones::normalize($phone))) {
            throw ValidationException::withMessages(['customer.phone' => [__('errors.phone')]]);
        }
        $editor->update($quote, $data, $request->user());

        return $this->show($id, $followUps);
    }

    public function destroy(int $id, FileStorage $files): Response
    {
        $quote = Quote::findOrFail($id);
        if ($quote->status !== QuoteStatus::Draft) {
            throw new QuoteStateException(__('quotes.only_drafts_deleted'));
        }
        DB::transaction(function () use ($quote, $files) {
            $voice = $quote->voiceFile;
            $quote->delete();
            if ($voice) {
                $files->delete($voice);
            }
        });

        return response()->noContent();
    }

    /** Reads a transcript without saving anything: the editor merges the result. */
    public function parse(Request $request, QuoteExtractor $extractor): JsonResponse
    {
        $data = $request->validate([
            'transcript' => ['required', 'string', 'max:5000'],
            'locale' => ['nullable', Rule::in(['en', 'sq'])],
        ]);

        return response()->json($extractor->extract($data['transcript'], $data['locale'] ?? $request->user()->organization->locale)->toApi());
    }

    public function pdf(int $id, QuotePdf $pdf): Response
    {
        return $pdf->download(Quote::findOrFail($id));
    }

    private function find(int $id): Quote
    {
        return Quote::with(['items', 'customer', 'events.user', 'nudges', 'voiceFile', 'organization'])
            ->withMax(['events as last_question_at' => fn ($q) => $q->where('type', EventType::Question)], 'created_at')
            ->findOrFail($id);
    }

    private function filtered(string $search): Builder
    {
        $query = Quote::query();
        if ($search === '') {
            return $query;
        }
        $like = '%'.addcslashes($search, '%_\\').'%';
        $digits = preg_replace('/\D/', '', $search);

        return $query->where(fn (Builder $q) => $q
            ->where('number', 'ilike', $like)
            ->orWhere('title', 'ilike', $like)
            ->orWhereHas('customer', fn (Builder $c) => $c
                ->where('name', 'ilike', $like)
                ->orWhere('address', 'ilike', $like)
                ->when(strlen($digits) >= 3, fn (Builder $c) => $c->orWhere('phone', 'like', '%'.$digits.'%'))));
    }

    /** Browsers label a WebM or MP4 recording by container; it is stored as the audio it is. */
    private function storeAudio(UploadedFile $file, FileStorage $files): StoredFile
    {
        $client = strtolower((string) $file->getClientMimeType());
        $type = str_starts_with($client, 'audio/') ? $client : (string) $file->getMimeType();
        $type = self::AUDIO_TYPES[strtolower(explode(';', $type)[0])] ?? $type;

        return $files->storeBytes($file->get(), $type, 'voice-note');
    }
}
