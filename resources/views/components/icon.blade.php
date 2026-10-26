@props(['name' => 'book', 'size' => 20])
<svg xmlns="http://www.w3.org/2000/svg" width="{{ (int) $size }}" height="{{ (int) $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('book') <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/> @break
        @case('arrow-right') <path d="M5 12h14m-6-6 6 6-6 6"/> @break
        @case('home') <path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M9 21v-7h6v7"/> @break
        @case('user') <circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/> @break
        @case('users') <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/> @break
        @case('grid') <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/> @break
        @case('layers') <path d="m12 2 9 5-9 5-9-5 9-5Zm-9 10 9 5 9-5M3 17l9 5 9-5"/> @break
        @case('calendar') <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/> @break
        @case('award') <circle cx="12" cy="8" r="6"/><path d="m8.2 13.4-1 8 4.8-2.5 4.8 2.5-1-8"/> @break
        @case('shield') <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/> @break
        @case('check') <path d="m5 12 4 4L19 6"/> @break
        @case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l4 2"/> @break
        @case('play') <circle cx="12" cy="12" r="10"/><path d="m10 8 6 4-6 4V8Z"/> @break
        @case('graduation') <path d="m2 10 10-5 10 5-10 5-10-5Z"/><path d="M6 12v5c4 3 8 3 12 0v-5M22 10v7"/> @break
        @case('menu') <path d="M4 6h16M4 12h16M4 18h16"/> @break
        @case('search') <circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/> @break
        @case('file') <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/> @break
        @case('credit-card') <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/> @break
        @default <circle cx="12" cy="12" r="9"/> @break
    @endswitch
</svg>
