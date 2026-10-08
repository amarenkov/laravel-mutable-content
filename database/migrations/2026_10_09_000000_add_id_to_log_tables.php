<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->logTables() as $table) {
            if (!$this->hasIdColumn($table)) {
                DB::statement("ALTER TABLE logs.\"{$table}\" ADD COLUMN id bigserial PRIMARY KEY");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->logTables() as $table) {
            if ($this->hasIdColumn($table)) {
                DB::statement("ALTER TABLE logs.\"{$table}\" DROP COLUMN id");
            }
        }
    }

    protected function logTables(): array
    {
        return array_map(
            fn ($row) => $row->table_name,
            DB::select("select table_name from information_schema.tables where table_schema = 'logs' and table_type = 'BASE TABLE'")
        );
    }

    protected function hasIdColumn(string $table): bool
    {
        return (bool)DB::selectOne(
            "select 1 from information_schema.columns where table_schema = 'logs' and table_name = ? and column_name = 'id'",
            [$table]
        );
    }
};
