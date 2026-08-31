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
            $table->string('chat_type')->default('direct')->after('whatsapp_jid');
            $table->index(['company_id', 'chat_type']);
        });

        DB::table('customers')
            ->where(function ($query): void {
                $query->whereNull('whatsapp_jid')
                    ->orWhere('whatsapp_jid', 'like', '%@g.us');
            })
            ->whereRaw('length(whatsapp_number) > 15')
            ->update([
                'chat_type' => 'group',
                'whatsapp_jid' => DB::raw("COALESCE(whatsapp_jid, whatsapp_number || '@g.us')"),
            ]);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'chat_type']);
            $table->dropColumn('chat_type');
        });
    }
};
