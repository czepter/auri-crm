<?php

namespace App\Crm\Actions;

use App\Crm\Models\FormInstance;
use App\Crm\Models\FormTemplate;

/**
 * Populates a new form instance's fields from a template's current
 * questions — E-02's seed step, mirroring
 * App\Quotes\Actions\SeedQuoteFromPackage exactly.
 *
 * FormTemplate::copyFields() already returns a deep, disconnected copy — no
 * id and no form_template_id in it — so this only has to lay that array onto
 * the instance. Nothing written here ever looks at the template again:
 * editing it afterwards leaves every instance already seeded from it alone.
 */
class SeedFormInstanceFromTemplate
{
    public function __invoke(FormInstance $instance, FormTemplate $template): FormInstance
    {
        $instance->fields()->createMany(
            collect($template->copyFields())
                ->values()
                ->map(fn (array $field, int $index) => [...$field, 'sort_order' => $index])
                ->all()
        );

        return $instance;
    }
}
