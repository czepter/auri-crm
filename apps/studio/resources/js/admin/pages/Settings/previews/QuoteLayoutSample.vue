<script setup lang="ts">
/**
 * Referenzmarkup fuer App\Quotes\Enums\QuoteLayout — eins zu eins die fuenf
 * Entwuerfe aus dem Design-Canvas (docs/superpowers/specs/
 * 2026-09-13-angebot-druck-layouts-design.md, §3), mit denselben
 * Beispieldaten. Kein Bildschirm der App: RenderQuotePdf baut dieselbe
 * Struktur serverseitig aus PHP, dieses Bauteil ist die versionierte
 * Referenz dafuer, nicht die Quelle.
 */
defineProps<{
  layout: 'classic' | 'minimal' | 'letter' | 'header_band' | 'structured'
}>()
</script>

<template>
  <div class="quote-layout-sample">
    <!-- Klassisch -->
    <div v-if="layout === 'classic'" class="k-a4">
      <div style="display:flex; justify-content:space-between; align-items:flex-start;">
        <div>
          <div class="k-wordmark">50mm freunde</div>
          <div class="k-wordmark-sub">Christian Zepter — Fotografie</div>

          <div class="k-recipient">
            <div class="from">50mm freunde, Christian Zepter · Am Planetarium 37 · 07743 Jena</div>
            <div class="name">Pfarrgemeinde St. Johann</div>
            <div>Kirchplatz 3</div>
            <div>07743 Jena</div>
          </div>
        </div>

        <table class="k-meta">
          <tr><td colspan="2" style="padding-bottom:14px;"><span class="k-title" style="font-size:17px; margin:0;">Angebot</span></td></tr>
          <tr><td>Angebotsnr.</td><td>2026-014</td></tr>
          <tr><td>Datum</td><td>12.09.2026</td></tr>
          <tr class="valid"><td>Gültig bis</td><td>26.09.2026</td></tr>
        </table>
      </div>

      <table class="k-table" style="margin-top:56px;">
        <thead>
          <tr>
            <th>Beschreibung</th>
            <th class="num" style="width:64px;">Menge</th>
            <th class="num" style="width:96px;">Einzelpreis</th>
            <th class="num">Betrag</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>
              Reportage Erstkommunion 2026
              <div class="k-item-desc">Begleitung von 2 Erstkommunionterminen, Bereitstellung in 2 Online-Galerien</div>
            </td>
            <td class="num">2</td>
            <td class="num">250,00&nbsp;€</td>
            <td class="num">500,00&nbsp;€</td>
          </tr>
          <tr>
            <td>
              Familienfoto-Ergänzung
              <div class="k-item-desc">Zusätzliches Gruppenfoto im Anschluss an den Termin</div>
            </td>
            <td class="num">1</td>
            <td class="num" colspan="2">inklusive</td>
          </tr>
        </tbody>
      </table>

      <table class="k-summary">
        <tr><td>Zwischensumme</td><td>500,00&nbsp;€</td></tr>
        <tr><td>Rabatt −10&nbsp;%</td><td>−50,00&nbsp;€</td></tr>
        <tr><td>Netto</td><td>450,00&nbsp;€</td></tr>
        <tr><td>USt. 19&nbsp;% von 450,00&nbsp;€</td><td>85,50&nbsp;€</td></tr>
        <tr class="total"><td>Gesamtbetrag</td><td>535,50&nbsp;€</td></tr>
      </table>

      <div class="k-footer">
        <strong>50mm freunde, Christian Zepter</strong> · Am Planetarium 37 · 07743 Jena · hi@50mm-freunde.de · 50mm-freunde.de<br>
        Steuernummer 162/292/11882
      </div>
    </div>

    <!-- Minimal -->
    <div v-else-if="layout === 'minimal'" class="m-a4">
      <div class="m-content">
        <div class="m-word">50mm freunde</div>

        <div class="m-head">
          <span class="m-num">Angebot 2026-014</span>
          <span class="m-valid">Gültig bis 26.09.2026</span>
        </div>

        <div class="m-to">
          für <strong>Pfarrgemeinde St. Johann</strong> · Kirchplatz 3, 07743 Jena
        </div>

        <table class="m-table">
          <tr>
            <td>
              Reportage Erstkommunion 2026 <span class="m-desc" style="display:inline;">· 2 × 250,00&nbsp;€</span>
              <div class="m-desc">Begleitung von 2 Erstkommunionterminen, Bereitstellung in 2 Online-Galerien</div>
            </td>
            <td>500,00&nbsp;€</td>
          </tr>
          <tr class="m-included">
            <td>
              Familienfoto-Ergänzung
              <div class="m-desc">Zusätzliches Gruppenfoto im Anschluss an den Termin</div>
            </td>
            <td>inklusive</td>
          </tr>
          <tr class="m-total-row">
            <td>Zwischensumme</td>
            <td>500,00&nbsp;€</td>
          </tr>
          <tr>
            <td class="m-sub">Rabatt −10&nbsp;%</td>
            <td class="m-sub">−50,00&nbsp;€</td>
          </tr>
          <tr>
            <td class="m-sub">Netto</td>
            <td class="m-sub">450,00&nbsp;€</td>
          </tr>
          <tr>
            <td class="m-sub">USt. 19&nbsp;% von 450,00&nbsp;€</td>
            <td class="m-sub">85,50&nbsp;€</td>
          </tr>
          <tr>
            <td class="m-sub">Gesamtbetrag</td>
            <td class="m-sub m-net">535,50&nbsp;€</td>
          </tr>
        </table>

        <div class="m-note">Alle Preise verstehen sich zzgl. 19&nbsp;% Umsatzsteuer.</div>
      </div>

      <div class="m-footer">
        50mm freunde, Christian Zepter — Am Planetarium 37, 07743 Jena — hi@50mm-freunde.de — Steuernummer 162/292/11882
      </div>
    </div>

    <!-- Brief -->
    <div v-else-if="layout === 'letter'" class="e-a4">
      <div class="e-top">
        <span>50mm freunde, Christian Zepter · Am Planetarium 37 · 07743 Jena</span>
        <span>Jena, 12.09.2026</span>
      </div>

      <div class="e-title">Angebot</div>
      <div class="e-sub">Nr. 2026-014 · gültig bis 26.09.2026</div>

      <div class="e-to">
        An<br><strong>Pfarrgemeinde St. Johann</strong><br>Kirchplatz 3, 07743 Jena
      </div>

      <div class="e-letter" style="margin-top:32px;">
        <p>vielen Dank für Ihre Anfrage zur Begleitung der Erstkommunion 2026. Ich freue mich, Ihnen folgendes Angebot zusammenzustellen.</p>
      </div>

      <table class="e-table">
        <thead>
          <tr><th>Beschreibung</th><th class="num" style="width:56px;">Menge</th><th class="num">Betrag</th></tr>
        </thead>
        <tbody>
          <tr>
            <td>
              Reportage Erstkommunion 2026
              <div class="e-desc">Begleitung von 2 Erstkommunionterminen, Bereitstellung in 2 Online-Galerien à 250,00&nbsp;€</div>
            </td>
            <td class="num">2</td>
            <td class="num">500,00&nbsp;€</td>
          </tr>
          <tr>
            <td>
              Familienfoto-Ergänzung
              <div class="e-desc">Zusätzliches Gruppenfoto im Anschluss an den Termin</div>
            </td>
            <td class="num">1</td>
            <td class="num">inklusive</td>
          </tr>
        </tbody>
      </table>

      <table class="e-summary">
        <tr><td>Zwischensumme</td><td>500,00&nbsp;€</td></tr>
        <tr><td>Rabatt −10&nbsp;%</td><td>−50,00&nbsp;€</td></tr>
        <tr><td>Netto</td><td>450,00&nbsp;€</td></tr>
        <tr><td>USt. 19&nbsp;%</td><td>85,50&nbsp;€</td></tr>
        <tr class="e-total"><td>Gesamtbetrag</td><td>535,50&nbsp;€</td></tr>
      </table>

      <div class="e-close">
        <p>Das Angebot ist bis zum 26.09.2026 gültig. Für Rückfragen stehe ich jederzeit zur Verfügung.</p>
        <div class="sig">Christian</div>
      </div>

      <div class="e-footer">
        50mm freunde, Christian Zepter · hi@50mm-freunde.de · 50mm-freunde.de · Steuernummer 162/292/11882
      </div>
    </div>

    <!-- Kopfband -->
    <div v-else-if="layout === 'header_band'" class="h-a4">
      <div class="h-band">
        <div class="h-word">50mm freunde</div>
        <div>
          <div class="h-band-title">Angebot</div>
          <div class="h-band-num">Nr. 2026-014</div>
        </div>
      </div>

      <div class="h-body">
        <div class="h-meta-row">
          <span>Datum <strong>12.09.2026</strong></span>
          <span>Gültig bis <strong>26.09.2026</strong></span>
          <span>Kundennr. <strong>014</strong></span>
        </div>

        <div class="h-to">
          <div class="from">50mm freunde, Christian Zepter · Am Planetarium 37 · 07743 Jena</div>
          <div class="name">Pfarrgemeinde St. Johann</div>
          <div>Kirchplatz 3</div>
          <div>07743 Jena</div>
        </div>

        <table class="h-table">
          <thead>
            <tr><th>Beschreibung</th><th class="num" style="width:64px;">Menge</th><th class="num" style="width:96px;">Einzelpreis</th><th class="num">Betrag</th></tr>
          </thead>
          <tbody>
            <tr>
              <td>
                Reportage Erstkommunion 2026
                <div class="h-desc">Begleitung von 2 Erstkommunionterminen, Bereitstellung in 2 Online-Galerien</div>
              </td>
              <td class="num">2</td>
              <td class="num">250,00&nbsp;€</td>
              <td class="num">500,00&nbsp;€</td>
            </tr>
            <tr>
              <td>
                Familienfoto-Ergänzung
                <div class="h-desc">Zusätzliches Gruppenfoto im Anschluss an den Termin</div>
              </td>
              <td class="num">1</td>
              <td class="num" colspan="2">inklusive</td>
            </tr>
          </tbody>
        </table>

        <div class="h-summary">
          <table>
            <tr><td>Zwischensumme</td><td>500,00&nbsp;€</td></tr>
            <tr><td>Rabatt −10&nbsp;%</td><td>−50,00&nbsp;€</td></tr>
            <tr><td>Netto</td><td>450,00&nbsp;€</td></tr>
            <tr><td>USt. 19&nbsp;%</td><td>85,50&nbsp;€</td></tr>
            <tr class="h-total"><td>Gesamtbetrag</td><td>535,50&nbsp;€</td></tr>
          </table>
        </div>

        <div class="h-footer">
          50mm freunde, Christian Zepter · hi@50mm-freunde.de · 50mm-freunde.de · Steuernummer 162/292/11882
        </div>
      </div>
    </div>

    <!-- Struktur -->
    <div v-else-if="layout === 'structured'" class="b-a4">
      <div class="b-head">
        <div class="b-word">50mm freunde</div>
        <div>
          <div class="b-title">Angebot Nr. 2026-014</div>
        </div>
      </div>

      <div class="b-cards">
        <div class="b-card">
          <div class="b-label">Empfänger</div>
          <div style="font-weight:600;">Pfarrgemeinde St. Johann</div>
          <div>Kirchplatz 3</div>
          <div>07743 Jena</div>
        </div>
        <div class="b-card">
          <div class="b-label">Details</div>
          <div class="b-meta-grid">
            <span class="k">Datum</span><span class="v">12.09.2026</span>
            <span class="k">Gültig bis</span><span class="v">26.09.2026</span>
            <span class="k">Kundennr.</span><span class="v">014</span>
          </div>
        </div>
      </div>

      <div class="b-card" style="padding:0; overflow:hidden;">
        <table class="b-table">
          <thead>
            <tr>
              <th style="padding-top:14px;">Beschreibung</th>
              <th class="num" style="padding-top:14px; width:56px;">Menge</th>
              <th class="num" style="padding-top:14px; width:88px;">Einzelpreis</th>
              <th class="num" style="padding-top:14px;">Betrag</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>
                Reportage Erstkommunion 2026
                <div class="b-desc">Begleitung von 2 Erstkommunionterminen, Bereitstellung in 2 Online-Galerien</div>
              </td>
              <td class="num">2</td>
              <td class="num">250,00&nbsp;€</td>
              <td class="num">500,00&nbsp;€</td>
            </tr>
            <tr>
              <td style="padding-bottom:14px;">
                Familienfoto-Ergänzung
                <div class="b-desc">Zusätzliches Gruppenfoto im Anschluss an den Termin</div>
              </td>
              <td class="num" style="padding-bottom:14px;">1</td>
              <td class="num" colspan="2" style="padding-bottom:14px;">inklusive</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="b-summary-card">
        <table>
          <tr><td>Zwischensumme</td><td>500,00&nbsp;€</td></tr>
          <tr><td>Rabatt −10&nbsp;%</td><td>−50,00&nbsp;€</td></tr>
          <tr><td>Netto</td><td>450,00&nbsp;€</td></tr>
          <tr><td>USt. 19&nbsp;%</td><td>85,50&nbsp;€</td></tr>
          <tr class="b-total"><td>Gesamtbetrag</td><td>535,50&nbsp;€</td></tr>
        </table>
      </div>

      <div class="b-card b-footer-card">
        50mm freunde, Christian Zepter · Am Planetarium 37 · 07743 Jena · hi@50mm-freunde.de · 50mm-freunde.de<br>
        Steuernummer 162/292/11882
      </div>
    </div>
  </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500&display=swap');
</style>

<style scoped>
.quote-layout-sample { display: inline-block; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15); }

/* Klassisch */
.k-a4 {
  width: 794px; height: 1123px; background: #FFFFFF; color: #191919;
  font-family: 'Hanken Grotesk', 'Helvetica Neue', Arial, sans-serif;
  font-size: 14px; line-height: 1.55; box-sizing: border-box;
  padding: 64px 72px 40px;
  display: flex; flex-direction: column;
}
.k-wordmark { font-size: 20px; font-weight: 700; letter-spacing: -0.01em; }
.k-wordmark-sub { font-size: 11px; color: #6B6B6B; margin-top: 2px; }
.k-recipient { margin-top: 44px; font-size: 14px; }
.k-recipient .from { font-size: 11px; color: #6B6B6B; margin-bottom: 10px; }
.k-recipient .name { font-weight: 600; }
.k-meta { border-collapse: collapse; }
.k-meta td { padding: 3px 0; font-size: 13px; }
.k-meta td:first-child { color: #6B6B6B; padding-right: 28px; }
.k-meta td:last-child { text-align: right; font-variant-numeric: tabular-nums; }
.k-meta .valid td { font-weight: 600; }
.k-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
.k-table th { text-align: left; font-size: 11px; font-weight: 600; color: #6B6B6B; text-transform: uppercase; letter-spacing: 0.03em; padding: 0 0 8px; border-bottom: 1.5px solid #191919; }
.k-table th.num, .k-table td.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.k-table td { padding: 12px 0; border-bottom: 0.75px solid #E9E9E7; vertical-align: top; }
.k-item-desc { font-size: 12.5px; color: #6B6B6B; margin-top: 3px; }
.k-summary { width: 260px; margin-left: auto; margin-top: 4px; }
.k-summary td { padding: 5px 0; font-size: 13px; }
.k-summary td:last-child { text-align: right; font-variant-numeric: tabular-nums; }
.k-summary .total td { border-top: 1.5px solid #191919; padding-top: 10px; font-weight: 700; font-size: 15px; }
.k-footer { margin-top: auto; padding-top: 14px; border-top: 0.75px solid #E9E9E7; font-size: 10.5px; color: #6B6B6B; text-align: center; }
.k-footer strong { color: #191919; font-weight: 600; }

/* Minimal */
.m-a4 {
  width: 794px; height: 1123px; background: #FFFFFF; color: #191919;
  font-family: 'Hanken Grotesk', 'Helvetica Neue', Arial, sans-serif;
  font-size: 14px; line-height: 1.6; box-sizing: border-box;
  padding: 96px 96px 56px;
  display: flex; flex-direction: column;
}
.m-content { max-width: 620px; margin: 0 auto; width: 100%; }
.m-word { font-size: 15px; font-weight: 700; }
.m-head { display: flex; justify-content: space-between; align-items: baseline; margin-top: 64px; }
.m-num { font-size: 11px; font-weight: 600; letter-spacing: 0.14em; text-transform: uppercase; color: #6B6B6B; }
.m-valid { font-size: 13px; color: #6B6B6B; }
.m-to { margin-top: 28px; font-size: 14px; color: #6B6B6B; }
.m-to strong { color: #191919; font-weight: 600; }
.m-table { width: 100%; border-collapse: collapse; margin-top: 40px; }
.m-table td { padding: 16px 0; vertical-align: top; }
.m-table tr + tr td { border-top: 1px solid #F0F0EE; }
.m-table td:last-child { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; padding-left: 24px; }
.m-desc { font-size: 12.5px; color: #6B6B6B; margin-top: 4px; }
.m-included { color: #6B6B6B; }
.m-sub { padding-top: 18px !important; }
.m-total-row td { border-top: 1px solid #191919 !important; padding-top: 16px !important; font-weight: 600; }
.m-net { font-family: 'Hanken Grotesk', serif; font-size: 26px; font-weight: 600; }
.m-note { margin-top: 44px; font-size: 12px; color: #6B6B6B; }
.m-footer { margin-top: auto; padding-top: 18px; font-size: 10.5px; color: #ABA6A2; }

/* Brief */
.e-a4 {
  width: 794px; height: 1123px; background: #FFFEFC; color: #191919;
  font-family: 'Hanken Grotesk', 'Helvetica Neue', Arial, sans-serif;
  font-size: 14px; line-height: 1.6; box-sizing: border-box;
  padding: 72px 96px 40px;
  display: flex; flex-direction: column;
}
.e-top { display: flex; justify-content: space-between; font-size: 12px; color: #6B6B6B; }
.e-title { font-family: 'Fraunces', Georgia, serif; font-size: 40px; font-weight: 500; margin: 28px 0 6px; }
.e-sub { font-size: 12px; color: #6B6B6B; margin-bottom: 40px; }
.e-to { font-size: 13px; color: #6B6B6B; margin-bottom: 4px; }
.e-to strong { color: #191919; font-weight: 600; font-size: 14px; }
.e-letter p { margin: 0 0 14px; max-width: 560px; }
.e-table { width: 100%; border-collapse: collapse; margin: 28px 0 20px; }
.e-table th { text-align: left; font-size: 10.5px; font-weight: 600; color: #6B6B6B; text-transform: uppercase; letter-spacing: 0.04em; padding: 0 16px 8px 0; border-bottom: 1px solid #DEDAD3; }
.e-table th:last-child, .e-table td:last-child { padding-right: 0; }
.e-table th.num, .e-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
.e-table td { padding: 12px 16px 12px 0; border-bottom: 1px solid #EFEBE4; vertical-align: top; }
.e-desc { font-size: 12.5px; color: #6B6B6B; font-style: italic; margin-top: 3px; }
.e-summary { width: 280px; margin-left: auto; }
.e-summary td { padding: 4px 0; font-size: 13px; }
.e-summary td:last-child { text-align: right; font-variant-numeric: tabular-nums; }
.e-summary .e-total td { border-top: 1px solid #191919; padding-top: 10px; font-family: 'Fraunces', serif; font-size: 19px; font-weight: 500; }
.e-close { margin-top: 48px; font-size: 13.5px; }
.e-close .sig { margin-top: 26px; font-family: 'Fraunces', serif; font-size: 20px; }
.e-footer { margin-top: auto; padding-top: 14px; border-top: 1px solid #EFEBE4; font-size: 10px; color: #A39C93; }

/* Kopfband */
.h-a4 {
  width: 794px; height: 1123px; background: #FFFFFF; color: #191919;
  font-family: 'Hanken Grotesk', 'Helvetica Neue', Arial, sans-serif;
  font-size: 14px; line-height: 1.55; box-sizing: border-box;
  display: flex; flex-direction: column;
}
.h-band { background: #1C6870; color: #FFFFFF; padding: 40px 72px; display: flex; justify-content: space-between; align-items: flex-end; }
.h-word { font-size: 18px; font-weight: 700; }
.h-band-title { font-size: 26px; font-weight: 700; text-align: right; }
.h-band-num { font-size: 12px; opacity: 0.85; text-align: right; margin-top: 4px; }
.h-body { padding: 40px 72px 40px; flex: 1; display: flex; flex-direction: column; }
.h-meta-row { display: flex; gap: 28px; font-size: 12px; color: #6B6B6B; padding-bottom: 20px; border-bottom: 1px solid #E9E9E7; }
.h-meta-row strong { color: #191919; font-weight: 600; }
.h-to { margin-top: 28px; font-size: 14px; }
.h-to .from { font-size: 11px; color: #6B6B6B; margin-bottom: 8px; }
.h-to .name { font-weight: 600; }
.h-table { width: 100%; border-collapse: collapse; margin-top: 40px; }
.h-table thead th { background: #EAF1F2; color: #1C6870; text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; padding: 10px 14px; }
.h-table thead th.num, .h-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
.h-table td { padding: 14px; border-bottom: 0.75px solid #E9E9E7; vertical-align: top; }
.h-desc { font-size: 12.5px; color: #6B6B6B; margin-top: 3px; }
.h-summary { width: 300px; margin-left: auto; margin-top: 20px; border: 1px solid #E9E9E7; border-radius: 4px; padding: 16px 18px; }
.h-summary table { width: 100%; border-collapse: collapse; }
.h-summary td { padding: 4px 0; font-size: 13px; }
.h-summary td:last-child { text-align: right; font-variant-numeric: tabular-nums; }
.h-summary .h-total td { border-top: 1px solid #1C6870; padding-top: 10px; font-weight: 700; color: #1C6870; font-size: 15px; }
.h-footer { margin-top: auto; padding-top: 12px; border-top: 3px solid #1C6870; font-size: 10.5px; color: #6B6B6B; }

/* Struktur */
.b-a4 {
  width: 794px; height: 1123px; background: #FFFFFF; color: #191919;
  font-family: 'Hanken Grotesk', 'Helvetica Neue', Arial, sans-serif;
  font-size: 13.5px; line-height: 1.5; box-sizing: border-box;
  padding: 56px 64px 32px;
  display: flex; flex-direction: column;
}
.b-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.b-word { font-size: 18px; font-weight: 700; }
.b-title { font-size: 20px; font-weight: 700; text-align: right; }
.b-card { border: 1px solid #DCDAD6; border-radius: 3px; padding: 16px 18px; margin-bottom: 16px; }
.b-cards { display: grid; grid-template-columns: 1.3fr 1fr; gap: 16px; }
.b-label { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: #6B6B6B; margin-bottom: 8px; }
.b-meta-grid { display: grid; grid-template-columns: auto 1fr; gap: 6px 16px; font-size: 12.5px; }
.b-meta-grid .k { color: #6B6B6B; }
.b-meta-grid .v { text-align: right; font-variant-numeric: tabular-nums; }
.b-table { width: 100%; border-collapse: collapse; }
.b-table th { text-align: left; font-size: 10.5px; font-weight: 600; color: #6B6B6B; text-transform: uppercase; letter-spacing: 0.03em; padding: 0 12px 8px; }
.b-table th.num, .b-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
.b-table td { padding: 10px 12px; vertical-align: top; }
.b-table tbody tr:nth-child(odd) { background: #FAF9F7; }
.b-desc { font-size: 12px; color: #6B6B6B; margin-top: 2px; }
.b-summary-card { width: 300px; margin-left: auto; margin-top: 16px; }
.b-summary-card table { width: 100%; border-collapse: collapse; }
.b-summary-card td { padding: 4px 0; font-size: 13px; }
.b-summary-card td:last-child { text-align: right; font-variant-numeric: tabular-nums; }
.b-summary-card .b-total td { border-top: 1px solid #191919; padding-top: 9px; font-weight: 700; font-size: 14.5px; }
.b-footer-card { margin-top: auto; font-size: 10.5px; color: #6B6B6B; text-align: center; }
</style>
