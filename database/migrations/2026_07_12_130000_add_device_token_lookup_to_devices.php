<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A-4 (Performance/Sicherheit): Schneller, indizierbarer Geraete-Lookup.
 *
 * PROBLEM vorher:
 *   Der Geraete-Token wird bcrypt-gehasht in `device_token_hash` gespeichert.
 *   bcrypt erzeugt fuer denselben Token JEDES MAL einen ANDEREN Hash (mit Salt),
 *   man kann also NICHT einfach `WHERE device_token_hash = ?` suchen.
 *   Deshalb wurde bisher pro Request JEDES aktive Geraet geladen und einzeln
 *   `Hash::check()` (bcrypt) durchlaufen — bei vielen Geraeten teuer und ein
 *   DoS-Hebel (Angreifer schickt Muell-Token -> voller Durchlauf).
 *
 * LOESUNG:
 *   Zusaetzliche Spalte `device_token_lookup` = HMAC-SHA256(token, APP_KEY).
 *   HMAC ist DETERMINISTISCH (gleicher Token -> gleicher Wert) und damit
 *   indizierbar: `WHERE device_token_lookup = ?` findet das Geraet in O(1).
 *   Die eigentliche Sicherheits-Pruefung macht weiterhin bcrypt
 *   (`device_token_hash`) — der Lookup ist nur ein schneller "Wegweiser".
 *
 * Bestandsgeraete haben zunaechst KEINEN Lookup-Wert (der Klartext-Token liegt
 * uns nicht vor, bcrypt ist nicht umkehrbar). Sie werden beim naechsten
 * erfolgreichen Zugriff automatisch nachgetragen (siehe Device::resolveByPlainToken).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('device_token_lookup', 64)->nullable()->after('device_token_hash');
            $table->index('device_token_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex(['device_token_lookup']);
            $table->dropColumn('device_token_lookup');
        });
    }
};
