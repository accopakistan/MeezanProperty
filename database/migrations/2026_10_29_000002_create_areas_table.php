<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('city', 80)->index();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->boolean('is_popular')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['city', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
