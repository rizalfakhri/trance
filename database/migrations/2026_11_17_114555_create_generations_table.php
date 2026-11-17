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
        Schema::create('generations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('model')->nullable();
            $table->string('modality');
            $table->text('prompt');
            $table->json('options');
            $table->string('status')->default('queued');
            $table->string('external_id')->nullable();
            $table->bigInteger('quote_total')->nullable();
            $table->unsignedInteger('fee_basis_points')->nullable();
            $table->foreignId('hold_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->unsignedBigInteger('input_text_tokens')->nullable();
            $table->unsignedBigInteger('input_image_tokens')->nullable();
            $table->unsignedBigInteger('output_tokens')->nullable();
            $table->unsignedBigInteger('reasoning_tokens')->nullable();
            $table->json('additional_units')->nullable();
            $table->json('rates')->nullable();
            $table->bigInteger('cost_total')->nullable();
            $table->bigInteger('fee_total')->nullable();
            $table->bigInteger('charge_total')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
