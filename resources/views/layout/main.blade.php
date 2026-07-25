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
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/iconify-icons.css') }}" />
  <link rel="stylesheet" href="{{ asset('assets/vendor/css/core.css') }}" />
  <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
  <script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>
  <script src="{{ asset('assets/js/config.js') }}"></script>
  @stack('styles')
</head>
<body>
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">
      <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
        <div class="app-brand demo">
          <a href="{{ route('dashboard') }}" class="app-brand-link">
            <span class="app-brand-text demo menu-text fw-bold ms-2">GestStock</span>
          </a>
          <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="bx bx-chevron-left d-block d-xl-none align-middle"></i>
          </a>
        </div>

        <div class="menu-inner-shadow"></div>

        <ul class="menu-inner py-1">
          <li class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <a href="{{ route('dashboard') }}" class="menu-link">
              <i class="menu-icon tf-icons bx bx-home-smile"></i>
              <div class="text-truncate">Tableau de bord</div>
            </a>
          </li>


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
                <li class="menu-item {{ request()->boolean('create') ? 'active' : '' }}">
                  <a href="{{ route('produits.index', ['create' => 1]) }}" class="menu-link">
                    <div class="text-truncate">Ajouter un parfum</div>
                  </a>
                </li>
              </ul>
            </li>

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

          @if(auth()->user()?->role === 'admin')
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

          <div class="navbar-nav-right d-flex align-items-center justify-content-end w-100" id="navbar-collapse">
            <ul class="navbar-nav flex-row align-items-center ms-auto">
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
