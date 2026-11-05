<div class="public-access">
    @if (\Illuminate\Support\Facades\Route::has('login'))
        <a class="btn btn-outline btn-sm" href="{{ route('login') }}"><x-icon name="user" :size="18" /> Iniciar sesión</a>
    @else
        <button class="btn btn-outline btn-sm login-pending" type="button" disabled aria-describedby="acceso-publico-estado"><x-icon name="user" :size="18" /> Iniciar sesión</button>
        <span class="access-status" id="acceso-publico-estado">Próximamente</span>
    @endif
</div>
