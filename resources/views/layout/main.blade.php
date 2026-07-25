<!DOCTYPE html>
<html
  lang="fr"
  class="layout-menu-fixed layout-compact"
  data-assets-path="{{ asset('assets') }}/"
  data-template="vertical-menu-template-free">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>@yield('title', config('app.name', 'GestStock'))</title>

  <link rel="icon" type="image/x-icon" href="{{ asset('img/favicon/favicon.ico') }}" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/iconify-icons.css') }}" />
  <link rel="stylesheet" href="{{ asset('assets/vendor/css/core.css') }}" />
  <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
  <script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>
  <script src="{{ asset('assets/js/config.js') }}"></script>
  <style>
    :root {
      --gs-ink: #14110f;
      --gs-ink-soft: #2a221e;
      --gs-champagne: #c4a574;
      --gs-sand: #efe6da;
      --gs-muted: rgba(239, 230, 218, 0.62);
      --gs-line: rgba(239, 230, 218, 0.12);
    }

    body {
      font-family: "Outfit", sans-serif;
      background: #f4efe8;
    }

    #layout-menu.menu-vertical {
      background: linear-gradient(180deg, #1a1512 0%, #100e0c 100%) !important;
      box-shadow: 8px 0 30px rgba(20, 17, 15, 0.18);
      border-right: 1px solid var(--gs-line);
    }

    #layout-menu .app-brand {
      min-height: 78px;
      padding: 0.9rem 1rem;
      border-bottom: 1px solid var(--gs-line);
      background: rgba(0, 0, 0, 0.18);
    }

    #layout-menu .app-brand-link {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      min-width: 0;
      text-decoration: none;
    }

    #layout-menu .brand-logo {
      width: 42px;
      height: 42px;
      border-radius: 10px;
      object-fit: cover;
      flex-shrink: 0;
      box-shadow: 0 0 0 1px rgba(196, 165, 116, 0.35);
    }

    #layout-menu .brand-copy {
      display: flex;
      flex-direction: column;
      min-width: 0;
      line-height: 1.15;
    }

    #layout-menu .brand-title {
      font-family: "Cormorant Garamond", serif;
      font-size: 1.15rem;
      font-weight: 700;
      color: #f7eee3;
      letter-spacing: 0.01em;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    #layout-menu .brand-subtitle {
      font-size: 0.68rem;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--gs-champagne);
      font-weight: 500;
      margin-top: 0.15rem;
    }

    #layout-menu .menu-inner {
      padding: 1rem 0.75rem 1.25rem !important;
    }

    #layout-menu .menu-header {
      margin: 0.85rem 0.5rem 0.45rem;
      padding: 0;
      color: rgba(196, 165, 116, 0.75);
      font-size: 0.68rem;
      font-weight: 600;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      line-height: 1.4;
    }

    #layout-menu .menu-inner > .menu-item {
      margin-bottom: 0.2rem;
    }

    #layout-menu .menu-link {
      border-radius: 12px !important;
      margin: 0 !important;
      color: var(--gs-muted) !important;
      font-weight: 450;
      font-size: 0.92rem;
      transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
    }

    #layout-menu .menu-icon {
      color: rgba(196, 165, 116, 0.85) !important;
    }

    #layout-menu .menu-item:not(.active):not(.open) > .menu-link:hover {
      background: rgba(196, 165, 116, 0.1) !important;
      color: #fff !important;
      transform: translateX(2px);
    }

    #layout-menu .menu-item.active > .menu-link,
    #layout-menu .menu-item.open > .menu-link {
      background: linear-gradient(135deg, rgba(196, 165, 116, 0.22), rgba(196, 165, 116, 0.08)) !important;
      color: #fff !important;
      box-shadow: inset 0 0 0 1px rgba(196, 165, 116, 0.25);
    }

    #layout-menu .menu-item.active > .menu-link .menu-icon,
    #layout-menu .menu-item.open > .menu-link .menu-icon {
      color: var(--gs-champagne) !important;
    }

    #layout-menu .menu-sub {
      padding-left: 0.35rem !important;
      margin-top: 0.15rem;
    }

    #layout-menu .menu-sub .menu-link {
      font-size: 0.86rem;
      padding-block: 0.55rem !important;
      color: rgba(239, 230, 218, 0.55) !important;
    }

    #layout-menu .menu-sub .menu-item.active > .menu-link {
      background: rgba(196, 165, 116, 0.16) !important;
      color: #fff !important;
    }

    #layout-menu .menu-toggle::after {
      color: rgba(196, 165, 116, 0.7) !important;
    }

    #layout-menu .app-brand .layout-menu-toggle {
      color: var(--gs-sand) !important;
    }

    .layout-navbar {
      background: rgba(255, 252, 247, 0.92) !important;
      backdrop-filter: blur(8px);
      border: 1px solid rgba(20, 17, 15, 0.06);
      box-shadow: 0 8px 24px rgba(20, 17, 15, 0.05);
    }
  </style>
  @stack('styles')
</head>
<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
        <div class="app-brand demo">
          <a href="{{ route('dashboard') }}" class="app-brand-link">
            <img src="{{ asset('img/logo/logo.jpeg') }}" alt="Logo" class="brand-logo" />
            <span class="brand-copy">
              <span class="brand-title">Gestion de stock</span>
              <span class="brand-subtitle">Uniko Parfums</span>
            </span>
          </a>
          <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="bx bx-chevron-left d-block d-xl-none align-middle"></i>
          </a>
        </div>

        <div class="menu-inner-shadow"></div>

        <ul class="menu-inner py-1">
          <li class="menu-header">Principal</li>
          <li class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <a href="{{ route('dashboard') }}" class="menu-link">
              <i class="menu-icon tf-icons bx bx-home-alt-2"></i>
              <div class="text-truncate">Tableau de bord</div>
            </a>
          </li>

          <li class="menu-header">Catalogue</li>
          <li class="menu-item {{ request()->routeIs('produits.*') ? 'active open' : '' }}">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-package"></i>
                <div class="text-truncate">Parfums</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('produits.index') && !request()->boolean('create') ? 'active' : '' }}">
                  <a href="{{ route('produits.index') }}" class="menu-link">
                    <div class="text-truncate">Liste des parfums</div>
                  </a>
                </li>
                <li class="menu-item {{ request()->routeIs('produits.*') && request()->boolean('create') ? 'active' : '' }}">
                  <a href="{{ route('produits.index', ['create' => 1]) }}" class="menu-link">
                    <div class="text-truncate">Ajouter un parfum</div>
                  </a>
                </li>
              </ul>
            </li>

            <li class="menu-item {{ request()->routeIs('flacons.*') ? 'active open' : '' }}">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-droplet"></i>
                <div class="text-truncate">Flacons</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('flacons.index') && !request()->boolean('create') ? 'active' : '' }}">
                  <a href="{{ route('flacons.index') }}" class="menu-link">
                    <div class="text-truncate">Liste des flacons</div>
                  </a>
                </li>
                <li class="menu-item {{ request()->boolean('create') && request()->routeIs('flacons.*') ? 'active' : '' }}">
                  <a href="{{ route('flacons.index', ['create' => 1]) }}" class="menu-link">
                    <div class="text-truncate">Ajouter un flacon</div>
                  </a>
                </li>
              </ul>
            </li>

          <li class="menu-header">Inventaire</li>
            <li class="menu-item {{ request()->routeIs('stock.*') ? 'active open' : '' }}">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-archive"></i>
                <div class="text-truncate">Stock</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('stock.entrees') ? 'active' : '' }}">
                  <a href="{{ route('stock.entrees') }}" class="menu-link">
                    <div class="text-truncate">Entrées de stock</div>
                  </a>
                </li>
                <li class="menu-item {{ request()->routeIs('stock.sorties') ? 'active' : '' }}">
                  <a href="{{ route('stock.sorties') }}" class="menu-link">
                    <div class="text-truncate">Historique des sorties</div>
                  </a>
                </li>
              </ul>
            </li>

          <li class="menu-header">Ventes</li>
            <li class="menu-item {{ request()->routeIs('commandes.*') ? 'active open' : '' }}">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-cart"></i>
                <div class="text-truncate">Commandes</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('commandes.index') ? 'active' : '' }}">
                  <a href="{{ route('commandes.index') }}" class="menu-link">
                    <div class="text-truncate">Liste des commandes</div>
                  </a>
                </li>
              </ul>
            </li>

            <li class="menu-item {{ request()->routeIs('prix-unitaires.*') ? 'active open' : '' }}">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-purchase-tag"></i>
                <div class="text-truncate">Prix Unitaire</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('prix-unitaires.index') ? 'active' : '' }}">
                  <a href="{{ route('prix-unitaires.index') }}" class="menu-link">
                    <div class="text-truncate">Liste des prix</div>
                  </a>
                </li>
              </ul>
            </li>

          @if(auth()->user()?->role === 'admin')
            <li class="menu-header">Administration</li>
            <li class="menu-item {{ request()->routeIs('utilisateurs.*') ? 'active open' : '' }}">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-user"></i>
                <div class="text-truncate">Utilisateurs</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('utilisateurs.index') ? 'active' : '' }}">
                  <a href="{{ route('utilisateurs.index') }}" class="menu-link">
                    <div class="text-truncate">Tous les utilisateurs</div>
                  </a>
                </li>
              </ul>
            </li>
          @endif
        </ul>
      </aside>

      <div class="layout-page">
        <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
          <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
            <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
              <i class="bx bx-menu bx-md"></i>
            </a>
          </div>

          <div class="navbar-nav-right d-flex align-items-center w-100" id="navbar-collapse">
            <div class="navbar-nav align-items-center flex-grow-1">
              <div class="nav-item d-flex align-items-center w-100 w-md-auto" style="max-width: 360px;">
                <i class="bx bx-search bx-md"></i>
                <input
                  type="text"
                  class="form-control border-0 shadow-none bg-transparent ps-2"
                  placeholder="Search..."
                  aria-label="Search..." />
              </div>
            </div>
            <ul class="navbar-nav flex-row align-items-center ms-auto">
              @php
                $nbRupturesNav = \App\Models\Produit::query()
                  ->where('statut', 'actif')
                  ->where('stock_ml', '<=', 0)
                  ->count();
              @endphp
              <li class="nav-item me-3">
                <a href="{{ route('produits.index') }}" class="btn btn-sm btn-outline-secondary position-relative">
                  En attente
                  <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    {{ $nbRupturesNav }}
                  </span>
                </a>
              </li>
              <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                  <div class="avatar avatar-online">
                    <img src="{{ auth()->user()->avatar_url ?? asset('img/avatars/default.png') }}" alt class="w-px-40 h-auto rounded-circle" />
                  </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                    <a class="dropdown-item" href="javascript:void(0);">
                      <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                          <div class="avatar avatar-online">
                            <img src="{{ auth()->user()->avatar_url ?? asset('img/avatars/default.png') }}" alt class="w-px-40 h-auto rounded-circle" />
                          </div>
                        </div>
                        <div class="flex-grow-1">
                          <h6 class="mb-0">{{ auth()->user()->name }} {{ auth()->user()->prenom }}</h6>
                          <small class="text-body-secondary">{{ auth()->user()->role }}</small>
                        </div>
                      </div>
                    </a>
                  </li>
                  <li><div class="dropdown-divider my-1"></div></li>
                  <li>
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                      @csrf
                      <button type="submit" class="dropdown-item">
                        <i class="icon-base bx bx-power-off icon-md me-3"></i>
                        <span>Déconnexion</span>
                      </button>
                    </form>
                  </li>
                </ul>
              </li>
            </ul>
          </div>
        </nav>

        @yield('content')
      </div>
    </div>

    <div class="layout-overlay layout-menu-toggle"></div>
  </div>

  <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
  <script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
  <script src="{{ asset('assets/vendor/js/menu.js') }}"></script>
  <script src="{{ asset('assets/js/main.js') }}"></script>
  @stack('scripts')
</body>
</html>
