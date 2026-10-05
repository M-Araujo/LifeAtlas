<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_practices', function (Blueprint $table) {
            $table->id();
            $table->date('practice_date')->unique();
            $table->foreignId('principle_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_practices');
    }
};
