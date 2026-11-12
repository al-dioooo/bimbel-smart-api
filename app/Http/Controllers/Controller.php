<?php

namespace App\Http\Controllers;

use App\Support\Sql;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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
            'Anda tidak memiliki akses ke data ini.'
        );
    }

    /**
     * A failed write: 500 with a user-facing message. The driver error (often
     * the SQL, with bound values) is logged, and only returned while debugging.
     */
    protected function serverError(string $message, \Throwable $e): JsonResponse
    {
        report($e);

        return response()->json(array_filter([
            'message' => $message,
            'error' => config('app.debug') ? $e->getMessage() : null,
        ]), 500);
    }

    /** Applies $fn to every row of a paginate() result, paginated or not. */
    protected function mapRows($data, callable $fn)
    {
        return $data instanceof AbstractPaginator ? $data->through($fn) : $data->map($fn);
    }

    /** paginate() for rows already in memory, with the same parameters. */
    protected function paginateCollection(Collection $rows, $limit = 15, $usePagination = true)
    {
        if ($usePagination === 'false' || $usePagination === false) {
            return $rows->values();
        }

        $limit = max(1, (int) $limit);
        $page = LengthAwarePaginator::resolveCurrentPage();

        return (new LengthAwarePaginator($rows->forPage($page, $limit)->values(), $rows->count(), $limit, $page))
            ->setPath('')
            ->withQueryString();
    }

    public function paginate($query, $limit = 15, $usePagination = true, $orderBy = 'created_at', $direction = 'desc')
    {
        if ($usePagination === 'false' || $usePagination === false) {
            return $query->orderBy($orderBy, $direction)->get();
        }

        return $query->orderBy($orderBy, $direction)->paginate($limit)->setPath('')->withQueryString();
    }
}
