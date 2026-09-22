@extends('layout.main')

@section('title', $cocktail->nom)

@section('content')
<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <h4 class="mb-1">{{ $cocktail->nom }}</h4>
        <p class="mb-0 text-muted">
          {{ $cocktail->lignes->count() }} parfum{{ $cocktail->lignes->count() > 1 ? 's' : '' }}
          ·
          <span class="badge {{ $cocktail->isActif() ? 'bg-label-success' : 'bg-label-secondary' }}">
            {{ ucfirst($cocktail->statut) }}
          </span>
        </p>
      </div>
      <div class="d-flex gap-2">
        <a href="{{ route('cocktails.index') }}" class="btn btn-outline-secondary">
          <i class="bx bx-arrow-back me-1"></i>Retour
        </a>
        <a href="{{ route('cocktails.index', ['edit' => $cocktail->id]) }}" class="btn btn-primary">
          <i class="bx bx-edit me-1"></i>Modifier
        </a>
      </div>
    </div>

    @if (session('success'))
      <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Composition</h5>
      </div>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>Parfum</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($cocktail->lignes as $ligne)
              <tr>
                <td class="fw-medium">
                  @if ($ligne->produit)
                    <a href="{{ route('produits.show', $ligne->produit) }}" class="text-heading text-decoration-none">
                      {{ $ligne->produit->nom }}
                    </a>
                  @else
                    —
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td class="text-center text-muted py-4">Aucune composition.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
