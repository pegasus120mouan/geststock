# Contexte du Projet - Gestion Camions

## Description
Application Laravel de gestion de flotte de camions avec suivi des tickets de pesage, dépenses et ponts bascule.

---

## Base de Données

### Tables Externes (API)

#### `vehicules`
```sql
- vehicules_id (int) - PK
- matricule_vehicule (varchar)
- date_mise_circulation (date)
- id_user (int) - FK propriétaire
```

#### `tickets`
```sql
- id_ticket (int) - PK
- vehicule_id (int) - FK
- matricule_vehicule (varchar)
- nom_usine (varchar)
- agent_nom (varchar)
- agent_prenom (varchar)
- poids_brut (decimal)
- poids_tare (decimal)
- poids_net (decimal)
- date_pesee (datetime)
```

#### `pont_bascule`
```sql
CREATE TABLE `pont_bascule` (
  `id_pont` int(11) NOT NULL,
  `code_pont` varchar(50) NOT NULL,
  `nom_pont` varchar(255) NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `gerant` varchar(100) NOT NULL,
  `cooperatif` varchar(100) DEFAULT NULL,
  `statut` enum('Actif','Inactif') NOT NULL DEFAULT 'Actif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

### Tables Locales (Laravel)

#### `depenses`
```sql
CREATE TABLE `depenses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `vehicule_id` int NOT NULL,
  `matricule_vehicule` varchar(50) NOT NULL,
  `type_depense` varchar(100) NOT NULL,
  `description` text,
  `montant` decimal(12,2) NOT NULL,
  `date_depense` date NOT NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  PRIMARY KEY (`id`),
  KEY `depenses_vehicule_id_index` (`vehicule_id`)
);
```

#### `users`
```sql
- id (bigint) - PK
- name (varchar)
- email (varchar)
- password (varchar)
- role (enum: 'proprietaire', 'admin', 'agent')
- external_user_id (int) - ID utilisateur API externe
```

---

## APIs Externes

Base URL: `https://api.objetombrepegasus.online/api/camions/`

| Endpoint | Description | Auth |
|----------|-------------|------|
| `login.php` | Authentification | Non |
| `mes_camions.php` | Liste des véhicules du propriétaire | Session |
| `mes_tickets.php` | Liste des tickets de pesage | Session |
| `mes_ponts.php` | Liste de tous les ponts bascule | Non |

---

## Fonctionnalités Implémentées

### Dashboard
- Nombre de camions (depuis API)
- Total des dépenses (depuis DB locale)
- Nombre de tickets (depuis API)

### Camions
- Liste des véhicules avec matricule cliquable
- Lien vers les dépenses du véhicule
- Colonne dépenses avec total

### Tickets
- Liste des tickets de pesage
- Recherche par véhicule, usine, agent
- Autocomplétion du matricule véhicule
- Matricule cliquable vers dépenses

### Dépenses
- CRUD local (base Laravel)
- Formulaire modal d'ajout
- Pagination
- Format montant avec espaces (200 000)
- Format date jj-mm-aaaa

### Ponts de Pesage
- Liste depuis API externe
- Affichage code, nom, gérant, coopératif, statut

---

## CI/CD

### GitHub Actions
Fichier: `.github/workflows/deploy.yml`

**Secrets requis:**
- `SSH_HOST` - IP du serveur VPS
- `SSH_USER` - Utilisateur SSH (ex: root)
- `SSH_PORT` - Port SSH (ex: 22)
- `SSH_PRIVATE_KEY` - Clé privée SSH
- `APP_DIR` - Chemin du projet (ex: /var/www/html/gestcamions)

**Actions:**
1. Récupération du code (git fetch/reset)
2. Installation dépendances (composer install)
3. Nettoyage cache
4. Migrations
5. Optimisation cache
6. Permissions storage/bootstrap
7. Redémarrage PHP-FPM

---

## Demandes Futures

<!-- Ajouter ici les nouvelles demandes -->

---

## Notes Techniques

- Laravel 11.x
- PHP 8.2+
- MySQL/MariaDB
- Authentification hybride (locale + API externe)
- Session externe stockée via `PHPSESSID`
