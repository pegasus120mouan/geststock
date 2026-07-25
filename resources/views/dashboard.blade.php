@extends('layout.main')

@section('title', 'Tableau de bord')

@section('content')
@php
  $fmt = fn ($n, $dec = 0) => number_format((float) $n, $dec, ',', ' ');
@endphp

<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="row">
      {{-- Hero stock --}}
      <div class="col-xxl-8 mb-6 order-0">
        <div class="card">
          <div class="d-flex align-items-start row">
            <div class="col-sm-7">
              <div class="card-body">
                <h5 class="card-title text-primary mb-1">Stock actuel</h5>
                <p class="mb-6">UNIKO PARFUMS</p>
                <h1 class="display-6 text-danger mb-1 fw-bold">{{ $fmt($totalStockMl) }} ml</h1>
                <p class="mb-6 text-body-secondary">Volume total disponible en millilitres</p>
                <div class="d-flex flex-wrap gap-3">
                  <div class="bg-lighter rounded p-3 flex-grow-1" style="min-width: 140px;">
                    <small class="text-body-secondary d-block">Parfums actifs</small>
                    <span class="fw-semibold text-danger">{{ $fmt($stockActifsMl) }} ml</span>
                  </div>
                  <div class="bg-lighter rounded p-3 flex-grow-1" style="min-width: 140px;">
                    <small class="text-body-secondary d-block">Autres / inactifs</small>
                    <span class="fw-semibold text-danger">{{ $fmt($stockInactifsMl) }} ml</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-sm-5 text-center text-sm-left">
              <div class="card-body pb-0 px-0 px-md-6">
                <img
                  src="{{ asset('img/illustrations/man-with-laptop.png') }}"
                  height="175"
                  class="scaleX-n1-rtl"
                  alt="Illustration stock" />
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Mini stats haut droite --}}
      <div class="col-xxl-4 col-lg-12 col-md-4 order-1">
        <div class="row">
          <div class="col-lg-6 col-md-12 col-6 mb-6">
            <div class="card h-100">
              <div class="card-body">
                <div class="card-title d-flex align-items-start justify-content-between mb-4">
                  <div class="avatar flex-shrink-0">
                    <img src="{{ asset('img/icons/unicons/chart-success.png') }}" alt="Parfums" class="rounded" />
                  </div>
                </div>
                <span class="d-block mb-1">Parfums</span>
                <h4 class="card-title mb-0">{{ $fmt($nbParfums) }}</h4>
                <small class="text-success fw-medium">Références enregistrées</small>
              </div>
            </div>
          </div>
          <div class="col-lg-6 col-md-12 col-6 mb-6">
            <div class="card h-100">
              <div class="card-body">
                <div class="card-title d-flex align-items-start justify-content-between mb-4">
                  <div class="avatar flex-shrink-0">
                    <img src="{{ asset('img/icons/unicons/wallet-info.png') }}" alt="Flacons" class="rounded" />
                  </div>
                </div>
                <span class="d-block mb-1">Flacons</span>
                <h4 class="card-title mb-0">{{ $fmt($nbFlacons) }}</h4>
                <small class="text-body-secondary fw-medium">Contenants disponibles</small>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Tableau stock par parfum --}}
      <div class="col-12 col-xxl-8 order-2 mb-6">
        <div class="card h-100">
          <div class="card-header d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
              <span class="avatar avatar-sm bg-label-primary rounded">
                <i class="bx bx-package"></i>
              </span>
              <h5 class="card-title mb-0">Stock disponible par parfum</h5>
            </div>
            <a href="{{ route('produits.index') }}" class="btn btn-sm btn-outline-primary">Voir détails</a>
          </div>
          <div class="card-body">
            <div class="row g-3 mb-4">
              <div class="col-md-4">
                <div class="rounded p-3 bg-label-success">
                  <small class="d-block text-success">Total entrées</small>
                  <span class="fw-semibold">{{ $fmt($totalEntrees) }} ml</span>
                </div>
              </div>
              <div class="col-md-4">
                <div class="rounded p-3 bg-label-danger">
                  <small class="d-block text-danger">Total sorties</small>
                  <span class="fw-semibold">{{ $fmt($totalSorties) }} ml</span>
                </div>
              </div>
              <div class="col-md-4">
                <div class="rounded p-3 bg-label-info">
                  <small class="d-block text-info">Stock disponible</small>
                  <span class="fw-semibold">{{ $fmt($totalStockMl) }} ml</span>
                </div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-borderless mb-0">
                <thead>
                  <tr>
                    <th class="text-uppercase text-body-secondary small">Parfum</th>
                    <th class="text-uppercase text-body-secondary small">Entrées</th>
                    <th class="text-uppercase text-body-secondary small">Sorties</th>
                    <th class="text-uppercase text-body-secondary small">Disponible</th>
                    <th class="text-uppercase text-body-secondary small">Utilisation</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse ($stockParParfum as $ligne)
                    <tr>
                      <td>
                        <div class="d-flex align-items-center">
                          <span class="avatar avatar-xs me-2 bg-label-primary rounded">
                            <i class="bx bx-droplet"></i>
                          </span>
                          <span class="fw-medium">{{ $ligne->produit->nom }}</span>
                        </div>
                      </td>
                      <td class="text-success fw-medium">{{ $fmt($ligne->entrees) }} ml</td>
                      <td class="text-danger fw-medium">{{ $fmt($ligne->sorties) }} ml</td>
                      <td>
                        <span class="badge bg-label-info rounded-pill">{{ $fmt($ligne->disponible) }} ml</span>
                      </td>
                      <td style="min-width: 140px;">
                        <div class="d-flex align-items-center gap-2">
                          <div class="progress w-100" style="height: 8px;">
                            <div
                              class="progress-bar {{ $ligne->utilisation >= 80 ? 'bg-danger' : ($ligne->utilisation >= 50 ? 'bg-warning' : 'bg-success') }}"
                              role="progressbar"
                              style="width: {{ $ligne->utilisation }}%;"
                              aria-valuenow="{{ $ligne->utilisation }}"
                              aria-valuemin="0"
                              aria-valuemax="100"></div>
                          </div>
                          <small class="text-body-secondary">{{ $ligne->utilisation }}%</small>
                        </div>
                      </td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="5" class="text-center text-body-secondary py-4">
                        Aucun parfum enregistré pour le moment.
                      </td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      {{-- Widgets droite --}}
      <div class="col-12 col-md-8 col-lg-12 col-xxl-4 order-3">
        <div class="row">
          <div class="col-6 mb-6">
            <div class="card h-100">
              <div class="card-body">
                <div class="card-title d-flex align-items-start justify-content-between mb-4">
                  <div class="avatar flex-shrink-0">
                    <img src="{{ asset('img/icons/unicons/paypal.png') }}" alt="Ruptures" class="rounded" />
                  </div>
                </div>
                <span class="d-block mb-1">Ruptures</span>
                <h4 class="card-title text-danger mb-0">{{ $fmt($nbRuptures) }}</h4>
                <small class="text-danger fw-medium">Stock à 0 ml</small>
              </div>
            </div>
          </div>
          <div class="col-6 mb-6">
            <div class="card h-100">
              <div class="card-body">
                <div class="card-title d-flex align-items-start justify-content-between mb-4">
                  <div class="avatar flex-shrink-0">
                    <img src="{{ asset('img/icons/unicons/cc-primary.png') }}" alt="Mouvements" class="rounded" />
                  </div>
                </div>
                <span class="d-block mb-1">Mouvements</span>
                <h4 class="card-title mb-0">{{ $fmt($nbMouvements) }}</h4>
                <small class="text-success fw-medium">
                  <i class="bx bx-up-arrow-alt"></i> Entrées & sorties
                </small>
              </div>
            </div>
          </div>

          <div class="col-12 mb-6">
            <div class="card h-100" style="background: linear-gradient(135deg, #fff8f0 0%, #fff 70%);">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <div class="d-flex align-items-center gap-2">
                    <span class="avatar avatar-sm bg-label-warning rounded">
                      <i class="bx bx-time-five"></i>
                    </span>
                    <h5 class="mb-0">Stock faible</h5>
                  </div>
                  <span class="badge bg-warning rounded-pill">Sous {{ $seuilFaible }} ml</span>
                </div>

                <div class="d-flex align-items-end justify-content-between">
                  <div>
                    <h2 class="text-warning mb-1 fw-bold">{{ $fmt($nbStockFaible) }}</h2>
                    <p class="mb-0 text-body-secondary">
                      sur {{ $fmt($nbParfums) }} parfum{{ $nbParfums > 1 ? 's' : '' }} au total
                    </p>
                  </div>
                  <div class="d-flex align-items-center gap-2">
                    <span class="avatar bg-label-warning rounded">
                      <i class="bx bx-error"></i>
                    </span>
                    <a href="{{ route('produits.index') }}" class="btn btn-sm btn-outline-warning">
                      <i class="bx bx-show me-1"></i> Voir
                    </a>
                  </div>
                </div>

                @if ($stockFaible->isNotEmpty())
                  <ul class="list-unstyled mb-0 mt-4">
                    @foreach ($stockFaible->take(4) as $produit)
                      <li class="d-flex justify-content-between align-items-center py-1">
                        <span class="text-truncate me-2">{{ $produit->nom }}</span>
                        <span class="badge bg-label-warning">{{ $fmt($produit->stock_ml) }} ml</span>
                      </li>
                    @endforeach
                  </ul>
                @endif
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
