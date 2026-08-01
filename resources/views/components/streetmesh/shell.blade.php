{{--
    The chrome, which belongs to the application rather than to any capability.

    A server may offer more than one, and a capability that drew the frame
    around itself would be deciding what the others look like. So packages
    supply pieces and this arranges them.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? $identity->handle }}</title>
</head>
<body>
    <header>
        <a href="{{ url('/') }}">{{ $identity->handle }}</a>
        <code>{{ $identity->did }}</code>
    </header>

    @if (($navigation ?? []) !== [])
        <nav>
            <ul>
                @foreach ($navigation as $item)
                    <li><a href="{{ route($item['route']) }}">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </nav>
    @endif

    <main>{{ $slot }}</main>
</body>
</html>
