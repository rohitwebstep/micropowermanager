<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bluetti_device_transactions', function (Blueprint $table) {
            $table->json('request_code_response')->nullable()->after('token_type');
            $table->json('query_code_history_response')->nullable()->after('request_code_response');
        });
    }

    public function down(): void
    {
        Schema::table('bluetti_device_transactions', function (Blueprint $table) {
            $table->dropColumn(['request_code_response', 'query_code_history_response']);
        });
    }
};