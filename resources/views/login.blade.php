<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>GestStock — Connexion</title>
  <link rel="icon" type="image/x-icon" href="{{ asset('img/favicon/favicon.ico') }}" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <style>
    :root {
      --ink: #14110f;
      --ink-soft: #2c2420;
      --sand: #e8dfd2;
      --champagne: #c4a574;
      --champagne-deep: #9a7b4f;
      --mist: rgba(232, 223, 210, 0.08);
      --line: rgba(232, 223, 210, 0.18);
      --danger: #c45c4a;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      min-height: 100vh;
      font-family: "Outfit", sans-serif;
      color: var(--sand);
      background: var(--ink);
      overflow-x: hidden;
    }

    .shell {
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1.15fr 1fr;
    }

    .hero {
      position: relative;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      padding: clamp(2rem, 5vw, 4.5rem);
      background:
        radial-gradient(ellipse 80% 60% at 20% 20%, rgba(196, 165, 116, 0.18), transparent 55%),
        radial-gradient(ellipse 70% 50% at 80% 80%, rgba(196, 165, 116, 0.08), transparent 50%),
        linear-gradient(165deg, #1c1714 0%, #0d0b0a 55%, #1a1512 100%);
      overflow: hidden;
    }

    .hero::before {
      content: "";
      position: absolute;
      inset: 0;
      background-image:
        linear-gradient(rgba(232, 223, 210, 0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(232, 223, 210, 0.04) 1px, transparent 1px);
      background-size: 48px 48px;
      mask-image: radial-gradient(ellipse at 30% 40%, black 20%, transparent 70%);
      animation: gridFade 8s ease-in-out infinite alternate;
    }

    .hero::after {
      content: "";
      position: absolute;
      width: 420px;
      height: 420px;
      right: -80px;
      top: 12%;
      border-radius: 50%;
      border: 1px solid var(--line);
      box-shadow: inset 0 0 0 40px rgba(196, 165, 116, 0.03);
      animation: orbFloat 10s ease-in-out infinite;
    }

    .hero-content {
      position: relative;
      z-index: 1;
      max-width: 32rem;
      animation: riseIn 0.9s ease both;
    }

    .brand {
      font-family: "Cormorant Garamond", serif;
      font-size: clamp(3.8rem, 8vw, 6.5rem);
      font-weight: 600;
      line-height: 0.92;
      letter-spacing: 0.04em;
      margin: 0 0 1.25rem;
      color: #f4ebe0;
    }

    .brand span {
      display: block;
      color: var(--champagne);
    }

    .tagline {
      margin: 0;
      font-size: 1.05rem;
      font-weight: 300;
      line-height: 1.6;
      color: rgba(232, 223, 210, 0.72);
      max-width: 26rem;
    }

    .accent-line {
      width: 72px;
      height: 2px;
      margin: 1.75rem 0 1.5rem;
      background: linear-gradient(90deg, var(--champagne), transparent);
      animation: lineGrow 1.1s ease 0.25s both;
    }

    .panel {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: clamp(1.5rem, 4vw, 3rem);
      background:
        linear-gradient(180deg, rgba(255, 248, 240, 0.98), #f3ebe1 100%);
      color: var(--ink);
      position: relative;
    }

    .panel::before {
      content: "";
      position: absolute;
      inset: 0;
      background:
        radial-gradient(circle at 90% 10%, rgba(196, 165, 116, 0.2), transparent 35%),
        radial-gradient(circle at 10% 90%, rgba(44, 36, 32, 0.05), transparent 40%);
      pointer-events: none;
    }

    .form-wrap {
      width: 100%;
      max-width: 380px;
      position: relative;
      z-index: 1;
      animation: riseIn 0.8s ease 0.15s both;
    }

    .form-kicker {
      font-size: 0.75rem;
      letter-spacing: 0.22em;
      text-transform: uppercase;
      color: var(--champagne-deep);
      margin: 0 0 0.75rem;
      font-weight: 500;
    }

    .form-title {
      font-family: "Cormorant Garamond", serif;
      font-size: 2.4rem;
      font-weight: 600;
      margin: 0 0 0.4rem;
      color: var(--ink);
      line-height: 1.1;
    }

    .form-sub {
      margin: 0 0 2rem;
      color: rgba(20, 17, 15, 0.58);
      font-size: 0.95rem;
      font-weight: 300;
    }

    .field {
      margin-bottom: 1.15rem;
    }

    .field label {
      display: block;
      margin-bottom: 0.45rem;
      font-size: 0.82rem;
      font-weight: 500;
      color: var(--ink-soft);
    }

    .field input[type="text"],
    .field input[type="password"] {
      width: 100%;
      border: 1px solid rgba(20, 17, 15, 0.14);
      background: rgba(255, 255, 255, 0.75);
      border-radius: 12px;
      padding: 0.9rem 1rem;
      font: inherit;
      font-size: 0.95rem;
      color: var(--ink);
      outline: none;
      transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }

    .field input:focus {
      border-color: var(--champagne-deep);
      background: #fff;
      box-shadow: 0 0 0 4px rgba(196, 165, 116, 0.18);
    }

    .password-row {
      position: relative;
    }

    .password-row input {
      padding-right: 3rem;
    }

    .toggle-pass {
      position: absolute;
      right: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      border: 0;
      background: transparent;
      color: rgba(20, 17, 15, 0.45);
      cursor: pointer;
      padding: 0.25rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .toggle-pass svg {
      width: 1.15rem;
      height: 1.15rem;
    }

    .error {
      margin-top: 0.4rem;
      color: var(--danger);
      font-size: 0.82rem;
    }

    .remember {
      display: flex;
      align-items: center;
      gap: 0.55rem;
      margin: 0.25rem 0 1.5rem;
      font-size: 0.9rem;
      color: rgba(20, 17, 15, 0.7);
    }

    .remember input {
      width: 1rem;
      height: 1rem;
      accent-color: var(--champagne-deep);
    }

    .submit {
      width: 100%;
      border: 0;
      border-radius: 12px;
      padding: 0.95rem 1.2rem;
      background: linear-gradient(135deg, #2c2420 0%, #14110f 100%);
      color: #f4ebe0;
      font: inherit;
      font-size: 0.95rem;
      font-weight: 500;
      letter-spacing: 0.02em;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .submit:hover {
      transform: translateY(-1px);
      box-shadow: 0 12px 28px rgba(20, 17, 15, 0.22);
    }

    .submit:active {
      transform: translateY(0);
    }

    .foot {
      margin-top: 1.75rem;
      text-align: center;
      font-size: 0.8rem;
      color: rgba(20, 17, 15, 0.42);
    }

    @keyframes riseIn {
      from { opacity: 0; transform: translateY(18px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes lineGrow {
      from { width: 0; opacity: 0; }
      to { width: 72px; opacity: 1; }
    }

    @keyframes orbFloat {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-18px); }
    }

    @keyframes gridFade {
      from { opacity: 0.35; }
      to { opacity: 0.8; }
    }

    @media (max-width: 900px) {
      .shell {
        grid-template-columns: 1fr;
      }

      .hero {
        min-height: 38vh;
        justify-content: center;
        padding: 2.5rem 1.5rem 2rem;
      }

      .brand {
        font-size: clamp(3rem, 12vw, 4rem);
      }

      .hero::after {
        width: 240px;
        height: 240px;
        top: auto;
        bottom: -60px;
        right: -40px;
      }

      .panel {
        min-height: 62vh;
        align-items: flex-start;
        padding-top: 2rem;
      }
    }
  </style>
</head>
<body>
  <div class="shell">
    <section class="hero" aria-label="Présentation GestStock">
      <div class="hero-content">
        <h1 class="brand">Gest<span>Stock</span></h1>
        <div class="accent-line" aria-hidden="true"></div>
        <p class="tagline">
          Gérez vos parfums, flacons et stocks avec précision — du millilitre à la vente.
        </p>
      </div>
    </section>

    <section class="panel">
      <div class="form-wrap">
        <p class="form-kicker">Espace sécurisé</p>
        <h2 class="form-title">Connexion</h2>
        <p class="form-sub">Accédez à votre boutique de parfums.</p>

        <form method="POST" action="{{ route('login.attempt') }}">
          @csrf

          <div class="field">
            <label for="login">Login</label>
            <input
              type="text"
              id="login"
              name="login"
              placeholder="Votre identifiant"
              autofocus
              value="{{ old('login') }}"
              required />
            @error('login')
              <div class="error">{{ $message }}</div>
            @enderror
          </div>

          <div class="field">
            <label for="password">Mot de passe</label>
            <div class="password-row">
              <input
                type="password"
                id="password"
                name="password"
                placeholder="••••••••"
                required />
              <button type="button" class="toggle-pass" id="togglePassword" aria-label="Afficher le mot de passe">
                <svg id="iconShow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                  <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg id="iconHide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="display:none">
                  <path d="M3 3l18 18M10.6 10.6A3 3 0 0012 15a3 3 0 002.4-1.2M6.7 6.7C4.3 8.2 2.8 10.4 2 12c0 0 3.5 7 10 7 1.8 0 3.4-.4 4.7-1M17.3 17.3C19.5 15.8 21 13.7 22 12c0 0-3.5-7-10-7-1.1 0-2.1.1-3 .4"/>
                </svg>
              </button>
            </div>
            @error('password')
              <div class="error">{{ $message }}</div>
            @enderror
          </div>

          <label class="remember">
            <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }} />
            Rester connecté
          </label>

          <button class="submit" type="submit">Se connecter</button>
        </form>

        <p class="foot">GestStock Parfums</p>
      </div>
    </section>
  </div>

  <script>
    document.getElementById('togglePassword')?.addEventListener('click', function () {
      var input = document.getElementById('password');
      var iconShow = document.getElementById('iconShow');
      var iconHide = document.getElementById('iconHide');
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      iconShow.style.display = show ? 'none' : 'block';
      iconHide.style.display = show ? 'block' : 'none';
      this.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
    });
  </script>
</body>
</html>
