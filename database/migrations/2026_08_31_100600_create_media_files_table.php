<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('disk')->default('public');
            $table->string('path')->nullable();
            $table->string('whatsapp_media_id')->nullable();
            $table->string('caption')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
