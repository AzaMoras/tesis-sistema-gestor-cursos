<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex">
    <title>@yield('titulo') · Aula Verde</title>
    <script src="{{ asset('js/tema-publico.js') }}"></script>
    <style>
        :root {
            --fondo: #F7FAF6;
            --superficie: #FFFFFF;
            --texto: #1E352F;
            --borde: #828C51;
            --accion: #335145;
            --texto-accion: #FFFFFF;
            --acento: #BEEF9E;
            --foco: #335145;
            color-scheme: light;
        }

        @media (prefers-color-scheme: dark) {
            :root:not([data-theme]) { color-scheme: dark; }
        }

        :root[data-theme="dark"] { color-scheme: dark; }

        body {
            margin: 0;
            min-block-size: 100vh;
            background: var(--fondo);
            background: light-dark(var(--fondo), var(--texto));
            color: var(--texto);
            color: light-dark(var(--texto), var(--fondo));
            font: 1rem/1.6 Manrope, system-ui, sans-serif;
        }

        .contenedor {
            box-sizing: border-box;
            max-inline-size: 56rem;
            margin-inline: auto;
            padding-inline: 1rem;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .75rem;
            padding-block: 1rem;
        }

        .marca {
            display: inline-flex;
            align-items: center;
            gap: .75rem;
            color: inherit;
            font-weight: 700;
            text-decoration: none;
        }

        .marca img {
            box-sizing: border-box;
            background: var(--superficie);
            border-radius: .625rem;
            padding: .25rem;
        }

        main { padding-block: clamp(2rem, 10vh, 6rem); }

        .panel {
            max-inline-size: 38rem;
            padding: clamp(1.25rem, 5vw, 3rem);
            border: 1px solid var(--borde);
            border-radius: 1rem;
            background: var(--superficie);
            background: light-dark(var(--superficie), #263F36);
        }

        .codigo {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .25rem .75rem;
            border-radius: .5rem;
            background: var(--acento);
            color: var(--texto);
            font-weight: 700;
        }

        h1 { margin: .75rem 0; font-size: clamp(1.75rem, 6vw, 2.75rem); line-height: 1.2; }
        p { margin: 0 0 1.25rem; }

        .boton {
            box-sizing: border-box;
            min-block-size: 2.75rem;
            display: inline-flex;
            align-items: center;
            padding: .5rem 1rem;
            border: 1px solid currentColor;
            border-radius: .625rem;
            background: transparent;
            color: inherit;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .boton[hidden] { display: none; }

        .principal {
            background: var(--accion);
            background: light-dark(var(--accion), #A6C36F);
            color: var(--texto-accion);
            color: light-dark(var(--texto-accion), var(--texto));
        }

        a:hover, button:hover { text-decoration: underline; }

        :focus-visible {
            outline: 3px solid var(--foco);
            outline: 3px solid light-dark(var(--foco), var(--acento));
            outline-offset: 4px;
        }
    </style>
</head>
<body>
    <header class="contenedor">
        <a class="marca" href="{{ route('inicio') }}">
            <img src="{{ asset('images/logoAulaVerde.svg') }}" width="44" height="44" alt="">
            <span>Aula Verde</span>
        </a>
        <button class="boton" type="button" data-theme-toggle hidden>
            <span data-theme-label>Cambiar tema</span>
        </button>
    </header>

    <main class="contenedor">
        <div class="panel">
            <span class="codigo">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 7v6m0 3v1" />
                </svg>
                Error @yield('codigo')
            </span>
            <h1>@yield('titulo')</h1>
            <p>@yield('mensaje')</p>
            <a class="boton principal" href="{{ route('inicio') }}">Volver al inicio</a>
        </div>
    </main>
</body>
</html>
