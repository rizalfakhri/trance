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
        Schema::create('generation_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('generation_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->unsignedSmallInteger('position');
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('duration_seconds', 8, 3)->nullable();
            $table->text('source_url')->nullable();
            $table->timestamp('source_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['generation_id', 'role', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generation_assets');
    }
};
