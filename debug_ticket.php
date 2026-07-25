<?php
// Debug du ticket 1902JH01

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

echo "=== DEBUG TICKET 1902JH01 ===\n\n";

// Ticket spécifique
$ticket = \App\Models\Ticket::where('matricule_vehicule', '1902JH01')
    ->where('particulier_agent_id', 1)
    ->orderBy('created_at', 'desc')
    ->first();

if ($ticket) {
    echo "Ticket trouvé:\n";
    echo "- ID: {$ticket->id_ticket}\n";
    echo "- Numéro: {$ticket->numero_ticket}\n";
    echo "- Prix unitaire (DB): " . ($ticket->prix_unitaire ?? 'NULL') . "\n";
    echo "- Usine ID: {$ticket->id_usine}\n";
    echo "- Agent ID: {$ticket->particulier_agent_id}\n";
    echo "- Matricule: {$ticket->matricule_vehicule}\n";
    echo "- Date ticket: {$ticket->date_ticket}\n\n";

    // Fiche de sortie
    $fiche = \App\Models\FicheSortie::where('id_ticket', $ticket->id_ticket)->first();
    if ($fiche) {
        echo "Fiche de sortie:\n";
        echo "- ID: {$fiche->id}\n";
        echo "- Prix unitaire transport: " . ($fiche->prix_unitaire_transport ?? 'NULL') . "\n";
        echo "- Produit ID: {$fiche->produit_id}\n";
        echo "- Usine: {$fiche->usine}\n\n";
    }

    // Prix de l'agent pour cette usine
    echo "Prix de l'agent (particulier_agent_prix):\n";
    $prixAgent = \App\Models\ParticulierAgentPrix::where('particulier_agent_id', 1)
        ->where('id_usine', $ticket->id_usine)
        ->get();
    foreach ($prixAgent as $p) {
        echo "- Usine {$p->id_usine}: {$p->prix} FCFA (Type: " . ($p->type_transporteur ?? 'null') . ")\n";
    }

    if ($prixAgent->isEmpty()) {
        echo "AUCUN PRIX TROUVÉ pour cette usine!\n";
    }

    // Prix dans prix_agents (API)
    echo "\nPrix dans prix_agents:\n";
    $prixApi = \App\Models\PrixAgent::where('id_agent', 1)
        ->where('id_usine', $ticket->id_usine)
        ->get();
    foreach ($prixApi as $p) {
        echo "- Type: {$p->type}, Prix: {$p->prix}\n";
    }

    if ($prixApi->isEmpty()) {
        echo "AUCUN PRIX API trouvé!\n";
    }
} else {
    echo "Ticket non trouvé\n";
}
