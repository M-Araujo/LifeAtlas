<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_improvements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_practice_id')->constrained()->cascadeOnDelete();
            $table->text('issue');
            $table->text('solution')->nullable();
            $table->enum('status', ['identified', 'working_on_it', 'improving', 'resolved'])->default('identified');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_improvements');
    }
};
