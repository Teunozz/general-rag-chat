<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->integer('position');
            $table->integer('token_count');
            $table->vector('embedding', 1536);
            $table->timestamps();

            $table->index(['document_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chunks');
    }
};
