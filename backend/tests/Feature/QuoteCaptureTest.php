<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\MakesQuotes;
use Tests\TestCase;

class QuoteCaptureTest extends TestCase
{
    use MakesQuotes, RefreshDatabase;

    private const SPOKEN = 'Znj. Hoxha, 069 234 5678, Rruga Myslym Shyri 24, ndërrim rubineti kuzhine dhe dy zorra, 85 pjesë, 60 punë, të enjten në mëngjes';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_a_voice_note_becomes_a_draft_with_lines_customer_and_the_recording(): void
    {
        $owner = $this->owner();

        $response = $this->actingAs($owner)->post('/api/quotes', [
            'transcript' => self::SPOKEN,
            'audio' => UploadedFile::fake()->createWithContent('voice.wav', $this->wav()),
            'locale' => 'sq',
        ], ['Accept' => 'application/json'])->assertCreated();

        $response->assertJsonPath('status', 'DRAFT')
            ->assertJsonPath('number', 'Q-'.now()->year.'-0001')
            ->assertJsonPath('source', 'VOICE')
            ->assertJsonPath('title', 'Ndërrim rubineti kuzhine dhe 2 zorra')
            ->assertJsonPath('proposedFor', 'Të enjten në mëngjes')
            ->assertJsonPath('totalCents', 14500)
            ->assertJsonPath('customer.name', 'Znj. Hoxha')
            ->assertJsonPath('customer.phone', '355692345678')
            ->assertJsonPath('customer.address', 'Rruga Myslym Shyri 24')
            ->assertJsonPath('items.0.kind', 'PARTS')
            ->assertJsonPath('items.1.kind', 'LABOUR')
            ->assertJsonPath('voice.contentType', 'audio/wav')
            ->assertJsonPath('capture.engine', 'parser')
            ->assertJsonPath('events.0.type', 'CREATED');

        $this->get($response->json('voice.url'))->assertOk();
        $this->assertSame(1, Customer::count());
    }

    public function test_the_customer_is_matched_by_phone_never_by_name_alone(): void
    {
        $owner = $this->owner();
        $known = $this->customerOf($owner, ['name' => 'Drita Hoxha', 'phone' => '355692345678', 'address' => null]);
        $namesake = $this->customerOf($owner, ['name' => 'Znj. Hoxha', 'phone' => '355681111111']);

        $this->actingAs($owner)->postJson('/api/quotes', ['transcript' => self::SPOKEN])
            ->assertCreated()
            ->assertJsonPath('customer.id', $known->id)
            ->assertJsonPath('customer.name', 'Drita Hoxha')
            ->assertJsonPath('customer.address', 'Rruga Myslym Shyri 24');

        $this->actingAs($owner)->postJson('/api/quotes', ['transcript' => 'Znj. Hoxha, montim bojleri 180 euro'])
            ->assertCreated()
            ->assertJsonPath('customer.name', 'Znj. Hoxha');
        $this->assertNotSame($namesake->id, Quote::latest('id')->first()->customer_id);
    }

    public function test_typed_and_blank_drafts_and_one_for_a_known_customer(): void
    {
        $owner = $this->owner();
        $customer = $this->customerOf($owner);

        $this->actingAs($owner)->postJson('/api/quotes', ['transcript' => 'replace bathroom tap, 120 euro all in', 'source' => 'TYPED'])
            ->assertCreated()
            ->assertJsonPath('source', 'TYPED')
            ->assertJsonPath('items.0.kind', 'OTHER')
            ->assertJsonPath('customer', null);

        $this->actingAs($owner)->postJson('/api/quotes', ['customerId' => $customer->id])
            ->assertCreated()
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonPath('items', [])
            ->assertJsonPath('terms', trans('quotes.default_terms', [], 'sq'))
            ->assertJsonPath('validityDays', 14);
    }

    public function test_numbers_run_per_business(): void
    {
        $a = $this->owner();
        $b = $this->owner();

        $this->actingAs($a)->postJson('/api/quotes', [])->assertJsonPath('number', 'Q-'.now()->year.'-0001');
        $this->actingAs($a)->postJson('/api/quotes', [])->assertJsonPath('number', 'Q-'.now()->year.'-0002');
        $this->asVisitor();
        $this->actingAs($b)->postJson('/api/quotes', [])->assertJsonPath('number', 'Q-'.now()->year.'-0001');
    }

    public function test_parse_reads_without_saving(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->postJson('/api/quotes/parse', ['transcript' => 'water heater 180 labour 70', 'locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('totalCents', 25000)
            ->assertJsonPath('items.1.description', 'Labour');
        $this->assertSame(0, Quote::count());
    }

    public function test_only_audio_is_accepted_as_a_recording(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post('/api/quotes', [
            'audio' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors(['audio']);
    }

    /** A tiny valid WAV file (8 kHz mono, a few milliseconds of silence). */
    private function wav(): string
    {
        $samples = str_repeat("\x00\x00", 80);

        return 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', strlen($samples)).$samples;
    }
}
