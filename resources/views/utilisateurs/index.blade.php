@extends('layout.main')
@section('content')
<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0">Utilisateurs</h4>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNouvelUtilisateur">Nouvel utilisateur</button>
    </div>

    <form method="GET" action="{{ route('utilisateurs.index') }}" class="mb-4">
      <div class="input-group">
        <input type="text" name="q" class="form-control" placeholder="Rechercher (nom, prénom, login, contact)" value="{{ request('q') }}" />
        <button class="btn btn-outline-secondary" type="submit">Rechercher</button>
      </div>
    </form>

    @if (session('code_pin_clair'))
      <div class="alert alert-success">
        Code PIN généré : <strong>{{ session('code_pin_clair') }}</strong>
      </div>
    @endif

    @if (session('success'))
      <div class="alert alert-success alert-dismissible fade show">
        <i class="bx bx-check-circle me-1"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    @if (session('error'))
      <div class="alert alert-danger alert-dismissible fade show">
        <i class="bx bx-error-circle me-1"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    <div class="card">
      <div class="table-responsive text-nowrap">
        <table class="table">
          <thead>
            <tr>
              <th>Avatar</th>
              <th>Nom</th>
              <th>Prénom</th>
              <th>Login</th>
              <th>Contact</th>
              <th>Matricule</th>
              <th>Rôle</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody class="table-border-bottom-0">
            @forelse($utilisateurs as $u)
              <tr>
                <td>
                  <div class="avatar avatar-online">
                    <img src="{{ $u->avatar_url }}" alt class="w-px-40 h-auto rounded-circle" />
                  </div>
                </td>
                <td>{{ $u->name }}</td>
                <td>{{ $u->prenom }}</td>
                <td>{{ $u->login }}</td>
                <td>{{ $u->contact }}</td>
                <td>{{ $u->matricule }}</td>
                <td>{{ $u->role }}</td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="{{ route('utilisateurs.edit', $u) }}" title="Modifier">
                    <i class="bx bx-edit"></i>
                  </a>
                  <button type="button" class="btn btn-sm btn-outline-danger" title="Supprimer" data-bs-toggle="modal" data-bs-target="#modalDeleteUser{{ $u->id }}">
                    <i class="bx bx-trash"></i>
                  </button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center">Aucun utilisateur</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="mt-3">
      {{ $utilisateurs->links() }}
    </div>

    <div class="modal fade" id="modalNouvelUtilisateur" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Créer un utilisateur</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form method="POST" action="{{ route('utilisateurs.store') }}" enctype="multipart/form-data">
              @csrf

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Nom</label>
                  <input type="text" name="name" class="form-control" value="{{ old('name') }}" required />
                  @error('name')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Prénom</label>
                  <input type="text" name="prenom" class="form-control" value="{{ old('prenom') }}" />
                  @error('prenom')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Login</label>
                  <input type="text" name="login" class="form-control" value="{{ old('login') }}" required />
                  @error('login')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Contact</label>
                  <input type="text" name="contact" class="form-control" value="{{ old('contact') }}" />
                  @error('contact')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                </div>
              </div>

              
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Rôle</label>
                  <select name="role" class="form-select" required>
                    <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>admin</option>
                    <option value="agent" {{ old('role') === 'agent' ? 'selected' : '' }}>agent</option>
                    <option value="driver" {{ old('role') === 'driver' ? 'selected' : '' }}>driver</option>
                  </select>
                  @error('role')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Mot de passe</label>
                  <div class="input-group">
                    <input type="password" name="password" id="password" class="form-control" required />
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password', 'eyeIcon1')">
                      <i class="bx bx-hide" id="eyeIcon1"></i>
                    </button>
                  </div>
                  <div id="passwordStrength" class="mt-1"></div>
                  @error('password')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Confirmer mot de passe</label>
                  <div class="input-group">
                    <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required />
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password_confirmation', 'eyeIcon2')">
                      <i class="bx bx-hide" id="eyeIcon2"></i>
                    </button>
                  </div>
                  <div id="passwordMatch" class="mt-1"></div>
                </div>
              </div>

              <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    @if ($errors->any() || request()->boolean('create'))
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          var el = document.getElementById('modalNouvelUtilisateur');
          if (el && window.bootstrap) {
            new bootstrap.Modal(el).show();
          }
        });
      </script>
    @endif
  </div>
</div>

@foreach($utilisateurs as $u)
<!-- Modal Supprimer Utilisateur -->
<div class="modal fade" id="modalDeleteUser{{ $u->id }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title text-white">
          <i class="bx bx-error-circle me-2"></i>Confirmer la suppression
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-4">
        <div class="mb-3">
          <i class="bx bx-user-x text-danger" style="font-size: 4rem;"></i>
        </div>
        <h5 class="mb-3">Êtes-vous sûr de vouloir supprimer cet utilisateur ?</h5>
        <div class="bg-light rounded p-3 mb-3">
          <p class="mb-1"><strong>Nom:</strong> {{ $u->name }} {{ $u->prenom }}</p>
          <p class="mb-1"><strong>Login:</strong> {{ $u->login }}</p>
          <p class="mb-0"><strong>Rôle:</strong> <span class="badge bg-secondary">{{ $u->role }}</span></p>
        </div>
        <p class="text-danger mb-0"><i class="bx bx-info-circle me-1"></i>Cette action est irréversible.</p>
      </div>
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="bx bx-x me-1"></i>Annuler
        </button>
        <form method="POST" action="{{ route('utilisateurs.destroy', $u) }}" class="d-inline">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn btn-danger">
            <i class="bx bx-trash me-1"></i>Supprimer
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
@endforeach

<script>
function togglePassword(inputId, iconId) {
  var input = document.getElementById(inputId);
  var icon = document.getElementById(iconId);
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.remove('bx-hide');
    icon.classList.add('bx-show');
  } else {
    input.type = 'password';
    icon.classList.remove('bx-show');
    icon.classList.add('bx-hide');
  }
}

document.addEventListener('DOMContentLoaded', function() {
  var passwordInput = document.getElementById('password');
  var confirmInput = document.getElementById('password_confirmation');
  var strengthDiv = document.getElementById('passwordStrength');
  var matchDiv = document.getElementById('passwordMatch');

  function checkPasswordStrength(password) {
    var strength = 0;
    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    return strength;
  }

  function updateStrengthIndicator() {
    var password = passwordInput.value;
    if (password.length === 0) {
      strengthDiv.innerHTML = '';
      return;
    }
    var strength = checkPasswordStrength(password);
    var text = '';
    var color = '';
    if (strength <= 1) {
      text = '<i class="bx bx-x-circle"></i> Faible';
      color = 'text-danger';
    } else if (strength <= 3) {
      text = '<i class="bx bx-minus-circle"></i> Moyen';
      color = 'text-warning';
    } else {
      text = '<i class="bx bx-check-circle"></i> Fort';
      color = 'text-success';
    }
    strengthDiv.innerHTML = '<small class="' + color + '">' + text + '</small>';
  }

  function checkPasswordMatch() {
    var password = passwordInput.value;
    var confirm = confirmInput.value;
    if (confirm.length === 0) {
      matchDiv.innerHTML = '';
      return;
    }
    if (password === confirm) {
      matchDiv.innerHTML = '<small class="text-success"><i class="bx bx-check-circle"></i> Les mots de passe correspondent</small>';
    } else {
      matchDiv.innerHTML = '<small class="text-danger"><i class="bx bx-x-circle"></i> Les mots de passe ne correspondent pas</small>';
    }
  }

  if (passwordInput) {
    passwordInput.addEventListener('input', function() {
      updateStrengthIndicator();
      checkPasswordMatch();
    });
  }

  if (confirmInput) {
    confirmInput.addEventListener('input', checkPasswordMatch);
  }
});
</script>
@endsection
