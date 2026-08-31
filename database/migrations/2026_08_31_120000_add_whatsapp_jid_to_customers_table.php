<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('whatsapp_jid')->nullable()->after('whatsapp_number');
            $table->index(['company_id', 'whatsapp_jid']);
        });

        DB::table('customers')
            ->whereNull('whatsapp_jid')
            ->where('whatsapp_number', 'like', '969%')
            ->update([
                'whatsapp_jid' => DB::raw("whatsapp_number || '@lid'"),
            ]);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'whatsapp_jid']);
            $table->dropColumn('whatsapp_jid');
        });
    }
};
