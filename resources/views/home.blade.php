<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Camions - Accueil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
        }
        .login-left {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-right {
            padding: 3rem;
        }
        .brand-logo {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        .feature-list {
            list-style: none;
            padding: 0;
        }
        .feature-list li {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }
        .feature-list i {
            margin-right: 0.75rem;
            font-size: 1.25rem;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        @media (max-width: 768px) {
            .login-left {
                display: none;
            }
            .login-right {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="row g-0 h-100">
            <div class="col-md-5">
                <div class="login-left h-100">
                    <div class="brand-logo">
                        <i class="bx bx-truck"></i> Gestion Camions
                    </div>
                    <h3 class="mb-4">Plateforme de Gestion</h3>
                    <p class="mb-4">Système complet pour la gestion des camions, agents, pisteurs et suivi des opérations.</p>
                    <ul class="feature-list">
                        <li>
                            <i class="bx bx-check-circle"></i>
                            <span>Gestion des camions et véhicules</span>
                        </li>
                        <li>
                            <i class="bx bx-check-circle"></i>
                            <span>Suivi des agents et pisteurs</span>
                        </li>
                        <li>
                            <i class="bx bx-check-circle"></i>
                            <span>Contrôle des stocks par pont et parc</span>
                        </li>
                        <li>
                            <i class="bx bx-check-circle"></i>
                            <span>Gestion financière intégrée</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="col-md-7">
                <div class="login-right">
                    <div class="text-center mb-4">
                        <h2 class="mb-3">Connexion</h2>
                        <p class="text-muted">Accédez à votre espace de travail</p>
                    </div>
                    
                    <form action="/login" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Identifiant</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bx bx-user"></i>
                                </span>
                                <input type="text" name="login" class="form-control" placeholder="Entrez votre identifiant" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mot de passe</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bx bx-lock-alt"></i>
                                </span>
                                <input type="password" name="password" class="form-control" placeholder="Entrez votre mot de passe" required>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="remember" class="form-check-input" id="remember">
                            <label class="form-check-label" for="remember">
                                Se souvenir de moi
                            </label>
                        </div>
                        
                        @if($errors->any())
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                {{ $errors->first('login') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-login">
                                <i class="bx bx-log-in-circle me-2"></i>Se connecter
                            </button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p class="text-muted small">
                            <i class="bx bx-shield-check"></i> 
                            Connexion sécurisée avec chiffrement SHA-1
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
