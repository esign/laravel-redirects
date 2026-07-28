<?php

/**
 * Benchmark: Eloquent Collection cache vs. plain array cache for redirects.
 *
 * Compares the current approach (caching full Eloquent Collection) against a
 * proposed change (caching plain arrays) across speed, memory, and cache size.
 *
 * Run with: php benchmark.php
 */

use Esign\Redirects\DataTransferObjects\RedirectDTO;
use Esign\Redirects\Models\Redirect;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Benchmark;

require __DIR__ . '/vendor/autoload.php';

// ──────────────────────────────────────────────────────────────────────────────
// Bootstrap minimal Laravel / Capsule environment
// ──────────────────────────────────────────────────────────────────────────────

$db = new DB();
$db->addConnection([
    'driver'   => 'sqlite',
    'database' => ':memory:',
    'prefix'   => '',
]);
$db->setAsGlobal();
$db->bootEloquent();

DB::statement('
    CREATE TABLE redirects (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        old_url    TEXT    NOT NULL UNIQUE,
        new_url    TEXT    NOT NULL,
        status_code INTEGER NOT NULL DEFAULT 302,
        constraints TEXT    NULL,
        created_at TEXT    NULL,
        updated_at TEXT    NULL
    )
');

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * A simple cache that round-trips through serialize/unserialize on every
 * get/put, simulating what Redis, Memcached, and file cache backends do.
 * ArrayStore skips serialization entirely, which makes warm-cache benchmarks
 * misleading for comparing payloads of different types/sizes.
 */
class SerializingStore
{
    private array $data = [];

    public function get(string $key): mixed
    {
        return isset($this->data[$key]) ? unserialize($this->data[$key]) : null;
    }

    public function put(string $key, mixed $value): void
    {
        $this->data[$key] = serialize($value);
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function rawSize(string $key): int
    {
        return isset($this->data[$key]) ? strlen($this->data[$key]) : 0;
    }
}

function makeStore(): SerializingStore
{
    return new SerializingStore();
}

function rememberOnStore(SerializingStore $store, string $key, callable $callback): mixed
{
    $value = $store->get($key);
    if ($value === null) {
        $value = $callback();
        $store->put($key, $value);
    }
    return $value;
}

function seedRedirects(int $count): void
{
    DB::table('redirects')->truncate();
    $rows = [];
    for ($i = 1; $i <= $count; $i++) {
        $rows[] = [
            'old_url'     => "/old-url-{$i}",
            'new_url'     => "/new-url-{$i}",
            'status_code' => 302,
            'constraints' => null,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];
    }
    DB::table('redirects')->insert($rows);
}

/**
 * OLD approach: caches the full Eloquent Collection.
 * On every read the collection is mapped to DTOs in memory.
 */
function oldApproach(SerializingStore $store): array
{
    $redirects = rememberOnStore($store, 'redirects', fn () => Redirect::get());

    return $redirects->map(fn (Redirect $r) => RedirectDTO::fromRedirect($r))->toArray();
}

/**
 * NEW approach: caches plain arrays only.
 * DTOs are reconstructed from the plain array on every read.
 */
function newApproach(SerializingStore $store): array
{
    $data = rememberOnStore($store, 'redirects', fn () =>
        Redirect::get()->map(fn (Redirect $r) => [
            'old_url'     => $r->getOldUrl(),
            'new_url'     => $r->getNewUrl(),
            'status_code' => $r->getStatusCode(),
            'constraints' => $r->getConstraints(),
        ])->values()->all()
    );

    return array_map(
        fn (array $item) => new RedirectDTO(
            $item['old_url'],
            $item['new_url'],
            $item['status_code'],
            $item['constraints'],
        ),
        $data
    );
}

/**
 * HYDRATE approach: caches model attribute arrays via ->toArray(), then
 * re-hydrates Eloquent models with ::hydrate() before mapping to DTOs.
 * Mirrors the laravel-translation-loader pattern exactly.
 */
function hydrateApproach(SerializingStore $store): array
{
    $data = rememberOnStore($store, 'redirects', fn () => Redirect::get()->toArray());

    return Redirect::hydrate($data)
        ->map(fn (Redirect $r) => RedirectDTO::fromRedirect($r))
        ->toArray();
}

function cacheSize(SerializingStore $store, string $key): int
{
    return $store->rawSize($key);
}

function memoryDelta(callable $fn): int
{
    gc_collect_cycles();
    $before = memory_get_usage(false);
    $fn();
    $after = memory_get_usage(false);
    gc_collect_cycles();
    return $after - $before;
}

function inMemorySize(mixed $value): int
{
    return strlen(serialize($value));
}

// ──────────────────────────────────────────────────────────────────────────────
// Run benchmarks
// ──────────────────────────────────────────────────────────────────────────────

$iterations = 200;
$counts     = [10, 100, 500, 1000, 10000];

echo str_repeat('─', 110) . PHP_EOL;
printf(
    "%-8s %-14s %-14s %-14s %-14s %-14s %-14s %-14s\n",
    'Count',
    'Old speed',
    'Hydrate speed',
    'New speed',
    'Hydrate vs old',
    'New vs old',
    'Old size',
    'Hydrate size',
    'New size',
);
echo str_repeat('─', 110) . PHP_EOL;

foreach ($counts as $count) {
    seedRedirects($count);

    $oldStore     = makeStore();
    $hydrateStore = makeStore();
    $newStore     = makeStore();

    // Prime all stores (cold hit — writes to serialized storage)
    oldApproach($oldStore);
    hydrateApproach($hydrateStore);
    newApproach($newStore);

    $results = Benchmark::measure([
        'old'     => fn () => oldApproach($oldStore),
        'hydrate' => fn () => hydrateApproach($hydrateStore),
        'new'     => fn () => newApproach($newStore),
    ], $iterations);

    $oldMs     = $results['old'];
    $hydrateMs = $results['hydrate'];
    $newMs     = $results['new'];

    $oldBytes     = cacheSize($oldStore, 'redirects');
    $hydrateBytes = cacheSize($hydrateStore, 'redirects');
    $newBytes     = cacheSize($newStore, 'redirects');

    printf(
        "%-8d %-14s %-14s %-14s %-14s %-14s %-14s %-14s\n",
        $count,
        round($oldMs, 4) . ' ms',
        round($hydrateMs, 4) . ' ms',
        round($newMs, 4) . ' ms',
        round($oldMs / $hydrateMs, 2) . '×',
        round($oldMs / $newMs, 2) . '×',
        number_format($oldBytes) . ' B',
        number_format($hydrateBytes) . ' B',
        number_format($newBytes) . ' B',
    );
}

echo str_repeat('─', 110) . PHP_EOL;

// ──────────────────────────────────────────────────────────────────────────────
// Memory breakdown (single run, 1000 redirects)
// ──────────────────────────────────────────────────────────────────────────────

echo PHP_EOL . '── Memory & payload size (1000 redirects, warm cache) ───────────────────────────' . PHP_EOL;

seedRedirects(1000);
$oldStoreMem     = makeStore();
$hydrateStoreMem = makeStore();
$newStoreMem     = makeStore();
$oldResult     = oldApproach($oldStoreMem);
$hydrateResult = hydrateApproach($hydrateStoreMem);
$newResult     = newApproach($newStoreMem);

$oldMem     = memoryDelta(fn () => oldApproach($oldStoreMem));
$hydrateMem = memoryDelta(fn () => hydrateApproach($hydrateStoreMem));
$newMem     = memoryDelta(fn () => newApproach($newStoreMem));

printf("Old approach memory delta:      %s KB\n", number_format($oldMem / 1024, 2));
printf("Hydrate approach memory delta:  %s KB\n", number_format($hydrateMem / 1024, 2));
printf("New approach memory delta:      %s KB\n", number_format($newMem / 1024, 2));
printf("Old result serialised size:     %s B\n",  number_format(inMemorySize($oldResult)));
printf("Hydrate result serialised size: %s B\n",  number_format(inMemorySize($hydrateResult)));
printf("New result serialised size:     %s B\n",  number_format(inMemorySize($newResult)));

// ──────────────────────────────────────────────────────────────────────────────
// Cold-cache benchmark (includes DB query + serialisation cost)
// ──────────────────────────────────────────────────────────────────────────────

echo PHP_EOL . '── Cold-cache speed (10 iterations, 500 redirects) ────────────────────────────' . PHP_EOL;

seedRedirects(500);

$coldResults = Benchmark::measure([
    'old (cold)'     => fn () => oldApproach(makeStore()),
    'hydrate (cold)' => fn () => hydrateApproach(makeStore()),
    'new (cold)'     => fn () => newApproach(makeStore()),
], 10);

printf("Old cold-cache:     %s ms/iter\n", round($coldResults['old (cold)'], 4));
printf("Hydrate cold-cache: %s ms/iter\n", round($coldResults['hydrate (cold)'], 4));
printf("New cold-cache:     %s ms/iter\n", round($coldResults['new (cold)'], 4));

// ──────────────────────────────────────────────────────────────────────────────
// Isolated write benchmark (serialisation only, no DB query)
// ──────────────────────────────────────────────────────────────────────────────

echo PHP_EOL . '── Write speed (serialisation only, warm DB, N=' . $iterations . ') ─────────────────────' . PHP_EOL;

$writeCounts = [10, 100, 500, 1000, 10000];

printf("%-8s %-16s %-16s %-16s\n", 'Count', 'Old write', 'Hydrate write', 'New write');
echo str_repeat('─', 60) . PHP_EOL;

foreach ($writeCounts as $count) {
    seedRedirects($count);

    $collection  = Redirect::get();
    $modelArrays = $collection->toArray();
    $plainArray  = $collection->map(fn (Redirect $r) => [
        'old_url'     => $r->getOldUrl(),
        'new_url'     => $r->getNewUrl(),
        'status_code' => $r->getStatusCode(),
        'constraints' => $r->getConstraints(),
    ])->values()->all();

    $writeResults = Benchmark::measure([
        'old'     => function () use ($collection) {
            $s = makeStore(); $s->put('redirects', $collection);
        },
        'hydrate' => function () use ($modelArrays) {
            $s = makeStore(); $s->put('redirects', $modelArrays);
        },
        'new'     => function () use ($plainArray) {
            $s = makeStore(); $s->put('redirects', $plainArray);
        },
    ], $iterations);

    printf("%-8d %-16s %-16s %-16s\n",
        $count,
        round($writeResults['old'], 4) . ' ms',
        round($writeResults['hydrate'], 4) . ' ms',
        round($writeResults['new'], 4) . ' ms',
    );
}

echo PHP_EOL;

