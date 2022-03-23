<?php

namespace App\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RonasIT\Support\Tests\TestCase as BaseTestCase;
use RonasIT\Support\AutoDoc\Tests\AutoDocTestCaseTrait;

abstract class TestCase extends BaseTestCase
{
    use AutoDocTestCaseTrait;

    /**
     * Creates the application.
     *
     * @return Application
     */
    public function createApplication(): Application
    {
        $app = require __DIR__ . '/../bootstrap/app.php';

        $app->loadEnvironmentFrom('.env.testing');
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function tearDown(): void
    {
        $this->saveDocumentation();

        parent::tearDown();
    }

    public function assertChangesEqualsFixture(string $table, string $fixture, Collection $originData, bool $exportMode = false)
    {
        $changes = $this->getChanges($table, $originData);

        $this->assertEqualsFixture($fixture, $changes, $exportMode);
    }

    public function assertNoChanges(string $table, Collection $originData)
    {
        $changes = $this->getChanges($table, $originData);

        $this->assertEquals([
            'updated' => [],
            'created' => [],
            'deleted' => []
        ], $changes);
    }

    protected function getChanges(string $table, Collection $originData): array
    {
        $updatedData = $this->getDataSet($table);

        $result = [
            'updated' => [],
            'created' => [],
            'deleted' => []
        ];

        $originData->each(function ($originItem) use (&$updatedData, &$result) {
            $updatedItemIndex = $updatedData->search(function ($updatedItem) use ($originItem) {
                return $updatedItem['id'] === $originItem['id'];
            });

            if ($updatedItemIndex === false) {
                $result['deleted'][] = $originItem;
            } else {
                $updatedItem = $updatedData->get($updatedItemIndex);
                $changes = array_diff_assoc($updatedItem, $originItem);

                if (!empty($changes)) {
                    $result['updated'][] = array_merge(['id' => $originItem['id']], $changes);
                }

                $updatedData->forget($updatedItemIndex);
            }
        });

        $result['created'] = $updatedData->values()->toArray();

        return $result;
    }

    protected function getDataSet(string $table, array $where = []): Collection
    {
        return DB::table($table)
            ->where($where)
            ->get()
            ->map(function ($record) {
                return (array) $record;
            });
    }
}
