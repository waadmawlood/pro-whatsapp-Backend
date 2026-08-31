<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 16)->default('#2563EB');
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });

        Schema::create('customer_tag', function (Blueprint $table) {
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['customer_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tag');
        Schema::dropIfExists('tags');
    }
};
