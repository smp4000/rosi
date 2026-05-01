<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tresor-Einlage Label-Template hinzufuegen.
 * Ueberschreibt KEINE bestehenden Templates.
 */
class TresorLabelTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // Nur anlegen wenn noch nicht vorhanden
        $exists = DB::table('label_templates')
            ->where('slug', 'tresor')
            ->exists();

        if ($exists) {
            $this->command->info('  Tresor-Template existiert bereits, uebersprungen.');
            return;
        }

        DB::table('label_templates')->insert([
            'id' => Str::uuid()->toString(),
            'tenant_id' => null,
            'slug' => 'tresor',
            'category' => 'tresor',
            'name' => 'Tresor-Einlage',
            'width' => 10.11,
            'height' => 5.41,
            'orientation' => 'Portrait',
            'placeholders' => json_encode([
                ['key' => 'station', 'label' => 'Tankstellen-Name', 'example' => 'Aral Tankstelle Welle'],
                ['key' => 'mitarbeiter', 'label' => 'Mitarbeiter-Name', 'example' => 'Christian Welle'],
                ['key' => 'datum', 'label' => 'Datum + Uhrzeit', 'example' => '01.05.2026 22:28'],
                ['key' => 'betrag', 'label' => 'Betrag', 'example' => '900,00 EUR'],
                ['key' => 'muenzen', 'label' => 'Mit Muenzen', 'example' => 'Nein'],
                ['key' => 'barcode', 'label' => 'Barcode', 'example' => 'SAFE-UHRBTWOA'],
            ]),
            'xml_template' => self::tresorXml(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('  Tresor-Einlage Template erstellt.');
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

    private static function tresorXml(): string
    {
        $objects = self::textObject('Header', 'TRESOR-EINLAGE', 10, true, 0.10, 0.05, 3.7, 0.25)
            . self::textObject('Amount', '{{betrag}}', 20, true, 0.10, 0.35, 3.7, 0.45)
            . self::textObject('Details', '{{datum}} | Muenzen: {{muenzen}}', 10, false, 0.10, 0.85, 3.7, 0.25)
            . self::textObject('Employee', '{{mitarbeiter}} | {{station}}', 9, false, 0.10, 1.15, 3.7, 0.25)
            . self::textObject('Barcode', '{{barcode}}', 11, true, 0.10, 1.50, 3.7, 0.25);

        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<DesktopLabel Version="1"><DYMOLabel Version="3">'
            . '<Description>ROSI Tresor-Einlage</Description>'
            . '<Orientation>Portrait</Orientation>'
            . '<LabelName>Shipping</LabelName>'
            . '<InitialLength>0</InitialLength>'
            . '<BorderStyle>SolidLine</BorderStyle>'
            . '<DYMORect><DYMOPoint><X>0</X><Y>0</Y></DYMOPoint><Size><Width>3.98</Width><Height>2.13</Height></Size></DYMORect>'
            . '<BorderColor><SolidColorBrush><Color A="0" R="0" G="0" B="0" /></SolidColorBrush></BorderColor>'
            . '<BorderThickness>1</BorderThickness>'
            . '<Show_Border>False</Show_Border>'
            . '<DynamicLayoutManager><RotationBehavior>ClearObjects</RotationBehavior><LabelObjects>'
            . $objects
            . '</LabelObjects></DynamicLayoutManager>'
            . '</DYMOLabel></DesktopLabel>';
    }
}
