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

    /**
     * 403 unless the user is an admin or the mentor who owns the record.
     * `$mentorId` is the owning kelas's mentor_id (null when unassigned).
     */
    protected function authorizeOwner($mentorId): void
    {
        $user = auth()->user();

        if ($user?->isAdmin()) {
            return;
        }

        abort_unless(
            $mentorId !== null && $user?->mentor && (int) $mentorId === (int) $user->mentor->id,
            403,
            'You do not have access to this record.'
        );
    }

    public function paginate($query, $limit = 15, $usePagination = true, $orderBy = "created_at", $direction = "desc")
    {
        if ($usePagination === 'false' || $usePagination === false)
            return $query->orderBy($orderBy, $direction)->get();

        return $query->orderBy($orderBy, $direction)->paginate($limit)->setPath("")->withQueryString();
    }
}
