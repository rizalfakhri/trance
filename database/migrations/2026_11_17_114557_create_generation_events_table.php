<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('generation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('generation_id')->constrained()->cascadeOnDelete();
            $table->string('provider_event_id')->nullable()->unique();
            $table->string('type');
            $table->string('status')->nullable();
            $table->string('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['generation_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generation_events');
    }
};
