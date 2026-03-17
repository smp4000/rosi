<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use Illuminate\Database\Seeder;

/**
 * Erstellt globale System-Dokumentvorlagen fuer HR-Dokumente.
 * Globale Vorlagen (tenant_id = NULL) sind fuer alle Mandanten verfuegbar.
 */
class DocumentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'type' => 'employment_contract',
                'category' => 'hr',
                'name' => 'Arbeitsvertrag',
                'description' => 'Standard-Arbeitsvertrag fuer Tankstellen-Mitarbeiter',
                'content' => $this->getEmploymentContractContent(),
                'variables' => [
                    'mitarbeiter_name' => 'Vollstaendiger Name des Mitarbeiters',
                    'mitarbeiter_adresse' => 'Adresse des Mitarbeiters',
                    'arbeitgeber_name' => 'Name des Arbeitgebers',
                    'arbeitgeber_adresse' => 'Adresse des Arbeitgebers',
                    'eintrittsdatum' => 'Eintrittsdatum',
                    'gehalt' => 'Brutto-Gehalt',
                    'wochenstunden' => 'Wochenarbeitszeit',
                    'urlaubstage' => 'Jahresurlaub in Tagen',
                    'probezeit_ende' => 'Ende der Probezeit',
                    'datum_heute' => 'Heutiges Datum',
                    'ort_datum' => 'Ort und Datum',
                ],
                'sort_order' => 1,
            ],
            [
                'type' => 'termination',
                'category' => 'hr',
                'name' => 'Kuendigung',
                'description' => 'Kuendigungsschreiben (ordentlich)',
                'content' => $this->getTerminationContent(),
                'variables' => [
                    'mitarbeiter_name' => 'Name des Mitarbeiters',
                    'mitarbeiter_adresse' => 'Adresse des Mitarbeiters',
                    'arbeitgeber_name' => 'Name des Arbeitgebers',
                    'datum_heute' => 'Heutiges Datum',
                    'ort_datum' => 'Ort und Datum',
                ],
                'sort_order' => 2,
            ],
            [
                'type' => 'warning',
                'category' => 'hr',
                'name' => 'Abmahnung',
                'description' => 'Abmahnung wegen Pflichtverletzung',
                'content' => $this->getWarningContent(),
                'variables' => [
                    'mitarbeiter_name' => 'Name des Mitarbeiters',
                    'mitarbeiter_adresse' => 'Adresse des Mitarbeiters',
                    'arbeitgeber_name' => 'Name des Arbeitgebers',
                    'datum_heute' => 'Heutiges Datum',
                    'ort_datum' => 'Ort und Datum',
                ],
                'sort_order' => 3,
            ],
            [
                'type' => 'work_certificate',
                'category' => 'hr',
                'name' => 'Arbeitszeugnis',
                'description' => 'Qualifiziertes Arbeitszeugnis',
                'content' => $this->getWorkCertificateContent(),
                'variables' => [
                    'mitarbeiter_name' => 'Name des Mitarbeiters',
                    'mitarbeiter_geburtsdatum' => 'Geburtsdatum',
                    'mitarbeiter_geburtsort' => 'Geburtsort',
                    'arbeitgeber_name' => 'Name des Arbeitgebers',
                    'eintrittsdatum' => 'Eintrittsdatum',
                    'austrittsdatum' => 'Austrittsdatum',
                    'datum_heute' => 'Heutiges Datum',
                    'ort_datum' => 'Ort und Datum',
                ],
                'sort_order' => 4,
            ],
            [
                'type' => 'interim_certificate',
                'category' => 'hr',
                'name' => 'Zwischenzeugnis',
                'description' => 'Qualifiziertes Zwischenzeugnis',
                'content' => $this->getInterimCertificateContent(),
                'variables' => [
                    'mitarbeiter_name' => 'Name des Mitarbeiters',
                    'arbeitgeber_name' => 'Name des Arbeitgebers',
                    'eintrittsdatum' => 'Eintrittsdatum',
                    'datum_heute' => 'Heutiges Datum',
                    'ort_datum' => 'Ort und Datum',
                ],
                'sort_order' => 5,
            ],
            [
                'type' => 'amendment',
                'category' => 'hr',
                'name' => 'Aenderungsvertrag',
                'description' => 'Aenderungsvertrag zum bestehenden Arbeitsvertrag',
                'content' => $this->getAmendmentContent(),
                'variables' => [
                    'mitarbeiter_name' => 'Name des Mitarbeiters',
                    'mitarbeiter_adresse' => 'Adresse des Mitarbeiters',
                    'arbeitgeber_name' => 'Name des Arbeitgebers',
                    'arbeitgeber_adresse' => 'Adresse des Arbeitgebers',
                    'eintrittsdatum' => 'Datum des Originalvertrags',
                    'datum_heute' => 'Heutiges Datum',
                    'ort_datum' => 'Ort und Datum',
                ],
                'sort_order' => 6,
            ],
        ];

        foreach ($templates as $template) {
            DocumentTemplate::updateOrCreate(
                ['type' => $template['type'], 'tenant_id' => null, 'name' => $template['name']],
                array_merge($template, [
                    'tenant_id' => null,
                    'is_active' => true,
                    'is_default' => true,
                ]),
            );
        }

        $this->command->info('6 globale Dokumentvorlagen erstellt/aktualisiert.');
    }

    protected function getEmploymentContractContent(): string
    {
        return '<div class="header"><h1>Arbeitsvertrag</h1></div>

<p>Zwischen</p>
<p><strong>{{arbeitgeber_name}}</strong><br>{{arbeitgeber_adresse}}<br><em>(nachfolgend &bdquo;Arbeitgeber&ldquo;)</em></p>

<p>und</p>
<p><strong>{{mitarbeiter_name}}</strong><br>{{mitarbeiter_adresse}}<br><em>(nachfolgend &bdquo;Arbeitnehmer/in&ldquo;)</em></p>

<p>wird folgender Arbeitsvertrag geschlossen:</p>

<h2>&sect; 1 Beginn und Dauer</h2>
<p>Das Arbeitsverhaeltnis beginnt am <strong>{{eintrittsdatum}}</strong> und wird auf unbestimmte Zeit geschlossen.</p>
<p>Die ersten sechs Monate gelten als Probezeit. Waehrend der Probezeit kann das Arbeitsverhaeltnis von beiden Seiten mit einer Frist von zwei Wochen gekuendigt werden.</p>

<h2>&sect; 2 Taetigkeit</h2>
<p>Der/Die Arbeitnehmer/in wird als Mitarbeiter/in im Tankstellenbetrieb eingestellt. Der Arbeitgeber behaelt sich vor, dem/der Arbeitnehmer/in andere zumutbare Taetigkeiten zuzuweisen.</p>

<h2>&sect; 3 Arbeitszeit</h2>
<p>Die regelmaessige woechentliche Arbeitszeit betraegt <strong>{{wochenstunden}} Stunden</strong>. Die Verteilung der Arbeitszeit richtet sich nach den betrieblichen Erfordernissen.</p>

<h2>&sect; 4 Verguetung</h2>
<p>Der/Die Arbeitnehmer/in erhaelt eine monatliche Bruttoverguetung in Hoehe von <strong>{{gehalt}} EUR</strong>. Die Auszahlung erfolgt jeweils zum Ende des Monats auf das vom Arbeitnehmer angegebene Konto.</p>

<h2>&sect; 5 Urlaub</h2>
<p>Der/Die Arbeitnehmer/in hat Anspruch auf <strong>{{urlaubstage}} Arbeitstage</strong> Erholungsurlaub pro Kalenderjahr. Die Lage des Urlaubs ist mit dem Arbeitgeber abzustimmen.</p>

<h2>&sect; 6 Kuendigungsfristen</h2>
<p>Nach Ablauf der Probezeit gelten die gesetzlichen Kuendigungsfristen. Die Kuendigung bedarf der Schriftform.</p>

<h2>&sect; 7 Nebentaetigkeiten</h2>
<p>Jede entgeltliche Nebentaetigkeit bedarf der vorherigen schriftlichen Zustimmung des Arbeitgebers.</p>

<h2>&sect; 8 Verschwiegenheit</h2>
<p>Der/Die Arbeitnehmer/in verpflichtet sich, ueber alle Betriebs- und Geschaeftsgeheimnisse Stillschweigen zu bewahren. Diese Pflicht besteht auch nach Beendigung des Arbeitsverhaeltnisses fort.</p>

<h2>&sect; 9 Schlussbestimmungen</h2>
<p>Aenderungen und Ergaenzungen dieses Vertrages beduerfen der Schriftform. Sollte eine Bestimmung unwirksam sein, bleibt der Vertrag im Uebrigen wirksam.</p>

<p style="margin-top: 40px;">{{ort_datum}}</p>';
    }

    protected function getTerminationContent(): string
    {
        return '<p><strong>{{arbeitgeber_name}}</strong></p>

<p>An<br>{{mitarbeiter_name}}<br>{{mitarbeiter_adresse}}</p>

<p style="text-align: right;">{{ort_datum}}</p>

<h2>Kuendigung des Arbeitsverhaeltnisses</h2>

<p>Sehr geehrte/r {{mitarbeiter_name}},</p>

<p>hiermit kuendigen wir das mit Ihnen bestehende Arbeitsverhaeltnis ordentlich und fristgerecht zum naechstmoeglichen Zeitpunkt.</p>

<p>Unter Beruecksichtigung der vertraglichen und gesetzlichen Kuendigungsfristen endet das Arbeitsverhaeltnis am <strong>_______________</strong>.</p>

<p>Wir bitten Sie, bis zum Beendigungszeitpunkt saemtliche im Eigentum des Unternehmens stehenden Gegenstaende (Schluessel, Arbeitskleidung etc.) zurueckzugeben.</p>

<p>Sie haben Anspruch auf ein qualifiziertes Arbeitszeugnis. Bitte teilen Sie uns mit, falls Sie dieses wuenschen.</p>

<p>Bitte bestaetigen Sie den Erhalt dieser Kuendigung durch Ihre Unterschrift.</p>

<p>Mit freundlichen Gruessen</p>';
    }

    protected function getWarningContent(): string
    {
        return '<p><strong>{{arbeitgeber_name}}</strong></p>

<p>An<br>{{mitarbeiter_name}}<br>{{mitarbeiter_adresse}}</p>

<p style="text-align: right;">{{ort_datum}}</p>

<h2>Abmahnung</h2>

<p>Sehr geehrte/r {{mitarbeiter_name}},</p>

<p>hiermit mahnen wir Sie wegen folgender Pflichtverletzung ab:</p>

<p><strong>Sachverhalt:</strong></p>
<p><em>[Hier den konkreten Vorfall beschreiben: Datum, Uhrzeit, Art der Pflichtverletzung]</em></p>

<p><strong>Pflichtverstoß:</strong></p>
<p>Ihr Verhalten verstoesst gegen Ihre arbeitsvertraglichen Pflichten. Sie sind verpflichtet, <em>[betroffene Pflicht benennen]</em>.</p>

<p><strong>Aufforderung:</strong></p>
<p>Wir fordern Sie hiermit auf, das beanstandete Verhalten kuenftig zu unterlassen und Ihren arbeitsvertraglichen Pflichten ordnungsgemaess nachzukommen.</p>

<p><strong>Hinweis:</strong></p>
<p>Im Wiederholungsfall behalten wir uns weitergehende arbeitsrechtliche Massnahmen bis hin zur Kuendigung des Arbeitsverhaeltnisses ausdruecklich vor.</p>

<p>Diese Abmahnung wird zu Ihrer Personalakte genommen.</p>

<p>Bitte bestaetigen Sie den Empfang dieser Abmahnung durch Ihre Unterschrift. Die Unterschrift stellt lediglich eine Empfangsbestaetigung dar.</p>

<p>Mit freundlichen Gruessen</p>';
    }

    protected function getWorkCertificateContent(): string
    {
        return '<h1 style="text-align: center;">Arbeitszeugnis</h1>

<p>{{mitarbeiter_name}}, geboren am {{mitarbeiter_geburtsdatum}} in {{mitarbeiter_geburtsort}}, war vom {{eintrittsdatum}} bis zum {{austrittsdatum}} in unserem Unternehmen als Mitarbeiter/in im Tankstellenbetrieb taetig.</p>

<h2>Aufgabenbereich</h2>
<p>Zu den Aufgaben von {{mitarbeiter_name}} gehoerten insbesondere:</p>
<ul>
    <li>Bedienung und Abrechnung der Kasse</li>
    <li>Kundenberatung und -betreuung</li>
    <li>Warenpraesentation und Regalbestickung</li>
    <li>Kontrolle der Warenbestaende und Bestellwesen</li>
    <li>Einhaltung der Hygiene- und Sicherheitsvorschriften</li>
</ul>

<h2>Leistungsbeurteilung</h2>
<p><em>[Hier die individuelle Leistungsbeurteilung einfuegen]</em></p>

<h2>Sozialverhalten</h2>
<p><em>[Hier die Beurteilung des Sozialverhaltens einfuegen]</em></p>

<h2>Beendigungsgrund</h2>
<p><em>[Hier den Beendigungsgrund und ggf. die Dankes-/Bedauernsformel einfuegen]</em></p>

<p>Wir wuenschen {{mitarbeiter_name}} fuer den weiteren beruflichen und persoenlichen Lebensweg alles Gute.</p>

<p>{{ort_datum}}</p>
<p>{{arbeitgeber_name}}</p>';
    }

    protected function getInterimCertificateContent(): string
    {
        return '<h1 style="text-align: center;">Zwischenzeugnis</h1>

<p>{{mitarbeiter_name}} ist seit dem {{eintrittsdatum}} in unserem Unternehmen als Mitarbeiter/in im Tankstellenbetrieb taetig.</p>

<h2>Aufgabenbereich</h2>
<p>Zu den Aufgaben von {{mitarbeiter_name}} gehoeren insbesondere:</p>
<ul>
    <li>Bedienung und Abrechnung der Kasse</li>
    <li>Kundenberatung und -betreuung</li>
    <li>Warenpraesentation und Regalbestickung</li>
    <li>Kontrolle der Warenbestaende und Bestellwesen</li>
    <li>Einhaltung der Hygiene- und Sicherheitsvorschriften</li>
</ul>

<h2>Leistungsbeurteilung</h2>
<p><em>[Hier die individuelle Leistungsbeurteilung einfuegen]</em></p>

<h2>Sozialverhalten</h2>
<p><em>[Hier die Beurteilung des Sozialverhaltens einfuegen]</em></p>

<p>Dieses Zwischenzeugnis wird auf Wunsch von {{mitarbeiter_name}} ausgestellt.</p>

<p>{{ort_datum}}</p>
<p>{{arbeitgeber_name}}</p>';
    }

    protected function getAmendmentContent(): string
    {
        return '<div class="header"><h1>Aenderungsvertrag</h1></div>

<p>zum Arbeitsvertrag vom {{eintrittsdatum}}</p>

<p>Zwischen</p>
<p><strong>{{arbeitgeber_name}}</strong><br>{{arbeitgeber_adresse}}<br><em>(nachfolgend &bdquo;Arbeitgeber&ldquo;)</em></p>

<p>und</p>
<p><strong>{{mitarbeiter_name}}</strong><br>{{mitarbeiter_adresse}}<br><em>(nachfolgend &bdquo;Arbeitnehmer/in&ldquo;)</em></p>

<p>wird folgende Aenderung des bestehenden Arbeitsvertrages vereinbart:</p>

<h2>&sect; 1 Aenderungen</h2>
<p><em>[Hier die konkreten Aenderungen einfuegen, z.B.:]</em></p>
<p>Ab dem <strong>_______________</strong> gelten folgende Aenderungen:</p>
<ul>
    <li><em>[Aenderung 1: z.B. neue Arbeitszeit, neues Gehalt, neuer Arbeitsort]</em></li>
    <li><em>[Aenderung 2]</em></li>
</ul>

<h2>&sect; 2 Fortgeltung</h2>
<p>Alle uebrigen Bestimmungen des Arbeitsvertrages vom {{eintrittsdatum}} bleiben unveraendert bestehen.</p>

<h2>&sect; 3 Schriftform</h2>
<p>Aenderungen und Ergaenzungen dieses Aenderungsvertrages beduerfen der Schriftform.</p>

<p style="margin-top: 40px;">{{ort_datum}}</p>';
    }
}
