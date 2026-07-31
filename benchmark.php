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

/**
 * DTO approach: caches an array of RedirectDTO objects directly.
 * No reconstruction needed on read — DTOs come straight out of the cache.
 */
function dtoApproach(SerializingStore $store): array
{
    return rememberOnStore($store, 'redirects', fn () =>
        Redirect::get()->map(fn (Redirect $r) => RedirectDTO::fromRedirect($r))->values()->all()
    );
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

echo str_repeat('─', 120) . PHP_EOL;
printf(
    "%-8s %-12s %-12s %-12s %-12s %-12s %-14s %-14s %-14s\n",
    'Count', 'Old', 'Hydrate', 'Plain arr', 'DTO',
    'Best vs old', 'Old size', 'Plain arr size', 'DTO size',
);
echo str_repeat('─', 120) . PHP_EOL;

foreach ($counts as $count) {
    seedRedirects($count);

    $oldStore     = makeStore();
    $hydrateStore = makeStore();
    $newStore     = makeStore();
    $dtoStore     = makeStore();

    oldApproach($oldStore);
    hydrateApproach($hydrateStore);
    newApproach($newStore);
    dtoApproach($dtoStore);

    $results = Benchmark::measure([
        'old'     => fn () => oldApproach($oldStore),
        'hydrate' => fn () => hydrateApproach($hydrateStore),
        'new'     => fn () => newApproach($newStore),
        'dto'     => fn () => dtoApproach($dtoStore),
    ], $iterations);

    $oldMs     = $results['old'];
    $hydrateMs = $results['hydrate'];
    $newMs     = $results['new'];
    $dtoMs     = $results['dto'];
    $bestMs    = min($newMs, $dtoMs);

    printf(
        "%-8d %-12s %-12s %-12s %-12s %-12s %-14s %-14s %-14s\n",
        $count,
        round($oldMs, 4) . ' ms',
        round($hydrateMs, 4) . ' ms',
        round($newMs, 4) . ' ms',
        round($dtoMs, 4) . ' ms',
        round($oldMs / $bestMs, 2) . '×',
        number_format(cacheSize($oldStore, 'redirects')) . ' B',
        number_format(cacheSize($newStore, 'redirects')) . ' B',
        number_format(cacheSize($dtoStore, 'redirects')) . ' B',
    );
}

echo str_repeat('─', 120) . PHP_EOL;

// ──────────────────────────────────────────────────────────────────────────────
// Write speed (serialisation only, no DB query)
// ──────────────────────────────────────────────────────────────────────────────

echo PHP_EOL . '── Write speed (serialisation only, warm DB, N=' . $iterations . ') ──────────────────────────────' . PHP_EOL;
printf("%-8s %-16s %-16s %-16s %-16s\n", 'Count', 'Old', 'Hydrate', 'Plain arr', 'DTO');
echo str_repeat('─', 70) . PHP_EOL;

foreach ($counts as $count) {
    seedRedirects($count);

    $collection  = Redirect::get();
    $modelArrays = $collection->toArray();
    $plainArray  = $collection->map(fn (Redirect $r) => [
        'old_url'     => $r->getOldUrl(),
        'new_url'     => $r->getNewUrl(),
        'status_code' => $r->getStatusCode(),
        'constraints' => $r->getConstraints(),
    ])->values()->all();
    $dtoArray = $collection->map(fn (Redirect $r) => RedirectDTO::fromRedirect($r))->values()->all();

    $writeResults = Benchmark::measure([
        'old'     => fn () => (makeStore())->put('redirects', $collection),
        'hydrate' => fn () => (makeStore())->put('redirects', $modelArrays),
        'new'     => fn () => (makeStore())->put('redirects', $plainArray),
        'dto'     => fn () => (makeStore())->put('redirects', $dtoArray),
    ], $iterations);

    printf("%-8d %-16s %-16s %-16s %-16s\n",
        $count,
        round($writeResults['old'], 4) . ' ms',
        round($writeResults['hydrate'], 4) . ' ms',
        round($writeResults['new'], 4) . ' ms',
        round($writeResults['dto'], 4) . ' ms',
    );
}

// ──────────────────────────────────────────────────────────────────────────────
// Cold-cache benchmark (DB query + write + read, N=10)
// ──────────────────────────────────────────────────────────────────────────────

echo PHP_EOL . '── Cold-cache speed (10 iterations, 500 redirects) ───────────────────────────' . PHP_EOL;

seedRedirects(500);

$coldResults = Benchmark::measure([
    'old (cold)'     => fn () => oldApproach(makeStore()),
    'hydrate (cold)' => fn () => hydrateApproach(makeStore()),
    'new (cold)'     => fn () => newApproach(makeStore()),
    'dto (cold)'     => fn () => dtoApproach(makeStore()),
], 10);

printf("Old:      %s ms/iter\n", round($coldResults['old (cold)'], 4));
printf("Hydrate:  %s ms/iter\n", round($coldResults['hydrate (cold)'], 4));
printf("Plain arr:%s ms/iter\n", round($coldResults['new (cold)'], 4));
printf("DTO:      %s ms/iter\n", round($coldResults['dto (cold)'], 4));

echo PHP_EOL;

