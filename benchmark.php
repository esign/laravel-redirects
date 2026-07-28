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
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
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

function makeCache(): Repository
{
    return new Repository(new ArrayStore(), []);
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
function oldApproach(Repository $cache): array
{
    $redirects = $cache->remember('redirects', 3600, fn () => Redirect::get());

    return $redirects->map(fn (Redirect $r) => RedirectDTO::fromRedirect($r))->toArray();
}

/**
 * NEW approach: caches plain arrays only.
 * DTOs are reconstructed from the plain array on every read.
 */
function newApproach(Repository $cache): array
{
    $data = $cache->remember('redirects', 3600, fn () =>
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

function cacheSize(Repository $cache, string $key): int
{
    // Retrieve serialised representation that PHP stores in memory
    $value = $cache->get($key);
    return strlen(serialize($value));
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

echo str_repeat('─', 90) . PHP_EOL;
printf(
    "%-8s %-14s %-14s %-14s %-14s %-14s\n",
    'Count',
    'Old speed',
    'New speed',
    'Speedup',
    'Old size',
    'New size',
);
echo str_repeat('─', 90) . PHP_EOL;

foreach ($counts as $count) {
    seedRedirects($count);

    // ── Speed (warm cache — only deserialisation + DTO mapping counted) ──
    $oldCache = makeCache();
    $newCache = makeCache();

    // Prime both caches with one uncached call each
    oldApproach($oldCache);
    newApproach($newCache);

    $results = Benchmark::measure([
        'old' => fn () => oldApproach($oldCache),
        'new' => fn () => newApproach($newCache),
    ], $iterations);

    $oldMs = $results['old'];
    $newMs = $results['new'];

    // ── Cache payload size ──
    $oldBytes = cacheSize($oldCache, 'redirects');
    $newBytes = cacheSize($newCache, 'redirects');

    $speedup = $oldMs > 0 ? round($oldMs / $newMs, 2) : '∞';

    printf(
        "%-8d %-14s %-14s %-14s %-14s %-14s\n",
        $count,
        round($oldMs, 4) . ' ms',
        round($newMs, 4) . ' ms',
        "{$speedup}×",
        number_format($oldBytes) . ' B',
        number_format($newBytes) . ' B',
    );
}

echo str_repeat('─', 90) . PHP_EOL;

// ──────────────────────────────────────────────────────────────────────────────
// Memory breakdown (single run, 1000 redirects)
// ──────────────────────────────────────────────────────────────────────────────

echo PHP_EOL . '── Memory & payload size (1000 redirects, warm cache) ───────────────────────────' . PHP_EOL;

seedRedirects(1000);
$oldCacheMem = makeCache();
$newCacheMem = makeCache();
$oldResult = oldApproach($oldCacheMem); // prime + capture result
$newResult = newApproach($newCacheMem); // prime + capture result

$oldMem = memoryDelta(fn () => oldApproach($oldCacheMem));
$newMem = memoryDelta(fn () => newApproach($newCacheMem));

$oldResultSize = inMemorySize($oldResult);
$newResultSize = inMemorySize($newResult);

printf("Old approach memory delta:   %s KB\n", number_format($oldMem / 1024, 2));
printf("New approach memory delta:   %s KB\n", number_format($newMem / 1024, 2));
printf("Old result serialised size:  %s B\n",  number_format($oldResultSize));
printf("New result serialised size:  %s B\n",  number_format($newResultSize));

// ──────────────────────────────────────────────────────────────────────────────
// Cold-cache benchmark (includes DB query + serialisation cost)
// ──────────────────────────────────────────────────────────────────────────────

echo PHP_EOL . '── Cold-cache speed (10 iterations, 500 redirects) ────────────────────────────' . PHP_EOL;

seedRedirects(500);

$coldResults = Benchmark::measure([
    'old (cold)' => function () {
        $cache = makeCache();
        return oldApproach($cache);
    },
    'new (cold)' => function () {
        $cache = makeCache();
        return newApproach($cache);
    },
], 10);

printf("Old cold-cache: %s ms/iter\n", round($coldResults['old (cold)'], 4));
printf("New cold-cache: %s ms/iter\n", round($coldResults['new (cold)'], 4));

// ──────────────────────────────────────────────────────────────────────────────
// Isolated write benchmark (serialisation only, no DB query)
// ──────────────────────────────────────────────────────────────────────────────

echo PHP_EOL . '── Write speed (serialisation only, warm DB, N=' . $iterations . ') ─────────────────────' . PHP_EOL;

$writeCounts = [10, 100, 500, 1000, 10000];

printf("%-8s %-16s %-16s %-10s\n", 'Count', 'Old write', 'New write', 'Speedup');
echo str_repeat('─', 55) . PHP_EOL;

foreach ($writeCounts as $count) {
    seedRedirects($count);

    // Pre-fetch the raw collection and array so the DB query doesn't pollute timing
    $collection = Redirect::get();
    $plainArray = $collection->map(fn (Redirect $r) => [
        'old_url'     => $r->getOldUrl(),
        'new_url'     => $r->getNewUrl(),
        'status_code' => $r->getStatusCode(),
        'constraints' => $r->getConstraints(),
    ])->values()->all();

    $writeResults = Benchmark::measure([
        'old' => function () use ($collection) {
            $cache = makeCache();
            $cache->remember('redirects', 3600, fn () => $collection);
        },
        'new' => function () use ($plainArray) {
            $cache = makeCache();
            $cache->remember('redirects', 3600, fn () => $plainArray);
        },
    ], $iterations);

    $oldW = $writeResults['old'];
    $newW = $writeResults['new'];
    $speedup = $oldW > 0 ? round($oldW / $newW, 2) : '∞';

    printf("%-8d %-16s %-16s %-10s\n",
        $count,
        round($oldW, 4) . ' ms',
        round($newW, 4) . ' ms',
        "{$speedup}×",
    );
}

echo PHP_EOL;
