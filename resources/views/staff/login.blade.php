{{-- Placeholder: la vista real (wireframe StaffLogin.dc.html) queda pendiente para frontend. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Acceso staff — {{ config('app.name') }}</title>
</head>
<body>
    <form method="POST" action="{{ route('staff.login.store') }}">
        @csrf

        @error('email')
            <p role="alert">{{ $message }}</p>
        @enderror
        @error('password')
            <p role="alert">{{ $message }}</p>
        @enderror

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">

        <label for="password">Contraseña</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">

        <button type="submit">Ingresar</button>
    </form>
</body>
</html>
