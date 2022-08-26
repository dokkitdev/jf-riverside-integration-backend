<?php

namespace App\Support\Traits;

use Illuminate\Support\Facades\DB;

trait MigrationTrait
{
    protected function changeEnum(string $table, string $field, array $newValues, array $renamedValues = [])
    {
        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_{$field}_check");

        foreach ($renamedValues as $oldValue => $newValue) {
            DB::table($table)->where($field, $oldValue)->update([$field => $newValue]);
        }

        $result = join(', ', array_map(function($value) {
            return sprintf("'%s'::character varying", $value);
        }, $newValues));

        DB::statement("ALTER TABLE {$table} add CONSTRAINT {$table}_{$field}_check CHECK (({$field})::text = ANY ((ARRAY[{$result}])::text[]))");
    }
}
