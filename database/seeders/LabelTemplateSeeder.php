<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Befuellt die label_templates-Tabelle mit Standard-Vorlagen.
 * Globale Templates (tenant_id = null) fuer alle Stationen.
 */
class LabelTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $tankbetrugPlaceholders = json_encode([
            ['key' => 'id', 'label' => 'Tankbetrug-ID', 'example' => '019d-7a3b-...'],
            ['key' => 'datum', 'label' => 'Datum + Uhrzeit', 'example' => '11.04.2026 16:25'],
            ['key' => 'kennzeichen', 'label' => 'KFZ-Kennzeichen', 'example' => 'FD-AB 123'],
            ['key' => 'produkt', 'label' => 'Kraftstoff-Sorte', 'example' => 'Super E5'],
            ['key' => 'zapfpunkt', 'label' => 'Zapfpunkt-Nummer', 'example' => '7'],
            ['key' => 'menge', 'label' => 'Liter-Menge', 'example' => '45,20'],
            ['key' => 'betrag', 'label' => 'EUR-Betrag', 'example' => '82,36'],
            ['key' => 'station', 'label' => 'Tankstellen-Name', 'example' => 'Aral Tankstelle Welle Fulda'],
            ['key' => 'mitarbeiter', 'label' => 'Mitarbeiter-Name', 'example' => 'Christian Welle'],
        ]);

        $templates = [
            [
                'slug' => 'tankbetrug-standard',
                'category' => 'tankbetrug',
                'name' => 'Tankbetrug Standard',
                'width' => 10.11,
                'height' => 5.41,
                'orientation' => 'Portrait',
                'placeholders' => $tankbetrugPlaceholders,
                'xml_template' => self::tankbetrugXml(),
            ],
            [
                'slug' => 'tankbetrug-kompakt',
                'category' => 'tankbetrug',
                'name' => 'Tankbetrug Kompakt',
                'width' => 10.11,
                'height' => 5.41,
                'orientation' => 'Portrait',
                'placeholders' => $tankbetrugPlaceholders,
                'xml_template' => self::tankbetrugKompaktXml(),
            ],
            [
                'slug' => 'tankbetrug-detail',
                'category' => 'tankbetrug',
                'name' => 'Tankbetrug Detailliert',
                'width' => 10.11,
                'height' => 5.41,
                'orientation' => 'Portrait',
                'placeholders' => $tankbetrugPlaceholders,
                'xml_template' => self::tankbetrugDetailXml(),
            ],
            [
                'slug' => 'testdruck',
                'category' => 'testdruck',
                'name' => 'Testdruck-Etikett',
                'width' => 10.11,
                'height' => 5.41,
                'orientation' => 'Portrait',
                'placeholders' => json_encode([
                    ['key' => 'datum', 'label' => 'Datum + Uhrzeit', 'example' => '11.04.2026 16:25'],
                ]),
                'xml_template' => self::testdruckXml(),
            ],
            // Hinweis: Die Tresor-Vorlagen (Modern + Klassisch) verwaltet der
            // dedizierte TresorLabelTemplateSeeder (am Ende dieser run()-Methode
            // aufgerufen) - hier NICHT seeden, sonst wuerde das moderne Design
            // bei einem vollen db:seed mit dem alten ueberschrieben.
            [
                'slug' => 'adresse',
                'category' => 'adresse',
                'name' => 'Adress-Etikett',
                'width' => 8.89,
                'height' => 2.79,
                'orientation' => 'Landscape',
                'placeholders' => json_encode([
                    ['key' => 'name', 'label' => 'Empfaenger-Name', 'example' => 'Max Mustermann'],
                    ['key' => 'strasse', 'label' => 'Strasse + Hausnr.', 'example' => 'Musterstr. 1'],
                    ['key' => 'ort', 'label' => 'PLZ + Ort', 'example' => '36100 Petersberg'],
                    ['key' => 'absender', 'label' => 'Absender', 'example' => 'Aral Tankstelle Welle'],
                ]),
                'xml_template' => self::adresseXml(),
            ],
        ];

        $now = now();

        foreach ($templates as $data) {
            DB::table('label_templates')->updateOrInsert(
                ['tenant_id' => null, 'slug' => $data['slug']],
                [
                    'id' => DB::table('label_templates')
                        ->where('tenant_id', null)
                        ->where('slug', $data['slug'])
                        ->value('id') ?? Str::uuid()->toString(),
                    'category' => $data['category'] ?? $data['slug'],
                    'name' => $data['name'],
                    'xml_template' => $data['xml_template'],
                    'placeholders' => $data['placeholders'],
                    'width' => $data['width'],
                    'height' => $data['height'],
                    'orientation' => $data['orientation'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $this->command->info('  ' . count($templates) . ' Label-Vorlagen erstellt/aktualisiert.');

        // Tresor-Vorlagen (Modern + Klassisch) separat seeden.
        $this->call(TresorLabelTemplateSeeder::class);
    }

    private static function textObject(
        string $name, string $text, int $fontSize, bool $bold,
        float $x, float $y, float $w, float $h
    ): string {
        $boldStr = $bold ? 'True' : 'False';
        return <<<XML
        <TextObject>
          <Name>{$name}</Name>
          <Brushes>
            <BackgroundBrush><SolidColorBrush><Color A="0" R="1" G="1" B="1" /></SolidColorBrush></BackgroundBrush>
            <BorderBrush><SolidColorBrush><Color A="0" R="0" G="0" B="0" /></SolidColorBrush></BorderBrush>
            <StrokeBrush><SolidColorBrush><Color A="0" R="0" G="0" B="0" /></SolidColorBrush></StrokeBrush>
            <FillBrush><SolidColorBrush><Color A="0" R="0" G="0" B="0" /></SolidColorBrush></FillBrush>
          </Brushes>
          <Rotation>Rotation0</Rotation>
          <OutlineThickness>1</OutlineThickness>
          <IsOutlined>False</IsOutlined>
          <BorderStyle>SolidLine</BorderStyle>
          <Margin><DYMOThickness Left="0" Top="0" Right="0" Bottom="0" /></Margin>
          <HorizontalAlignment>Left</HorizontalAlignment>
          <VerticalAlignment>Middle</VerticalAlignment>
          <FitMode>ShrinkToFit</FitMode>
          <IsVertical>False</IsVertical>
          <FormattedText>
            <FitMode>ShrinkToFit</FitMode>
            <HorizontalAlignment>Left</HorizontalAlignment>
            <VerticalAlignment>Middle</VerticalAlignment>
            <IsVertical>False</IsVertical>
            <LineTextSpan>
              <TextSpan>
                <Text>{$text}</Text>
                <FontInfo>
                  <FontName>Arial</FontName>
                  <FontSize>{$fontSize}</FontSize>
                  <IsBold>{$boldStr}</IsBold>
                  <IsItalic>False</IsItalic>
                  <IsUnderline>False</IsUnderline>
                  <FontBrush><SolidColorBrush><Color A="1" R="0" G="0" B="0" /></SolidColorBrush></FontBrush>
                </FontInfo>
              </TextSpan>
            </LineTextSpan>
          </FormattedText>
          <ObjectLayout>
            <DYMOPoint><X>{$x}</X><Y>{$y}</Y></DYMOPoint>
            <Size><Width>{$w}</Width><Height>{$h}</Height></Size>
          </ObjectLayout>
        </TextObject>
XML;
    }

    private static function wrapLabel(string $objects, string $desc, string $orientation, string $labelName, float $w, float $h): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<DesktopLabel Version="1"><DYMOLabel Version="3">'
            . "<Description>{$desc}</Description>"
            . "<Orientation>{$orientation}</Orientation>"
            . "<LabelName>{$labelName}</LabelName>"
            . '<InitialLength>0</InitialLength>'
            . '<BorderStyle>SolidLine</BorderStyle>'
            . "<DYMORect><DYMOPoint><X>0</X><Y>0</Y></DYMOPoint><Size><Width>{$w}</Width><Height>{$h}</Height></Size></DYMORect>"
            . '<BorderColor><SolidColorBrush><Color A="0" R="0" G="0" B="0" /></SolidColorBrush></BorderColor>'
            . '<BorderThickness>1</BorderThickness>'
            . '<Show_Border>False</Show_Border>'
            . '<DynamicLayoutManager><RotationBehavior>ClearObjects</RotationBehavior><LabelObjects>'
            . $objects
            . '</LabelObjects></DynamicLayoutManager>'
            . '</DYMOLabel></DesktopLabel>';
    }

    private static function tankbetrugXml(): string
    {
        $objects = self::textObject('Header', 'TANKBETRUG  {{datum}}', 10, true, 0.10, 0.05, 3.7, 0.25)
            . self::textObject('Plate', '{{kennzeichen}}', 20, true, 0.10, 0.35, 3.7, 0.45)
            . self::textObject('Product', '{{produkt}} | Zapfpunkt {{zapfpunkt}}', 11, false, 0.10, 0.85, 3.7, 0.25)
            . self::textObject('Amount', '{{menge}} l | {{betrag}} EUR', 13, true, 0.10, 1.15, 3.7, 0.30)
            . self::textObject('Station', '{{station}}', 9, false, 0.10, 1.50, 3.7, 0.25);

        return self::wrapLabel($objects, 'ROSI Tankbetrug', 'Portrait', 'Shipping', 3.98, 2.13);
    }

    private static function tankbetrugKompaktXml(): string
    {
        $objects = self::textObject('Header', 'TANKBETRUG  {{datum}}', 9, true, 0.10, 0.05, 3.7, 0.20)
            . self::textObject('Plate', '{{kennzeichen}}', 18, true, 0.10, 0.28, 3.7, 0.35)
            . self::textObject('Details', '{{produkt}} | Zapfpunkt {{zapfpunkt}} | {{menge}} l | {{betrag}} EUR', 10, false, 0.10, 0.68, 3.7, 0.25)
            . self::textObject('Footer', '{{station}} | {{mitarbeiter}}', 8, false, 0.10, 0.98, 3.7, 0.20);

        return self::wrapLabel($objects, 'ROSI Tankbetrug Kompakt', 'Portrait', 'Shipping', 3.98, 2.13);
    }

    private static function tankbetrugDetailXml(): string
    {
        // Vollstaendiges Protokoll-Layout: nutzt alle Felder inkl. Mitarbeiter + ID.
        $objects = self::textObject('Header', 'TANKBETRUG-PROTOKOLL', 11, true, 0.10, 0.05, 3.7, 0.22)
            . self::textObject('Datum', '{{datum}}', 9, false, 0.10, 0.27, 3.7, 0.18)
            . self::textObject('Plate', '{{kennzeichen}}', 22, true, 0.10, 0.45, 3.7, 0.42)
            . self::textObject('Product', '{{produkt}}  -  Zapfpunkt {{zapfpunkt}}', 10, false, 0.10, 0.88, 3.7, 0.22)
            . self::textObject('Amount', '{{menge}} l  =  {{betrag}} EUR', 15, true, 0.10, 1.11, 3.7, 0.32)
            . self::textObject('Station', '{{station}}', 9, false, 0.10, 1.46, 3.7, 0.20)
            . self::textObject('Employee', 'Kassierer: {{mitarbeiter}}', 9, false, 0.10, 1.66, 3.7, 0.20)
            . self::textObject('RefId', 'ID {{id}}', 6, false, 0.10, 1.88, 3.7, 0.18);

        return self::wrapLabel($objects, 'ROSI Tankbetrug Detailliert', 'Portrait', 'Shipping', 3.98, 2.13);
    }

    private static function testdruckXml(): string
    {
        $objects = self::textObject('Text', 'ROSI Testdruck  {{datum}}', 12, true, 0.10, 0.20, 3.7, 0.7);

        return self::wrapLabel($objects, 'ROSI Testdruck', 'Portrait', 'Shipping', 3.98, 2.13);
    }

    private static function tresorXml(): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<DieCutLabel Version="8.0" Units="twips" MediaType="Default">'
            . '<PaperOrientation>Landscape</PaperOrientation>'
            . '<Id>LargeShipping</Id>'
            . '<IsOutlined>false</IsOutlined>'
            . '<PaperName>30256 Shipping</PaperName>'
            . '<DrawCommands>'
            . '<RoundRectangle X="0" Y="0" Width="3331" Height="5715" Rx="270" Ry="270" />'
            . '</DrawCommands>'
            // TankstellenName
            . '<ObjectInfo>'
            . '<TextObject>'
            . '<Name>TankstellenName</Name>'
            . '<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />'
            . '<BackColor Alpha="0" Red="255" Green="255" Blue="255" />'
            . '<LinkedObjectName /><Rotation>Rotation0</Rotation>'
            . '<IsMirrored>False</IsMirrored><IsVariable>False</IsVariable>'
            . '<GroupID>-1</GroupID><IsOutlined>False</IsOutlined>'
            . '<HorizontalAlignment>Left</HorizontalAlignment>'
            . '<VerticalAlignment>Top</VerticalAlignment>'
            . '<TextFitMode>ShrinkToFit</TextFitMode>'
            . '<UseFullFontHeight>True</UseFullFontHeight>'
            . '<Verticalized>False</Verticalized>'
            . '<StyledText><Element>'
            . '<String xml:space="preserve">{{station}}</String>'
            . '<Attributes>'
            . '<Font Family="Arial" Size="16" Bold="True" Italic="False" Underline="False" Strikeout="False" />'
            . '<ForeColor Alpha="255" Red="0" Green="0" Blue="0" HueScale="100" />'
            . '</Attributes></Element></StyledText>'
            . '</TextObject>'
            . '<Bounds X="566.929142372689" Y="346.771656949076" Width="4605" Height="420" />'
            . '</ObjectInfo>'
            // MitarbeiterName
            . '<ObjectInfo>'
            . '<TextObject>'
            . '<Name>MitarbeiterName</Name>'
            . '<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />'
            . '<BackColor Alpha="0" Red="255" Green="255" Blue="255" />'
            . '<LinkedObjectName /><Rotation>Rotation0</Rotation>'
            . '<IsMirrored>False</IsMirrored><IsVariable>False</IsVariable>'
            . '<GroupID>-1</GroupID><IsOutlined>False</IsOutlined>'
            . '<HorizontalAlignment>Left</HorizontalAlignment>'
            . '<VerticalAlignment>Top</VerticalAlignment>'
            . '<TextFitMode>ShrinkToFit</TextFitMode>'
            . '<UseFullFontHeight>True</UseFullFontHeight>'
            . '<Verticalized>False</Verticalized>'
            . '<StyledText><Element>'
            . '<String xml:space="preserve">Mitarbeiter: {{mitarbeiter}}</String>'
            . '<Attributes>'
            . '<Font Family="Arial" Size="11" Bold="False" Italic="False" Underline="False" Strikeout="False" />'
            . '<ForeColor Alpha="255" Red="0" Green="0" Blue="0" HueScale="100" />'
            . '</Attributes></Element></StyledText>'
            . '</TextObject>'
            . '<Bounds X="566.929142372689" Y="795" Width="4850" Height="315" />'
            . '</ObjectInfo>'
            // TresoreinwurfBetrag
            . '<ObjectInfo>'
            . '<TextObject>'
            . '<Name>TresoreinwurfBetrag</Name>'
            . '<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />'
            . '<BackColor Alpha="0" Red="255" Green="255" Blue="255" />'
            . '<LinkedObjectName /><Rotation>Rotation0</Rotation>'
            . '<IsMirrored>False</IsMirrored><IsVariable>False</IsVariable>'
            . '<GroupID>-1</GroupID><IsOutlined>False</IsOutlined>'
            . '<HorizontalAlignment>Left</HorizontalAlignment>'
            . '<VerticalAlignment>Top</VerticalAlignment>'
            . '<TextFitMode>None</TextFitMode>'
            . '<UseFullFontHeight>True</UseFullFontHeight>'
            . '<Verticalized>False</Verticalized>'
            . '<StyledText><Element>'
            . '<String xml:space="preserve">Tresoreinwurf: {{betrag}}</String>'
            . '<Attributes>'
            . '<Font Family="Arial" Size="18" Bold="True" Italic="False" Underline="False" Strikeout="False" />'
            . '<ForeColor Alpha="255" Red="0" Green="0" Blue="0" HueScale="100" />'
            . '</Attributes></Element></StyledText>'
            . '</TextObject>'
            . '<Bounds X="566.929142372689" Y="1200" Width="4622.3622813542" Height="480" />'
            . '</ObjectInfo>'
            // Datum
            . '<ObjectInfo>'
            . '<TextObject>'
            . '<Name>Datum</Name>'
            . '<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />'
            . '<BackColor Alpha="0" Red="255" Green="255" Blue="255" />'
            . '<LinkedObjectName /><Rotation>Rotation0</Rotation>'
            . '<IsMirrored>False</IsMirrored><IsVariable>False</IsVariable>'
            . '<GroupID>-1</GroupID><IsOutlined>False</IsOutlined>'
            . '<HorizontalAlignment>Left</HorizontalAlignment>'
            . '<VerticalAlignment>Top</VerticalAlignment>'
            . '<TextFitMode>None</TextFitMode>'
            . '<UseFullFontHeight>True</UseFullFontHeight>'
            . '<Verticalized>False</Verticalized>'
            . '<StyledText><Element>'
            . '<String xml:space="preserve">Datum: {{datum}}</String>'
            . '<Attributes>'
            . '<Font Family="Arial" Size="12" Bold="True" Italic="False" Underline="False" Strikeout="False" />'
            . '<ForeColor Alpha="255" Red="0" Green="0" Blue="0" HueScale="100" />'
            . '</Attributes></Element></StyledText>'
            . '</TextObject>'
            . '<Bounds X="596.929142372689" Y="1855" Width="2312.71656949076" Height="645" />'
            . '</ObjectInfo>'
            // Zeit
            . '<ObjectInfo>'
            . '<TextObject>'
            . '<Name>Zeit</Name>'
            . '<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />'
            . '<BackColor Alpha="0" Red="255" Green="255" Blue="255" />'
            . '<LinkedObjectName /><Rotation>Rotation0</Rotation>'
            . '<IsMirrored>False</IsMirrored><IsVariable>False</IsVariable>'
            . '<GroupID>-1</GroupID><IsOutlined>False</IsOutlined>'
            . '<HorizontalAlignment>Left</HorizontalAlignment>'
            . '<VerticalAlignment>Top</VerticalAlignment>'
            . '<TextFitMode>ShrinkToFit</TextFitMode>'
            . '<UseFullFontHeight>True</UseFullFontHeight>'
            . '<Verticalized>False</Verticalized>'
            . '<StyledText><Element>'
            . '<String xml:space="preserve">Zeit: {{zeit}}</String>'
            . '<Attributes>'
            . '<Font Family="Arial" Size="12" Bold="True" Italic="False" Underline="False" Strikeout="False" />'
            . '<ForeColor Alpha="255" Red="0" Green="0" Blue="0" HueScale="100" />'
            . '</Attributes></Element></StyledText>'
            . '</TextObject>'
            . '<Bounds X="2879.64571186344" Y="1850" Width="1650" Height="255" />'
            . '</ObjectInfo>'
            // QR-Code
            . '<ObjectInfo>'
            . '<BarcodeObject>'
            . '<Name>QrCode</Name>'
            . '<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />'
            . '<BackColor Alpha="0" Red="255" Green="255" Blue="255" />'
            . '<LinkedObjectName /><Rotation>Rotation0</Rotation>'
            . '<IsMirrored>False</IsMirrored><IsVariable>False</IsVariable>'
            . '<GroupID>-1</GroupID><IsOutlined>False</IsOutlined>'
            . '<Text>{{barcode}}</Text>'
            . '<Type>QRCode</Type>'
            . '<Size>Medium</Size>'
            . '<TextPosition>None</TextPosition>'
            . '<TextFont Family="Arial" Size="8" Bold="False" Italic="False" Underline="False" Strikeout="False" />'
            . '<CheckSumFont Family="Arial" Size="8" Bold="False" Italic="False" Underline="False" Strikeout="False" />'
            . '<TextEmbedding>None</TextEmbedding>'
            . '<ECLevel>0</ECLevel>'
            . '<HorizontalAlignment>Center</HorizontalAlignment>'
            . '<QuietZonesPadding Left="0" Top="0" Right="0" Bottom="0" />'
            . '</BarcodeObject>'
            . '<Bounds X="3205" Y="2080" Width="2295" Height="1065" />'
            . '</ObjectInfo>'
            . '</DieCutLabel>';
    }

    private static function adresseXml(): string
    {
        $objects = self::textObject('Sender', 'Abs: {{absender}}', 8, false, 0.35, 0.02, 3.1, 0.15)
            . self::textObject('Name', '{{name}}', 14, true, 0.35, 0.22, 3.1, 0.3)
            . self::textObject('Address', '{{strasse}}&#xD;&#xA;{{ort}}', 12, false, 0.35, 0.54, 3.1, 0.5);

        return self::wrapLabel($objects, 'ROSI Adressetikett', 'Landscape', 'Address', 3.5, 1.1);
    }
}
