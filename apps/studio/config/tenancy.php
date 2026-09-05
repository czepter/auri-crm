<?php

return [
    // Dormant multi-tenancy seam. One studio today; the column exists so
    // activating tenancy later is a data migration, not a schema rewrite.
    'tenant_id' => env('TENANT_ID', '00000000-0000-4000-8000-000000000001'),
];
