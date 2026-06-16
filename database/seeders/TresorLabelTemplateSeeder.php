<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tresor-Einlage Label-Template anlegen oder aktualisieren.
 * Ueberschreibt das XML mit der aktuellen Version (Platzhalter).
 *
 * Neues Design (Juni 2026): grosser Header "TRESOREINWURF", Station,
 * Betrag als XXL-Blickfang, Mitarbeiter + Zeitstempel unten links,
 * QR-Code (SAFE-Nummer) rechts.
 */
class TresorLabelTemplateSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('label_templates')->updateOrInsert(
            ['slug' => 'tresor', 'tenant_id' => null],
            [
                'id' => DB::table('label_templates')
                    ->where('slug', 'tresor')
                    ->whereNull('tenant_id')
                    ->value('id') ?? Str::uuid()->toString(),
                'category' => 'tresor',
                'name' => 'Tresor-Einlage',
                'width' => 5.87,
                'height' => 10.16,
                'orientation' => 'Landscape',
                'placeholders' => json_encode([
                    ['key' => 'station', 'label' => 'Tankstellen-Name', 'example' => 'Aral Tankstelle Welle'],
                    ['key' => 'mitarbeiter', 'label' => 'Mitarbeiter-Name', 'example' => 'Christian Welle'],
                    ['key' => 'datum', 'label' => 'Datum', 'example' => '01.05.2026'],
                    ['key' => 'zeit', 'label' => 'Uhrzeit', 'example' => '22:28'],
                    ['key' => 'betrag', 'label' => 'Betrag', 'example' => '900,00 EUR'],
                    ['key' => 'muenzen', 'label' => 'Mit Muenzen', 'example' => 'Nein'],
                    ['key' => 'barcode', 'label' => 'QR-Code / Barcode', 'example' => 'SAFE-UHRBTWOA'],
                ]),
                'xml_template' => self::tresorXml(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->command->info('  Tresor-Einlage Template erstellt/aktualisiert (neues Design).');
    }

    /**
     * Ein einzelnes Textfeld als DieCutLabel-ObjectInfo zusammenbauen.
     */
    private static function text(
        string $name,
        string $value,
        int $size,
        bool $bold,
        string $align,
        string $fit,
        float $x,
        float $y,
        float $w,
        float $h,
    ): string {
        return '<ObjectInfo>'
            . '<TextObject>'
            . '<Name>' . $name . '</Name>'
            . '<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />'
            . '<BackColor Alpha="0" Red="255" Green="255" Blue="255" />'
            . '<LinkedObjectName />'
            . '<Rotation>Rotation0</Rotation>'
            . '<IsMirrored>False</IsMirrored>'
            . '<IsVariable>False</IsVariable>'
            . '<GroupID>-1</GroupID>'
            . '<IsOutlined>False</IsOutlined>'
            . '<HorizontalAlignment>' . $align . '</HorizontalAlignment>'
            . '<VerticalAlignment>Top</VerticalAlignment>'
            . '<TextFitMode>' . $fit . '</TextFitMode>'
            . '<UseFullFontHeight>True</UseFullFontHeight>'
            . '<Verticalized>False</Verticalized>'
            . '<StyledText><Element>'
            . '<String xml:space="preserve">' . $value . '</String>'
            . '<Attributes>'
            . '<Font Family="Arial" Size="' . $size . '" Bold="' . ($bold ? 'True' : 'False') . '" Italic="False" Underline="False" Strikeout="False" />'
            . '<ForeColor Alpha="255" Red="0" Green="0" Blue="0" HueScale="100" />'
            . '</Attributes>'
            . '</Element></StyledText>'
            . '</TextObject>'
            . '<Bounds X="' . $x . '" Y="' . $y . '" Width="' . $w . '" Height="' . $h . '" />'
            . '</ObjectInfo>';
    }

    private static function tresorXml(): string
    {
        // Nutzbare Flaeche (Landscape, twips): ca. 5715 breit x 3145 hoch.
        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<DieCutLabel Version="8.0" Units="twips" MediaType="Default">'
            . '<PaperOrientation>Landscape</PaperOrientation>'
            . '<Id>LargeShipping</Id>'
            . '<IsOutlined>false</IsOutlined>'
            . '<PaperName>30256 Shipping</PaperName>'
            . '<DrawCommands>'
            . '<RoundRectangle X="0" Y="0" Width="3331" Height="5715" Rx="270" Ry="270" />'
            . '</DrawCommands>'
            // Header
            . self::text('Header', 'TRESOREINWURF', 30, true, 'Center', 'ShrinkToFit', 180, 150, 5355, 640)
            // Trennlinie (horizontale Linie)
            . '<ObjectInfo>'
            . '<ShapeObject>'
            . '<Name>Linie</Name>'
            . '<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />'
            . '<BackColor Alpha="255" Red="0" Green="0" Blue="0" />'
            . '<LinkedObjectName />'
            . '<Rotation>Rotation0</Rotation>'
            . '<IsMirrored>False</IsMirrored>'
            . '<IsVariable>False</IsVariable>'
            . '<GroupID>-1</GroupID>'
            . '<IsOutlined>False</IsOutlined>'
            . '<ShapeType>HorizontalLine</ShapeType>'
            . '<LineWidth>30</LineWidth>'
            . '<LineAlignment>Center</LineAlignment>'
            . '<FillColor Alpha="255" Red="0" Green="0" Blue="0" />'
            . '</ShapeObject>'
            . '<Bounds X="300" Y="830" Width="5115" Height="30" />'
            . '</ObjectInfo>'
            // Station
            . self::text('Station', '{{station}}', 16, true, 'Center', 'ShrinkToFit', 180, 920, 5355, 380)
            // Betrag-Label
            . self::text('BetragLabel', 'Einwurfbetrag', 13, true, 'Left', 'None', 320, 1430, 3200, 300)
            // Betrag XXL
            . self::text('Betrag', '{{betrag}}', 42, true, 'Left', 'ShrinkToFit', 300, 1700, 3320, 760)
            // QR-Code (SAFE-Nummer)
            . '<ObjectInfo>'
            . '<BarcodeObject>'
            . '<Name>QrCode</Name>'
            . '<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />'
            . '<BackColor Alpha="0" Red="255" Green="255" Blue="255" />'
            . '<LinkedObjectName />'
            . '<Rotation>Rotation0</Rotation>'
            . '<IsMirrored>False</IsMirrored>'
            . '<IsVariable>False</IsVariable>'
            . '<GroupID>-1</GroupID>'
            . '<IsOutlined>False</IsOutlined>'
            . '<Text>{{barcode}}</Text>'
            . '<Type>QRCode</Type>'
            . '<Size>Large</Size>'
            . '<TextPosition>None</TextPosition>'
            . '<TextFont Family="Arial" Size="8" Bold="False" Italic="False" Underline="False" Strikeout="False" />'
            . '<CheckSumFont Family="Arial" Size="8" Bold="False" Italic="False" Underline="False" Strikeout="False" />'
            . '<TextEmbedding>None</TextEmbedding>'
            . '<ECLevel>0</ECLevel>'
            . '<HorizontalAlignment>Center</HorizontalAlignment>'
            . '<QuietZonesPadding Left="0" Top="0" Right="0" Bottom="0" />'
            . '</BarcodeObject>'
            . '<Bounds X="3760" Y="1480" Width="1755" Height="1755" />'
            . '</ObjectInfo>'
            // Mitarbeiter
            . self::text('Mitarbeiter', 'Mitarbeiter: {{mitarbeiter}}', 12, false, 'Left', 'ShrinkToFit', 320, 2540, 3320, 300)
            // Datum + Zeit
            . self::text('Zeitstempel', '{{datum}} um {{zeit}} Uhr', 12, true, 'Left', 'ShrinkToFit', 320, 2820, 3320, 300)
            . '</DieCutLabel>';
    }
}
