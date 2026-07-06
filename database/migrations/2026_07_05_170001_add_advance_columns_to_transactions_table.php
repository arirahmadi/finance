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
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('is_advance')->default(false)->after('description');
            $table->string('advance_status')->nullable()->after('is_advance'); // 'open', 'settled'
            $table->timestamp('settled_at')->nullable()->after('advance_status');
            $table->decimal('settlement_amount', 15, 2)->nullable()->after('settled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['is_advance', 'advance_status', 'settled_at', 'settlement_amount']);
        });
    }
};
