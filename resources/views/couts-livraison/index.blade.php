@extends('layout.main')

@section('title', 'Coût de livraison')

@section('content')
@php
  $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
@endphp

<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Coût de livraison</h4>
        <p class="mb-0 text-muted">Tarif de livraison par commune</p>
      </div>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNouveauCout">
        <i class="bx bx-plus me-1"></i>Nouveau coût
      </button>
    </div>

    @if (session('success'))
      <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <form method="GET" action="{{ route('couts-livraison.index') }}" class="card mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-6">
            <label class="form-label">Recherche</label>
            <input type="text" name="q" class="form-control" placeholder="Nom de la commune…" value="{{ request('q') }}" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Statut</label>
            <select name="statut" class="form-select">
              <option value="">Tous</option>
              <option value="actif" @selected(request('statut') === 'actif')>Actif</option>
              <option value="inactif" @selected(request('statut') === 'inactif')>Inactif</option>
            </select>
          </div>
          <div class="col-md-3">
            <button class="btn btn-outline-primary w-100" type="submit">Filtrer</button>
          </div>
        </div>
      </div>
    </form>

    <div class="card">
      <div class="table-responsive text-nowrap">
        <table class="table">
          <thead>
            <tr>
              <th>Commune</th>
              <th>Montant</th>
              <th>Statut</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($couts as $cout)
              <tr>
                <td class="fw-medium">{{ $cout->commune?->nom ?? '—' }}</td>
                <td class="fw-semibold text-primary">{{ $fmt($cout->montant) }} FCFA</td>
                <td>
                  <span class="badge {{ $cout->isActif() ? 'bg-label-success' : 'bg-label-secondary' }}">
                    {{ ucfirst($cout->statut) }}
                  </span>
                </td>
                <td class="text-end">
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-primary"
                    title="Modifier"
                    data-bs-toggle="modal"
                    data-bs-target="#modalEditCout{{ $cout->id }}">
                    <i class="bx bx-edit"></i>
                  </button>
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-danger"
                    title="Supprimer"
                    data-bs-toggle="modal"
                    data-bs-target="#modalDeleteCout{{ $cout->id }}">
                    <i class="bx bx-trash"></i>
                  </button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="text-center py-4 text-muted">Aucun coût de livraison enregistré.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($couts->hasPages())
        <div class="card-footer">{{ $couts->links() }}</div>
      @endif
    </div>

    <div class="modal fade" id="modalNouveauCout" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <form method="POST" action="{{ route('couts-livraison.store') }}" class="modal-content">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Nouveau coût de livraison</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Commune <span class="text-danger">*</span></label>
              <select name="commune_id" class="form-select" required>
                <option value="">Sélectionner une commune</option>
                @forelse ($communesDisponibles as $commune)
                  <option value="{{ $commune->id }}" @selected((string) old('commune_id') === (string) $commune->id)>
                    {{ $commune->nom }}
                  </option>
                @empty
                  <option value="" disabled>Toutes les communes ont déjà un coût</option>
                @endforelse
              </select>
              @error('commune_id')<div class="text-danger mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
              <label class="form-label">Montant (FCFA) <span class="text-danger">*</span></label>
              <input
                type="text"
                inputmode="numeric"
                class="form-control prix-fr-display"
                data-target="montant_create"
                placeholder="Ex: 1 500"
                value="{{ old('montant') !== null ? number_format((float) old('montant'), 0, ',', ' ') : '' }}"
                autocomplete="off"
                required />
              <input type="hidden" name="montant" id="montant_create" value="{{ old('montant') }}" />
              @error('montant')<div class="text-danger mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
              <label class="form-label">Statut</label>
              <select name="statut" class="form-select" required>
                <option value="actif" @selected(old('statut', 'actif') === 'actif')>Actif</option>
                <option value="inactif" @selected(old('statut') === 'inactif')>Inactif</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>

    @foreach ($couts as $cout)
      <div class="modal fade" id="modalEditCout{{ $cout->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" role="document">
          <form method="POST" action="{{ route('couts-livraison.update', $cout) }}" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
              <h5 class="modal-title">Modifier le coût</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Commune <span class="text-danger">*</span></label>
                <select name="commune_id" class="form-select" required>
                  @foreach ($communes as $commune)
                    <option value="{{ $commune->id }}" @selected((string) $cout->commune_id === (string) $commune->id)>
                      {{ $commune->nom }}
                    </option>
                  @endforeach
                  @if ($cout->commune && ! $communes->contains('id', $cout->commune_id))
                    <option value="{{ $cout->commune->id }}" selected>{{ $cout->commune->nom }}</option>
                  @endif
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Montant (FCFA) <span class="text-danger">*</span></label>
                <input
                  type="text"
                  inputmode="numeric"
                  class="form-control prix-fr-display"
                  data-target="montant_edit_{{ $cout->id }}"
                  value="{{ number_format((float) $cout->montant, 0, ',', ' ') }}"
                  autocomplete="off"
                  required />
                <input type="hidden" name="montant" id="montant_edit_{{ $cout->id }}" value="{{ (int) $cout->montant }}" />
              </div>
              <div class="mb-3">
                <label class="form-label">Statut</label>
                <select name="statut" class="form-select" required>
                  <option value="actif" @selected($cout->statut === 'actif')>Actif</option>
                  <option value="inactif" @selected($cout->statut === 'inactif')>Inactif</option>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
              <button type="submit" class="btn btn-primary">Mettre à jour</button>
            </div>
          </form>
        </div>
      </div>

      <div class="modal fade" id="modalDeleteCout{{ $cout->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-danger">
              <h5 class="modal-title text-white">Confirmer la suppression</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
              <p class="mb-0">
                Supprimer le coût pour <strong>{{ $cout->commune?->nom }}</strong>
                ({{ $fmt($cout->montant) }} FCFA) ?
              </p>
            </div>
            <div class="modal-footer justify-content-center">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
              <form method="POST" action="{{ route('couts-livraison.destroy', $cout) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">Supprimer</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    @endforeach

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        function sanitize(value) {
          return String(value || '').replace(/\s/g, '').replace(/[^\d]/g, '');
        }
        function formatFr(value) {
          var clean = sanitize(value);
          if (clean === '') return '';
          clean = clean.replace(/^0+(?=\d)/, '');
          return clean.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        }
        document.querySelectorAll('.prix-fr-display').forEach(function (display) {
          var hidden = document.getElementById(display.dataset.target);
          if (!hidden) return;
          function sync() {
            var formatted = formatFr(display.value);
            display.value = formatted;
            hidden.value = sanitize(formatted);
          }
          display.addEventListener('input', sync);
          display.addEventListener('blur', sync);
          sync();
        });
        @if ($errors->any() || request()->boolean('create'))
          var el = document.getElementById('modalNouveauCout');
          if (el && window.bootstrap) new bootstrap.Modal(el).show();
        @endif
      });
    </script>
  </div>
</div>
@endsection
