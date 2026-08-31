<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->string('connection_type')->default('web')->after('name');
            $table->text('bridge_qr')->nullable()->after('webhook_verify_token');
            $table->timestamp('bridge_connected_at')->nullable()->after('last_webhook_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropColumn(['connection_type', 'bridge_qr', 'bridge_connected_at']);
        });
    }
};
