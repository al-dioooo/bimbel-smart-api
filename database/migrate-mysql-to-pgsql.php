<?php
/**
 * One-off data copy: MySQL `bimbel_smart` -> PostgreSQL `bimbel_smart`.
 *
 * Schema is created by `php artisan migrate` beforehand; this only moves rows.
 * Runtime tables (cache, sessions, jobs, pulse) are deliberately skipped — they
 * regenerate themselves and carry no business meaning.
 */

$mysql = new PDO('mysql:host=127.0.0.1;port=3306;dbname=bimbel_smart;charset=utf8mb4', 'root', '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pgsql = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=bimbel_smart', 'postgres', '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Parent-before-child, so foreign keys hold at every step.
$tables = [
    'users',
    'mentor',
    'kelas',
    'siswa',
    'jadwal',
    'pengajuan_jadwal',
    'absensi',
    'aturan_gaji',
    'notifications',
    'report_gaji',
];

$dryRun = in_array('--dry-run', $argv, true);

/** Postgres column types, so values can be coerced to match. */
function pgTypes(PDO $pgsql, string $table): array {
    $stmt = $pgsql->prepare(
        "SELECT column_name, data_type FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = ?"
    );
    $stmt->execute([$table]);
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'data_type', 'column_name');
}

function coerce($value, ?string $pgType) {
    if ($value === null) return null;
    if ($pgType === 'boolean') {
        // PDO binds PHP false as '' for pgsql, which Postgres rejects, so send
        // a literal Postgres accepts for boolean input.
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? 'true' : 'false';
    }
    if (in_array($pgType, ['integer', 'bigint', 'smallint'], true)) return $value === '' ? null : (int) $value;
    if (in_array($pgType, ['numeric', 'double precision', 'real'], true)) return $value === '' ? null : (float) $value;
    return $value;
}

$pgsql->beginTransaction();
$summary = [];

try {
    // Load children-first for the wipe so FKs don't block it.
    foreach (array_reverse($tables) as $table) {
        $pgsql->exec('DELETE FROM "' . $table . '"');
    }

    foreach ($tables as $table) {
        $types = pgTypes($pgsql, $table);
        if (!$types) { $summary[] = [$table, 'skipped (no such table in postgres)']; continue; }

        $rows = $mysql->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) { $summary[] = [$table, '0 rows']; continue; }

        // Only columns that exist on both sides.
        $columns = array_values(array_filter(array_keys($rows[0]), fn($c) => isset($types[$c])));
        $dropped = array_diff(array_keys($rows[0]), $columns);

        $quoted = implode(', ', array_map(fn($c) => '"' . $c . '"', $columns));
        $holders = implode(', ', array_fill(0, count($columns), '?'));
        $insert = $pgsql->prepare("INSERT INTO \"$table\" ($quoted) VALUES ($holders)");

        foreach ($rows as $row) {
            $insert->execute(array_map(
                fn($c) => coerce($row[$c], $types[$c] ?? null),
                $columns
            ));
        }

        $note = count($rows) . ' rows';
        if ($dropped) $note .= ' (dropped columns: ' . implode(',', $dropped) . ')';
        $summary[] = [$table, $note];
    }

    // Explicit ids were inserted, so every identity sequence is still at 1.
    $seqFixed = [];
    foreach ($tables as $table) {
        $seq = $pgsql->query("SELECT pg_get_serial_sequence('\"$table\"', 'id')")->fetchColumn();
        if (!$seq) continue;
        $pgsql->exec("SELECT setval('$seq', COALESCE((SELECT MAX(id) FROM \"$table\"), 0) + 1, false)");
        $next = $pgsql->query("SELECT last_value FROM $seq")->fetchColumn();
        $seqFixed[] = "{$table}={$next}";
    }

    if ($dryRun) { $pgsql->rollBack(); echo "DRY RUN — rolled back\n\n"; }
    else { $pgsql->commit(); }

    foreach ($summary as [$t, $n]) printf("  %-22s %s\n", $t, $n);
    echo "\n  sequences reset: " . implode('  ', $seqFixed) . "\n";

} catch (Throwable $e) {
    $pgsql->rollBack();
    fwrite(STDERR, "FAILED (rolled back): " . $e->getMessage() . "\n");
    exit(1);
}
