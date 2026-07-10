<!DOCTYPE html>
<html lang="id" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'AKTA IAT')</title>

    <script>
        (function () {
            try {
                var theme = localStorage.getItem('akta_theme') || 'dark';
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/akta-shell.js'])
</head>

<body class="min-h-full bg-slate-950 text-slate-100 antialiased">
    <div class="min-h-screen">
        @include('akta.partials.sidebar')

        <div class="min-h-screen lg:pl-72">
            @include('akta.partials.topbar')

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    @stack('scripts')
</body>

</html>