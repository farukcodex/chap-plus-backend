<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — API</title>
    <meta name="description" content="{{ config('app.name') }} API backend service.">

    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <style>
        /* ── Light theme (default) ── */
        :root {
            --bg:          #f5f5f5;
            --surface:     #ffffff;
            --on-surface:  #212121;
            --on-muted:    #757575;
            --on-faint:    #9e9e9e;
            --divider:     #e0e0e0;
            --shadow:      rgba(0, 0, 0, 0.12);
            --chip-bg:     #e8f5e9;
            --chip-text:   #2e7d32;
            --chip-dot:    #43a047;
        }

        /* ── Dark theme (auto via OS preference) ── */
        @media (prefers-color-scheme: dark) {
            :root {
                --bg:         #121212;
                --surface:    #1e1e1e;
                --on-surface: #e0e0e0;
                --on-muted:   #9e9e9e;
                --on-faint:   #616161;
                --divider:    #2c2c2c;
                --shadow:     rgba(0, 0, 0, 0.5);
                --chip-bg:    #1b3a1c;
                --chip-text:  #81c784;
                --chip-dot:   #66bb6a;
            }
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--bg);
            color: var(--on-surface);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
            transition: background-color .3s, color .3s;
        }

        .card {
            background: var(--surface);
            border-radius: 8px;
            box-shadow: 0 2px 4px var(--shadow), 0 4px 8px var(--shadow);
            padding: 40px 48px;
            max-width: 440px;
            width: 100%;
            text-align: center;
            transition: background-color .3s, box-shadow .3s;
        }

        /* Status chip */
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--chip-bg);
            color: var(--chip-text);
            border-radius: 16px;
            padding: 4px 14px;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: .5px;
            text-transform: uppercase;
            margin-bottom: 24px;
            transition: background-color .3s, color .3s;
        }
        .chip .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--chip-dot);
            animation: blink 2s ease-in-out infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50%       { opacity: .3; }
        }

        .app-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--on-surface);
            letter-spacing: .1px;
            margin-bottom: 20px;
        }

        h1 {
            font-size: 22px;
            font-weight: 500;
            color: var(--on-surface);
            margin-bottom: 8px;
            letter-spacing: .15px;
        }

        .subtitle {
            font-size: 14px;
            color: var(--on-muted);
            line-height: 1.65;
            margin-bottom: 28px;
        }

        /* Divider */
        .divider {
            border: none;
            border-top: 1px solid var(--divider);
            margin-bottom: 20px;
            transition: border-color .3s;
        }

        /* Footer label */
        .app-label {
            font-size: 12px;
            color: var(--on-faint);
            letter-spacing: .4px;
        }
        .app-label strong {
            color: var(--on-muted);
            font-weight: 500;
        }
    </style>
</head>
<body>

    <div class="card">

        <p class="app-name">{{ config('app.name') }}</p>

        <div class="chip">
            <span class="dot"></span>
            Operational
        </div>

        <h1>API is Working</h1>

        <p class="subtitle">
            The {{ config('app.name') }} backend API is running successfully
            and ready to accept requests.
        </p>

        <hr class="divider">

        <p class="app-label">&copy; {{ date('Y') }} <strong>{{ config('app.name') }}</strong></p>

    </div>

</body>
</html>
