<?php

// This map is the allow-list (see ProjectActivity), so an absence here is a
// decision, not an oversight. Left out on purpose, grouped by why: drafting
// churn on a quote in progress (quote.created, quote.updated); per-field or
// per-item writes whose milestone is already represented by a call site above
// — a form filled in one field at a time, a shot list ticked one row at a
// time (form_instance.updated, form_instance_field.answered,
// shot_list_item.checked, shot_list_item.unchecked); task completion, because
// Task.audience marks a task client-facing or internal and the event key
// carries no such distinction, so labelling it would put internal notes into
// what reads as the project's shared history (task.completed, task.reopened);
// and a booking attempt that failed rather than one that happened
// (project.booking_attempted).
return [
    'lead.captured' => 'Anfrage eingegangen',
    'lead.stage_changed' => 'Phase: :to',
    'lead.lost' => 'Anfrage verloren',
    'quote.sent' => 'Angebot gesendet',
    'quote.revised' => 'Angebot überarbeitet',
    'quote.accepted' => 'Angebot angenommen',
    'contract.render_requested' => 'Vertrag erstellt',
    'contract.signed' => 'Vertrag unterschrieben',
    'project.booked' => 'Projekt gebucht',
    'project.fields_changed' => 'Details geändert',
    'project.portal_shown' => 'Portal freigegeben',
    'project.portal_hidden' => 'Portal verborgen',
    'payment_plan.created' => 'Zahlungsplan angelegt',
    'payment_plan.updated' => 'Zahlungsplan geändert',
    'payment_installment.paid' => 'Zahlung erhalten',
    'shoot.status_changed' => 'Shooting-Status: :to',
    'shoot.primary_designated' => 'Haupttermin festgelegt',
    'shoot.cancelled' => 'Shooting abgesagt',
    'form_instance.created' => 'Fragebogen angelegt',
];
