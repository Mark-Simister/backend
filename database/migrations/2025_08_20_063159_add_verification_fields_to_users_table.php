<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
           // $table->boolean('is_verified')->default(false)->after('password');
            $table->string('otp_code', 6)->nullable()->after('is_verified');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_verified', 'otp_code', 'otp_expires_at']);
        });
    }
};
