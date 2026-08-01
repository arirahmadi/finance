<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add approval_status and other potentially missing columns to transactions table
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'amount')) {
                $table->decimal('amount', 15, 2)->nullable();
            }
            if (!Schema::hasColumn('transactions', 'transferred_amount')) {
                $table->decimal('transferred_amount', 15, 2)->nullable();
            }
            $table->string('approval_status')->default('pending')->after('is_transferred');
        });

        // 2. Set all existing transactions to 'approved'
        DB::table('transactions')->update(['approval_status' => 'approved']);

        // 3. Create transaction_approvals table
        Schema::create('transaction_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['transaction_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_approvals');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('approval_status');
            if (Schema::hasColumn('transactions', 'amount')) {
                $table->dropColumn('amount');
            }
            if (Schema::hasColumn('transactions', 'transferred_amount')) {
                $table->dropColumn('transferred_amount');
            }
        });
    }
};
