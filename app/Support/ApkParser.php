<?php

namespace App\Support;

/**
 * Liest versionCode + versionName direkt aus einer APK aus.
 *
 * Die APK ist ein ZIP; AndroidManifest.xml liegt darin als binaeres AXML
 * (Android Binary XML). Wir parsen den String-Pool + die Resource-Map und
 * lesen am <manifest>-Tag die Attribute android:versionCode (0x0101021b)
 * und android:versionName (0x0101021c).
 *
 * Zweck: Verhindert, dass im Admin ein falscher version_code zur APK
 * gespeichert wird (sonst Update-Endlosschleife im Updater).
 */
class ApkParser
{
    private const ATTR_VERSION_CODE = 0x0101021b;
    private const ATTR_VERSION_NAME = 0x0101021c;

    /**
     * @return array{versionCode: int|null, versionName: string|null}
     */
    public static function readVersion(string $apkPath): array
    {
        $manifest = self::extractManifest($apkPath);
        if ($manifest === null) {
            return ['versionCode' => null, 'versionName' => null];
        }

        try {
            return self::parseAxml($manifest);
        } catch (\Throwable $e) {
            return ['versionCode' => null, 'versionName' => null];
        }
    }

    private static function extractManifest(string $apkPath): ?string
    {
        if (! is_readable($apkPath) || ! class_exists(\ZipArchive::class)) {
            return null;
        }

        $zip = new \ZipArchive();
        if ($zip->open($apkPath) !== true) {
            return null;
        }

        $data = $zip->getFromName('AndroidManifest.xml');
        $zip->close();

        return $data === false ? null : $data;
    }

    private static function parseAxml(string $data): array
    {
        $len = strlen($data);
        $u16 = fn (int $o) => unpack('v', substr($data, $o, 2))[1];
        $u32 = fn (int $o) => unpack('V', substr($data, $o, 4))[1];

        $strings = [];
        $resMap = [];
        $result = ['versionCode' => null, 'versionName' => null];

        // Datei-Header (8 Bytes), dann Chunks
        $off = 8;
        while ($off + 8 <= $len) {
            $type = $u16($off);
            $headerSize = $u16($off + 2);
            $size = $u32($off + 4);
            if ($size < 8 || $off + $size > $len) {
                break;
            }

            if ($type === 0x0001) {
                // STRING_POOL
                $strings = self::parseStringPool($data, $off, $u16, $u32);
            } elseif ($type === 0x0180) {
                // RESOURCE_MAP: Array aus uint32 (Index == String-Pool-Index)
                $count = intdiv($size - $headerSize, 4);
                for ($i = 0; $i < $count; $i++) {
                    $resMap[$i] = $u32($off + $headerSize + $i * 4);
                }
            } elseif ($type === 0x0102) {
                // START_TAG
                $nameIdx = $u32($off + 20);
                $tagName = $strings[$nameIdx] ?? '';
                if ($tagName === 'manifest') {
                    $attributeStart = $u16($off + 24);
                    $attributeCount = $u16($off + 28);
                    $attrBase = $off + 16 + $attributeStart;

                    for ($a = 0; $a < $attributeCount; $a++) {
                        $p = $attrBase + $a * 20;
                        if ($p + 20 > $len) {
                            break;
                        }
                        $attrNameIdx = $u32($p + 4);
                        $rawValue = $u32($p + 8);
                        $data32 = $u32($p + 16);
                        $resId = $resMap[$attrNameIdx] ?? 0;
                        $attrName = $strings[$attrNameIdx] ?? '';

                        if ($resId === self::ATTR_VERSION_CODE || $attrName === 'versionCode') {
                            $result['versionCode'] = $data32;
                        } elseif ($resId === self::ATTR_VERSION_NAME || $attrName === 'versionName') {
                            $result['versionName'] = $strings[$rawValue] ?? ($strings[$data32] ?? null);
                        }
                    }

                    // <manifest> ist eindeutig — fertig
                    break;
                }
            }

            $off += $size;
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private static function parseStringPool(string $data, int $off, callable $u16, callable $u32): array
    {
        $stringCount = $u32($off + 8);
        $flags = $u32($off + 16);
        $stringsStart = $u32($off + 20);
        $isUtf8 = ($flags & 0x100) !== 0;

        $strings = [];
        $offsetsBase = $off + 28;
        $dataBase = $off + $stringsStart;

        for ($i = 0; $i < $stringCount; $i++) {
            $strOff = $u32($offsetsBase + $i * 4);
            $pos = $dataBase + $strOff;
            $strings[$i] = $isUtf8
                ? self::decodeUtf8($data, $pos)
                : self::decodeUtf16($data, $pos, $u16);
        }

        return $strings;
    }

    private static function decodeUtf8(string $data, int $pos): string
    {
        // Zwei Laengen (Zeichen, dann Bytes), je 1-2 Bytes
        $decode = function (int $p) use ($data): array {
            $b = ord($data[$p]);
            $p++;
            if ($b & 0x80) {
                $b = (($b & 0x7f) << 8) | ord($data[$p]);
                $p++;
            }
            return [$b, $p];
        };

        [, $pos] = $decode($pos);          // Zeichenanzahl (ignoriert)
        [$byteLen, $pos] = $decode($pos);  // Byteanzahl

        return substr($data, $pos, $byteLen);
    }

    private static function decodeUtf16(string $data, int $pos, callable $u16): string
    {
        $len = $u16($pos);
        $pos += 2;
        if ($len & 0x8000) {
            $len = (($len & 0x7fff) << 16) | $u16($pos);
            $pos += 2;
        }

        $bytes = substr($data, $pos, $len * 2);

        return mb_convert_encoding($bytes, 'UTF-8', 'UTF-16LE');
    }
}
