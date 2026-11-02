<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * hold_reason: why a lead is waiting for a person (for example every
 * suitable dealer has used their monthly lead allowance), so it is never
 * lost. invalidated_*: staff mark spam or duplicate leads, which then do
 * not count against any dealer's allowance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('hold_reason', 30)->nullable()->after('status')->index();
            $table->timestamp('invalidated_at')->nullable()->after('hold_reason');
            $table->string('invalid_reason', 30)->nullable()->after('invalidated_at');
            $table->foreignId('invalidated_by')->nullable()->after('invalid_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invalidated_by');
            $table->dropIndex(['hold_reason']);
            $table->dropColumn(['hold_reason', 'invalidated_at', 'invalid_reason']);
        });
    }
};
