<?php

namespace App\Support\Traits;

use Illuminate\Support\Facades\DB;

trait MigrationTrait
{
    protected function changeEnum(string $table, string $field, array $newValues, array $renamedValues = [])
    {
        $this->dropEnumCheck($table, $field);

        foreach ($renamedValues as $oldValue => $newValue) {
            DB::table($table)->where($field, $oldValue)->update([$field => $newValue]);
        }

        $this->addEnumCheck($table, $field, $newValues);
    }

    protected function dropEnumCheck(string $table, string $field)
    {
        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_{$field}_check");
    }

    protected function addEnumCheck(string $table, string $field, array $values)
    {
        $result = join(', ', array_map(function($value) {
            return sprintf("'%s'::character varying", $value);
        }, $values));

        DB::statement("ALTER TABLE {$table} add CONSTRAINT {$table}_{$field}_check CHECK (({$field})::text = ANY ((ARRAY[{$result}])::text[]))");
    }
}
