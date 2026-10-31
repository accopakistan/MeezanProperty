<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('buyer')->after('email')->index();
            $table->string('phone', 30)->nullable()->after('role');
            $table->string('city', 80)->nullable()->after('phone');
            $table->string('status', 20)->default('active')->after('city')->index();
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);
            $table->dropColumn(['role', 'phone', 'city', 'status', 'last_login_at']);
        });
    }
};
