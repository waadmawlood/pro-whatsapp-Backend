<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->unique('whatsapp_message_id');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->unique(['company_id', 'whatsapp_jid']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique('customers_company_id_whatsapp_jid_unique');
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropUnique('messages_whatsapp_message_id_unique');
        });
    }
};
