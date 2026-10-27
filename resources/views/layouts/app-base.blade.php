<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1E352F">
    <meta name="description" content="Plataforma de gestión de cursos y aprendizaje virtual.">
    <title>@yield('titulo', 'Plataforma de cursos') · @yield('marca', 'Aula Verde')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Noto+Serif:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/tesis-ui.css') }}">
    @stack('estilos')
</head>
<body>
    @yield('estructura')
    @stack('scripts')
</body>
</html>
