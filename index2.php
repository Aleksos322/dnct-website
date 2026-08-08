<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Wylogowanie: /index.php?logout=1
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: /login');
    exit();
}

$isLoggedIn = isset($_SESSION['authentik_username']);
$username = $_SESSION['authentik_username'] ?? null;
$email = $_SESSION['authentik_email'] ?? null;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<title>DNCT Hosting</title>
<style>
/* Nagłówki i paragrafy */
h1, p {
    color: #f8f9fa; /* delikatna, lekko złamana biel, przyjemniejsza dla oczu niż czyste #fff */
}

/* Stylowanie linków */
a {
    color: #ffffff;
    text-decoration: underline; /* Zmień na 'none', jeśli chcesz usunąć domyślne podkreślenie */
    transition: color 0.2s ease;
}

/* Efekt po najechaniu na link */
a:hover {
    color: #cbd5e1; /* subtelnie przygasa po najechaniu */
}
    .btn-panel {
        display: inline-block;
        padding: 10px 20px;
        background-color: #0d6efd;
        color: #ffffff;
        text-decoration: none;
        border-radius: 6px;
        font-weight: bold;
        margin: 10px 0;
        transition: background-color 0.2s ease;
    }
    .btn-panel:hover {
        background-color: #0b5ed7;
    }
</style>
</head>
<body>
<h1>DNCT Hosting</h1>
  <div class="header finisher-header" style="width: 100%; height: 100vh;">
    <?php if ($isLoggedIn): ?>
    <p>Zalogowano jako: <strong><?php echo htmlspecialchars($username); ?></strong>
        <?php if ($email): ?> (<?php echo htmlspecialchars($email); ?>)<?php endif; ?>
    </p>

    <!-- Przycisk przejścia do Pterodaktyla przez SSO -->
    <p>
<a href="https://panel.dnct.pl/extensions/vikingsso/auth/auto" class="btn-panel">
    Przejdź do panelu
</a>
    </p>

    <p><a href="/wyloguj">Wyloguj się</a></p>
<?php else: ?>
    <p>Nie jesteś zalogowany.</p>
    <p>
        <a href="/login">Zaloguj się</a> |
        <a href="/register">Zarejestruj się</a>
    </p>
<?php endif; ?>
  </div>
  <script src="finisher-header.es5.min.js" type="text/javascript"></script>


<script type="text/javascript">
new FinisherHeader({
  "count": 6,
  "size": {
    "min": 797,
    "max": 1492,
    "pulse": 0
  },
  "speed": {
    "x": {
      "min": 0.1,
      "max": 0.4
    },
    "y": {
      "min": 0.1,
      "max": 0.3
    }
  },
  "colors": {
    "background": "#050505",
    "particles": [
      "#4f7cfa",
      "#4f7cfa",
      "#885ef6",
      "#984ede",
      "#984ede",
      "#676ef6"
    ]
  },
  "blending": "none",
  "opacity": {
    "center": 0.15,
    "edge": 0
  },
  "skew": 0,
  "shapes": [
    "c"
  ]
});
</script>
</body>
</html>