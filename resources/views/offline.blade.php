<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offline — OnHost</title>
    <meta name="theme-color" content="#7366ff">
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f6f7fb; color: #2b2f3a; padding: 24px; text-align: center;
        }
        .card {
            max-width: 460px; width: 100%; background: #fff; border-radius: 16px; padding: 40px 28px;
            box-shadow: 0 12px 32px rgba(16, 24, 40, .08);
        }
        .icon { width: 64px; height: 64px; margin: 0 auto 20px; color: #7366ff; }
        h1 { font-size: 20px; margin: 0 0 10px; }
        p { margin: 0 0 22px; line-height: 1.6; color: #5b6172; font-size: 14px; }
        button {
            background: #7366ff; color: #fff; border: 0; border-radius: 10px;
            padding: 12px 22px; font-size: 14px; font-weight: 600; cursor: pointer;
        }
        button:hover { filter: brightness(1.05); }
        .hint { margin-top: 18px; font-size: 12px; color: #8b90a0; }
        @media (prefers-color-scheme: dark) {
            body { background: #1d1e26; color: #e7e9ee; }
            .card { background: #262832; box-shadow: none; }
            p { color: #a9adbb; }
            .hint { color: #7f8494; }
        }
    </style>
</head>
<body>
    <div class="card">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M1 1l22 22"/>
            <path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/>
            <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/>
            <path d="M10.71 5.05A16 16 0 0 1 22.58 9"/>
            <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/>
            <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
            <line x1="12" y1="20" x2="12.01" y2="20"/>
        </svg>

        <h1>Jste offline</h1>
        <p>
            Tuto stránku se nepodařilo načíst, protože zařízení nemá připojení.
            Naposledy navštívené stránky panelu zůstávají dostupné i offline.
        </p>
        <button type="button" onclick="location.reload()">Zkusit znovu</button>
        <div class="hint">Jakmile se připojení obnoví, stránka se načte normálně.</div>
    </div>

    <script>
        // Reload automatically the moment connectivity returns.
        window.addEventListener('online', function () { location.reload(); });
    </script>
</body>
</html>
