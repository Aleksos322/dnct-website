<?php
/**
 * errors/500.php
 * Podepnij w .htaccess: ErrorDocument 500 /errors/500.php
 *
 * CELOWO nie używa clear.php / navbar.php - jeśli przyczyną 500 jest awaria
 * bazy danych czy backendu, layout (który zależy od sesji/navbar/powiadomień)
 * mógłby wywalić kolejny błąd przy próbie renderowania strony błędu.
 * Ta strona jest w pełni samodzielna, żeby zawsze się wyświetliła.
 */
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coś poszło nie tak (500) - DNCT Hosting</title>
    <meta name="robots" content="noindex">
    <link rel="icon" type="image/png" href="/assets/media/icons/favicon.png">
    <style>
        body {
            background-color: #050505;
            color: #f8f9fa;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Inter, sans-serif;
            text-align: center;
            padding: 24px;
        }
        .code {
            font-size: 5rem;
            font-weight: 900;
            background: linear-gradient(135deg, #8b5cf6, #3b82f6);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin: 0 0 8px;
        }
        h1 { font-size: 1.75rem; margin: 0 0 12px; }
        p { color: #9ca3af; max-width: 420px; margin: 0 auto 32px; line-height: 1.5; }
        .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        a.btn {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: opacity .2s ease;
        }
        a.btn:hover { opacity: 0.85; }
        .btn-primary { background: linear-gradient(90deg, #8b5cf6, #4f7cfa); color: #fff; }
        .btn-secondary { background: rgba(255,255,255,0.06); color: #fff; border: 1px solid rgba(255,255,255,0.1); }
    </style>
</head>
<body>
    <div>
        <p class="code">500</p>
        <h1>Coś poszło nie tak po naszej stronie</h1>
        <p>Wystąpił nieoczekiwany błąd serwera. Już o tym wiemy albo właśnie sprawdzamy - spróbuj ponownie za chwilę.</p>
        <div class="actions">
            <a href="/" class="btn btn-primary">Strona główna</a>
            <a href="https://discord.gg/4kcXP5WmQ8" target="_blank" class="btn btn-secondary">Zgłoś na Discordzie</a>
        </div>
    </div>
</body>
</html>