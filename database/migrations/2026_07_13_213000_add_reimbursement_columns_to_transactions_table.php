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
            $table->boolean('is_reimbursement')->default(false)->after('loan_parent_id');
            $table->string('reimbursement_status')->nullable()->after('is_reimbursement'); // 'pending', 'transferred'
            $table->string('transfer_proof_path')->nullable()->after('reimbursement_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['is_reimbursement', 'reimbursement_status', 'transfer_proof_path']);
        });
    }
};
