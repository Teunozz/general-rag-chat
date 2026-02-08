<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table): void {
            $table->json('json_ld_types')->nullable()->after('require_article_markup');
        });
    }

    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table): void {
            $table->dropColumn('json_ld_types');
        });
    }
};
