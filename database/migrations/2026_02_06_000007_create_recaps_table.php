<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('recaps', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('document_count');
            $table->text('summary');
            $table->timestamps();

            $table->index(['type', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recaps');
    }
};
