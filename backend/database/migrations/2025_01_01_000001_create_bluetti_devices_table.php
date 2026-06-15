<?php
/*
    // micropowermanager-main\backend\database\migrations\2025_01_01_000001_create_bluetti_devices_table.php
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    // tenant connection NAHI - default (micro_power_manager) use karega
    public function up(): void
    {
        if (Schema::hasTable('bluetti_devices')) {
            return;
        }
        Schema::create('bluetti_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_name');
            $table->string('serial_number')->unique();
            $table->string('client');
            $table->string('style');
            $table->timestamp('created_date')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('customer_no')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('bluetti_devices');
    }
};
