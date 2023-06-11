<?php

namespace App\Tests;

use App\Resources\Resource;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use ReflectionMethod;
use RonasIT\Support\Tests\TestCase as BaseTestCase;
use RonasIT\Support\AutoDoc\Tests\AutoDocTestCaseTrait;

abstract class TestCase extends BaseTestCase
{
    use AutoDocTestCaseTrait;

    protected bool $forceExportMode = false;

    protected $testCaseName = null;
    protected $requiredOriginStates = [];
    protected $testCaseOriginStates = [];

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

    public function setUp(): void
    {
        parent::setUp();

        $this->setTestCase();

        Queue::fake();
    }

    public function tearDown(): void
    {
        $this->saveDocumentation();

        parent::tearDown();
    }

    public function assertChangesEqualsFixture(string $table, ?string $fixture = null, ?Collection $originData = null, bool $exportMode = false)
    {
        $fixture = $fixture ?? "{$table}_state.json";

        $originData = $originData ?? $this->getOriginState($table);

        $changes = $this->getChanges($table, $originData);

        $this->assertEqualsFixture($fixture, $changes, $exportMode);
    }

    public function assertNoChanges(string $table, ?Collection $originData = null)
    {
        $originData = $originData ?? $this->getOriginState($table);

        $changes = $this->getChanges($table, $originData);

        $this->assertEquals([
            'updated' => [],
            'created' => [],
            'deleted' => []
        ], $changes);
    }

    public function getFixturePath(string $fixtureName): string
    {
        $class = get_class($this);
        $explodedClass = explode('\\', $class);
        $className = Arr::last($explodedClass);

        return base_path("tests/fixtures/{$className}/{$this->testCaseName}/{$fixtureName}");
    }

    protected function setTestCase()
    {
        $reflection = new ReflectionMethod(get_class($this) . "::" . $this->getName(false));
        $docComment = $reflection->getDocComment();

        if (preg_match('/@testCase\s+([a-zA-Z0-9_]+)/', $docComment, $matches)) {
            $testCaseName = $matches[1];
        } elseif (preg_match('/@providedTestCase/', $docComment)) {
            $testCaseName = Arr::last($this->getProvidedData());
        }

        if (isset($testCaseName)) {
            $this->testCaseName = $testCaseName;

            $this->loadTestDump();
        }

        $this->loadOriginStates();
    }

    protected function loadOriginStates()
    {
        if (!isset($this->testCaseOriginStates[$this->testCaseName])) {
            foreach ($this->requiredOriginStates as $table) {
                $this->testCaseOriginStates[$this->testCaseName][$table] = $this->getDataSet($table);
            }
        }
    }

    protected function getOriginState(string $table): Collection
    {
        return $this->testCaseOriginStates[$this->testCaseName][$table];
    }

    protected function loadTestDump(): void
    {
        $clearDb = is_null($this->testCaseName);

        $dump = $this->getFixture('dump.sql', false);

        if (empty($dump)) {
            return;
        }

        $dump = preg_replace('/--.*/', '', $dump);

        if ($clearDb) {
            $databaseTables = $this->getTables();
            $scheme = config('database.default');

            $this->clearDatabase($scheme, $databaseTables, array_merge($this->postgisTables, $this->truncateExceptTables));
        }

        DB::unprepared($dump);

        if (config('database.default') === 'pgsql') {
            $this->prepareSequences($this->getTables());
        }
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

    protected function getDataSet(string $table, string $orderField = 'id', array $where = []): Collection
    {
        return DB::table($table)
            ->where($where)
            ->orderBy($orderField)
            ->get()
            ->map(function ($record) {
                return (array) $record;
            });
    }

    public function xml(string $method, string $uri, string $xmlFixture, array $headers = [])
    {
        $content = $this->getFixture($xmlFixture);

        return $this->call(
            $method,
            $uri,
            [], [], [],
            $this->transformHeadersToServerVars($headers),
            $content
        );
    }

    public function assertEqualsFixture(string $fixture, $data, bool $exportMode = false): void
    {
        if ($data instanceof Resource) {
            $data = (array) $data;
        }

        if ($exportMode || $this->forceExportMode) {
            $this->exportJson($fixture, $data);
        }

        $this->assertEquals($this->getJsonFixture($fixture), $data);
    }
}
