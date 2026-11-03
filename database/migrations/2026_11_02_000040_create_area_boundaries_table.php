<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Society / area boundaries (additive). Each row is one version of the
 * outline of an existing area, stored as GeoJSON geometry (Polygon or
 * MultiPolygon, [lng, lat] order) with its bounding box in plain columns
 * so the map can load only boundaries in view without spatial extensions.
 *
 * Workflow: draft -> verified -> published -> archived. Only one boundary
 * per area is published at a time; public maps read published rows only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('area_boundaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->string('status', 12)->default('draft');
            $table->longText('geometry');
            $table->decimal('min_lat', 10, 7);
            $table->decimal('max_lat', 10, 7);
            $table->decimal('min_lng', 10, 7);
            $table->decimal('max_lng', 10, 7);
            $table->unsignedInteger('vertex_count');
            $table->decimal('area_sq_km', 10, 3)->nullable();
            // admin_drawn | imported | openstreetmap
            $table->string('source', 20)->default('admin_drawn');
            // Where imported data came from and under which licence (e.g. "OSM relation 123, ODbL").
            $table->string('source_reference', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'min_lat', 'max_lat'], 'area_boundaries_status_lat_index');
            $table->index(['area_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_boundaries');
    }
};
