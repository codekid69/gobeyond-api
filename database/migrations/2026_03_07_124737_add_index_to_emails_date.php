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
        // Index is already created in 2026_03_07_121108_add_prod_fields_to_emails_table.php
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Handled by previous migration's down logic
    }
};
