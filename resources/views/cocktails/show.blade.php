@extends('layout.main')

@section('title', $cocktail->nom)

@section('content')
@php
  $fmt = fn ($n) => number_format((float) $n, 2, ',', ' ');
@endphp

<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <h4 class="mb-1">{{ $cocktail->nom }}</h4>
        <p class="mb-0 text-muted">
          Volume total : <strong>{{ $fmt($cocktail->volumeTotalMl()) }} ml</strong>
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
        <h5 class="mb-0">Composition (parfum + quantité)</h5>
      </div>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>Parfum</th>
              <th>Quantité</th>
              <th>Part</th>
            </tr>
          </thead>
          <tbody>
            @php $total = max($cocktail->volumeTotalMl(), 0.0001); @endphp
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
                <td>{{ $fmt($ligne->quantite_ml) }} ml</td>
                <td>{{ number_format(((float) $ligne->quantite_ml / $total) * 100, 1, ',', ' ') }} %</td>
              </tr>
            @empty
              <tr>
                <td colspan="3" class="text-center text-muted py-4">Aucune composition.</td>
              </tr>
            @endforelse
          </tbody>
          @if ($cocktail->lignes->isNotEmpty())
            <tfoot>
              <tr>
                <th>Total</th>
                <th colspan="2">{{ $fmt($cocktail->volumeTotalMl()) }} ml</th>
              </tr>
            </tfoot>
          @endif
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
