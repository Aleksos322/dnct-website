<?php
/**
 * Diagnoza łącza: gdy usługi za Cloudflare nie odpowiadają, rozstrzyga, czy
 * to nasz węzeł, dostawca internetu, czy sam Cloudflare.
 *
 * Wszystkie publiczne adresy (game, panel, login, web) idą przez Cloudflare,
 * a Wings przez NAT reflection routera prosto do NPM-a. Gdy pada łącze, z
 * zewnątrz wygląda to identycznie jak padnięty węzeł - crash-watch krzyczał
 * wtedy "Sprawdź węzeł" (2026-10-13), choć węzły działały, a ASUS nie widział
 * żadnego rozłączenia WAN-u.
 *
 * Trzy próby naraz, każda max SIEC_TIMEOUT_S:
 *   internet   - Google generate_204, poza Cloudflare'em
 *   cloudflare - brzeg Cloudflare'a po samym IP (bez DNS); HEAD daje 302
 *   wezly      - /api/system każdego węzła; 401 = demon żyje
 */

declare(strict_types=1);

const SIEC_TIMEOUT_S = 6;

/**
 * @return array{internet:bool, cloudflare:bool, wezly:?bool, werdykt:string}
 *         wezly = null, gdy nie dało się pobrać listy węzłów z panelu
 */
function siec_diagnoza(): array
{
    $cele = [
        'internet'   => ['https://www.google.com/generate_204', [204]],
        'cloudflare' => ['https://1.1.1.1/cdn-cgi/trace', [200, 302]],
    ];

    try {
        $wezly = panel_db()->query('SELECT id, fqdn, scheme, daemonListen FROM nodes WHERE public = 1')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($wezly as $n) {
            $cele['wezel-' . $n['id']] = [sprintf('%s://%s:%d/api/system', $n['scheme'], $n['fqdn'], (int)$n['daemonListen']), [200, 401]];
        }
    } catch (Throwable $e) {
        $wezly = null;
    }

    $multi = curl_multi_init();
    $uchwyty = [];
    foreach ($cele as $klucz => [$url]) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => SIEC_TIMEOUT_S,
            CURLOPT_TIMEOUT        => SIEC_TIMEOUT_S,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,   // v6 na 7510 nie ma trasy
            CURLOPT_USERAGENT      => 'DNCT-Siec/1.0',
        ]);
        curl_multi_add_handle($multi, $ch);
        $uchwyty[$klucz] = $ch;
    }
    do {
        $stan = curl_multi_exec($multi, $aktywne);
        if ($aktywne) {
            curl_multi_select($multi, 1.0);
        }
    } while ($aktywne && $stan === CURLM_OK);

    $ok = [];
    foreach ($uchwyty as $klucz => $ch) {
        $ok[$klucz] = in_array((int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), $cele[$klucz][1], true);
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }
    curl_multi_close($multi);

    $wezlyOk = null;
    if ($wezly !== null && $wezly !== []) {
        $wezlyOk = !in_array(false, array_intersect_key($ok, array_flip(array_map(static fn($n) => 'wezel-' . $n['id'], $wezly))), true);
    }

    return [
        'internet'   => $ok['internet'],
        'cloudflare' => $ok['cloudflare'],
        'wezly'      => $wezlyOk,
        'werdykt'    => siec_werdykt($ok['internet'], $ok['cloudflare'], $wezlyOk),
    ];
}

/** Jedno zdanie po polsku: gdzie leży problem. */
function siec_werdykt(bool $internet, bool $cloudflare, ?bool $wezly): string
{
    $stanWezlow = match ($wezly) {
        true  => 'Węzły w sieci lokalnej działają.',
        false => 'Któryś węzeł nie odpowiada też w sieci lokalnej - sprawdź go.',
        null  => 'Stanu węzłów nie udało się sprawdzić.',
    };

    if (!$internet && !$cloudflare) {
        return 'Brak internetu na łączu (dostawca albo router). ' . $stanWezlow;
    }
    if ($internet && !$cloudflare) {
        return 'Internet działa, ale Cloudflare jest nieosiągalny z naszej sieci. ' . $stanWezlow;
    }
    if (!$internet) {
        return 'Cloudflare odpowiada, Google nie - łącze działa częściowo (dostawca). ' . $stanWezlow;
    }
    return 'Internet i Cloudflare odpowiadają - problem na drodze Cloudflare -> NPM albo w samym panelu. ' . $stanWezlow;
}
