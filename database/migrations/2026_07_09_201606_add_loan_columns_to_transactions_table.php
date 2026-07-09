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
            $table->boolean('is_loan')->default(false)->after('settlement_amount');
            $table->string('loan_status')->nullable()->after('is_loan'); // 'open', 'repaid'
            $table->decimal('loan_repaid_amount', 15, 2)->default(0)->after('loan_status');
            $table->foreignId('loan_parent_id')->nullable()->after('loan_repaid_amount')->constrained('transactions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['loan_parent_id']);
            $table->dropColumn(['is_loan', 'loan_status', 'loan_repaid_amount', 'loan_parent_id']);
        });
    }
};
