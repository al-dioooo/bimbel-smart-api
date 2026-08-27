<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Cross-driver SQL fragments.
 *
 * The app targets PostgreSQL in production, MySQL on some local machines, and
 * SQLite in the test suite (see phpunit.xml). These three disagree on date
 * extraction and on whether LIKE is case sensitive, so the differences are
 * collected here instead of being spread across models and controllers.
 *
 * Named `Sql` rather than `Db` because PHP class names are case insensitive,
 * so `App\Support\Db` cannot coexist with the `DB` facade import above.
 */
class Sql
{
    public static function driver(): string
    {
        return DB::connection()->getDriverName();
    }

    /**
     * Case-insensitive LIKE operator.
     *
     * MySQL's default collation makes LIKE case insensitive; PostgreSQL's is
     * case sensitive and needs ILIKE. SQLite's LIKE is case insensitive for
     * ASCII, which is close enough for the test suite.
     */
    public static function like(): string
    {
        return self::driver() === 'pgsql' ? 'ilike' : 'like';
    }

    /** SQL expression yielding the year of a date column, as an integer. */
    public static function year(string $column): string
    {
        return match (self::driver()) {
            'pgsql'  => "EXTRACT(YEAR FROM {$column})::int",
            'sqlite' => "CAST(strftime('%Y', {$column}) AS INTEGER)",
            default  => "YEAR({$column})",
        };
    }

    /** SQL expression yielding the month (1-12) of a date column, as an integer. */
    public static function month(string $column): string
    {
        return match (self::driver()) {
            'pgsql'  => "EXTRACT(MONTH FROM {$column})::int",
            'sqlite' => "CAST(strftime('%m', {$column}) AS INTEGER)",
            default  => "MONTH({$column})",
        };
    }
}
