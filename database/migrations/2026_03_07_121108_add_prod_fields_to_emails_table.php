<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('emails', 'snippet')) {
            Schema::table('emails', function (Blueprint $table) {
                $table->text('snippet')->nullable()->after('subject');
            });
        }

        if (!Schema::hasColumn('emails', 'labels')) {
            Schema::table('emails', function (Blueprint $table) {
                $table->json('labels')->nullable()->after('body_html');
            });
        }

        try {
            Schema::table('emails', function (Blueprint $table) {
                $table->index('date');
            });
        } catch (\Exception $e) {
            // Index likely already exists
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropIndex(['date']);
            $table->dropColumn(['snippet', 'labels']);
        });
    }
};
