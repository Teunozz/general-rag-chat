<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('messages', 'is_summary')) {
            Schema::table('messages', function (Blueprint $table): void {
                $table->dropColumn('is_summary');
            });
        }

        if (Schema::hasColumn('conversations', 'summary')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->dropColumn('summary');
            });
        }
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->boolean('is_summary')->default(false);
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->text('summary')->nullable();
        });
    }
};
