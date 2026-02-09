<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // -----------------------------
        // Orders Table
        // -----------------------------
        Schema::connection('tenant')->create('orders', function (Blueprint $table) {
            $table->increments('id');

            $table->string('order_id')->unique();
            $table->unsignedInteger('customer_id');
            $table->unsignedInteger('meter_id')->nullable(); // Nullable for non-meter orders

            $table->enum('type', ['meter_order', 'meter_electricity_order', 'product_order'])
                ->default('meter_order');

            // Snapshot of customer info
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone_number');

            $table->enum('status', ['pending', 'paid', 'installed', 'completed', 'cancelled'])
                ->default('pending');

            $table->decimal('amount', 10, 2);

            // ✅ Electricity-specific fields
            $table->string('power_code')->nullable();
            $table->string('token')->nullable();

            $table->timestamp('purchased_at')->useCurrent(); // timestamp instead of date

            $table->text('notes')->nullable();
            $table->json('product_meta')->nullable();

            $table->timestamps();

            // Foreign Keys
            $table->foreign('customer_id')->references('id')->on('people')->onDelete('cascade');
            $table->foreign('meter_id')->references('id')->on('meters')->onDelete('cascade');
        });

        // -----------------------------
        // Order Addresses Table
        // -----------------------------
        Schema::connection('tenant')->create('order_addresses', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('order_id');
            $table->enum('type', ['billing', 'shipping']);

            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('address1');
            $table->string('address2')->nullable();
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('phone_number');

            $table->timestamps();

            // Foreign Key
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');

            // Prevent duplicate billing/shipping
            $table->unique(['order_id', 'type']);
        });
    }

    public function down()
    {
        Schema::connection('tenant')->dropIfExists('order_addresses');
        Schema::connection('tenant')->dropIfExists('orders');
    }
};
