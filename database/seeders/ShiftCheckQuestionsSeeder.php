<?php

namespace Database\Seeders;

use App\Models\ShiftSettlementCheckQuestion;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Standard-Prueffragen fuer Schichtabrechnungen.
 * Basierend auf dem Papier-Schichtzettel.
 */
class ShiftCheckQuestionsSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            [
                'question_text' => 'Haben Sie Waren ohne Kassenbon angetroffen?',
                'question_type' => 'yes_no_comment',
                'sort_order' => 1,
            ],
            [
                'question_text' => 'Traegt der Kassierer Aral-Kleidung und Namensschild?',
                'question_type' => 'yes_no_comment',
                'sort_order' => 2,
            ],
            [
                'question_text' => 'Hat Ihr Vorgaenger alle Arbeiten zufriedenstellend erledigt?',
                'question_type' => 'yes_no_comment',
                'sort_order' => 3,
            ],
            [
                'question_text' => 'War eine reibungslose Weiterarbeit moeglich?',
                'question_type' => 'yes_no_comment',
                'sort_order' => 4,
            ],
        ];

        // Fuer jeden Tenant die Standard-Fragen anlegen
        $tenants = Tenant::all();
        $count = 0;

        foreach ($tenants as $tenant) {
            foreach ($questions as $data) {
                ShiftSettlementCheckQuestion::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'question_text' => $data['question_text'],
                    ],
                    [
                        'question_type' => $data['question_type'],
                        'sort_order' => $data['sort_order'],
                        'gas_station_id' => null, // gilt fuer alle Stationen
                        'is_active' => true,
                    ],
                );
                $count++;
            }
        }

        $this->command->info("  {$count} Prueffragen fuer {$tenants->count()} Tenant(s) erstellt/aktualisiert.");
    }
}
