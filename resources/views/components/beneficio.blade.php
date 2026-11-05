@props(['icono' => 'book', 'titulo'])
<article class="feature-card">
    <span class="icon-bubble"><x-icon :name="$icono" :size="23" /></span>
    <h3>{{ $titulo }}</h3>
    <p>{{ $slot }}</p>
</article>
