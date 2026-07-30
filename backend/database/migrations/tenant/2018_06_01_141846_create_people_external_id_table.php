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
    public function up() {
       Schema::connection('tenant')->create('people_external_ids', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('person_id');
            $table->string('external_id');
            $table->string('portal_name'); // e.g., 'shopify', 'crm_v1', 'legacy_db'
            $table->timestamps();

            // Composite Unique Constraint: Ek portal mein ek hi ID bar bar na ho
            $table->unique(['portal_name', 'external_id']); 
            
            $table->foreign('person_id')->references('id')->on('people')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::connection('tenant')->dropIfExists('people_external_ids');
    }
};
