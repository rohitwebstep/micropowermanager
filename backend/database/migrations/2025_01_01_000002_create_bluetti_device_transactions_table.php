<?php
/*
    micropowermanager-main\backend\database\migrations\2025_01_01_000002_create_bluetti_device_transactions_table.php
*/
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * tenant connection use nahi hoga — default (micro_power_manager) use karega
     * same as BluettiDevice migration
     */
    public function up(): void
    {
        if (Schema::hasTable('bluetti_device_transactions')) {
            return;
        }

        Schema::create('bluetti_device_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->string('transaction_id');
            $table->tinyInteger('month');    // 1–12
            $table->smallInteger('year');    // e.g. 2025
            $table->timestamps();

            // Ek device ka ek month mein sirf ek transaction
            $table->unique(['device_id', 'month', 'year']);

            $table->foreign('device_id')
                  ->references('id')
                  ->on('bluetti_devices')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bluetti_device_transactions');
    }
};
