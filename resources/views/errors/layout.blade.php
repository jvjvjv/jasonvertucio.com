<!DOCTYPE html>
<html lang="en" style="height:100%">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>@yield('status', 'Error') | Jason Vertucio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Josefin+Sans:wght@400;700&family=Montserrat:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-primary: #1b587c;
            --color-secondary: #b35e06;
            --color-dark: #25292c;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body { height: 100%; }

        body {
            background: #fff;
            font-family: "Montserrat", Arial, sans-serif;
            color: var(--color-dark);
            display: flex;
            flex-direction: column;
            padding: 1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        h1 {
            font-family: "Josefin Sans", Arial, sans-serif;
            color: var(--color-secondary);
            font-size: 2.25rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-align: center;
            text-transform: uppercase;
        }

        .underline {
            margin-top: 0.5rem;
            height: 2px;
            background-color: var(--color-secondary);
        }

        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .error-body {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 1.5rem;
        }

        h2 {
            font-family: "Josefin Sans", Arial, sans-serif;
            color: var(--color-primary);
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
        }

        .divider {
            width: 2px;
            height: 3rem;
            background-color: var(--color-primary);
            flex-shrink: 0;
        }

        footer {
            text-align: center;
            font-size: 0.875rem;
        }

        footer a {
            color: var(--color-link, var(--color-primary));
            text-decoration: none;
        }

        footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <header>
        <h1>Jason Vertucio</h1>
        <div class="underline"></div>
    </header>

    <main>
        <div class="error-body">
            <h2>@yield('status')</h2>
            <div class="divider"></div>
            <p>@yield('message')</p>
        </div>
    </main>

    <footer>
        <a href="/" onclick="event.preventDefault(); history.back()">Go back</a>
    </footer>
</body>

</html>
