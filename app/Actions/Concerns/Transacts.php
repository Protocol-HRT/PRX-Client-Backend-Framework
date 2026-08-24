<?php

namespace App\Actions\Concerns;

use Closure;
use Illuminate\Support\Facades\DB;

trait Transacts
{
    protected function tx(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }
}
