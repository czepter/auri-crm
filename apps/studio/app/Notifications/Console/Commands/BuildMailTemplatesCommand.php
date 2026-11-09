<?php

namespace App\Notifications\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Mjml\Mjml;
use Spatie\Mjml\ValidationLevel;

/**
 * Compiles resources/mail/*.mjml into the checked-in Blade views under
 * resources/views/vendor/mail/html — see docs/superpowers/specs/
 * 2026-09-05-studio-onboarding-mail-journey-design.md §1 for why this runs
 * at build time (dev machine, CI) and never at request time.
 */
class BuildMailTemplatesCommand extends Command
{
    protected $signature = 'mail:build';

    protected $description = 'Compiles the MJML mail sources into the checked-in Blade layout and button views.';

    // Single-pass, simultaneous replacement (strtr with an array never
    // rescans its own output), so no replacement text may itself contain
    // another sentinel — none of these do.
    private const SENTINELS = [
        '__SUBCOPY__' => '{{ $subcopy ?? \'\' }}',
        '__BRAND__' => '{{ $brand ?? config(\'app.name\') }}',
        '__LOGO_URL__' => '{{ $logoUrl ?? null }}',
        '__LOGO_IF__' => '@if(! empty($logoUrl))',
        '__LOGO_ENDIF__' => '@endif',
        // MJML joins neighbouring raw blocks without a break, and `@endif@if` does not compile.
        '__BRAND_IF__' => "\n@if(\$showBrand ?? true)",
        '__BRAND_ENDIF__' => '@endif',
        // The footer zone is opt-in: without a mailFooter the whole section
        // is skipped, so a mail that sets none shows no empty ruled band.
        '__FOOTER__' => '{!! $mailFooter ?? \'\' !!}',
        '__FOOTER_IF__' => '@if(! empty($mailFooter))',
        '__FOOTER_ENDIF__' => '@endif',
        '__ACCENT__' => '{{ $accent ?? \'#111111\' }}',
        '__URL__' => '{{ $url }}',
    ];

    public function handle(): int
    {
        $sourceDir = resource_path('mail');
        $outputDir = resource_path('views/vendor/mail/html');

        File::ensureDirectoryExists($outputDir);

        foreach (File::files($sourceDir) as $file) {
            if ($file->getExtension() !== 'mjml') {
                continue;
            }

            $result = Mjml::new()
                ->validationLevel(ValidationLevel::Skip)
                ->convert(File::get($file->getPathname()));

            if ($result->hasErrors()) {
                foreach ($result->errors() as $error) {
                    $this->error($error->formattedMessage());
                }

                return self::FAILURE;
            }

            $html = $result->html();

            // MJML always emits a complete document; only layout.mjml is one.
            // Every other source is a fragment embedded inside that layout.
            if ($file->getFilenameWithoutExtension() !== 'layout' && preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $matches)) {
                $html = trim($matches[1]);

                // The fragment is spliced into the layout's markdown slot, where
                // a blank line ends an HTML block and an indented line after it
                // opens a code block: the button arrived as escaped text in a
                // <pre>. Unindented and without blank lines it stays one block.
                $html = preg_replace('/^\s+/m', '', $html);
            }

            // layout.mjml's slot is the notification's whole markdown body
            // (greeting, lines, the button's own compiled fragment); Laravel's
            // stock html/layout.blade.php runs it through Markdown::parse()
            // for exactly this reason, and our override had silently dropped
            // that call — every notification rendered literal "# " greetings,
            // "**bold**" and "[text](url)" links. button.mjml's __SLOT__ is
            // only the button's own label text and must stay unparsed.
            $sentinels = self::SENTINELS + [
                '__SLOT__' => $file->getFilenameWithoutExtension() === 'layout'
                    ? '{!! \Illuminate\Mail\Markdown::parse($slot) !!}'
                    : '{{ $slot }}',
            ];

            $blade = strtr($html, $sentinels);

            File::put($outputDir.'/'.$file->getFilenameWithoutExtension().'.blade.php', $blade);
        }

        return self::SUCCESS;
    }
}
