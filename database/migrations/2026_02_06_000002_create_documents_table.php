<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('title', 500);
            $table->string('url', 2048)->nullable();
            $table->text('content');
            $table->string('content_hash', 64);
            $table->string('external_guid', 500)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['source_id', 'content_hash']);
            $table->index(['source_id', 'external_guid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
