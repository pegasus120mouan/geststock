<?php
// Diagnostic complet du prix du ticket

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$matricule = '1902JH01';
$idUsine = 30; // VOP - à vérifier
$dateTicket = '2026-06-11';

echo "=== DIAGNOSTIC PRIX POUR CAMION $matricule ===\n\n";

// 1. Vérifier le code transporteur du camion
$link = \App\Models\CodeTransporteurVehicule::with('codeTransporteur')
    ->where('matricule_vehicule', $matricule)
    ->first();

if ($link && $link->codeTransporteur) {
    echo "1. Code transporteur du camion :\n";
    echo "   - Nom : " . $link->codeTransporteur->nom . "\n";
    echo "   - ID : " . $link->codeTransporteur->id . "\n";
    $typePrix = strtolower($link->codeTransporteur->nom) === 'camion pgf' ? 'pgf' : 
                (strtolower($link->codeTransporteur->nom) === 'autre camion' ? 'autre_camion' : 'transporteur');
    echo "   - Type prix : $typePrix\n\n";
} else {
    echo "1. Aucun code transporteur trouvé → type 'transporteur'\n\n";
    $typePrix = 'transporteur';
}

// 2. Vérifier les prix de Ouattara Peter (agent ID 1)
$agent = \App\Models\ParticulierAgent::find(1);
if ($agent) {
    echo "2. Agent : " . $agent->nom_complet . " (ID: {$agent->id})\n";
    echo "   - ID Agent API : " . ($agent->id_agent ?? 'null') . "\n\n";

    // 3. Vérifier les prix particulier_agent_prix
    echo "3. Prix dans particulier_agent_prix :\n";
    $prixAgent = \App\Models\ParticulierAgentPrix::where('particulier_agent_id', $agent->id)
        ->where('id_usine', $idUsine)
        ->get();
    
    if ($prixAgent->isEmpty()) {
        echo "   Aucun prix trouvé pour cette usine ($idUsine)\n";
    } else {
        foreach ($prixAgent as $p) {
            echo "   - Usine: {$p->nom_usine}, Type: " . ($p->type_transporteur ?? 'null') . ", Prix: {$p->prix}\n";
        }
    }
    echo "\n";

    // 4. Vérifier les prix dans prix_agents (API)
    if ($agent->id_agent) {
        echo "4. Prix dans prix_agents (API) pour agent {$agent->id_agent} :\n";
        $prixApi = \App\Models\PrixAgent::where('id_agent', $agent->id_agent)
            ->where('id_usine', $idUsine)
            ->get();
        
        if ($prixApi->isEmpty()) {
            echo "   Aucun prix trouvé pour cette usine ($idUsine)\n";
        } else {
            foreach ($prixApi as $p) {
                echo "   - Type: {$p->type}, Prix: {$p->prix}, Usine: {$p->id_usine}\n";
            }
        }
    }
}

// 5. Vérifier le ticket spécifique
echo "\n5. Ticket avec ce camion :\n";
$tickets = \App\Models\Ticket::where('matricule_vehicule', $matricule)
    ->where('particulier_agent_id', 1)
    ->orderBy('created_at', 'desc')
    ->limit(3)
    ->get();

foreach ($tickets as $t) {
    echo "   Ticket #{$t->numero_ticket}:\n";
    echo "   - Usine ID: {$t->id_usine}\n";
    echo "   - Prix unitaire (DB): " . ($t->prix_unitaire ?? 'null') . "\n";
    echo "   - Date: " . ($t->date_ticket ?? 'null') . "\n";
}
