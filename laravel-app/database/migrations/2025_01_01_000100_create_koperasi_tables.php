<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('account_number', 20)->unique();
            $table->decimal('balance', 15, 2)->default(0);
            $table->enum('status', ['active', 'inactive', 'closed'])->default('active');
            $table->date('opened_date');
            $table->date('closed_date')->nullable();
            $table->timestamp('last_transaction_at')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('application_number', 30)->unique();
            $table->decimal('loan_amount', 15, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->unsignedInteger('loan_term_months');
            $table->decimal('monthly_installment', 15, 2);
            $table->decimal('total_payment', 15, 2);
            $table->string('purpose', 100);
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'reviewing', 'approved', 'rejected', 'disbursed'])->default('pending');
            $table->boolean('auto_approved')->default(false);
            $table->string('decision_rule_code', 20)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('active_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->unique()->constrained('loan_applications');
            $table->string('loan_number', 30)->unique();
            $table->decimal('original_amount', 15, 2);
            $table->decimal('remaining_balance', 15, 2);
            $table->decimal('monthly_installment', 15, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->unsignedInteger('loan_term_months');
            $table->unsignedInteger('remaining_months');
            $table->date('start_date');
            $table->date('next_due_date')->nullable();
            $table->enum('status', ['active', 'completed', 'restructured'])->default('active');
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('late_fees', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference_number', 30)->unique();
            $table->enum('type', ['savings_deposit', 'savings_withdrawal', 'loan_disbursement', 'loan_payment', 'interest_credit', 'fee_debit']);
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->foreignId('related_account_id')->nullable()->constrained('savings_accounts')->nullOnDelete();
            $table->foreignId('related_loan_id')->nullable()->constrained('active_loans')->nullOnDelete();
            // Tanpa FK karena loan_schedules juga mereferensikan transactions.
            $table->unsignedBigInteger('related_schedule_id')->nullable()->index();
            $table->decimal('balance_before', 15, 2)->nullable();
            $table->decimal('balance_after', 15, 2)->nullable();
            $table->enum('payment_method', ['cash', 'transfer', 'payroll_deduction', 'online'])->default('cash');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('completed');
            $table->text('notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->date('transaction_date');
            $table->timestamps();

            $table->index(['user_id', 'transaction_date']);
        });

        Schema::create('loan_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('active_loans')->cascadeOnDelete();
            $table->unsignedInteger('installment_number');
            $table->date('due_date');
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->date('paid_date')->nullable();
            $table->decimal('late_fee', 15, 2)->default(0);
            $table->enum('status', ['pending', 'paid', 'overdue', 'partial'])->default('pending');
            $table->foreignId('payment_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'installment_number']);
            $table->index(['loan_id', 'status']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('message');
            $table->enum('type', ['info', 'warning', 'success', 'error'])->default('info');
            $table->enum('category', ['loan_approval', 'payment_reminder', 'account_activity', 'system'])->default('system');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->string('action_url')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 100)->unique();
            $table->text('setting_value');
            $table->enum('setting_type', ['string', 'number', 'boolean', 'json'])->default('string');
            $table->text('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            // nullable: percobaan login gagal tidak punya user.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100);
            $table->string('entity_type', 50)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('loan_schedules');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('active_loans');
        Schema::dropIfExists('loan_applications');
        Schema::dropIfExists('savings_accounts');
    }
};
