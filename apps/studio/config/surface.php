<?php

return [
    // Which surface this process serves. Exactly one route file is loaded.
    // The admin container is network-restricted and must never carry the
    // public routes; that is enforced by absence, not by middleware.
    'name' => env('APP_SURFACE', 'admin'),
];
