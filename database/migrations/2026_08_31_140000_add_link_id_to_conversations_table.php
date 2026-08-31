<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('link_id')->nullable()->after('customer_id');
            $table->unique(['company_id', 'link_id']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'link_id']);
            $table->dropColumn('link_id');
        });
    }
};
