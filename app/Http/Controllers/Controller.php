<?php

namespace App\Http\Controllers;

use App\Support\Sql;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * SQL expression for the year part of a date column.
     *
     * PostgreSQL (production), MySQL and SQLite (the test suite, see
     * phpunit.xml) all spell this differently — see App\Support\Sql.
     */
    protected function yearExpr(string $column): string
    {
        return Sql::year($column);
    }

    /** SQL expression for the month part of a date column. */
    protected function monthExpr(string $column): string
    {
        return Sql::month($column);
    }

    public function paginate($query, $limit = 15, $usePagination = true, $orderBy = "created_at", $direction = "desc")
    {
        if ($usePagination === 'false' || $usePagination === false)
            return $query->orderBy($orderBy, $direction)->get();

        return $query->orderBy($orderBy, $direction)->paginate($limit)->setPath("")->withQueryString();
    }
}
