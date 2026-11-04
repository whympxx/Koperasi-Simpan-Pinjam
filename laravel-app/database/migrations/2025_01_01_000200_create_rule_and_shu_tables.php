<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_code', 20)->unique();
            $table->enum('rule_group', ['loan_eligibility', 'loan_decision']);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->enum('conclusion', ['approve', 'reject', 'manual_review']);
            $table->text('message');
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rule_group', 'is_active', 'priority']);
        });

        Schema::create('rule_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('rules')->cascadeOnDelete();
            $table->string('fact_key', 50);
            $table->string('operator', 10);
            $table->enum('value_type', ['static', 'setting', 'fact'])->default('static');
            $table->string('compare_value');
            $table->string('fallback_value')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('rule_evaluation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('rule_group', 50);
            $table->string('entity_type', 50)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('decision', 30);
            $table->string('fired_rule_code', 20)->nullable();
            $table->json('facts')->nullable();
            $table->json('trace')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('shu_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->date('start_date');
            $table->date('end_date');
            foreach (['operating_expense', 'other_income', 'interest_income', 'late_fee_income', 'total_income', 'total_expense', 'shu_amount'] as $col) {
                $table->decimal($col, 15, 2)->default(0);
            }
            foreach (['cadangan', 'jasa_anggota', 'jasa_simpanan', 'jasa_pinjaman', 'dana_pengurus', 'dana_karyawan', 'dana_pendidikan', 'dana_sosial'] as $pos) {
                $table->decimal('pct_'.$pos, 5, 2)->default(0);
                $table->decimal('amt_'.$pos, 15, 2)->default(0);
            }
            $table->decimal('total_savings_base', 15, 2)->default(0);
            $table->decimal('total_interest_base', 15, 2)->default(0);
            $table->unsignedInteger('member_count')->default(0);
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('shu_member_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('shu_periods')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            foreach (['savings_balance', 'interest_paid', 'late_fee_paid', 'shu_simpanan', 'shu_pinjaman', 'shu_total'] as $col) {
                $table->decimal($col, 15, 2)->default(0);
            }
            $table->timestamps();

            $table->unique(['period_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shu_member_shares');
        Schema::dropIfExists('shu_periods');
        Schema::dropIfExists('rule_evaluation_logs');
        Schema::dropIfExists('rule_conditions');
        Schema::dropIfExists('rules');
    }
};
