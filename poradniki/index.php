<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/backend/bootstrap.php';

$pageTitle       = 'Poradniki: Minecraft, Project Zomboid, Vintage Story | DNCT';
$metaDescription = 'Poradniki o serwerach Minecraft i Project Zomboid: jak postawić serwer, dodać pluginy, mody i modpacki, ustawić hasło, PvP i trudność świata.';
$canonicalUrl    = 'https://game.dnct.pl/poradniki/';
$centerContent   = false;

// Lista w backend/data/poradniki.php - ta sama zasila podpowiedzi pod zamówieniem.
$lista = array_map(static fn(array $p): array => [$p[0], $p[2], $p[3]], require dirname(__DIR__) . '/backend/data/poradniki.php');

ob_start(); ?>
<link rel="stylesheet" href="<?php echo asset('/assets/css/legal.css'); ?>">
<article class="legal glass glass-content" style="border-radius:22px;padding:clamp(1.25rem,4vw,3rem);max-width:900px;margin:0 auto 4rem;">
    <h1>Poradniki</h1>
    <p>Praktyczne instrukcje sprawdzone na naszych serwerach - Minecraft, Project Zomboid i Vintage Story.</p>
    <?php foreach ($lista as [$slug, $tytul, $opis]): ?>
        <h2><a href="/poradniki/<?php echo $slug; ?>" style="color:#f1f5f9;text-decoration:none;"><?php echo htmlspecialchars($tytul); ?></a></h2>
        <p><?php echo htmlspecialchars($opis); ?> <a href="/poradniki/<?php echo $slug; ?>">Czytaj</a></p>
    <?php endforeach; ?>
    <p style="margin-top:2rem;">Szukasz serwera? <a href="/hosting-minecraft">Hosting Minecraft od 6,99 zł/mies.</a> · <a href="/hosting-project-zomboid">Hosting Project Zomboid od 29,99 zł/mies.</a> · <a href="/hosting-vintage-story">Hosting Vintage Story od 19,99 zł/mies.</a></p>
</article>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/assets/clear/clear.php';
