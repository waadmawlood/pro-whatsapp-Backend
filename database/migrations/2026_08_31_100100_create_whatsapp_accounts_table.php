<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone_number');
            $table->string('phone_number_id')->nullable();
            $table->string('waba_id')->nullable();
            $table->text('access_token')->nullable();
            $table->string('app_secret')->nullable();
            $table->string('webhook_verify_token');
            $table->string('status')->default('pending');
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('last_webhook_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->unique(['company_id', 'phone_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_accounts');
    }
};
