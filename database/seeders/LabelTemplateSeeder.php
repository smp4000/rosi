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
        $templates = [
            [
                'slug' => 'tankbetrug',
                'name' => 'Tankbetrug-Etikett',
                'width' => 3.98,
                'height' => 2.13,
                'orientation' => 'Portrait',
                'placeholders' => json_encode([
                    ['key' => 'datum', 'label' => 'Datum + Uhrzeit', 'example' => '11.04.2026 16:25'],
                    ['key' => 'kennzeichen', 'label' => 'KFZ-Kennzeichen', 'example' => 'FD-AB 123'],
                    ['key' => 'produkt', 'label' => 'Kraftstoff-Sorte', 'example' => 'Super E5'],
                    ['key' => 'zapfpunkt', 'label' => 'Zapfpunkt-Nummer', 'example' => '7'],
                    ['key' => 'menge', 'label' => 'Liter-Menge', 'example' => '45,20'],
                    ['key' => 'betrag', 'label' => 'EUR-Betrag', 'example' => '82,36'],
                    ['key' => 'station', 'label' => 'Tankstellen-Name', 'example' => 'Aral Tankstelle Welle Fulda'],
                ]),
                'xml_template' => self::tankbetrugXml(),
            ],
            [
                'slug' => 'testdruck',
                'name' => 'Testdruck-Etikett',
                'width' => 3.98,
                'height' => 2.13,
                'orientation' => 'Portrait',
                'placeholders' => json_encode([
                    ['key' => 'datum', 'label' => 'Datum + Uhrzeit', 'example' => '11.04.2026 16:25'],
                ]),
                'xml_template' => self::testdruckXml(),
            ],
            [
                'slug' => 'adresse',
                'name' => 'Adress-Etikett',
                'width' => 3.5,
                'height' => 1.1,
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

    private static function testdruckXml(): string
    {
        $objects = self::textObject('Text', 'ROSI Testdruck  {{datum}}', 12, true, 0.10, 0.20, 3.7, 0.7);

        return self::wrapLabel($objects, 'ROSI Testdruck', 'Portrait', 'Shipping', 3.98, 2.13);
    }

    private static function adresseXml(): string
    {
        $objects = self::textObject('Sender', 'Abs: {{absender}}', 8, false, 0.35, 0.02, 3.1, 0.15)
            . self::textObject('Name', '{{name}}', 14, true, 0.35, 0.22, 3.1, 0.3)
            . self::textObject('Address', '{{strasse}}&#xD;&#xA;{{ort}}', 12, false, 0.35, 0.54, 3.1, 0.5);

        return self::wrapLabel($objects, 'ROSI Adressetikett', 'Landscape', 'Address', 3.5, 1.1);
    }
}
