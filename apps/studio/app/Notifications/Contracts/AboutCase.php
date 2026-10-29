<?php

namespace App\Notifications\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * A notification about one case. InAppTransport keeps the case's id on the
 * row so the bell can link to it; the kernel cannot name ClientCase itself.
 */
interface AboutCase
{
    public function case(): ?Model;
}
