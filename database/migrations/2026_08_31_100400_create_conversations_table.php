<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open');
            $table->unsignedInteger('unread_count')->default(0);
            $table->text('last_message_preview')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'assigned_user_id']);
            $table->index(['company_id', 'last_message_at']);
            $table->index(['whatsapp_account_id', 'customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
