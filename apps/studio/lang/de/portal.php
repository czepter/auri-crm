<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Client portal
    |--------------------------------------------------------------------------
    |
    | link_sent is deliberately non-committal. It is the answer whether the
    | address is known, unknown or rate-limited, because any difference would
    | turn the sign-in form into a way of asking the studio who its clients
    | are.
    |
    */

    'link_sent' => 'Wenn ein Zugang zu dieser Adresse gehört, ist die E-Mail unterwegs. Der Link gilt 15 Minuten.',

    // Uniform for expired, already used and never existed: all three mean the
    // same thing to whoever is holding the link.
    'link_dead' => 'Dieser Link gilt nicht mehr. Bitte einen neuen anfordern.',
];
