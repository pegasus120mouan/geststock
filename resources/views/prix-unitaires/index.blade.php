@extends('layout.main')

@section('title', 'Prix unitaire')

@section('content')
@php
  $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
  $openSection = request('section', 'en_gros');
  if (! in_array($openSection, ['en_gros', 'detail'], true)) {
    $openSection = 'en_gros';
  }
  $allPrix = $prixEnGros->concat($prixDetail);
@endphp

<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Prix unitaire</h4>
        <p class="mb-0 text-muted">Référence, contenance et tarif — cliquez une référence pour voir les parfums</p>
      </div>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNouveauPrix">
        <i class="bx bx-plus me-1"></i>Ajouter un prix
      </button>
    </div>

    @if (session('success'))
      <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <form method="GET" action="{{ route('prix-unitaires.index') }}" class="card mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-5">
            <label class="form-label">Recherche</label>
            <input
              type="text"
              name="q"
              class="form-control"
              placeholder="Référence, parfum ou flacon…"
              value="{{ request('q') }}" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Contenance</label>
            <select name="flacon_id" class="form-select">
              <option value="">Toutes</option>
              @foreach ($flacons as $flacon)
                <option value="{{ $flacon->id }}" @selected((string) request('flacon_id') === (string) $flacon->id)>
                  {{ $flacon->label() }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <input type="hidden" name="section" value="{{ $openSection }}" />
            <button class="btn btn-outline-primary w-100" type="submit">Filtrer</button>
          </div>
        </div>
      </div>
    </form>

    <div class="row g-3 mb-4" id="prixSectionTabs">
      <div class="col-md-6">
        <button
          type="button"
          class="card w-100 text-start border-0 shadow-none prix-section-card {{ $openSection === 'en_gros' ? 'prix-section-active' : '' }}"
          data-section="en_gros"
          style="{{ $openSection === 'en_gros' ? 'background: linear-gradient(135deg, #03c3ec, #0aa2c0);' : 'background: #e7f8fc;' }}">
          <div class="card-body d-flex justify-content-between align-items-center py-4">
            <div>
              <div class="text-uppercase small fw-semibold mb-1 {{ $openSection === 'en_gros' ? 'text-white' : 'text-info' }}" style="opacity: .9;">Catégorie</div>
              <h4 class="mb-0 {{ $openSection === 'en_gros' ? 'text-white' : 'text-heading' }}">Prix de gros</h4>
            </div>
            <div class="{{ $openSection === 'en_gros' ? 'text-white' : 'text-info' }}" style="font-size: 2rem; font-weight: 700; line-height: 1;">
              {{ $prixEnGros->count() }}
            </div>
          </div>
        </button>
      </div>
      <div class="col-md-6">
        <button
          type="button"
          class="card w-100 text-start border-0 shadow-none prix-section-card {{ $openSection === 'detail' ? 'prix-section-active' : '' }}"
          data-section="detail"
          style="{{ $openSection === 'detail' ? 'background: linear-gradient(135deg, #696cff, #5a5fe0);' : 'background: #efefff;' }}">
          <div class="card-body d-flex justify-content-between align-items-center py-4">
            <div>
              <div class="text-uppercase small fw-semibold mb-1 {{ $openSection === 'detail' ? 'text-white' : 'text-primary' }}" style="opacity: .9;">Catégorie</div>
              <h4 class="mb-0 {{ $openSection === 'detail' ? 'text-white' : 'text-heading' }}">Prix en détail</h4>
            </div>
            <div class="{{ $openSection === 'detail' ? 'text-white' : 'text-primary' }}" style="font-size: 2rem; font-weight: 700; line-height: 1;">
              {{ $prixDetail->count() }}
            </div>
          </div>
        </button>
      </div>
    </div>

    <div class="card" id="panelEnGros" @if ($openSection !== 'en_gros') style="display: none;" @endif>
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Liste des prix de gros</h5>
      </div>
      @include('prix-unitaires._table', ['prixUnitaires' => $prixEnGros, 'fmt' => $fmt, 'emptyMessage' => 'Aucun prix de gros enregistré.'])
    </div>

    <div class="card" id="panelDetail" @if ($openSection !== 'detail') style="display: none;" @endif>
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Liste des prix en détail</h5>
      </div>
      @include('prix-unitaires._table', ['prixUnitaires' => $prixDetail, 'fmt' => $fmt, 'emptyMessage' => 'Aucun prix en détail enregistré.'])
    </div>

    <div class="modal fade" id="modalNouveauPrix" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Ajouter un prix unitaire</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST" action="{{ route('prix-unitaires.store') }}">
            @csrf
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Référence</label>
                <input
                  type="text"
                  name="reference"
                  class="form-control @error('reference') is-invalid @enderror"
                  placeholder="Auto si vide (ex: PU-20260910-AB12)"
                  value="{{ old('reference') }}" />
                @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="mb-3">
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
              <div class="mb-3">
                <label class="form-label">Catégorie <span class="text-danger">*</span></label>
                <select name="categorie" class="form-select @error('categorie') is-invalid @enderror" required>
                  @foreach ($categories as $value => $label)
                    <option value="{{ $value }}" @selected(old('categorie', 'detail') === $value)>{{ $label }}</option>
                  @endforeach
                </select>
                @error('categorie')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="mb-3">
                <label class="form-label">Prix (FCFA) <span class="text-danger">*</span></label>
                <input
                  type="text"
                  inputmode="numeric"
                  class="form-control prix-fr-display @error('prix') is-invalid @enderror"
                  data-target="prix_create"
                  placeholder="Ex: 5 000"
                  value="{{ old('prix') !== null ? number_format((float) old('prix'), 0, ',', ' ') : '' }}"
                  autocomplete="off"
                  required />
                <input type="hidden" name="prix" id="prix_create" value="{{ old('prix') }}" />
                @error('prix')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              </div>
              <div class="mb-3">
                <label class="form-label">Parfums associés (optionnel)</label>
                <select name="produit_ids[]" class="form-select" multiple size="6">
                  @foreach ($produits as $produit)
                    <option value="{{ $produit->id }}" @selected(collect(old('produit_ids', []))->contains($produit->id))>
                      {{ $produit->nom }}
                    </option>
                  @endforeach
                </select>
                <div class="form-text">Maintenez Ctrl pour en sélectionner plusieurs.</div>
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

    @foreach ($allPrix as $prix)
      <div class="modal fade" id="modalDeletePrix{{ $prix->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-danger">
              <h5 class="modal-title text-white">Confirmer la suppression</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
              <p class="mb-0">
                Supprimer le prix <strong>{{ $prix->reference }}</strong>
                ({{ $prix->flacon?->contenance_ml }} ml — {{ $prix->categorieLabel() }}) ?
              </p>
            </div>
            <div class="modal-footer justify-content-center">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
              <form method="POST" action="{{ route('prix-unitaires.destroy', $prix) }}">
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
        var sectionInput = document.querySelector('input[name="section"]');
        var panelEnGros = document.getElementById('panelEnGros');
        var panelDetail = document.getElementById('panelDetail');
        var cards = document.querySelectorAll('.prix-section-card');

        function setSection(section) {
          var isGros = section === 'en_gros';
          if (sectionInput) sectionInput.value = section;
          if (panelEnGros) panelEnGros.style.display = isGros ? '' : 'none';
          if (panelDetail) panelDetail.style.display = isGros ? 'none' : '';

          cards.forEach(function (card) {
            var active = card.dataset.section === section;
            var isGrosCard = card.dataset.section === 'en_gros';
            card.classList.toggle('prix-section-active', active);
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
            if (title) {
              title.className = 'mb-0 ' + (active ? 'text-white' : 'text-heading');
            }
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
          var el = document.getElementById('modalNouveauPrix');
          if (el && window.bootstrap) new bootstrap.Modal(el).show();
        @endif
      });
    </script>
  </div>
</div>
@endsection
