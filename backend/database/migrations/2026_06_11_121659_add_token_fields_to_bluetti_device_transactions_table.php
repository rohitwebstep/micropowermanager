<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bluetti_device_transactions', function (Blueprint $table) {
            $table->string('code_serial_number')->nullable()->after('is_active');
            $table->string('token')->nullable()->after('code_serial_number');
            $table->integer('days_to_activate')->nullable()->after('token');
            $table->integer('token_type')->nullable()->after('days_to_activate');
        });
    }

    public function down(): void
    {
        Schema::table('bluetti_device_transactions', function (Blueprint $table) {
            $table->dropColumn(['code_serial_number', 'token', 'days_to_activate', 'token_type']);
        });
    }
};