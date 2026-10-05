<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('principles', function (Blueprint $table) {
            $table->id();
            $table->text('text');
            $table->text('source_reference');
            $table->text('source_url')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('principles');
    }
};
