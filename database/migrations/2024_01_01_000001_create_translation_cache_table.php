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
        Schema::create('translation_cache', function (Blueprint $table) {
            $table->id();
            $table->text('source_text');
            $table->text('translated_text');
            $table->char('source_language', 2);
            $table->char('target_language', 2);
            $table->timestamps();
            $table->unsignedInteger('requests_count')->default(1);
            
            // Индексы для быстрого поиска
            $table->index(['source_language', 'target_language']);
            $table->index('target_language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translation_cache');
    }
};
