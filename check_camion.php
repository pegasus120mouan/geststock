<?php
// Script pour vérifier le type de transporteur d'un camion

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$matricule = '1902JH01';

echo "=== Vérification du camion : $matricule ===\n\n";

// Vérifier dans code_transporteur_vehicules
$link = \App\Models\CodeTransporteurVehicule::with('codeTransporteur')
    ->where('matricule_vehicule', $matricule)
    ->first();

if ($link && $link->codeTransporteur) {
    echo "✓ Camion trouvé dans la table code_transporteur_vehicules\n";
    echo "  - Code transporteur : " . $link->codeTransporteur->nom . "\n";
    echo "  - ID du code : " . $link->codeTransporteur->id . "\n";
} else {
    echo "✗ Camion NON trouvé dans code_transporteur_vehicules\n";
    echo "  -> Considéré comme 'transporteur' (prix générique)\n";
}

// Vérifier les tickets de ce camion
$tickets = \App\Models\Ticket::where('matricule_vehicule', $matricule)
    ->whereNotNull('prix_unitaire')
    ->orderBy('date_ticket', 'desc')
    ->limit(5)
    ->get();

echo "\n=== Derniers tickets avec ce camion ===\n";
foreach ($tickets as $ticket) {
    echo "Ticket #{$ticket->numero_ticket} : {$ticket->prix_unitaire} FCFA\n";
}
