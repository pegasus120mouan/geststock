# Déploiement GestStock sur Apache

## Prérequis serveur

- PHP 8.2+ (extensions : `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `gd`)
- MySQL / MariaDB
- Apache avec `mod_rewrite` activé
- Composer

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

## 1. Envoyer le code

Sur le serveur :

```bash
cd /var/www
git clone VOTRE_REPO geststock
# ou uploader le projet (sans vendor/node_modules)
cd geststock
```

## 2. Configurer `.env`

```bash
cp .env.example .env
nano .env
```

Valeurs importantes :

```env
APP_NAME=GestStock
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votredomaine.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=geststock
DB_USERNAME=votre_user
DB_PASSWORD=votre_mot_de_passe

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```

Puis :

```bash
php artisan key:generate
```

## 3. Installer et migrer

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force   # crée le compte admin
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Ou :

```bash
bash deploy/deploy.sh
```

## 4. Permissions

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

## 5. Apache (DocumentRoot = public)

Copier l’exemple :

```bash
sudo cp deploy/apache-vhost.conf.example /etc/apache2/sites-available/geststock.conf
sudo nano /etc/apache2/sites-available/geststock.conf
```

Adapter `ServerName` et `DocumentRoot` (`/var/www/geststock/public`).

```bash
sudo a2ensite geststock
sudo a2dissite 000-default   # si besoin
sudo apache2ctl configtest
sudo systemctl reload apache2
```

## 6. HTTPS (recommandé)

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d votredomaine.com -d www.votredomaine.com
```

## 7. Hébergement mutualisé (cPanel)

Si vous ne pouvez pas changer le DocumentRoot :

1. Uploaderz le projet **au-dessus** de `public_html`
2. Placez le contenu de `public/` dans `public_html/`
3. Dans `public_html/index.php`, corrigez les chemins :

```php
require __DIR__.'/../geststock/vendor/autoload.php';
$app = require_once __DIR__.'/../geststock/bootstrap/app.php';
```

Ou gardez le `.htaccess` à la racine du projet qui redirige vers `public/`.

## Compte de connexion

Après seed :

- Login : `admin`
- Mot de passe : `password`

Changez ce mot de passe immédiatement en production.

## Checklist rapide

- [ ] DocumentRoot → `.../public`
- [ ] `AllowOverride All` + `mod_rewrite`
- [ ] `.env` production (`APP_DEBUG=false`)
- [ ] Base MySQL créée + migrate
- [ ] `storage` et `bootstrap/cache` en écriture
- [ ] `php artisan storage:link`
- [ ] HTTPS activé
