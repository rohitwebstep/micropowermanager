<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('bluetti_device_transactions', function (Blueprint $table) {
            $table->string('plan_type')->nullable()->after('year');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('bluetti_device_transactions', function (Blueprint $table) {
            $table->dropColumn('plan_type');
        });
    }
};