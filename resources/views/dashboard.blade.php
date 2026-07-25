@extends('layout.main')
@section('content')

          <div class="content-wrapper">
            <div class="container-xxl flex-grow-1 container-p-y">
              <div class="card">
                <div class="card-body">
                  <h4 class="mb-1">Tableau de bord</h4>
                  <p class="mb-0 text-muted">Bienvenue{{ auth()->user() ? ', ' . auth()->user()->name : '' }}.</p>
                </div>
              </div>
            </div>
          </div>

@endsection
