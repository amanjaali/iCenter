@props(['name' => 'dot'])

@php
    /**
     * A small inline icon set. Kept in the codebase rather than pulled from a
     * CDN: the application must work on a network that cannot reach one, and
     * the stroke weight is matched to the brand's line work.
     */
    $paths = [
        'grid' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z',
        'building' => 'M4 20V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v15M15 20V9h4a1 1 0 0 1 1 1v10M3 20h18M7.5 8h3M7.5 12h3M7.5 16h3',
        'bus' => 'M4 16V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10M4 16h16M4 16v2h2v-2M18 16v2h2v-2M4 10h16M8 13h.01M16 13h.01',
        'users' => 'M16 19v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 17.5V19M10 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7M20 19v-1.5a3.5 3.5 0 0 0-2.6-3.4M15.5 4.2a3.5 3.5 0 0 1 0 6.6',
        'file' => 'M14 3H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7zM14 3v4h4M9 12h6M9 16h6',
        'wallet' => 'M3 7a2 2 0 0 1 2-2h12v3M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3M3 7h16a2 2 0 0 1 2 2v2h-5a2 2 0 0 0 0 4h5',
        'tag' => 'M20.5 12.5 12 21l-8.5-8.5V4a.5.5 0 0 1 .5-.5h8.5zM7.5 7.5h.01',
        'calendar' => 'M4 6a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1zM4 10h16M8 3v4M16 3v4',
        'receipt' => 'M5 21V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v17l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6',
        'truck' => 'M3 7a1 1 0 0 1 1-1h10v10H4a1 1 0 0 1-1-1zM14 10h4l3 3v3h-7zM7.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3M17.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3',
        'badge' => 'M6 4h12a1 1 0 0 1 1 1v15l-7-3-7 3V5a1 1 0 0 1 1-1M12 11a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5',
        'user-square' => 'M4 5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1zM12 12a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5M7.5 17a4.5 4.5 0 0 1 9 0',
        'box' => 'M12 3 4 7v10l8 4 8-4V7zM4 7l8 4 8-4M12 11v10',
        'book' => 'M5 4a1 1 0 0 1 1-1h13v18H6a1 1 0 0 1-1-1zM5 17h14M9 7h6',
        'list' => 'M4 6h2M4 12h2M4 18h2M9 6h11M9 12h11M9 18h11',
        'lock' => 'M6 11a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1zM8.5 10V7.5a3.5 3.5 0 1 1 7 0V10',
        'split' => 'M12 4v6m0 0-4 4v6m4-10 4 4v6M9 7l3-3 3 3',
        'pie' => 'M12 3a9 9 0 1 0 9 9h-9z M14 3.3A9 9 0 0 1 20.7 10H14z',
        'handshake' => 'm11 17-2.5-2.5M7.5 8 4 11.5 8 15.5l1.5-1.5 2 2 2-2 1.5 1.5 4-4L16.5 8M7.5 8h3l1.5 1.5L13.5 8h3',
        'chart' => 'M4 20V4M4 20h16M8 17V11M12.5 17V7M17 17v-4',
        'shield' => 'M12 3 5 6v6c0 4 3 7 7 9 4-2 7-5 7-9V6zM9.5 12l2 2 3.5-4',
        'sliders' => 'M4 8h9M17 8h3M4 16h3M11 16h9M15 5.5v5M9 13.5v5',
        'history' => 'M4 12a8 8 0 1 0 2.5-5.8M4 5v4h4M12 8v4.5l3 1.5',
        'logout' => 'M9 20H6a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h3M15 8l4 4-4 4M19 12H9',
        'menu' => 'M4 7h16M4 12h16M4 17h16',
        'plus' => 'M12 5v14M5 12h14',
        'search' => 'M15.5 15.5 20.5 20.5M11 17A6 6 0 1 0 11 5a6 6 0 0 0 0 12',
        'filter' => 'M3.5 6.5h17M6.5 12h11M10 17.5h4',
        'download' => 'M12 4v11M8 11.5l4 4 4-4M4 19h16',
        'print' => 'M7 9V4h10v5M7 18H5a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1h-2M7 14h10v6H7z',
        'check' => 'M5 12.5 10 17.5 19 7',
        'x' => 'M6 6l12 12M18 6 6 18',
        'alert' => 'M12 8v5M12 16.5h.01M10.3 3.9 2.8 17a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0',
        'info' => 'M12 16v-5M12 8h.01M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18',
        'arrow-up' => 'M12 19V5M6 11l6-6 6 6',
        'arrow-down' => 'M12 5v14M6 13l6 6 6-6',
        'arrow-right' => 'M5 12h14M13 6l6 6-6 6',
        'arrow-left' => 'M19 12H5M11 6l-6 6 6 6',
        'chevron-right' => 'm9 5 7 7-7 7',
        'chevron-down' => 'm5 9 7 7 7-7',
        'edit' => 'M4 20h4L19 9a2.1 2.1 0 0 0-3-3L5 17v3zM14.5 7.5l2 2',
        'trash' => 'M5 7h14M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M6.5 7l.8 12a1 1 0 0 0 1 1h7.4a1 1 0 0 0 1-1l.8-12M10 11v5M14 11v5',
        'undo' => 'M4 9h10a5 5 0 0 1 0 10h-3M4 9l4-4M4 9l4 4',
        'refresh' => 'M20 12a8 8 0 1 1-2.5-5.8M20 4v4h-4',
        'bell' => 'M6.5 17h11l-1-2v-4a4.5 4.5 0 0 0-9 0v4zM10.5 17a1.5 1.5 0 0 0 3 0',
        'dot' => 'M12 12h.01',
        'eye' => 'M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6',
        'link' => 'M10 13a4 4 0 0 0 5.7.4l3-3A4 4 0 0 0 13 4.7l-1.7 1.7M14 11a4 4 0 0 0-5.7-.4l-3 3A4 4 0 0 0 11 19.3l1.7-1.7',
    ];

    $d = $paths[$name] ?? $paths['dot'];
@endphp

<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
     {{ $attributes->merge(['class' => 'size-4']) }}>
    <path d="{{ $d }}"/>
</svg>
