<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PrintController extends ApiController
{
    private const DYMO_PORTS = [41951, 41952, 41953, 41954, 41955];
    private const DEFAULT_PRINTER = 'DYMO LabelWriter Wireless';
    private static ?int $activePort = null;

    /**
     * POST /api/v1/print/label
     */
    public function printLabel(Request $request)
    {
        $request->validate([
            'template' => 'required|string|in:address',
            'printer' => 'nullable|string',
            'copies' => 'nullable|integer|min:1|max:10',
            'data' => 'required|array',
            'data.name' => 'required|string',
            'data.street' => 'nullable|string',
            'data.city' => 'nullable|string',
            'data.sender' => 'nullable|string',
        ]);

        $printer = $request->input('printer') ?? self::DEFAULT_PRINTER;
        $copies = $request->input('copies', 1);
        $data = $request->input('data');

        try {
            $labelXml = $this->buildAddressLabelXml($data);
            $result = $this->printViaDymoApi($labelXml, $printer, $copies);

            if ($result['success']) {
                Log::info('Label gedruckt', [
                    'printer' => $printer,
                    'data' => $data,
                    'user' => $request->user()?->name,
                ]);
                return $this->success($result, 'Etikett gedruckt');
            }

            return $this->error($result['message'], 500);
        } catch (\Exception $e) {
            Log::error('Label-Druck fehlgeschlagen', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            return $this->error('Druckfehler: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/print/test
     * Testdruck — kann ohne Auth genutzt werden.
     */
    public function testPrint(Request $request)
    {
        $request->validate([
            'printer' => 'nullable|string',
        ]);

        $printer = $request->input('printer') ?? self::DEFAULT_PRINTER;

        try {
            $now = now()->format('d.m.Y H:i');
            $labelXml = $this->buildTestLabelXml($now);
            $result = $this->printViaDymoApi($labelXml, $printer, 1);

            if ($result['success']) {
                Log::info('Testdruck erfolgreich', ['printer' => $printer]);
                return $this->success($result, 'Testdruck gedruckt');
            }

            return $this->error($result['message'], 500);
        } catch (\Exception $e) {
            Log::error('Testdruck fehlgeschlagen', ['error' => $e->getMessage()]);
            return $this->error('Druckfehler: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/print/status
     * Prueft ob DYMO Service erreichbar ist.
     */
    public function status()
    {
        try {
            $port = $this->findActivePort();
            return $this->success([
                'connected' => true,
                'port' => $port,
            ], 'DYMO Service erreichbar');
        } catch (\Exception $e) {
            return $this->error('DYMO Service nicht erreichbar', 503);
        }
    }

    /**
     * GET /api/v1/print/printers
     */
    public function printers()
    {
        try {
            $xml = $this->dymoGet('/GetPrinters');
            $printers = [];

            if (preg_match_all('/<LabelWriterPrinter>(.*?)<\/LabelWriterPrinter>/s', $xml, $matches)) {
                foreach ($matches[1] as $block) {
                    $name = $this->xmlVal($block, 'Name');
                    $model = $this->xmlVal($block, 'ModelName');
                    $connected = $this->xmlVal($block, 'IsConnected') === 'True';

                    $printers[] = [
                        'id' => Str::slug($name),
                        'name' => $name,
                        'model' => $model,
                        'online' => $connected,
                        'type' => 'label',
                    ];
                }
            }

            return $this->success($printers);
        } catch (\Exception $e) {
            return $this->error('DYMO-Service nicht erreichbar: ' . $e->getMessage(), 503);
        }
    }

    /**
     * Druckt ein Tankbetrug-Etikett ueber DYMO.
     * Wird automatisch aus FuelTheftController aufgerufen.
     */
    public static function printFuelTheftLabel(array $data): array
    {
        $instance = new self();

        try {
            $labelXml = $instance->buildFuelTheftLabelXml($data);
            $result = $instance->printViaDymoApi($labelXml, self::DEFAULT_PRINTER, 1);

            if ($result['success']) {
                Log::info('Tankbetrug-Etikett gedruckt', ['data' => $data]);
            } else {
                Log::warning('Tankbetrug-Etikett Fehler', ['result' => $result]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Tankbetrug-Etikett fehlgeschlagen', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Erzeugt das DYMO Label XML fuer ein Tankbetrug-Etikett (101x54mm).
     *
     * Layout:
     *   TANKBETRUG                [Datum]
     *   [Kennzeichen oder "Kein Kennzeichen"]
     *   [Produkt] | Zapfpunkt [Nr]
     *   [Menge] l | [Betrag] EUR
     *   [Tankstelle]
     */
    private function buildFuelTheftLabelXml(array $data): string
    {
        $date = $data['date'] ?? now()->format('d.m.Y H:i');
        $plate = $data['license_plate'] ?? 'Kein Kennzeichen';
        $product = $data['product'] ?? '';
        $pump = $data['pump_number'] ?? '';
        $quantity = $data['quantity'] ?? '';
        $amount = $data['amount'] ?? '';
        $station = $data['station'] ?? '';

        // 101x54mm = 3.98 x 2.13 inch, nutzbar ca. 3.7 x 2.0
        $objects = '';

        // Zeile 1: TANKBETRUG + Datum
        $headerText = "TANKBETRUG  $date";
        $objects .= $this->textObject('Header', htmlspecialchars($headerText, ENT_XML1), 10, true,
            0.35, 0.20, 3.5, 0.25);

        // Zeile 2: Kennzeichen (gross + fett)
        $objects .= $this->textObject('Plate', htmlspecialchars($plate, ENT_XML1), 20, true,
            0.35, 0.50, 3.5, 0.45);

        // Zeile 3: Produkt | Zapfpunkt
        $line3 = "$product | Zapfpunkt $pump";
        $objects .= $this->textObject('Product', htmlspecialchars($line3, ENT_XML1), 11, false,
            0.35, 1.00, 3.5, 0.25);

        // Zeile 4: Menge + Betrag
        $line4 = "{$quantity} l | {$amount} EUR";
        $objects .= $this->textObject('Amount', htmlspecialchars($line4, ENT_XML1), 13, true,
            0.35, 1.30, 3.5, 0.30);

        // Zeile 5: Tankstelle
        $objects .= $this->textObject('Station', htmlspecialchars($station, ENT_XML1), 9, false,
            0.35, 1.65, 3.5, 0.25);

        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<DesktopLabel Version="1">
  <DYMOLabel Version="3">
    <Description>ROSI Tankbetrug-Etikett</Description>
    <Orientation>Landscape</Orientation>
    <LabelName>Shipping</LabelName>
    <InitialLength>0</InitialLength>
    <BorderStyle>SolidLine</BorderStyle>
    <DYMORect>
      <DYMOPoint><X>0</X><Y>0</Y></DYMOPoint>
      <Size><Width>3.98</Width><Height>2.13</Height></Size>
    </DYMORect>
    <BorderColor><SolidColorBrush><Color A="0" R="0" G="0" B="0" /></SolidColorBrush></BorderColor>
    <BorderThickness>1</BorderThickness>
    <Show_Border>False</Show_Border>
    <DynamicLayoutManager>
      <RotationBehavior>ClearObjects</RotationBehavior>
      <LabelObjects>
        {$objects}
      </LabelObjects>
    </DynamicLayoutManager>
  </DYMOLabel>
</DesktopLabel>
XML;
    }

    /**
     * Erzeugt das DYMO Label XML fuer ein Adressetikett.
     */
    private function buildAddressLabelXml(array $data): string
    {
        $name = htmlspecialchars($data['name'] ?? 'Unbekannt', ENT_XML1);
        $street = htmlspecialchars($data['street'] ?? '', ENT_XML1);
        $city = htmlspecialchars($data['city'] ?? '', ENT_XML1);
        $sender = htmlspecialchars($data['sender'] ?? '', ENT_XML1);

        // Adresszeilen zusammenbauen
        $lines = [];
        if (!empty($sender)) {
            $lines[] = "Abs: $sender";
        }
        $lines[] = '';
        $lines[] = $name;
        if (!empty($street)) $lines[] = $street;
        if (!empty($city)) $lines[] = $city;

        $text = implode("&#xD;&#xA;", $lines);

        // Zwei TextObjects: Absender klein + Empfaenger gross
        $objects = '';

        if (!empty($sender)) {
            $objects .= $this->textObject('Sender', "Abs: $sender", 8, false,
                0.35, 0.02, 3.1, 0.15);
        }

        $empfY = !empty($sender) ? 0.22 : 0.05;
        $empfH = !empty($sender) ? 0.83 : 1.0;

        $objects .= $this->textObject('Name', $name, 14, true,
            0.35, $empfY, 3.1, 0.3);

        $addrLines = [];
        if (!empty($street)) $addrLines[] = $street;
        if (!empty($city)) $addrLines[] = $city;

        if (!empty($addrLines)) {
            $addrText = implode("&#xD;&#xA;", $addrLines);
            $objects .= $this->textObject('Address', $addrText, 12, false,
                0.35, $empfY + 0.32, 3.1, $empfH - 0.35);
        }

        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<DesktopLabel Version="1">
  <DYMOLabel Version="3">
    <Description>ROSI Adressetikett</Description>
    <Orientation>Landscape</Orientation>
    <LabelName>Address</LabelName>
    <InitialLength>0</InitialLength>
    <BorderStyle>SolidLine</BorderStyle>
    <DYMORect>
      <DYMOPoint><X>0</X><Y>0</Y></DYMOPoint>
      <Size><Width>3.5</Width><Height>1.1</Height></Size>
    </DYMORect>
    <BorderColor><SolidColorBrush><Color A="0" R="0" G="0" B="0" /></SolidColorBrush></BorderColor>
    <BorderThickness>1</BorderThickness>
    <Show_Border>False</Show_Border>
    <DynamicLayoutManager>
      <RotationBehavior>ClearObjects</RotationBehavior>
      <LabelObjects>
        {$objects}
      </LabelObjects>
    </DynamicLayoutManager>
  </DYMOLabel>
</DesktopLabel>
XML;
    }

    /**
     * Erzeugt ein DYMO TextObject XML-Element.
     */
    private function textObject(
        string $name,
        string $text,
        int $fontSize,
        bool $bold,
        float $x,
        float $y,
        float $w,
        float $h,
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

    /**
     * Erzeugt ein einfaches Test-Label XML (101x54mm).
     */
    private function buildTestLabelXml(string $dateTime): string
    {
        $text = htmlspecialchars("ROSI Testdruck  $dateTime", ENT_XML1);

        $objects = $this->textObject('Text', $text, 12, true,
            0.35, 0.20, 3.0, 0.7);

        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<DesktopLabel Version="1">
  <DYMOLabel Version="3">
    <Description>ROSI Testdruck</Description>
    <Orientation>Landscape</Orientation>
    <LabelName>Shipping</LabelName>
    <InitialLength>0</InitialLength>
    <BorderStyle>SolidLine</BorderStyle>
    <DYMORect>
      <DYMOPoint><X>0</X><Y>0</Y></DYMOPoint>
      <Size><Width>3.98</Width><Height>2.13</Height></Size>
    </DYMORect>
    <BorderColor><SolidColorBrush><Color A="0" R="0" G="0" B="0" /></SolidColorBrush></BorderColor>
    <BorderThickness>1</BorderThickness>
    <Show_Border>False</Show_Border>
    <DynamicLayoutManager>
      <RotationBehavior>ClearObjects</RotationBehavior>
      <LabelObjects>
        {$objects}
      </LabelObjects>
    </DynamicLayoutManager>
  </DYMOLabel>
</DesktopLabel>
XML;
    }

    /**
     * Druckt ein Label ueber die DYMO WebApi.
     */
    private function printViaDymoApi(string $labelXml, string $printerName, int $copies): array
    {
        $printParams = "<LabelWriterPrintParams><Copies>{$copies}</Copies></LabelWriterPrintParams>";

        $response = $this->dymoPost('/PrintLabel2', [
            'printerName' => $printerName,
            'labelXml' => $labelXml,
            'printParamsXml' => $printParams,
        ]);

        if ($response === 'true') {
            return [
                'success' => true,
                'message' => "Gedruckt auf: $printerName",
                'printer' => $printerName,
                'copies' => $copies,
            ];
        }

        return [
            'success' => false,
            'message' => "DYMO-Fehler: $response",
            'printer' => $printerName,
        ];
    }

    /**
     * Findet den aktiven DYMO Port (41951-41955).
     */
    private function findActivePort(): int
    {
        // Cached Port pruefen
        if (self::$activePort !== null) {
            $url = "https://localhost:" . self::$activePort . "/DYMO/DLS/Printing/StatusConnected";
            $result = $this->curlGet($url, 3);
            if ($result === 'true') {
                return self::$activePort;
            }
            self::$activePort = null;
        }

        // Port-Scan
        foreach (self::DYMO_PORTS as $port) {
            $url = "https://localhost:{$port}/DYMO/DLS/Printing/StatusConnected";
            $result = $this->curlGet($url, 3);
            if ($result === 'true') {
                self::$activePort = $port;
                Log::info("DYMO Service gefunden auf Port {$port}");
                return $port;
            }
        }

        throw new \Exception('DYMO Service nicht erreichbar (Ports 41951-41955)');
    }

    /**
     * HTTP GET an DYMO WebApi.
     */
    private function dymoGet(string $path): string
    {
        $port = $this->findActivePort();
        $url = "https://localhost:{$port}/DYMO/DLS/Printing{$path}";
        $result = $this->curlGet($url, 10);

        if ($result === false) {
            throw new \Exception("DYMO API nicht erreichbar");
        }

        return $result;
    }

    /**
     * HTTP POST an DYMO WebApi.
     */
    private function dymoPost(string $path, array $data): string
    {
        $port = $this->findActivePort();
        $url = "https://localhost:{$port}/DYMO/DLS/Printing{$path}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            throw new \Exception("DYMO API nicht erreichbar: $error");
        }

        return $result;
    }

    /**
     * Einfacher cURL GET.
     */
    private function curlGet(string $url, int $timeout = 10): string|false
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private function xmlVal(string $xml, string $tag): string
    {
        if (preg_match("/<{$tag}>(.*?)<\/{$tag}>/s", $xml, $m)) {
            return trim($m[1]);
        }
        return '';
    }
}
