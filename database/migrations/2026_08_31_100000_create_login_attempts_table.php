<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('successful')->default(false);
            $table->string('failure_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['email', 'created_at']);
            $table->index(['ip_address', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
