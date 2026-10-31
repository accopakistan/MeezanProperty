<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only.
 * - dealer_documents: verification evidence (CNIC, agency registration, …)
 *   stored on the private disk and shown only to the dealer and admins.
 * - property_images: a card-sized thumbnail and the processed dimensions.
 *   Existing rows keep thumb_path NULL and fall back to the full image.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);
            $table->string('path');
            $table->string('original_name', 190);
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->timestamps();

            $table->index(['dealer_profile_id', 'type']);
        });

        Schema::table('property_images', function (Blueprint $table) {
            $table->string('thumb_path')->nullable()->after('path');
            $table->unsignedSmallInteger('width')->nullable()->after('thumb_path');
            $table->unsignedSmallInteger('height')->nullable()->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('property_images', fn (Blueprint $table) => $table->dropColumn(['thumb_path', 'width', 'height']));
        Schema::dropIfExists('dealer_documents');
    }
};
