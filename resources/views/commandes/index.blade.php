@extends('layout.main')

@section('title', 'Commandes')

@section('content')
@php
  $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
  $openSection = request('section', 'en_gros');
  if (! in_array($openSection, ['en_gros', 'detail'], true)) {
    $openSection = 'en_gros';
  }
  $editId = old('_edit_id', request('edit'));
  $oldLignes = old('lignes', [['categorie' => $openSection, 'produit_id' => '', 'flacon_id' => '', 'quantite' => 1]]);
@endphp

<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-1">Liste des commandes</h4>
        <p class="mb-0 text-muted">Une commande peut regrouper plusieurs parfums (gros et/ou détail)</p>
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
          class="card w-100 text-start border-0 shadow-none commande-section-card"
          data-section="en_gros"
          style="{{ $openSection === 'en_gros' ? 'background: linear-gradient(135deg, #03c3ec, #0aa2c0);' : 'background: #e7f8fc;' }}">
          <div class="card-body d-flex justify-content-between align-items-center py-4">
            <div>
              <div class="text-uppercase small fw-semibold mb-1 {{ $openSection === 'en_gros' ? 'text-white' : 'text-info' }}" style="opacity: .9;">Contient du</div>
              <h4 class="mb-0 {{ $openSection === 'en_gros' ? 'text-white' : 'text-heading' }}">Gros</h4>
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
          class="card w-100 text-start border-0 shadow-none commande-section-card"
          data-section="detail"
          style="{{ $openSection === 'detail' ? 'background: linear-gradient(135deg, #696cff, #5a5fe0);' : 'background: #efefff;' }}">
          <div class="card-body d-flex justify-content-between align-items-center py-4">
            <div>
              <div class="text-uppercase small fw-semibold mb-1 {{ $openSection === 'detail' ? 'text-white' : 'text-primary' }}" style="opacity: .9;">Contient du</div>
              <h4 class="mb-0 {{ $openSection === 'detail' ? 'text-white' : 'text-heading' }}">Détail</h4>
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
        <h5 class="mb-0">Commandes avec lignes en gros</h5>
      </div>
      @include('commandes._table', [
        'commandes' => $commandesEnGros,
        'fmt' => $fmt,
        'section' => 'en_gros',
        'emptyMessage' => 'Aucune commande avec des lignes en gros.',
      ])
    </div>

    <div class="card" id="panelCommandesDetail" @if ($openSection !== 'detail') style="display: none;" @endif>
      <div class="card-header">
        <h5 class="mb-0">Commandes avec lignes en détail</h5>
      </div>
      @include('commandes._table', [
        'commandes' => $commandesDetail,
        'fmt' => $fmt,
        'section' => 'detail',
        'emptyMessage' => 'Aucune commande avec des lignes en détail.',
      ])
    </div>

    <div class="modal fade" id="modalNouvelleCommande" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Ajouter une commande</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form method="POST" action="{{ route('commandes.store') }}">
            @csrf
            <div class="modal-body">
              @if ($errors->any() && ! $editId)
                <div class="alert alert-danger">
                  <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                      <li>{{ $error }}</li>
                    @endforeach
                  </ul>
                </div>
              @endif

              <div class="row g-3 mb-4">
                <div class="col-md-3">
                  <label class="form-label">Date commande <span class="text-danger">*</span></label>
                  <input
                    type="date"
                    name="date_commande"
                    class="form-control @error('date_commande') is-invalid @enderror"
                    value="{{ old('date_commande') }}"
                    required />
                </div>
                <div class="col-md-3">
                  <label class="form-label">Statut</label>
                  <select name="statut" class="form-select" required>
                    <option value="en_attente" @selected(old('statut', 'en_attente') === 'en_attente')>En attente</option>
                    <option value="confirmee" @selected(old('statut') === 'confirmee')>Confirmée</option>
                    <option value="livree" @selected(old('statut') === 'livree')>Livrée</option>
                    <option value="annulee" @selected(old('statut') === 'annulee')>Annulée</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Téléphone <span class="text-danger">*</span></label>
                  <input
                    type="text"
                    name="client_telephone"
                    class="form-control"
                    placeholder="Ex: 07 00 00 00 00"
                    value="{{ old('client_telephone') }}"
                    required />
                </div>
                <div class="col-md-3">
                  <label class="form-label">Nom du client</label>
                  <input
                    type="text"
                    name="client_nom"
                    class="form-control"
                    placeholder="Optionnel"
                    value="{{ old('client_nom') }}" />
                </div>
                <div class="col-12">
                  <label class="form-label">Notes</label>
                  <input
                    type="text"
                    name="notes"
                    class="form-control"
                    placeholder="Optionnel"
                    value="{{ old('notes') }}" />
                </div>
              </div>

              <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Articles (parfum + catégorie + quantité)</h6>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddLigneCommande">
                  <i class="bx bx-plus me-1"></i>Ajouter un parfum
                </button>
              </div>

              <div class="table-responsive">
                <table class="table" id="tableLignesCommande">
                  <thead>
                    <tr>
                      <th style="min-width: 140px;">Catégorie <span class="text-danger">*</span></th>
                      <th style="min-width: 220px;">Parfum <span class="text-danger">*</span></th>
                      <th style="min-width: 140px;">Contenance <span class="text-danger">*</span></th>
                      <th style="min-width: 100px;">Qté <span class="text-danger">*</span></th>
                      <th style="width: 60px;"></th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach ($oldLignes as $index => $ligne)
                      <tr class="ligne-commande">
                        <td>
                          <select name="lignes[{{ $index }}][categorie]" class="form-select" required>
                            <option value="en_gros" @selected(($ligne['categorie'] ?? $openSection) === 'en_gros')>En gros</option>
                            <option value="detail" @selected(($ligne['categorie'] ?? $openSection) === 'detail')>Détail</option>
                          </select>
                        </td>
                        <td>
                          <select name="lignes[{{ $index }}][produit_id]" class="form-select" required>
                            <option value="">Sélectionner</option>
                            @foreach ($produits as $produit)
                              <option value="{{ $produit->id }}" @selected((string) ($ligne['produit_id'] ?? '') === (string) $produit->id)>
                                {{ $produit->nom }}
                              </option>
                            @endforeach
                          </select>
                        </td>
                        <td>
                          <select name="lignes[{{ $index }}][flacon_id]" class="form-select" required>
                            <option value="">Sélectionner</option>
                            @foreach ($flacons as $flacon)
                              <option value="{{ $flacon->id }}" @selected((string) ($ligne['flacon_id'] ?? '') === (string) $flacon->id)>
                                {{ $flacon->label() }}
                              </option>
                            @endforeach
                          </select>
                        </td>
                        <td>
                          <input
                            type="number"
                            name="lignes[{{ $index }}][quantite]"
                            class="form-control"
                            min="1"
                            step="1"
                            value="{{ $ligne['quantite'] ?? 1 }}"
                            required />
                        </td>
                        <td class="text-end">
                          <button type="button" class="btn btn-sm btn-outline-danger btn-remove-ligne-commande" title="Retirer">
                            <i class="bx bx-trash"></i>
                          </button>
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
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

    @foreach ($allCommandes as $commande)
      @php
        $isThisEdit = (string) $editId === (string) $commande->id;
        $editRows = $isThisEdit && is_array(old('lignes'))
          ? old('lignes')
          : ($commande->lignes->isNotEmpty()
            ? $commande->lignes->map(fn ($l) => [
              'categorie' => $l->categorie,
              'produit_id' => $l->produit_id,
              'flacon_id' => $l->flacon_id,
              'quantite' => $l->quantite,
            ])->all()
            : [['categorie' => 'detail', 'produit_id' => '', 'flacon_id' => '', 'quantite' => 1]]);
      @endphp

      <div class="modal fade" id="modalEditCommande{{ $commande->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Modifier {{ $commande->reference }}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('commandes.update', $commande) }}">
              @csrf
              @method('PUT')
              <input type="hidden" name="_edit_id" value="{{ $commande->id }}" />
              <input type="hidden" name="section" value="{{ $openSection }}" />
              <div class="modal-body">
                @if ($errors->any() && $isThisEdit)
                  <div class="alert alert-danger">
                    <ul class="mb-0">
                      @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                      @endforeach
                    </ul>
                  </div>
                @endif

                <div class="row g-3 mb-4">
                  <div class="col-md-3">
                    <label class="form-label">Date commande <span class="text-danger">*</span></label>
                    <input
                      type="date"
                      name="date_commande"
                      class="form-control"
                      value="{{ $isThisEdit ? old('date_commande', optional($commande->date_commande)->format('Y-m-d')) : optional($commande->date_commande)->format('Y-m-d') }}"
                      required />
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Statut</label>
                    <select name="statut" class="form-select" required>
                      @php $statutVal = $isThisEdit ? old('statut', $commande->statut) : $commande->statut; @endphp
                      <option value="en_attente" @selected($statutVal === 'en_attente')>En attente</option>
                      <option value="confirmee" @selected($statutVal === 'confirmee')>Confirmée</option>
                      <option value="livree" @selected($statutVal === 'livree')>Livrée</option>
                      <option value="annulee" @selected($statutVal === 'annulee')>Annulée</option>
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Téléphone <span class="text-danger">*</span></label>
                    <input
                      type="text"
                      name="client_telephone"
                      class="form-control"
                      value="{{ $isThisEdit ? old('client_telephone', $commande->client_telephone) : $commande->client_telephone }}"
                      required />
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Nom du client</label>
                    <input
                      type="text"
                      name="client_nom"
                      class="form-control"
                      value="{{ $isThisEdit ? old('client_nom', $commande->client_nom) : $commande->client_nom }}" />
                  </div>
                  <div class="col-12">
                    <label class="form-label">Notes</label>
                    <input
                      type="text"
                      name="notes"
                      class="form-control"
                      value="{{ $isThisEdit ? old('notes', $commande->notes) : $commande->notes }}" />
                  </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h6 class="mb-0">Articles (parfum + catégorie + quantité)</h6>
                  <button type="button" class="btn btn-sm btn-outline-primary btn-add-ligne-edit" data-target="editLignesCommande{{ $commande->id }}">
                    <i class="bx bx-plus me-1"></i>Ajouter un parfum
                  </button>
                </div>

                <div class="table-responsive">
                  <table class="table" id="editLignesCommande{{ $commande->id }}">
                    <thead>
                      <tr>
                        <th style="min-width: 140px;">Catégorie <span class="text-danger">*</span></th>
                        <th style="min-width: 220px;">Parfum <span class="text-danger">*</span></th>
                        <th style="min-width: 140px;">Contenance <span class="text-danger">*</span></th>
                        <th style="min-width: 100px;">Qté <span class="text-danger">*</span></th>
                        <th style="width: 60px;"></th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($editRows as $index => $ligne)
                        <tr class="ligne-commande">
                          <td>
                            <select name="lignes[{{ $index }}][categorie]" class="form-select" required>
                              <option value="en_gros" @selected(($ligne['categorie'] ?? '') === 'en_gros')>En gros</option>
                              <option value="detail" @selected(($ligne['categorie'] ?? '') === 'detail')>Détail</option>
                            </select>
                          </td>
                          <td>
                            <select name="lignes[{{ $index }}][produit_id]" class="form-select" required>
                              <option value="">Sélectionner</option>
                              @foreach ($produits as $produit)
                                <option value="{{ $produit->id }}" @selected((string) ($ligne['produit_id'] ?? '') === (string) $produit->id)>
                                  {{ $produit->nom }}
                                </option>
                              @endforeach
                            </select>
                          </td>
                          <td>
                            <select name="lignes[{{ $index }}][flacon_id]" class="form-select" required>
                              <option value="">Sélectionner</option>
                              @foreach ($flacons as $flacon)
                                <option value="{{ $flacon->id }}" @selected((string) ($ligne['flacon_id'] ?? '') === (string) $flacon->id)>
                                  {{ $flacon->label() }}
                                </option>
                              @endforeach
                            </select>
                          </td>
                          <td>
                            <input
                              type="number"
                              name="lignes[{{ $index }}][quantite]"
                              class="form-control"
                              min="1"
                              step="1"
                              value="{{ $ligne['quantite'] ?? 1 }}"
                              required />
                          </td>
                          <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-ligne-commande" title="Retirer">
                              <i class="bx bx-trash"></i>
                            </button>
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary">Mettre à jour</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    @endforeach

    <template id="tplLigneCommande">
      <tr class="ligne-commande">
        <td>
          <select name="lignes[__INDEX__][categorie]" class="form-select" required>
            <option value="en_gros">En gros</option>
            <option value="detail">Détail</option>
          </select>
        </td>
        <td>
          <select name="lignes[__INDEX__][produit_id]" class="form-select" required>
            <option value="">Sélectionner</option>
            @foreach ($produits as $produit)
              <option value="{{ $produit->id }}">{{ $produit->nom }}</option>
            @endforeach
          </select>
        </td>
        <td>
          <select name="lignes[__INDEX__][flacon_id]" class="form-select" required>
            <option value="">Sélectionner</option>
            @foreach ($flacons as $flacon)
              <option value="{{ $flacon->id }}">{{ $flacon->label() }}</option>
            @endforeach
          </select>
        </td>
        <td>
          <input type="number" name="lignes[__INDEX__][quantite]" class="form-control" min="1" step="1" value="1" required />
        </td>
        <td class="text-end">
          <button type="button" class="btn btn-sm btn-outline-danger btn-remove-ligne-commande" title="Retirer">
            <i class="bx bx-trash"></i>
          </button>
        </td>
      </tr>
    </template>

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var sectionInput = document.querySelector('input[name="section"]');
        var panelEnGros = document.getElementById('panelCommandesEnGros');
        var panelDetail = document.getElementById('panelCommandesDetail');
        var cards = document.querySelectorAll('.commande-section-card');
        var createTbody = document.querySelector('#tableLignesCommande tbody');
        var tpl = document.getElementById('tplLigneCommande').innerHTML;
        var createIndex = createTbody.querySelectorAll('.ligne-commande').length;

        function setSection(section) {
          var isGros = section === 'en_gros';
          if (sectionInput) sectionInput.value = section;
          if (panelEnGros) panelEnGros.style.display = isGros ? '' : 'none';
          if (panelDetail) panelDetail.style.display = isGros ? 'none' : '';

          cards.forEach(function (card) {
            var active = card.dataset.section === section;
            var isGrosCard = card.dataset.section === 'en_gros';
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

        document.getElementById('btnAddLigneCommande').addEventListener('click', function () {
          createTbody.insertAdjacentHTML('beforeend', tpl.replaceAll('__INDEX__', String(createIndex)));
          createIndex += 1;
        });

        document.querySelectorAll('.btn-add-ligne-edit').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var table = document.getElementById(btn.getAttribute('data-target'));
            if (!table) return;
            var tbody = table.querySelector('tbody');
            var index = tbody.querySelectorAll('.ligne-commande').length;
            tbody.insertAdjacentHTML('beforeend', tpl.replaceAll('__INDEX__', String(index)));
          });
        });

        document.addEventListener('click', function (e) {
          var btn = e.target.closest('.btn-remove-ligne-commande');
          if (!btn) return;
          var tbody = btn.closest('tbody');
          if (!tbody) return;
          var rows = tbody.querySelectorAll('.ligne-commande');
          if (rows.length <= 1) return;
          btn.closest('tr').remove();
        });

        @if (($errors->any() && ! $editId) || request()->boolean('create'))
          var createEl = document.getElementById('modalNouvelleCommande');
          if (createEl && window.bootstrap) new bootstrap.Modal(createEl).show();
        @elseif ($editId)
          var editEl = document.getElementById('modalEditCommande{{ $editId }}');
          if (editEl && window.bootstrap) new bootstrap.Modal(editEl).show();
        @endif
      });
    </script>
  </div>
</div>
@endsection
