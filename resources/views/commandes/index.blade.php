@extends('layout.main')

@section('title', 'Commandes')

@section('content')
@php
  $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
  $openSection = request('section', 'en_gros');
  if (! in_array($openSection, ['en_gros', 'detail'], true)) {
    $openSection = 'en_gros';
  }
@endphp

<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Liste des commandes</h4>
        <p class="mb-0 text-muted">Suivi des commandes clients Uniko Parfums</p>
      </div>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNouvelleCommande">
        <i class="bx bx-plus me-1"></i>Ajouter une commande
      </button>
    </div>

    @if (session('success'))
      <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    @if ($errors->has('statut'))
      <div class="alert alert-danger alert-dismissible fade show">
        {{ $errors->first('statut') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <form method="GET" action="{{ route('commandes.index') }}" class="card mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-6">
            <label class="form-label">Recherche</label>
            <input
              type="text"
              name="q"
              class="form-control"
              placeholder="Référence, client, téléphone, parfum…"
              value="{{ request('q') }}" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Statut</label>
            <select name="statut" class="form-select">
              <option value="">Tous</option>
              <option value="en_attente" @selected(request('statut') === 'en_attente')>En attente</option>
              <option value="confirmee" @selected(request('statut') === 'confirmee')>Confirmée</option>
              <option value="livree" @selected(request('statut') === 'livree')>Livrée</option>
              <option value="annulee" @selected(request('statut') === 'annulee')>Annulée</option>
            </select>
          </div>
          <div class="col-md-3">
            <input type="hidden" name="section" value="{{ $openSection }}" />
            <button class="btn btn-outline-primary w-100" type="submit">Filtrer</button>
          </div>
        </div>
      </div>
    </form>

    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <button
          type="button"
          class="card w-100 text-start border-0 shadow-none commande-section-card {{ $openSection === 'en_gros' ? 'commande-section-active' : '' }}"
          data-section="en_gros"
          style="{{ $openSection === 'en_gros' ? 'background: linear-gradient(135deg, #03c3ec, #0aa2c0);' : 'background: #e7f8fc;' }}">
          <div class="card-body d-flex justify-content-between align-items-center py-4">
            <div>
              <div class="text-uppercase small fw-semibold mb-1 {{ $openSection === 'en_gros' ? 'text-white' : 'text-info' }}" style="opacity: .9;">Catégorie</div>
              <h4 class="mb-0 {{ $openSection === 'en_gros' ? 'text-white' : 'text-heading' }}">Commandes en gros</h4>
            </div>
            <div class="{{ $openSection === 'en_gros' ? 'text-white' : 'text-info' }}" style="font-size: 2rem; font-weight: 700; line-height: 1;">
              {{ $commandesEnGros->count() }}
            </div>
          </div>
        </button>
      </div>
      <div class="col-md-6">
        <button
          type="button"
          class="card w-100 text-start border-0 shadow-none commande-section-card {{ $openSection === 'detail' ? 'commande-section-active' : '' }}"
          data-section="detail"
          style="{{ $openSection === 'detail' ? 'background: linear-gradient(135deg, #696cff, #5a5fe0);' : 'background: #efefff;' }}">
          <div class="card-body d-flex justify-content-between align-items-center py-4">
            <div>
              <div class="text-uppercase small fw-semibold mb-1 {{ $openSection === 'detail' ? 'text-white' : 'text-primary' }}" style="opacity: .9;">Catégorie</div>
              <h4 class="mb-0 {{ $openSection === 'detail' ? 'text-white' : 'text-heading' }}">Commandes en détail</h4>
            </div>
            <div class="{{ $openSection === 'detail' ? 'text-white' : 'text-primary' }}" style="font-size: 2rem; font-weight: 700; line-height: 1;">
              {{ $commandesDetail->count() }}
            </div>
          </div>
        </button>
      </div>
    </div>

    <div class="card" id="panelCommandesEnGros" @if ($openSection !== 'en_gros') style="display: none;" @endif>
      <div class="card-header">
        <h5 class="mb-0">Liste des commandes en gros</h5>
      </div>
      @include('commandes._table', [
        'commandes' => $commandesEnGros,
        'fmt' => $fmt,
        'emptyMessage' => 'Aucune commande en gros enregistrée.',
      ])
    </div>

    <div class="card" id="panelCommandesDetail" @if ($openSection !== 'detail') style="display: none;" @endif>
      <div class="card-header">
        <h5 class="mb-0">Liste des commandes en détail</h5>
      </div>
      @include('commandes._table', [
        'commandes' => $commandesDetail,
        'fmt' => $fmt,
        'emptyMessage' => 'Aucune commande en détail enregistrée.',
      ])
    </div>

    <div class="modal fade" id="modalNouvelleCommande" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Ajouter une commande</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST" action="{{ route('commandes.store') }}">
            @csrf
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Catégorie <span class="text-danger">*</span></label>
                  <select name="categorie" class="form-select @error('categorie') is-invalid @enderror" required>
                    <option value="en_gros" @selected(old('categorie', $openSection) === 'en_gros')>En gros</option>
                    <option value="detail" @selected(old('categorie', $openSection) === 'detail')>Détail</option>
                  </select>
                  @error('categorie')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label">Statut</label>
                  <select name="statut" class="form-select @error('statut') is-invalid @enderror" required>
                    <option value="en_attente" @selected(old('statut', 'en_attente') === 'en_attente')>En attente</option>
                    <option value="confirmee" @selected(old('statut') === 'confirmee')>Confirmée</option>
                    <option value="livree" @selected(old('statut') === 'livree')>Livrée</option>
                    <option value="annulee" @selected(old('statut') === 'annulee')>Annulée</option>
                  </select>
                  @error('statut')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label">Parfum <span class="text-danger">*</span></label>
                  <select name="produit_id" class="form-select @error('produit_id') is-invalid @enderror" required>
                    <option value="">Sélectionner un parfum</option>
                    @foreach ($produits as $produit)
                      <option value="{{ $produit->id }}" @selected((string) old('produit_id') === (string) $produit->id)>
                        {{ $produit->nom }}
                      </option>
                    @endforeach
                  </select>
                  @error('produit_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                  <label class="form-label">Contenance <span class="text-danger">*</span></label>
                  <select name="flacon_id" class="form-select @error('flacon_id') is-invalid @enderror" required>
                    <option value="">Sélectionner une contenance</option>
                    @foreach ($flacons as $flacon)
                      <option value="{{ $flacon->id }}" @selected((string) old('flacon_id') === (string) $flacon->id)>
                        {{ $flacon->label() }}
                      </option>
                    @endforeach
                  </select>
                  @error('flacon_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                  <label class="form-label">Quantité <span class="text-danger">*</span></label>
                  <input
                    type="number"
                    name="quantite"
                    class="form-control @error('quantite') is-invalid @enderror"
                    min="1"
                    step="1"
                    value="{{ old('quantite', 1) }}"
                    required />
                  @error('quantite')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                  <label class="form-label">Téléphone <span class="text-danger">*</span></label>
                  <input
                    type="text"
                    name="client_telephone"
                    class="form-control @error('client_telephone') is-invalid @enderror"
                    placeholder="Ex: 07 00 00 00 00"
                    value="{{ old('client_telephone') }}"
                    required />
                  @error('client_telephone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                  <label class="form-label">Nom du client</label>
                  <input
                    type="text"
                    name="client_nom"
                    class="form-control @error('client_nom') is-invalid @enderror"
                    placeholder="Optionnel"
                    value="{{ old('client_nom') }}" />
                  @error('client_nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                  <label class="form-label">Notes</label>
                  <input
                    type="text"
                    name="notes"
                    class="form-control @error('notes') is-invalid @enderror"
                    placeholder="Optionnel"
                    value="{{ old('notes') }}" />
                  @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
              <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var sectionInput = document.querySelector('input[name="section"]');
        var panelEnGros = document.getElementById('panelCommandesEnGros');
        var panelDetail = document.getElementById('panelCommandesDetail');
        var cards = document.querySelectorAll('.commande-section-card');
        var categorieSelect = document.querySelector('#modalNouvelleCommande select[name="categorie"]');

        function setSection(section) {
          var isGros = section === 'en_gros';
          if (sectionInput) sectionInput.value = section;
          if (panelEnGros) panelEnGros.style.display = isGros ? '' : 'none';
          if (panelDetail) panelDetail.style.display = isGros ? 'none' : '';
          if (categorieSelect && !categorieSelect.dataset.userTouched) {
            categorieSelect.value = section;
          }

          cards.forEach(function (card) {
            var active = card.dataset.section === section;
            var isGrosCard = card.dataset.section === 'en_gros';
            card.classList.toggle('commande-section-active', active);
            card.style.background = active
              ? (isGrosCard ? 'linear-gradient(135deg, #03c3ec, #0aa2c0)' : 'linear-gradient(135deg, #696cff, #5a5fe0)')
              : (isGrosCard ? '#e7f8fc' : '#efefff');

            var label = card.querySelector('.text-uppercase');
            var title = card.querySelector('h4');
            var count = card.querySelector('.card-body > div:last-child');
            if (label) {
              label.className = 'text-uppercase small fw-semibold mb-1 ' + (active ? 'text-white' : (isGrosCard ? 'text-info' : 'text-primary'));
              label.style.opacity = '.9';
            }
            if (title) title.className = 'mb-0 ' + (active ? 'text-white' : 'text-heading');
            if (count) {
              count.className = active ? 'text-white' : (isGrosCard ? 'text-info' : 'text-primary');
              count.style.fontSize = '2rem';
              count.style.fontWeight = '700';
              count.style.lineHeight = '1';
            }
          });
        }

        cards.forEach(function (card) {
          card.addEventListener('click', function () {
            setSection(card.dataset.section);
          });
        });

        if (categorieSelect) {
          categorieSelect.addEventListener('change', function () {
            categorieSelect.dataset.userTouched = '1';
          });
        }

        @if ($errors->any() || request()->boolean('create'))
          var el = document.getElementById('modalNouvelleCommande');
          if (el && window.bootstrap) new bootstrap.Modal(el).show();
        @endif
      });
    </script>
  </div>
</div>
@endsection
