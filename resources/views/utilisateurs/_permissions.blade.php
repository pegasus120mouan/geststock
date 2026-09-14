@php
  $selectedPermissions = old('permissions', $selectedPermissions ?? []);
  $selectedPermissions = is_array($selectedPermissions) ? $selectedPermissions : [];
  $roleValue = old('role', $roleValue ?? 'gestionnaire');
@endphp

<div class="row">
  <div class="col-md-6 mb-3">
    <label class="form-label">Rôle</label>
    <select name="role" id="{{ $roleSelectId ?? 'role' }}" class="form-select role-select" required>
      <option value="admin" @selected($roleValue === 'admin')>Administrateur</option>
      <option value="gestionnaire" @selected($roleValue === 'gestionnaire')>Gestionnaire</option>
    </select>
    <div class="form-text">Admin = tous les droits. Gestionnaire = consultation des modules cochés.</div>
    @error('role')<div class="text-danger mt-1">{{ $message }}</div>@enderror
  </div>
</div>

<div class="mb-3 permissions-box {{ $roleValue === 'gestionnaire' ? '' : 'd-none' }}">
  <label class="form-label">Permissions de consultation</label>
  <div class="border rounded p-3">
    <div class="row g-2">
      @foreach ($modules as $key => $label)
        <div class="col-md-6">
          <div class="form-check">
            <input
              class="form-check-input"
              type="checkbox"
              name="permissions[]"
              value="{{ $key }}"
              id="{{ ($roleSelectId ?? 'role') }}_perm_{{ $key }}"
              @checked(in_array($key, $selectedPermissions, true)) />
            <label class="form-check-label" for="{{ ($roleSelectId ?? 'role') }}_perm_{{ $key }}">
              {{ $label }}
            </label>
          </div>
        </div>
      @endforeach
    </div>
  </div>
  @error('permissions')<div class="text-danger mt-1">{{ $message }}</div>@enderror
  @error('permissions.*')<div class="text-danger mt-1">{{ $message }}</div>@enderror
</div>
