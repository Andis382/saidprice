<?php

namespace App\Messaging\Handlers;

use App\Files\FileStorage;
use App\Messaging\InboundHandler;
use App\Messaging\WhatsAppCloudDriver;
use App\Models\InboundMessage;
use App\Models\Organization;
use App\Models\Quote;
use App\Quotes\QuoteCapture;
use App\Quotes\QuoteMessages;
use App\Support\Tenant;

/**
 * The tradesperson forwards a voice note (or types the quote) to the platform number from
 * the business phone: it becomes a draft, with the recording attached, ready to check.
 */
class BusinessDraftHandler implements InboundHandler
{
    public function __construct(
        private readonly QuoteCapture $capture,
        private readonly QuoteMessages $messages,
        private readonly WhatsAppCloudDriver $whatsApp,
        private readonly FileStorage $files,
    ) {}

    public function handle(InboundMessage $message): bool
    {
        $organization = Organization::where('phone', $message->from_phone)->first();
        $isVoice = in_array($message->kind, ['audio', 'voice'], true);
        // A text only makes a draft when it names a price; "ok" to a notice does not.
        $isQuoteText = $message->kind === 'text' && preg_match('/\d/', (string) $message->body) === 1;
        if (! $organization || (! $isVoice && ! $isQuoteText)) {
            return false;
        }

        return Tenant::run($organization->id, function () use ($organization, $message, $isVoice) {
            $voice = null;
            if ($isVoice && ($media = $this->whatsApp->downloadMedia($message->media_id))) {
                $voice = $this->files->storeBytes($media['bytes'], $media['mime'], 'whatsapp-voice-note', $organization->id);
            }
            // A voice note has no words yet (unless captioned): the transcript is typed or dictated in the editor.
            $quote = $this->capture->draft($organization, null, $message->body, $voice, Quote::SOURCE_WHATSAPP);
            $this->messages->draftReady($quote, $isVoice ? 'voice' : 'text');
            $message->markHandled('business_draft', $organization->id, 'quote', $quote->id);

            return true;
        });
    }
}
