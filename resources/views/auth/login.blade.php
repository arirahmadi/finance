<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Finance System</title>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card glass-panel">
            <div class="auth-header">
                <h1>Finance System</h1>
                <p>Silakan masuk ke akun keuangan perusahaan Anda</p>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form action="{{ url('/login') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="email" class="form-label">Alamat Email</label>
                    <input 
                        type="email" 
                        name="email" 
                        id="email" 
                        class="form-input" 
                        placeholder="nama@perusahaan.com" 
                        value="{{ old('email') }}" 
                        required 
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Kata Sandi</label>
                    <input 
                        type="password" 
                        name="password" 
                        id="password" 
                        class="form-input" 
                        placeholder="••••••••" 
                        required
                    >
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 8px; margin-bottom: 24px;">
                    <input 
                        type="checkbox" 
                        name="remember" 
                        id="remember" 
                        style="accent-color: var(--color-primary); cursor: pointer;"
                    >
                    <label for="remember" class="form-label" style="margin-bottom: 0; cursor: pointer; user-select: none;">
                        Ingat Saya
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-full">
                    Masuk Ke Dashboard
                </button>
            </form>
        </div>
    </div>
</body>
</html>
