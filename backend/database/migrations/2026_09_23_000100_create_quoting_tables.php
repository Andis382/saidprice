<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How this business quotes: one row per organisation, created on first use.
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('trade', 120)->nullable();
            $table->unsignedSmallInteger('default_validity_days')->default(14);
            $table->unsignedSmallInteger('first_nudge_hours')->default(48);
            $table->unsignedSmallInteger('second_nudge_days')->default(5);
            $table->text('terms')->nullable();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->string('locale', 5)->default('sq');
            $table->timestamps();
            $table->index(['organization_id', 'phone']);
            $table->index(['organization_id', 'name']);
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 20);
            $table->string('title', 200)->nullable();
            $table->string('status', 16)->default('DRAFT');
            $table->string('outcome', 8)->nullable();
            $table->string('source', 16)->default('TYPED');
            $table->bigInteger('total_cents')->default(0);
            $table->string('proposed_for', 160)->nullable();
            $table->unsignedSmallInteger('validity_days')->default(14);
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->uuid('voice_file_id')->nullable();
            $table->foreign('voice_file_id')->references('id')->on('stored_files')->nullOnDelete();
            $table->text('transcript')->nullable();
            $table->string('public_token', 64)->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('response_note')->nullable();
            $table->unsignedSmallInteger('nudge_count')->default(0);
            $table->timestamp('last_nudged_at')->nullable();
            $table->string('lost_reason', 200)->nullable();
            $table->timestamp('outcome_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'sent_at']);
            $table->index('customer_id');
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('description');
            $table->decimal('qty', 10, 2)->default(1);
            $table->string('unit', 16)->nullable();
            $table->bigInteger('unit_price_cents')->default(0);
            $table->string('kind', 8)->default('PARTS');
            $table->bigInteger('total_cents')->default(0);
            $table->timestamps();
            $table->index(['quote_id', 'position']);
        });

        // The quote's timeline: who did what, when, through which channel.
        Schema::create('quote_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('actor', 12);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 12)->nullable();
            $table->text('body')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['quote_id', 'created_at']);
        });

        // Follow-up messages the app drafts; nothing is sent until someone taps Send.
        Schema::create('nudges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->text('body');
            $table->string('status', 12)->default('DRAFTED');
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('outbound_message_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
            $table->index(['quote_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nudges');
        Schema::dropIfExists('quote_events');
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('business_settings');
    }
};
