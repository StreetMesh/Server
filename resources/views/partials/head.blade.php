<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'StreetMesh') : config('app.name', 'StreetMesh') }}
</title>

{{--
    Two icons, because the mark carries its own near-black ground: right on a
    light tab strip and a dark smudge against a dark one. Both are drawn on the
    micro geometry, whose junctions are widened so the two white islands survive
    at 16px — below that they stop reading as islands at all.
--}}
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon-32.png" type="image/png" sizes="32x32" media="(prefers-color-scheme: light)">
<link rel="icon" href="/favicon-dark-32.png" type="image/png" sizes="32x32" media="(prefers-color-scheme: dark)">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<meta property="og:image" content="{{ url('/og-image-1200x630.png') }}">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
