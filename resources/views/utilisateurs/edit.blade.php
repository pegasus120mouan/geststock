@extends('layout.main')
@section('content')
<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0">Modifier l'utilisateur</h4>
      <a href="{{ route('utilisateurs.index') }}" class="btn btn-outline-secondary">Retour</a>
    </div>

    <div class="card">
      <div class="card-body">
        <form method="POST" action="{{ route('utilisateurs.update', $utilisateur) }}" enctype="multipart/form-data">
          @csrf
          @method('PUT')

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Nom</label>
              <input type="text" name="name" class="form-control" value="{{ old('name', $utilisateur->name) }}" required />
              @error('name')<div class="text-danger mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Prénom</label>
              <input type="text" name="prenom" class="form-control" value="{{ old('prenom', $utilisateur->prenom) }}" />
              @error('prenom')<div class="text-danger mt-1">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Login</label>
              <input type="text" name="login" class="form-control" value="{{ old('login', $utilisateur->login) }}" required />
              @error('login')<div class="text-danger mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Contact</label>
              <input type="text" name="contact" class="form-control" value="{{ old('contact', $utilisateur->contact) }}" />
              @error('contact')<div class="text-danger mt-1">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Matricule</label>
              <input type="text" name="matricule" class="form-control" value="{{ old('matricule', $utilisateur->matricule) }}" />
              @error('matricule')<div class="text-danger mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Avatar</label>
              <input type="file" name="avatar" class="form-control" accept="image/*" />
              @error('avatar')<div class="text-danger mt-1">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Chef d'équipe</label>
              <select name="id_chef" class="form-select">
                <option value="">— Aucun (tous les agents) —</option>
                @foreach($chefsEquipe ?? [] as $chef)
                  <option value="{{ $chef['id_chef'] }}" @selected((int) old('id_chef', $utilisateur->id_chef ?? 0) === (int) $chef['id_chef'])>
                    {{ $chef['nom_complet'] }}
                    @if(!empty($chef['token']))
                      ({{ $chef['token'] }})
                    @endif
                  </option>
                @endforeach
              </select>
              <small class="text-muted">Le token est chargé automatiquement depuis ce chef à la connexion.</small>
              @error('id_chef')<div class="text-danger mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Rôle</label>
              <select name="role" class="form-select" required>
                <option value="admin" {{ old('role', $utilisateur->role) === 'admin' ? 'selected' : '' }}>admin</option>
                <option value="agent" {{ old('role', $utilisateur->role) === 'agent' ? 'selected' : '' }}>agent</option>
                <option value="driver" {{ old('role', $utilisateur->role) === 'driver' ? 'selected' : '' }}>driver</option>
              </select>
              @error('role')<div class="text-danger mt-1">{{ $message }}</div>@enderror
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Nouveau mot de passe (laisser vide pour ne pas changer)</label>
              <input type="password" name="password" class="form-control" />
              @error('password')<div class="text-danger mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Confirmer mot de passe</label>
              <input type="password" name="password_confirmation" class="form-control" />
            </div>
          </div>

          <div class="mt-3">
            <button type="submit" class="btn btn-primary">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
