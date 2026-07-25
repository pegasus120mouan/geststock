<?php
// Vérifier d'où vient le prix 150

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

echo "=== RECHERCHE DU PRIX 150 ===\n\n";

// 1. Vérifier si le prix 150 est dans prix_agents pour l'agent API 1
echo "1. Prix dans prix_agents pour agent API 1 (Ouattara Peter):\n";
$prixAgents = \App\Models\PrixAgent::where('id_agent', 1)->get();
foreach ($prixAgents as $p) {
    if ($p->prix == 150) {
        echo "   ⚠️  PRIX 150 TROUVÉ: Usine {$p->id_usine}, Type: {$p->type}\n";
    }
}
echo "\n";

// 2. Vérifier les tickets avec prix 150
echo "2. Tickets avec prix_unitaire = 150 pour Ouattara Peter:\n";
$tickets = \App\Models\Ticket::where('particulier_agent_id', 1)
    ->where('prix_unitaire', 150)
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();

foreach ($tickets as $t) {
    echo "   Ticket #{$t->numero_ticket}:\n";
    echo "   - Usine ID: {$t->id_usine}\n";
    echo "   - Matricule: {$t->matricule_vehicule}\n";
    echo "   - Date: {$t->date_ticket}\n";
    
    // Vérifier la fiche de sortie
    $fiche = \App\Models\FicheSortie::where('id_ticket', $t->id_ticket)->first();
    if ($fiche) {
        echo "   - Fiche sortie trouvée:\n";
        echo "     * Prix transport: {$fiche->prix_unitaire_transport}\n";
    }
}
