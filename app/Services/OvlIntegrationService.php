<?php

namespace App\Services;

use App\Models\Commande;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OvlIntegrationService
{
    public function isConfigured(): bool
    {
        return filled(config('services.ovl.url'))
            && filled(config('services.ovl.identifiant'))
            && filled(config('services.ovl.token'));
    }

    /**
     * @return array{id: int|null, message: string, raw: array}
     */
    public function envoyerCommande(Commande $commande): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Configuration OVL manquante dans le fichier .env.');
        }

        if (!$commande->commune?->nom) {
            throw new RuntimeException('La commande doit avoir une commune avant l\'envoi vers OVL.');
        }

        if ($commande->ovl_commande_id) {
            throw new RuntimeException('Cette commande a déjà été envoyée vers OVL.');
        }

        $payload = [
            'communes' => $commande->commune->nom,
            'cout_global' => (int) round($commande->montant()),
            'cout_livraison' => (int) round((float) $commande->frais_livraison),
            'date_reception' => ($commande->date_commande ?? now())->format('Y-m-d'),
            'reference_externe' => $commande->reference,
        ];

        try {
            $response = Http::acceptJson()
                ->withoutVerifying()
                ->timeout((int) config('services.ovl.timeout', 15))
                ->withHeaders([
                    'X-Integration-Id' => (string) config('services.ovl.identifiant'),
                    'X-Integration-Token' => (string) config('services.ovl.token'),
                ])
                ->post((string) config('services.ovl.url'), $payload);

            if ($response->status() === 200 && data_get($response->json(), 'already_exists')) {
                $data = $response->json() ?? [];
                $ovlId = data_get($data, 'commande.id');

                $commande->forceFill([
                    'ovl_commande_id' => $ovlId ? (int) $ovlId : null,
                    'ovl_sent_at' => now(),
                ])->save();

                return [
                    'id' => $ovlId ? (int) $ovlId : null,
                    'message' => 'Commande déjà présente chez OVL.',
                    'raw' => is_array($data) ? $data : [],
                ];
            }

            $response->throw();
        } catch (RequestException $e) {
            $body = $e->response?->json();
            $message = is_array($body)
                ? ($body['message'] ?? $e->getMessage())
                : $e->getMessage();

            throw new RuntimeException('Échec envoi OVL : '.$message, previous: $e);
        }

        $data = $response->json() ?? [];
        $ovlId = data_get($data, 'commande.id');

        $commande->forceFill([
            'ovl_commande_id' => $ovlId ? (int) $ovlId : null,
            'ovl_sent_at' => now(),
        ])->save();

        return [
            'id' => $ovlId ? (int) $ovlId : null,
            'message' => (string) ($data['message'] ?? 'Commande envoyée vers OVL.'),
            'raw' => is_array($data) ? $data : [],
        ];
    }
}
