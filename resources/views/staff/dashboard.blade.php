{{-- Placeholder: el panel de staff se implementa en otra historia. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Panel staff — {{ config('app.name') }}</title>
</head>
<body>
    <p>Sesión iniciada como {{ auth('staff')->user()->nombre }} ({{ auth('staff')->user()->rol->value }}).</p>

    <form method="POST" action="{{ route('staff.logout') }}">
        @csrf
        <button type="submit">Cerrar sesión</button>
    </form>
</body>
</html>
