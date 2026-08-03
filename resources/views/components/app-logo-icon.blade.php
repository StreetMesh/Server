{{--
    The StreetMesh mark, in one colour.

    The mono geometry rather than the colour mark, because every caller styles
    this with `fill-current` and a text colour — a sidebar tile, an auth header.
    The colour mark cannot survive that: its green would be flattened to
    whatever the surrounding text colour is, and the white islands with it.

    The clip path needs an id and this component renders more than once on a
    page — twice in the split auth layout alone — so the id is generated rather
    than fixed. Two elements sharing an id is invalid, and the second reference
    would silently resolve to the first.
--}}
@php($clip = 'streetmesh-mark-'.Str::random(8))

<svg xmlns="http://www.w3.org/2000/svg" viewBox="4 4 92 92" fill="currentColor" role="img" {{ $attributes }}>
    <title>StreetMesh</title>

    <defs>
        <clipPath id="{{ $clip }}">
            <circle cx="50" cy="50" r="46" />
        </clipPath>
    </defs>

    {{-- The grid is on a 22° tilt that the circular crop conceals. --}}
    <g clip-path="url(#{{ $clip }})">
        <g transform="rotate(22 50 50)">
            <path d="M-7 -14 H31 V14.254 A8 8 0 0 0 25.254 20 H-7 Z" />
            <path d="M35 -14 H65 V20 H40.746 A8 8 0 0 0 35 14.254 Z" />
            <rect x="69" y="-14" width="50" height="66" rx="1.5" />
            <path d="M-7 24 H25.254 A8 8 0 0 0 31 29.746 V52 H-7 Z" />
            <path d="M40.746 24 H65 V70.254 A8 8 0 0 0 59.254 76 H35 V29.746 A8 8 0 0 0 40.746 24 Z" />
            <rect x="-7" y="56" width="38" height="60" rx="1.5" />
            <path d="M69 56 H119 V76 H74.746 A8 8 0 0 0 69 70.254 Z" />
            <path d="M35 80 H59.254 A8 8 0 0 0 65 85.746 V116 H35 Z" />
            <path d="M74.746 80 H119 V116 H69 V85.746 A8 8 0 0 0 74.746 80 Z" />

            {{--
                The roundabout islands. White against the green in the colour
                mark; here they take the same ink as the blocks and read as
                dots sitting in the street junctions.
            --}}
            <circle cx="33" cy="22" r="4" />
            <circle cx="67" cy="78" r="4" />
        </g>
    </g>
</svg>
