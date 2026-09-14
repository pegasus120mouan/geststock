<?php

namespace App\Support;

class ModulePermissions
{
    public const DASHBOARD = 'dashboard';

    public const FINANCE = 'finance';

    public const PRODUITS = 'produits';

    public const FLACONS = 'flacons';

    public const COCKTAILS = 'cocktails';

    public const STOCK = 'stock';

    public const COMMANDES = 'commandes';

    public const PRIX_UNITAIRES = 'prix_unitaires';

    public const COMMUNES = 'communes';

    public const COUTS_LIVRAISON = 'couts_livraison';

    /**
     * Modules consultables par un gestionnaire (l'admin coche lesquels).
     *
     * @return array<string, string>
     */
    public static function catalog(): array
    {
        return [
            self::DASHBOARD => 'Tableau de bord',
            self::FINANCE => 'Gestion financière',
            self::PRODUITS => 'Parfums',
            self::FLACONS => 'Flacons',
            self::COCKTAILS => 'Cocktails',
            self::STOCK => 'Stock',
            self::COMMANDES => 'Commandes',
            self::PRIX_UNITAIRES => 'Prix unitaires',
            self::COMMUNES => 'Communes',
            self::COUTS_LIVRAISON => 'Coût de livraison',
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::catalog());
    }

    /**
     * Mappe un nom de route Laravel vers un module.
     */
    public static function moduleForRoute(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        return match (true) {
            $routeName === 'dashboard' => self::DASHBOARD,
            str_starts_with($routeName, 'finance.') => self::FINANCE,
            str_starts_with($routeName, 'produits.') => self::PRODUITS,
            str_starts_with($routeName, 'flacons.') => self::FLACONS,
            str_starts_with($routeName, 'cocktails.') => self::COCKTAILS,
            str_starts_with($routeName, 'stock.') => self::STOCK,
            str_starts_with($routeName, 'commandes.') => self::COMMANDES,
            str_starts_with($routeName, 'prix-unitaires.') => self::PRIX_UNITAIRES,
            str_starts_with($routeName, 'communes.') => self::COMMUNES,
            str_starts_with($routeName, 'couts-livraison.') => self::COUTS_LIVRAISON,
            str_starts_with($routeName, 'utilisateurs.') => null,
            default => null,
        };
    }

    public static function homeRouteForModules(array $permissions): string
    {
        $order = [
            self::DASHBOARD => 'dashboard',
            self::COMMANDES => 'commandes.index',
            self::PRODUITS => 'produits.index',
            self::STOCK => 'stock.entrees',
            self::FLACONS => 'flacons.index',
            self::COCKTAILS => 'cocktails.index',
            self::PRIX_UNITAIRES => 'prix-unitaires.index',
            self::COMMUNES => 'communes.index',
            self::COUTS_LIVRAISON => 'couts-livraison.index',
            self::FINANCE => 'finance.bilan-mois',
        ];

        foreach ($order as $module => $route) {
            if (in_array($module, $permissions, true)) {
                return $route;
            }
        }

        return 'dashboard';
    }
}
