<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Support installations that already ran the earlier fixed-slot migrations.
        if (Schema::hasColumn('daily_improvements', 'position')) {
            $blank = fn () => DB::table('daily_improvements')->where(fn ($query) => $query
                ->whereNull('issue')->orWhereRaw("TRIM(issue) = ''"));
            if ($blank()->where(fn ($query) => $query->whereRaw("TRIM(COALESCE(solution, '')) <> ''")
                ->orWhere('status', '!=', 'identified')->orWhereNotNull('resolved_at'))->exists()) {
                throw new RuntimeException('Blank improvement with saved content requires review before migration.');
            }
            $blank()->delete();
            Schema::table('daily_improvements', function (Blueprint $table) {
                $table->index('daily_practice_id', 'improvements_practice_index');
                $table->dropUnique(['daily_practice_id', 'position']);
            });
            Schema::table('daily_improvements', function (Blueprint $table) {
                $table->dropColumn('position');
                $table->text('issue')->nullable(false)->change();
            });
        }
        foreach (['honest_expression', 'improvements_created'] as $column) {
            if (Schema::hasColumn('daily_practices', $column)) {
                Schema::table('daily_practices', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }

    public function down(): void
    {
        // Intentionally retain the corrected schema; removed legacy content cannot be restored.
    }
};
