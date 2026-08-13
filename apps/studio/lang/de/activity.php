<?php

// This map is the allow-list (see ProjectActivity), so an absence here is a
// decision, not an oversight. Deliberately unlabelled: quote.created,
// quote.updated, form_instance.updated, form_instance_field.answered,
// project.booking_attempted. Those are drafting churn, per-field edits and a
// failed attempt — form_instance_field.answered alone would fire once per
// answered field and bury everything else in the feed. The feed shows the
// milestone a run of edits produces, not every write along the way.
return [
    'lead.captured' => 'Anfrage eingegangen',
    'lead.stage_changed' => 'Phase gewechselt',
    'lead.lost' => 'Anfrage verloren',
    'quote.sent' => 'Angebot gesendet',
    'quote.revised' => 'Angebot überarbeitet',
    'quote.accepted' => 'Angebot angenommen',
    'contract.render_requested' => 'Vertrag erstellt',
    'contract.signed' => 'Vertrag unterschrieben',
    'project.booked' => 'Projekt gebucht',
    'payment_plan.created' => 'Zahlungsplan angelegt',
    'payment_plan.updated' => 'Zahlungsplan geändert',
    'payment_installment.paid' => 'Zahlung erhalten',
    'shoot.status_changed' => 'Shooting-Status geändert',
    'shoot.primary_designated' => 'Haupttermin festgelegt',
    'shoot.cancelled' => 'Shooting abgesagt',
    'form_instance.created' => 'Fragebogen angelegt',
];
