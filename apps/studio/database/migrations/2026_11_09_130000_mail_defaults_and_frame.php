<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Visus\Cuid2\Cuid2;

return new class extends Migration
{
    private const LEGACY = [
        'quote_sent' => ['quote', 'Angebot verschickt'],
        'invoice_sent' => ['invoice', 'Rechnung verschickt'],
        'payment_reminder' => ['reminder', 'Zahlungserinnerung'],
        'credit_note_sent' => ['credit_note', 'Gutschrift verschickt'],
    ];

    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $blueprint) {
            $blueprint->string('subject')->nullable();
        });

        Schema::table('studio_settings', function (Blueprint $blueprint) {
            $blueprint->json('mail_defaults')->nullable();
            $blueprint->boolean('mail_header_name')->default(true);
            $blueprint->boolean('mail_footer_legal')->default(true);
            $blueprint->string('mail_footer_extra')->nullable();
        });

        // The wording a studio typed into the four fixed pairs becomes a
        // template and that occasion's default, so nothing it wrote is lost.
        foreach (DB::table('studio_settings')->get() as $row) {
            $defaults = [];

            foreach (self::LEGACY as $occasion => [$prefix, $name]) {
                $subject = $row->{"{$prefix}_mail_subject"};
                $body = $row->{"{$prefix}_mail_body"};

                if (blank($subject) && blank($body)) {
                    continue;
                }

                $paragraphs = array_values(array_filter(preg_split('/\n{2,}/', trim((string) $body))));
                $family = (string) new Cuid2;

                DB::table('document_templates')->insert([
                    'public_id' => (string) new Cuid2,
                    'tenant_id' => $row->tenant_id,
                    'family_id' => $family,
                    'version' => 1,
                    'type' => 'mail',
                    'name' => $name,
                    'subject' => blank($subject) ? null : $subject,
                    'body' => collect($paragraphs)->map(fn (string $text) => '<p>'.e($text).'</p>')->implode(''),
                    'blocks' => json_encode(['blocks' => collect($paragraphs)->map(fn (string $text) => ['type' => 'paragraph', 'data' => ['text' => e($text)]])->all()]),
                    'is_current' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $defaults[$occasion] = $family;
            }

            if ($defaults !== []) {
                DB::table('studio_settings')->where('id', $row->id)->update(['mail_defaults' => json_encode($defaults)]);
            }
        }

        Schema::table('studio_settings', function (Blueprint $blueprint) {
            $blueprint->dropColumn([
                'invoice_mail_subject', 'invoice_mail_body', 'quote_mail_subject', 'quote_mail_body',
                'reminder_mail_subject', 'reminder_mail_body', 'credit_note_mail_subject', 'credit_note_mail_body',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('studio_settings', function (Blueprint $blueprint) {
            foreach (['invoice', 'quote', 'reminder', 'credit_note'] as $prefix) {
                $blueprint->string("{$prefix}_mail_subject")->nullable();
                $blueprint->text("{$prefix}_mail_body")->nullable();
            }

            $blueprint->dropColumn(['mail_defaults', 'mail_header_name', 'mail_footer_legal', 'mail_footer_extra']);
        });

        Schema::table('document_templates', function (Blueprint $blueprint) {
            $blueprint->dropColumn('subject');
        });
    }
};
