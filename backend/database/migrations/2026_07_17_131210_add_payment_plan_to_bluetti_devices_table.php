<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('bluetti_devices', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedSmallInteger('emi_months')->nullable()->after('price');
            $table->decimal('installment_amount', 12, 2)->nullable()->after('emi_months');
            $table->date('plan_start_date')->nullable()->after('installment_amount');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('bluetti_devices', function (Blueprint $table) {
            $table->dropColumn(['price', 'emi_months', 'installment_amount', 'plan_start_date']);
        });
    }
};