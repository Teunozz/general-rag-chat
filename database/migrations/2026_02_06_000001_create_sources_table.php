<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('type', 20);
            $table->string('url', 2048)->nullable();
            $table->integer('crawl_depth')->default(1);
            $table->integer('refresh_interval')->nullable();
            $table->integer('min_content_length')->default(200);
            $table->boolean('require_article_markup')->default(true);
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('last_indexed_at')->nullable();
            $table->integer('document_count')->default(0);
            $table->integer('chunk_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
