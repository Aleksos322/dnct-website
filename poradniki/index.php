<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/backend/bootstrap.php';

$pageTitle       = 'Poradniki Project Zomboid: serwer, mody, ustawienia';
$metaDescription = 'Poradniki o serwerach Project Zomboid: jak postawić serwer, jak dodać mody ze Steam Workshop i jak ustawić hasło, PvP i trudność świata.';
$canonicalUrl    = 'https://game.dnct.pl/poradniki/';
$centerContent   = false;

$lista = [
    ['jak-postawic-serwer-project-zomboid', 'Jak postawić serwer Project Zomboid (Build 42)', 'Ile RAM-u potrzeba, pierwsze uruchomienie, łączenie przez IP i port, komendy admina.'],
    ['mody-na-serwer-project-zomboid', 'Jak dodać mody ze Steam Workshop na serwer Zomboid', 'Workshop ID i Mod ID, kolejność bibliotek, co zrobić, gdy serwer nie startuje.'],
    ['ustawienia-serwera-project-zomboid', 'Ustawienia serwera Zomboid: hasło, PvP, gracze, kopie', 'ProjectZomboid.ini i SandboxVars - najważniejsze opcje z wyjaśnieniem.'],
];

ob_start(); ?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/legal.css'); ?>">
<article class="legal glass glass-content" style="border-radius:22px;padding:clamp(1.25rem,4vw,3rem);max-width:900px;margin:0 auto;">
    <h1>Poradniki</h1>
    <p>Praktyczne instrukcje sprawdzone na naszych serwerach. Na razie Project Zomboid - kolejne gry wkrótce.</p>
    <?php foreach ($lista as [$slug, $tytul, $opis]): ?>
        <h2><a href="/poradniki/<?php echo $slug; ?>" style="color:#f1f5f9;text-decoration:none;"><?php echo htmlspecialchars($tytul); ?></a></h2>
        <p><?php echo htmlspecialchars($opis); ?> <a href="/poradniki/<?php echo $slug; ?>">Czytaj</a></p>
    <?php endforeach; ?>
    <p style="margin-top:2rem;">Szukasz serwera? <a href="/hosting-project-zomboid">Hosting Project Zomboid od 29,99 zł/mies.</a></p>
</article>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/assets/clear/clear.php';
