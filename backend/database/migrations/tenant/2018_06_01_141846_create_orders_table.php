<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('tenant')->create('orders', function (Blueprint $table) {
            $table->increments('id');

            $table->string('order_id')->unique();

            $table->unsignedInteger('customer_id');
            $table->unsignedInteger('meter_id');

            $table->decimal('amount', 10, 2);
            $table->date('purchased_date');

            $table->timestamps();

            // 🔗 Foreign Keys
            $table->foreign('customer_id')
                ->references('id')
                ->on('people')
                ->onDelete('cascade');

            $table->foreign('meter_id')
                ->references('id')
                ->on('meters')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('tenant')->dropIfExists('orders');
    }
};
