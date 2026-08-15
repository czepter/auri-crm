<?php

use App\Enums\StaffRole;

it('previews a contract body against sample data with no unresolved placeholder', function () {
    $response = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen/vorschau', [
            'type' => 'contract',
            'body' => '<p>Vertrag mit {{ contact.name }} über {{ project.name }}.</p>',
        ])
        ->assertOk();

    expect($response->getContent())
        ->toContain('Lena Meier')
        ->toContain('Hochzeit Meier & Kowalski')
        ->not->toContain('{{');
});

it('previews a mail body unwrapped, while a contract preview carries the letterhead shell', function () {
    $mail = $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen/vorschau', ['type' => 'mail', 'body' => '<p>Hallo {{ contact.name }}</p>'])
        ->assertOk();

    expect($mail->getContent())->not->toContain('<html');
});

it('refuses a preview for a role without ManageContracts', function () {
    $this->actingAs(staffMember(StaffRole::SecondShooter), 'staff')
        ->post('/dokumentvorlagen/vorschau', ['type' => 'contract', 'body' => '<p>x</p>'])
        ->assertForbidden();
});

it('rejects a preview with an unknown document type', function () {
    $this->actingAs(staffMember(StaffRole::Owner), 'staff')
        ->post('/dokumentvorlagen/vorschau', ['type' => 'receipt', 'body' => '<p>x</p>'])
        ->assertInvalid(['type']);
});
