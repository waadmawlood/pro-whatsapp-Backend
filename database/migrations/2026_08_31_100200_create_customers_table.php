<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('phone');
            $table->string('whatsapp_number');
            $table->string('avatar')->nullable();
            $table->string('status')->default('new');
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'whatsapp_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'assigned_user_id']);
            $table->index(['company_id', 'phone']);
            $table->index('last_contacted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
