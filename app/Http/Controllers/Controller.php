<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    public function paginate($query, $limit = 15, $usePagination = true, $orderBy = "created_at", $direction = "desc")
    {
        if ($usePagination === 'false' || $usePagination === false)
            return $query->orderBy($orderBy, $direction)->get();

        return $query->orderBy($orderBy, $direction)->paginate($limit)->setPath("")->withQueryString();
    }
}
