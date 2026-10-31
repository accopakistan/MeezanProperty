<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alternative spellings and abbreviations per location ("DHA Phase V",
 * "Gulshan e Iqbal", "F7"), matched by search and the AI requirement parser.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('area_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('alias', 160);
            $table->string('normalized', 160)->index();
            $table->timestamps();

            $table->unique(['area_id', 'normalized']);
            $table->index(['city_id', 'normalized']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_aliases');
    }
};
