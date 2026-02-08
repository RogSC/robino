<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->string('partner_code')->unique()->nullable()->after('settings');
            $table->integer('average_cycle_length')->default(28)->after('partner_code'); // days
            $table->integer('average_period_length')->default(5)->after('average_cycle_length'); // days
            $table->enum('gender', ['female', 'male', 'other'])->nullable()->after('average_period_length');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->dropColumn(['partner_code', 'average_cycle_length', 'average_period_length', 'gender']);
        });
    }
};
