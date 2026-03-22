<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Admin | Jason Vertucio</title>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://db.onlinewebfonts.com">
    <link rel="preconnect" href="https://fonts.cdnfonts.com">
    <link href="https://fonts.googleapis.com/css?family=Saira+Extra+Condensed:500,700" rel="stylesheet">
    <link href="https://db.onlinewebfonts.com/c/29dc27977e417a98e56556776f41607c?family=Corbel" rel="stylesheet">
    <link href="https://fonts.cdnfonts.com/css/convection" rel="stylesheet">

    @inertiaHead
    @viteReactRefresh
    @vite(['resources/js/admin/app.tsx'])
</head>

<body class="h-full">
    @inertia
</body>

</html>
