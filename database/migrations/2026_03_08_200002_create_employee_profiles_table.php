<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Personalbogen-Tabelle erstellen (erweitert).
     * Sensible Daten werden auf Model-Ebene verschluesselt (Laravel Casts).
     */
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v7');
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // --- Persoenliche Daten ---
            $table->text('date_of_birth')->nullable()->comment('Geburtsdatum (verschluesselt)');
            $table->string('place_of_birth')->nullable()->comment('Geburtsort');
            $table->string('gender', 20)->nullable()->comment('male, female, diverse');
            $table->string('marital_status', 20)->nullable()->comment('single, married, divorced, widowed');
            $table->string('nationality', 100)->nullable()->comment('Staatsangehoerigkeit');
            $table->string('second_nationality', 100)->nullable()->comment('Zweite Staatsangehoerigkeit');
            $table->string('residence_permit_type', 100)->nullable()->comment('Art des Aufenthaltstitels');
            $table->date('residence_permit_expires')->nullable()->comment('Ablaufdatum Aufenthaltstitel');
            $table->string('residence_permit_file')->nullable()->comment('Scan des Aufenthaltstitels');

            // --- Adresse ---
            $table->string('street')->nullable();
            $table->string('zip', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('DE');

            // --- Steuer & Sozialversicherung (verschluesselt) ---
            $table->text('tax_id')->nullable()->comment('Steuerliche ID (verschluesselt)');
            $table->string('tax_class', 5)->nullable()->comment('Steuerklasse 1-6');
            $table->boolean('church_tax')->default(false)->comment('Kirchensteuerpflichtig');
            $table->string('denomination', 50)->nullable()->comment('Konfession');
            $table->text('social_security_number')->nullable()->comment('SV-Nummer (verschluesselt)');
            $table->string('health_insurance_name')->nullable()->comment('Name der Krankenkasse');
            $table->string('health_insurance_type', 20)->nullable()->comment('gesetzlich, privat');
            $table->text('health_insurance_number')->nullable()->comment('Versichertennummer (verschluesselt)');
            $table->boolean('pension_insurance')->default(true)->comment('Rentenversicherungspflichtig');

            // --- Bankverbindung (verschluesselt) ---
            $table->text('bank_name')->nullable()->comment('Bankname (verschluesselt)');
            $table->text('iban')->nullable()->comment('IBAN (verschluesselt)');
            $table->text('bic')->nullable()->comment('BIC (verschluesselt)');
            $table->text('account_holder')->nullable()->comment('Kontoinhaber (verschluesselt)');

            // --- Notfallkontakt ---
            $table->string('emergency_name')->nullable();
            $table->string('emergency_phone', 50)->nullable();
            $table->string('emergency_relation', 100)->nullable()->comment('Beziehung (Ehepartner, Eltern, etc.)');

            // --- Kinder ---
            $table->unsignedTinyInteger('num_children')->default(0)->comment('Anzahl Kinder');
            $table->text('children_data')->nullable()->comment('Kinderdaten JSON (verschluesselt)');
            $table->boolean('child_allowance')->default(false)->comment('Kindergeldberechtigt');

            // --- Qualifikationen ---
            $table->string('education_level', 100)->nullable()->comment('Hoechster Bildungsabschluss');
            $table->string('professional_training')->nullable()->comment('Berufsausbildung');
            $table->json('drivers_license')->nullable()->comment('Fuehrerscheinklassen Array');
            $table->date('drivers_license_expiry')->nullable()->comment('Ablaufdatum');
            $table->json('certifications')->nullable()->comment('Zertifikate/Schulungen Array');
            $table->date('safety_training_date')->nullable()->comment('Letzte Sicherheitsunterweisung');
            $table->date('first_aid_training_date')->nullable()->comment('Letzte Erste-Hilfe-Schulung');
            $table->date('hazmat_training_date')->nullable()->comment('Gefahrgut-Schulung');

            // --- Arbeitsmedizin ---
            $table->date('medical_exam_date')->nullable()->comment('Letzte arbeitsmedizinische Untersuchung');
            $table->date('medical_exam_next')->nullable()->comment('Naechste Untersuchung faellig');
            $table->text('medical_restrictions')->nullable()->comment('Einschraenkungen (verschluesselt)');
            $table->boolean('health_certificate')->default(false)->comment('Gesundheitszeugnis vorhanden');
            $table->date('health_certificate_date')->nullable()->comment('Ausstellungsdatum');

            // --- Beschaeftigung ---
            $table->date('employment_start')->nullable()->comment('Eintrittsdatum');
            $table->date('employment_end')->nullable()->comment('Austrittsdatum');
            $table->string('employment_type', 20)->nullable()->comment('full_time, part_time, mini_job, intern');
            $table->decimal('weekly_hours', 4, 1)->nullable()->comment('Wochenstunden');
            $table->date('probation_end')->nullable()->comment('Ende der Probezeit');
            $table->unsignedInteger('vacation_days')->nullable()->comment('Jahresurlaub');
            $table->text('salary')->nullable()->comment('Bruttogehalt (verschluesselt)');
            $table->string('salary_type', 10)->nullable()->comment('monthly, hourly');

            // --- Status ---
            $table->string('status', 20)->default('pending')->comment('pending, incomplete, complete, archived');
            $table->timestamp('completed_at')->nullable()->comment('Wann vollstaendig ausgefuellt');
            $table->text('notes')->nullable()->comment('Interne Notizen');
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
