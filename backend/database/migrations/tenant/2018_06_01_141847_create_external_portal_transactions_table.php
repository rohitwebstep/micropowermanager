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
        Schema::connection('tenant')->create('external_portal_transactions', function (Blueprint $table) {
            $table->increments('id');

            // Reference details
            $table->string('reference_id')->unique();

            // Customer info snapshot
            $table->unsignedInteger('customer_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();

            // Payment details
            $table->decimal('amount', 12, 2);
            $table->string('payment_method')->nullable();

            // Status control
            $table->integer('status')->unsigned();

            $table->timestamps();

            // Foreign Key
            $table->foreign('customer_id')
                ->references('id')
                ->on('people')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::connection('tenant')->dropIfExists('external_portal_transactions');
    }
};
