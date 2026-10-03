<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_practices', function (Blueprint $table) {
            $table->dropUnique(['practice_date']);
            $table->index('practice_date');
        });
    }

    public function down(): void
    {
        Schema::table('daily_practices', function (Blueprint $table) {
            $table->unique('practice_date');
            $table->dropIndex(['practice_date']);
        });
    }
};
