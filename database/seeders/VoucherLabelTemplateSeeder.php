<?php

namespace Database\Seeders;

use App\Models\LabelTemplate;
use Illuminate\Database\Seeder;

/**
 * Erstellt das globale Gutschein-DYMO-Label-Template.
 * Basiert auf 30323 Shipping (54x101mm), Landscape.
 *
 * Platzhalter:
 *   {{betrag}}        -> "50,00 €"
 *   {{betrag_worte}}  -> "fünfzig Euro"
 *   {{datum}}         -> "11.05.2026"
 *   {{gueltig_bis}}   -> "11.05.2029"
 *   {{nummer}}        -> "4567.000"
 *   {{barcode}}       -> "www.aral-welle.de"
 */
class VoucherLabelTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // Nur anlegen wenn noch kein Gutschein-Template existiert
        if (LabelTemplate::where('slug', 'gutschein')->exists()) {
            $this->command->info('Gutschein-Template existiert bereits — uebersprungen.');
            return;
        }

        $xml = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<DieCutLabel Version="8.0" Units="twips">
	<PaperOrientation>Landscape</PaperOrientation>
	<Id>Shipping</Id>
	<IsOutlined>false</IsOutlined>
	<PaperName>30323 Shipping</PaperName>
	<DrawCommands>
		<RoundRectangle X="0" Y="0" Width="3060" Height="5715" Rx="270" Ry="270" />
	</DrawCommands>
	<ObjectInfo>
		<ShapeObject Stroke="SolidLine">
			<Name>Linie</Name>
			<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />
			<BackColor Alpha="0" Red="255" Green="255" Blue="255" />
			<LinkedObjectName />
			<Rotation>Rotation0</Rotation>
			<IsMirrored>False</IsMirrored>
			<IsVariable>False</IsVariable>
			<GroupID>-1</GroupID>
			<IsOutlined>False</IsOutlined>
			<ShapeType>HorizontalLine</ShapeType>
			<LineWidth>15</LineWidth>
			<LineAlignment>Center</LineAlignment>
			<FillColor Alpha="0" Red="255" Green="255" Blue="255" />
		</ShapeObject>
		<Bounds X="307.318859322899" Y="850.393713559034" Width="5102.3622813542" Height="15" />
	</ObjectInfo>
	<ObjectInfo>
		<ShapeObject Stroke="SolidLine">
			<Name>Linie_2</Name>
			<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />
			<BackColor Alpha="0" Red="255" Green="255" Blue="255" />
			<LinkedObjectName />
			<Rotation>Rotation0</Rotation>
			<IsMirrored>False</IsMirrored>
			<IsVariable>False</IsVariable>
			<GroupID>-1</GroupID>
			<IsOutlined>False</IsOutlined>
			<ShapeType>HorizontalLine</ShapeType>
			<LineWidth>15</LineWidth>
			<LineAlignment>Center</LineAlignment>
			<FillColor Alpha="0" Red="255" Green="255" Blue="255" />
		</ShapeObject>
		<Bounds X="307.318859322899" Y="1700.78742711807" Width="5102.3622813542" Height="15" />
	</ObjectInfo>
	<ObjectInfo>
		<TextObject>
			<Name>TEXT</Name>
			<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />
			<BackColor Alpha="0" Red="255" Green="255" Blue="255" />
			<LinkedObjectName />
			<Rotation>Rotation0</Rotation>
			<IsMirrored>False</IsMirrored>
			<IsVariable>False</IsVariable>
			<GroupID>-1</GroupID>
			<IsOutlined>False</IsOutlined>
			<HorizontalAlignment>Left</HorizontalAlignment>
			<VerticalAlignment>Top</VerticalAlignment>
			<TextFitMode>ShrinkToFit</TextFitMode>
			<UseFullFontHeight>True</UseFullFontHeight>
			<Verticalized>False</Verticalized>
			<StyledText>
				<Element>
					<String xml:space="preserve">Betrag in Worten</String>
					<Attributes>
						<Font Family="Arial" Size="10" Bold="False" Italic="False" Underline="False" Strikeout="False" />
						<ForeColor Alpha="255" Red="0" Green="0" Blue="0" HueScale="100" />
					</Attributes>
				</Element>
			</StyledText>
		</TextObject>
		<Bounds X="427" Y="1785" Width="2880" Height="195" />
	</ObjectInfo>
	<ObjectInfo>
		<TextObject>
			<Name>TEXT_1</Name>
			<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />
			<BackColor Alpha="0" Red="255" Green="255" Blue="255" />
			<LinkedObjectName />
			<Rotation>Rotation0</Rotation>
			<IsMirrored>False</IsMirrored>
			<IsVariable>False</IsVariable>
			<GroupID>-1</GroupID>
			<IsOutlined>False</IsOutlined>
			<HorizontalAlignment>Left</HorizontalAlignment>
			<VerticalAlignment>Top</VerticalAlignment>
			<TextFitMode>ShrinkToFit</TextFitMode>
			<UseFullFontHeight>True</UseFullFontHeight>
			<Verticalized>False</Verticalized>
			<StyledText>
				<Element>
					<String xml:space="preserve">Betrag in Euro</String>
					<Attributes>
						<Font Family="Arial" Size="10" Bold="False" Italic="False" Underline="False" Strikeout="False" />
						<ForeColor Alpha="255" Red="0" Green="0" Blue="0" HueScale="100" />
					</Attributes>
				</Element>
			</StyledText>
		</TextObject>
		<Bounds X="427" Y="930" Width="1350" Height="195" />
	</ObjectInfo>
	<ObjectInfo>
		<TextObject>
			<Name>TEXT__1</Name>
			<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />
			<BackColor Alpha="0" Red="255" Green="255" Blue="255" />
			<LinkedObjectName />
			<Rotation>Rotation0</Rotation>
			<IsMirrored>False</IsMirrored>
			<IsVariable>False</IsVariable>
			<GroupID>-1</GroupID>
			<IsOutlined>False</IsOutlined>
			<HorizontalAlignment>Left</HorizontalAlignment>
			<VerticalAlignment>Top</VerticalAlignment>
			<TextFitMode>ShrinkToFit</TextFitMode>
			<UseFullFontHeight>True</UseFullFontHeight>
			<Verticalized>False</Verticalized>
			<StyledText>
				<Element>
					<String xml:space="preserve">Ausgabedatum</String>
					<Attributes>
						<Font Family="Arial" Size="10" Bold="False" Italic="False" Underline="False" Strikeout="False" />
						<ForeColor Alpha="255" Red="0" Green="0" Blue="0" HueScale="100" />
					</Attributes>
				</Element>
			</StyledText>
		</TextObject>
		<Bounds X="3712" Y="930" Width="1350" Height="195" />
	</ObjectInfo>
	<ObjectInfo>
		<TextObject>
			<Name>TEXT_2</Name>
			<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />
			<BackColor Alpha="0" Red="255" Green="255" Blue="255" />
			<LinkedObjectName />
			<Rotation>Rotation0</Rotation>
			<IsMirrored>False</IsMirrored>
			<IsVariable>False</IsVariable>
			<GroupID>-1</GroupID>
			<IsOutlined>False</IsOutlined>
			<HorizontalAlignment>Left</HorizontalAlignment>
			<VerticalAlignment>Top</VerticalAlignment>
			<TextFitMode>ShrinkToFit</TextFitMode>
			<UseFullFontHeight>True</UseFullFontHeight>
			<Verticalized>False</Verticalized>
			<StyledText>
				<Element>
					<String xml:space="preserve">Gutscheinnr.:</String>
					<Attributes>
						<Font Family="Arial" Size="12" Bold="True" Italic="False" Underline="False" Strikeout="False" />
						<ForeColor Alpha="255" Red="0" Green="0" Blue="0" HueScale="100" />
					</Attributes>
				</Element>
			</StyledText>
		</TextObject>
		<Bounds X="427" Y="2100" Width="1815" Height="270" />
	</ObjectInfo>
	<ObjectInfo>
		<TextObject>
			<Name>TEXT_3</Name>
			<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />
			<BackColor Alpha="0" Red="255" Green="255" Blue="255" />
			<LinkedObjectName />
			<Rotation>Rotation0</Rotation>
			<IsMirrored>False</IsMirrored>
			<IsVariable>False</IsVariable>
			<GroupID>-1</GroupID>
			<IsOutlined>False</IsOutlined>
			<HorizontalAlignment>Left</HorizontalAlignment>
			<VerticalAlignment>Top</VerticalAlignment>
			<TextFitMode>ShrinkToFit</TextFitMode>
			<UseFullFontHeight>True</UseFullFontHeight>
			<Verticalized>False</Verticalized>
			<StyledText>
				<Element>
					<String xml:space="preserve">Einzul&#xF6;sen bis zum:</String>
					<Attributes>
						<Font Family="Arial" Size="12" Bold="False" Italic="False" Underline="False" Strikeout="False" />
						<ForeColor Alpha="255" Red="0" Green="0" Blue="0" HueScale="100" />
					</Attributes>
				</Element>
			</StyledText>
		</TextObject>
		<Bounds X="427" Y="2556" Width="1620" Height="210" />
	</ObjectInfo>
	<ObjectInfo>
		<TextObject>
			<Name>TEXT_4</Name>
			<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />
			<BackColor Alpha="0" Red="255" Green="255" Blue="255" />
			<LinkedObjectName />
			<Rotation>Rotation0</Rotation>
			<IsMirrored>False</IsMirrored>
			<IsVariable>False</IsVariable>
			<GroupID>-1</GroupID>
			<IsOutlined>False</IsOutlined>
			<HorizontalAlignment>Left</HorizontalAlignment>
			<VerticalAlignment>Top</VerticalAlignment>
			<TextFitMode>ShrinkToFit</TextFitMode>
			<UseFullFontHeight>True</UseFullFontHeight>
			<Verticalized>False</Verticalized>
			<StyledText>
				<Element>
					<String xml:space="preserve">{{gueltig_bis}}</String>
					<Attributes>
						<Font Family="Arial" Size="12" Bold="True" Italic="False" Underline="False" Strikeout="False" />
						<ForeColor Alpha="255" Red="0" Green="0" Blue="0" HueScale="100" />
					</Attributes>
				</Element>
			</StyledText>
		</TextObject>
		<Bounds X="2130" Y="2520" Width="1290" Height="285" />
	</ObjectInfo>
	<ObjectInfo>
		<TextObject>
			<Name>TEXT_5</Name>
			<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />
			<BackColor Alpha="0" Red="255" Green="255" Blue="255" />
			<LinkedObjectName />
			<Rotation>Rotation0</Rotation>
			<IsMirrored>False</IsMirrored>
			<IsVariable>False</IsVariable>
			<GroupID>-1</GroupID>
			<IsOutlined>False</IsOutlined>
			<HorizontalAlignment>Center</HorizontalAlignment>
			<VerticalAlignment>Middle</VerticalAlignment>
			<TextFitMode>ShrinkToFit</TextFitMode>
			<UseFullFontHeight>True</UseFullFontHeight>
			<Verticalized>False</Verticalized>
			<StyledText>
				<Element>
					<String xml:space="preserve">{{betrag_worte}}</String>
					<Attributes>
						<Font Family="Arial" Size="14" Bold="True" Italic="False" Underline="False" Strikeout="False" />
						<ForeColor Alpha="255" Red="0" Green="0" Blue="0" HueScale="100" />
					</Attributes>
				</Element>
			</StyledText>
		</TextObject>
		<Bounds X="352" Y="1230" Width="4860" Height="375" />
	</ObjectInfo>
	<ObjectInfo>
		<TextObject>
			<Name>TEXT__2</Name>
			<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />
			<BackColor Alpha="0" Red="255" Green="255" Blue="255" />
			<LinkedObjectName />
			<Rotation>Rotation0</Rotation>
			<IsMirrored>False</IsMirrored>
			<IsVariable>False</IsVariable>
			<GroupID>-1</GroupID>
			<IsOutlined>False</IsOutlined>
			<HorizontalAlignment>Center</HorizontalAlignment>
			<VerticalAlignment>Middle</VerticalAlignment>
			<TextFitMode>ShrinkToFit</TextFitMode>
			<UseFullFontHeight>True</UseFullFontHeight>
			<Verticalized>False</Verticalized>
			<StyledText>
				<Element>
					<String xml:space="preserve">{{betrag}}</String>
					<Attributes>
						<Font Family="Arial" Size="14" Bold="True" Italic="False" Underline="False" Strikeout="False" />
						<ForeColor Alpha="255" Red="0" Green="0" Blue="0" HueScale="100" />
					</Attributes>
				</Element>
			</StyledText>
		</TextObject>
		<Bounds X="397" Y="360" Width="1560" Height="375" />
	</ObjectInfo>
	<ObjectInfo>
		<TextObject>
			<Name>TEXT___1</Name>
			<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />
			<BackColor Alpha="0" Red="255" Green="255" Blue="255" />
			<LinkedObjectName />
			<Rotation>Rotation0</Rotation>
			<IsMirrored>False</IsMirrored>
			<IsVariable>False</IsVariable>
			<GroupID>-1</GroupID>
			<IsOutlined>False</IsOutlined>
			<HorizontalAlignment>Center</HorizontalAlignment>
			<VerticalAlignment>Middle</VerticalAlignment>
			<TextFitMode>ShrinkToFit</TextFitMode>
			<UseFullFontHeight>True</UseFullFontHeight>
			<Verticalized>False</Verticalized>
			<StyledText>
				<Element>
					<String xml:space="preserve">{{datum}}</String>
					<Attributes>
						<Font Family="Arial" Size="14" Bold="True" Italic="False" Underline="False" Strikeout="False" />
						<ForeColor Alpha="255" Red="0" Green="0" Blue="0" HueScale="100" />
					</Attributes>
				</Element>
			</StyledText>
		</TextObject>
		<Bounds X="3577" Y="360" Width="1560" Height="375" />
	</ObjectInfo>
	<ObjectInfo>
		<TextObject>
			<Name>TEXT____1</Name>
			<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />
			<BackColor Alpha="0" Red="255" Green="255" Blue="255" />
			<LinkedObjectName />
			<Rotation>Rotation0</Rotation>
			<IsMirrored>False</IsMirrored>
			<IsVariable>False</IsVariable>
			<GroupID>-1</GroupID>
			<IsOutlined>False</IsOutlined>
			<HorizontalAlignment>Right</HorizontalAlignment>
			<VerticalAlignment>Middle</VerticalAlignment>
			<TextFitMode>ShrinkToFit</TextFitMode>
			<UseFullFontHeight>True</UseFullFontHeight>
			<Verticalized>False</Verticalized>
			<StyledText>
				<Element>
					<String xml:space="preserve">{{nummer}}</String>
					<Attributes>
						<Font Family="Arial" Size="14" Bold="True" Italic="False" Underline="False" Strikeout="False" />
						<ForeColor Alpha="255" Red="0" Green="0" Blue="0" HueScale="100" />
					</Attributes>
				</Element>
			</StyledText>
		</TextObject>
		<Bounds X="2452" Y="1995" Width="2955" Height="375" />
	</ObjectInfo>
	<ObjectInfo>
		<BarcodeObject>
			<Name>BARCODE</Name>
			<ForeColor Alpha="255" Red="0" Green="0" Blue="0" />
			<BackColor Alpha="0" Red="255" Green="255" Blue="255" />
			<LinkedObjectName />
			<Rotation>Rotation0</Rotation>
			<IsMirrored>False</IsMirrored>
			<IsVariable>False</IsVariable>
			<GroupID>-1</GroupID>
			<IsOutlined>False</IsOutlined>
			<Text>{{barcode}}</Text>
			<Type>QRCode</Type>
			<Size>Medium</Size>
			<TextPosition>None</TextPosition>
			<TextFont Family="Arial" Size="8" Bold="False" Italic="False" Underline="False" Strikeout="False" />
			<CheckSumFont Family="Arial" Size="8" Bold="False" Italic="False" Underline="False" Strikeout="False" />
			<TextEmbedding>None</TextEmbedding>
			<ECLevel>0</ECLevel>
			<HorizontalAlignment>Center</HorizontalAlignment>
			<QuietZonesPadding Left="0" Top="0" Right="0" Bottom="0" />
		</BarcodeObject>
		<Bounds X="3800" Y="2361" Width="1680" Height="540" />
	</ObjectInfo>
</DieCutLabel>
XML;

        LabelTemplate::create([
            'tenant_id' => null, // Global fuer alle Stationen
            'slug' => 'gutschein',
            'category' => 'gutschein',
            'name' => 'Gutschein (30323 Shipping)',
            'xml_template' => $xml,
            'placeholders' => [
                ['key' => 'betrag', 'label' => 'Betrag in Euro', 'example' => '50,00 €'],
                ['key' => 'betrag_worte', 'label' => 'Betrag in Worten', 'example' => 'fünfzig Euro'],
                ['key' => 'datum', 'label' => 'Ausgabedatum', 'example' => '11.05.2026'],
                ['key' => 'gueltig_bis', 'label' => 'Gueltig bis', 'example' => '11.05.2029'],
                ['key' => 'nummer', 'label' => 'Gutscheinnummer', 'example' => '4567.000'],
                ['key' => 'barcode', 'label' => 'QR-Code Inhalt', 'example' => 'www.aral-welle.de'],
            ],
            'width' => 5.38,
            'height' => 10.16,
            'orientation' => 'Landscape',
            'is_active' => true,
        ]);

        $this->command->info('Gutschein-Label-Template angelegt (global, slug=gutschein).');
    }
}
