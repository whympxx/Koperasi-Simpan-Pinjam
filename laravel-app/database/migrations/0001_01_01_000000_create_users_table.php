<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 20)->unique();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 20)->unique();
            $table->string('full_name', 100);
            $table->string('email', 100)->unique();
            $table->string('phone', 15);
            $table->string('password');
            $table->enum('role', ['nasabah', 'admin', 'super_admin'])->default('nasabah');
            $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->string('position', 50)->nullable();
            $table->enum('member_category', ['karyawan', 'driver', 'outsourcing'])->default('karyawan');
            $table->boolean('payroll_deduction')->default(true);
            $table->date('employment_date')->nullable();
            $table->decimal('monthly_salary', 15, 2)->default(0);
            $table->enum('status', ['active', 'inactive', 'resigned'])->default('active');
            $table->text('address')->nullable();
            $table->boolean('is_verified')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['role', 'status']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('divisions');
    }
};
