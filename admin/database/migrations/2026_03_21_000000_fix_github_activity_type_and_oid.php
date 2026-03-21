<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE github_activity MODIFY COLUMN type ENUM('commit', 'pr', 'review', 'issue') NOT NULL DEFAULT 'commit'");
        DB::statement("ALTER TABLE github_activity MODIFY COLUMN oid VARCHAR(255) NOT NULL");
        DB::statement("UPDATE github_activity SET type = 'review' WHERE (type = '' OR type IS NULL) AND oid LIKE 'review:%'");
        DB::statement("UPDATE github_activity SET type = 'issue' WHERE (type = '' OR type IS NULL) AND oid LIKE 'issue:%'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE github_activity MODIFY COLUMN type ENUM('commit', 'pr') NOT NULL DEFAULT 'commit'");
        DB::statement("ALTER TABLE github_activity MODIFY COLUMN oid VARCHAR(40) NOT NULL");
    }
};
