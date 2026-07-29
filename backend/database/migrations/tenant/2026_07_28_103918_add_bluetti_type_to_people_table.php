<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('people', function (Blueprint $table) {
            $table->string('bluetti_type')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('people', function (Blueprint $table) {
            $table->dropColumn('bluetti_type');
        });
    }
};