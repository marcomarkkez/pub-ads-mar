<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Postgres stores enum() as a varchar + check constraint. Drop the
        // constraint, widen the column to a plain string, then re-map data.
        DB::statement('ALTER TABLE collaborators DROP CONSTRAINT IF EXISTS collaborators_role_check');
        DB::statement('ALTER TABLE collaborators ALTER COLUMN role TYPE varchar(20)');
        DB::statement("ALTER TABLE collaborators ALTER COLUMN role SET DEFAULT 'manager'");
        DB::statement("UPDATE collaborators SET role = 'installator' WHERE role = 'proof_uploader'");
    }

    public function down(): void
    {
        // Revert any new roles back to the original single value, then restore
        // the prior enum check constraint and default.
        DB::statement("UPDATE collaborators SET role = 'proof_uploader' WHERE role <> 'proof_uploader'");
        DB::statement("ALTER TABLE collaborators ALTER COLUMN role SET DEFAULT 'proof_uploader'");
        DB::statement("ALTER TABLE collaborators ADD CONSTRAINT collaborators_role_check CHECK (role IN ('proof_uploader'))");
    }
};
