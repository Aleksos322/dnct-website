<?php
/* Tłumaczenie /polityka-prywatnosci (backend/lib/i18n.php, i18n_dokument).
   Informacyjne - wiąże wersja polska w polityka-prywatnosci.php. Zmiana
   tamtej wersji = zmiana tego pliku. */
declare(strict_types=1);

return [
'en' => [
    'title' => 'Privacy policy - DNCT Hosting',
    'opis'  => 'How DNCT Hosting processes personal data: legal bases, scope of data, retention periods, your rights and cookies.',
    'tresc' => <<<'HTML'
    <div class="gdpr-badge">
          🔒 GDPR compliant (EU Regulation 2016/679)
        </div>

        <h1>Privacy Policy</h1>
        <p><strong>Effective date:</strong> 8 February 2026 · <strong>updated:</strong> 29 September 2026 (DNCT account, profile, web hosting)</p>
        <p><strong>Last updated:</strong> 8 February 2026</p>

        <div class="info-box">
          <strong>📌 In short:</strong> We only collect the data needed to provide hosting services. We don't sell your data. You have full rights to access, correct and delete your data.
        </div>

        <section>
          <h2>1. Personal data controller</h2>
          <div class="contact-info">
            <p><strong>Name:</strong> DNCT Hosting</p>
            <p><strong>Contact email:</strong> register@dnct.pl</p>
            <p><strong>Website:</strong> https://game.dnct.pl</p>
            <p><strong>Discord server (support):</strong> https://discord.gg/4kcXP5WmQ8</p>
          </div>
          <p>The controller of your personal data is DNCT Hosting, hereinafter the "Controller".</p>
        </section>

        <section>
          <h2>2. Legal basis for processing</h2>
          <p>We process your personal data on the basis of:</p>
          <ul>
            <li><strong>Art. 6(1)(b) GDPR</strong> – performance of a contract (providing hosting services)</li>
            <li><strong>Art. 6(1)(c) GDPR</strong> – legal obligation (accounting, taxes)</li>
            <li><strong>Art. 6(1)(f) GDPR</strong> – legitimate interest (security, fraud prevention)</li>
            <li><strong>Art. 6(1)(a) GDPR</strong> – consent (marketing, analytics cookies)</li>
          </ul>
        </section>

        <section>
          <h2>3. What data do we collect?</h2>

          <h3>3.1. Data provided by the user</h3>
          <table>
            <thead>
              <tr><th>Data category</th><th>Examples</th><th>Purpose of processing</th></tr>
            </thead>
            <tbody>
              <tr><td><strong>Registration data</strong></td><td>Email address, username, password (hash), first name, last name</td><td>Creating and managing the account, authentication</td></tr>
              <tr><td><strong>Payment data</strong></td><td>Transaction history, Stripe transaction ID, plan name</td><td>Billing, invoices, complaints</td></tr>
              <tr><td><strong>Server data</strong></td><td>Server name, configuration, assigned IP, Pterodactyl ID</td><td>Providing hosting services</td></tr>
              <tr><td><strong>Account profile</strong></td><td>Display name, bio, location, website, avatar, banner, in-game nicknames (Minecraft, Discord, Steam), optionally date of birth and phone number</td><td>Showing the profile (publicly <strong>only</strong> the fields you choose and only after you enable the public profile); date of birth and phone - only for contact with support, never public</td></tr>
              <tr><td><strong>Hosting data</strong></td><td>Website files, databases, mailboxes</td><td>Providing the service - we process this data <strong>on the customer's behalf</strong> as a processor (<a href="/umowa-powierzenia">data processing agreement</a>); the customer is its controller</td></tr>
              <tr><td><strong>Correspondence</strong></td><td>Content of Discord messages, support emails</td><td>Handling requests, technical support</td></tr>
            </tbody>
          </table>

          <h3>3.2. Automatically collected data</h3>
          <table>
            <thead>
              <tr><th>Data type</th><th>Source</th><th>Legal basis</th></tr>
            </thead>
            <tbody>
              <tr><td><strong>System logs</strong></td><td>IP address, User-Agent, request time, URI</td><td>Security, debugging (Art. 6(1)(f) GDPR)</td></tr>
              <tr><td><strong>Login sessions</strong></td><td>IP address, browser and system, approximate location (city), time of last use</td><td>Account security - the "Where you're logged in" list in the account settings (Art. 6(1)(f) GDPR)</td></tr>
              <tr><td><strong>Analytics cookies</strong></td><td>Google Analytics 4 (_ga, _ga_*)</td><td>User consent (Art. 6(1)(a) GDPR)</td></tr>
              <tr><td><strong>Functional cookies</strong></td><td>PHP session (PHPSESSID), cookie consent</td><td>Necessary for the website to work</td></tr>
            </tbody>
          </table>

          <div class="warning-box">
            <strong>⚠️ Important:</strong> We don't collect sensitive data (health data, racial origin, political opinions, sexual orientation). We don't profile users for marketing purposes.
          </div>
        </section>

        <section>
          <h2>4. How long do we keep data?</h2>
          <table>
            <thead>
              <tr><th>Data category</th><th>Retention period</th></tr>
            </thead>
            <tbody>
              <tr><td>User account (active)</td><td>Until the account is deleted</td></tr>
              <tr><td>Payment data</td><td>5 years (legal requirement - accounting)</td></tr>
              <tr><td>System logs</td><td>30 days (security)</td></tr>
              <tr><td>Access logs (Apache/Nginx)</td><td>90 days</td></tr>
              <tr><td>Account profile, avatar and banner</td><td>Until the account or the given field is deleted; images are deleted immediately when changed or removed</td></tr>
              <tr><td>Hosting data (websites, mail, databases)</td><td>For the duration of the service; after it ends, according to the Terms of Service (section 15) and the data processing agreement</td></tr>
              <tr><td>Login sessions</td><td>Until logout or session expiry; login events - 12 months</td></tr>
              <tr><td>Server backups</td><td>7 days (rotation)</td></tr>
              <tr><td>Support correspondence</td><td>2 years from the last interaction</td></tr>
              <tr><td>Analytics cookies</td><td>26 months (GA4 default)</td></tr>
            </tbody>
          </table>
        </section>

        <section>
          <h2>5. Who do we share data with?</h2>
          <p>We don't sell or share your personal data with third parties <strong>for marketing purposes</strong>. We may share data with the following entities:</p>

          <h3>5.1. Processors</h3>
          <ul>
            <li><strong>Stripe, Inc.</strong> – payment processor (USA/Ireland, Standard Contractual Clauses)</li>
            <li><strong>Google Analytics</strong> – website traffic analysis (only with the user's consent)</li>
            <li><strong>Cloudflare, Inc.</strong> – CDN, DDoS protection (USA, Standard Contractual Clauses)</li>
            <li><strong>Mailcow (self-hosted)</strong> – sending transactional emails and web hosting customers' mail (our own server in Poland)</li>
            <li><strong>Authentik (self-hosted)</strong> – single sign-on with one DNCT account (our own server in Poland)</li>
            <li><strong>Web hosting and game servers (self-hosted)</strong> – our own infrastructure in Poland, without external providers</li>
          </ul>

          <h3>5.2. Public authorities</h3>
          <p>We may share data at the request of law enforcement or courts <strong>only in legal proceedings</strong> (Art. 6(1)(c) GDPR).</p>
        </section>

        <section>
          <h2>6. Transfers outside the EEA</h2>
          <div class="info-box">
            <p><strong>🌍 International transfers:</strong></p>
            <ul>
              <li><strong>Stripe (USA):</strong> European Commission decision + Standard Contractual Clauses (SCC)</li>
              <li><strong>Cloudflare (USA):</strong> Standard Contractual Clauses (SCC)</li>
              <li><strong>Google (USA):</strong> EU-US Data Privacy Framework adequacy decision (2023)</li>
            </ul>
            <p>All transfers are secured by mechanisms compliant with the GDPR (Chapter V).</p>
          </div>
        </section>

        <section>
          <h2>7. Your rights (GDPR)</h2>
          <p>Under the GDPR you have the following rights:</p>

          <table>
            <thead>
              <tr><th>Right</th><th>Description</th><th>How to use it?</th></tr>
            </thead>
            <tbody>
              <tr><td><strong>Access (Art. 15)</strong></td><td>Getting a copy of your personal data</td><td>Yourself: <a href="https://account.dnct.pl/ustawienia#rodo">Account settings → Download my data</a>, or email: support@dnct.pl</td></tr>
              <tr><td><strong>Rectification (Art. 16)</strong></td><td>Correcting inaccurate data</td><td>User panel or email</td></tr>
              <tr><td><strong>Erasure (Art. 17)</strong></td><td>"Right to be forgotten" - deleting the account</td><td>Yourself: <a href="https://account.dnct.pl/ustawienia#rodo">Account settings → Delete account</a>, or Discord / email</td></tr>
              <tr><td><strong>Restriction (Art. 18)</strong></td><td>Blocking processing during verification</td><td>Email: register@dnct.pl</td></tr>
              <tr><td><strong>Portability (Art. 20)</strong></td><td>Data export in JSON/CSV format</td><td>Yourself: JSON file in the account settings, or a request by email</td></tr>
              <tr><td><strong>Objection (Art. 21)</strong></td><td>Objecting to marketing/cookies</td><td>Cookie settings on the website</td></tr>
              <tr><td><strong>Withdrawing consent (Art. 7(3))</strong></td><td>Withdrawing consent to cookies/marketing</td><td>Cookie banner or email</td></tr>
              <tr><td><strong>Complaint to the UODO</strong></td><td>Lodging a complaint with the supervisory authority (Polish Data Protection Office)</td><td><a href="https://uodo.gov.pl" target="_blank">uodo.gov.pl</a></td></tr>
            </tbody>
          </table>

          <div class="warning-box">
            <strong>⏱️ Response times:</strong> The Controller has 30 calendar days to respond to your request (extendable by another 60 days in complex cases).
          </div>
        </section>

        <section>
          <h2 id="cookies">8. Cookies and tracking technologies</h2>

          <h3>8.1. Types of cookies</h3>
          <table>
            <thead>
              <tr><th>Cookie name</th><th>Type</th><th>Expiry</th><th>Purpose</th></tr>
            </thead>
            <tbody>
              <tr><td><code>PHPSESSID</code></td><td>Functional (necessary)</td><td>Session</td><td>Keeping the user logged in</td></tr>
              <tr><td><code>dnct_cookie_consent</code></td><td>Functional (necessary)</td><td>365 days</td><td>Remembering cookie consent</td></tr>
              <tr><td><code>_ga</code></td><td>Analytics (requires consent)</td><td>2 years</td><td>Google Analytics - user identifier</td></tr>
              <tr><td><code>_ga_0EK3K0EMFP</code></td><td>Analytics (requires consent)</td><td>2 years</td><td>Google Analytics 4 - session state</td></tr>
            </tbody>
          </table>

          <h3>8.2. Managing cookies</h3>
          <ul>
            <li><strong>Consent banner:</strong> On your first visit we show a banner where you can accept or reject analytics cookies</li>
            <li><strong>Withdrawing consent:</strong> You can change your cookie settings at any time by deleting the <code>dnct_cookie_consent</code> cookie in your browser</li>
            <li><strong>Browser:</strong> You can block all cookies in your browser settings (this may affect how the website works)</li>
          </ul>

          <div class="info-box">
            <strong>🔍 Google Analytics:</strong> We use Google Analytics 4 with <strong>IP anonymisation</strong> (the last octet of the IP is masked). The data is not used to personalise ads.
          </div>
        </section>

        <section>
          <h2>9. Data security</h2>
          <p>We use the following security measures:</p>
          <ul>
            <li>✅ <strong>HTTPS/TLS 1.3</strong> – encrypted communication</li>
            <li>✅ <strong>HSTS</strong> (HTTP Strict Transport Security) – enforcing HTTPS</li>
            <li>✅ <strong>CSP</strong> (Content Security Policy) – protection against XSS</li>
            <li>✅ <strong>Password hashing</strong> – bcrypt (cost 12)</li>
            <li>✅ <strong>Rate limiting</strong> – protection against brute force (8 attempts/10 min)</li>
            <li>✅ <strong>CSRF tokens</strong> – protection against CSRF</li>
            <li>✅ <strong>Prepared statements</strong> – protection against SQL injection</li>
            <li>✅ <strong>Firewall</strong> – Cloudflare WAF + iptables</li>
            <li>✅ <strong>Backups</strong> – daily (7-day retention, encrypted)</li>
            <li>✅ <strong>Monitoring</strong> – security logs, intrusion alerts</li>
          </ul>

          <div class="warning-box">
            <strong>⚠️ Your responsibility:</strong> Use a strong password (at least 8 characters, letters, digits, special characters). Don't share your password with others. Enable 2FA when it becomes available.
          </div>
        </section>

        <section>
          <h2>10. Automated decision-making</h2>
          <p>We <strong>don't use</strong> automated decision-making or profiling (Art. 22 GDPR). All decisions about your account (e.g. a block for breaching the Terms of Service) are made by a human.</p>
        </section>

        <section>
          <h2>11. Children's data</h2>
          <p>Our services are intended for people aged <strong>13 and over</strong>. If you are under 16, parental/guardian consent is required under Art. 8 GDPR. We don't knowingly collect data of children under 13.</p>
        </section>

        <section>
          <h2>12. Changes to the Privacy Policy</h2>
          <p>We reserve the right to update this Privacy Policy. In case of significant changes:</p>
          <ul>
            <li>We will publish the new version on the website (with an updated date)</li>
            <li>We will inform users by email (if the change affects their rights)</li>
            <li>We will keep an archive of previous versions (available on request)</li>
          </ul>
        </section>

        <section>
          <h2>13. Privacy contact</h2>
          <div class="contact-info">
            <p><strong>Email:</strong> register@dnct.pl</p>
            <p><strong>Subject:</strong> "GDPR - [your request]"</p>
            <p><strong>Discord support:</strong> <a href="https://discord.gg/4kcXP5WmQ8" target="_blank">https://discord.gg/4kcXP5WmQ8</a></p>
            <p><strong>Response time:</strong> up to 30 calendar days</p>
          </div>
          <p>If you believe your rights have been violated, you can lodge a complaint with the <strong>Polish Personal Data Protection Office (UODO)</strong>:</p>
          <div class="contact-info">
            <p><strong>UODO</strong><br>
            ul. Stawki 2<br>
            00-193 Warszawa, Poland<br>
            Phone: +48 22 531 03 00<br>
            <a href="https://uodo.gov.pl" target="_blank">https://uodo.gov.pl</a></p>
          </div>
        </section>

        <section>
          <h2>14. Version and date</h2>
          <table>
            <tbody>
              <tr><td><strong>Document version:</strong></td><td>1.1</td></tr>
              <tr><td><strong>Effective date:</strong></td><td>8 February 2026</td></tr>
              <tr><td><strong>Last updated:</strong></td><td>29 September 2026</td></tr>
            </tbody>
          </table>
        </section>

        <hr style="margin: 40px 0; border: none; border-top: 2px solid var(--border);">

        <p style="text-align: center; color: #666; font-size: 0.9rem;">
          © 2026 DNCT Hosting. All rights reserved.<br>
           |
          <a href="/regulamin" class="back-link">Terms of Service</a>
        </p>
HTML,
],
'de' => [
    'title' => 'Datenschutzerklärung - DNCT Hosting',
    'opis'  => 'Wie DNCT Hosting personenbezogene Daten verarbeitet: Rechtsgrundlagen, Umfang der Daten, Speicherdauer, deine Rechte und Cookies.',
    'tresc' => <<<'HTML'
    <div class="gdpr-badge">
          🔒 DSGVO-konform (EU-Verordnung 2016/679)
        </div>

        <h1>Datenschutzerklärung</h1>
        <p><strong>Inkrafttreten:</strong> 8. Februar 2026 · <strong>aktualisiert:</strong> 29. September 2026 (DNCT-Konto, Profil, Webhosting)</p>
        <p><strong>Letzte Aktualisierung:</strong> 8. Februar 2026</p>

        <div class="info-box">
          <strong>📌 Kurz gesagt:</strong> Wir erheben nur die Daten, die für Hosting-Dienste nötig sind. Wir verkaufen deine Daten nicht. Du hast das volle Recht auf Auskunft, Berichtigung und Löschung deiner Daten.
        </div>

        <section>
          <h2>1. Verantwortlicher für personenbezogene Daten</h2>
          <div class="contact-info">
            <p><strong>Name:</strong> DNCT Hosting</p>
            <p><strong>Kontakt-E-Mail:</strong> register@dnct.pl</p>
            <p><strong>Website:</strong> https://game.dnct.pl</p>
            <p><strong>Discord-Server (Support):</strong> https://discord.gg/4kcXP5WmQ8</p>
          </div>
          <p>Verantwortlicher für deine personenbezogenen Daten ist DNCT Hosting, im Folgenden „Verantwortlicher“.</p>
        </section>

        <section>
          <h2>2. Rechtsgrundlage der Verarbeitung</h2>
          <p>Wir verarbeiten deine personenbezogenen Daten auf Grundlage von:</p>
          <ul>
            <li><strong>Art. 6 Abs. 1 lit. b DSGVO</strong> – Vertragserfüllung (Erbringung von Hosting-Diensten)</li>
            <li><strong>Art. 6 Abs. 1 lit. c DSGVO</strong> – rechtliche Verpflichtung (Buchhaltung, Steuern)</li>
            <li><strong>Art. 6 Abs. 1 lit. f DSGVO</strong> – berechtigtes Interesse (Sicherheit, Betrugsprävention)</li>
            <li><strong>Art. 6 Abs. 1 lit. a DSGVO</strong> – Einwilligung (Marketing, Analyse-Cookies)</li>
          </ul>
        </section>

        <section>
          <h2>3. Welche Daten erheben wir?</h2>

          <h3>3.1. Vom Nutzer angegebene Daten</h3>
          <table>
            <thead>
              <tr><th>Datenkategorie</th><th>Beispiele</th><th>Zweck der Verarbeitung</th></tr>
            </thead>
            <tbody>
              <tr><td><strong>Registrierungsdaten</strong></td><td>E-Mail-Adresse, Benutzername, Passwort (Hash), Vorname, Nachname</td><td>Anlegen und Verwalten des Kontos, Authentifizierung</td></tr>
              <tr><td><strong>Zahlungsdaten</strong></td><td>Transaktionsverlauf, Stripe-Transaktions-ID, Paketname</td><td>Abrechnung, Rechnungen, Reklamationen</td></tr>
              <tr><td><strong>Serverdaten</strong></td><td>Servername, Konfiguration, zugewiesene IP, Pterodactyl-ID</td><td>Erbringung der Hosting-Dienste</td></tr>
              <tr><td><strong>Kontoprofil</strong></td><td>Anzeigename, Beschreibung, Standort, Website, Avatar, Banner, Spielernamen (Minecraft, Discord, Steam), optional Geburtsdatum und Telefonnummer</td><td>Anzeige des Profils (öffentlich <strong>nur</strong> die von dir gewählten Felder und nur nach Aktivierung des öffentlichen Profils); Geburtsdatum und Telefon - nur für den Kontakt mit dem Support, nie öffentlich</td></tr>
              <tr><td><strong>Hosting-Daten</strong></td><td>Website-Dateien, Datenbanken, Postfächer</td><td>Erbringung des Dienstes - diese Daten verarbeiten wir <strong>im Auftrag des Kunden</strong> als Auftragsverarbeiter (<a href="/umowa-powierzenia">Auftragsverarbeitungsvertrag</a>); Verantwortlicher ist der Kunde</td></tr>
              <tr><td><strong>Korrespondenz</strong></td><td>Inhalt von Discord-Nachrichten, Support-E-Mails</td><td>Bearbeitung von Anfragen, technischer Support</td></tr>
            </tbody>
          </table>

          <h3>3.2. Automatisch erhobene Daten</h3>
          <table>
            <thead>
              <tr><th>Datentyp</th><th>Quelle</th><th>Rechtsgrundlage</th></tr>
            </thead>
            <tbody>
              <tr><td><strong>Systemprotokolle</strong></td><td>IP-Adresse, User-Agent, Zeitpunkt der Anfrage, URI</td><td>Sicherheit, Fehlersuche (Art. 6 Abs. 1 lit. f DSGVO)</td></tr>
              <tr><td><strong>Login-Sitzungen</strong></td><td>IP-Adresse, Browser und System, ungefährer Standort (Stadt), Zeitpunkt der letzten Nutzung</td><td>Kontosicherheit - die Liste „Wo du angemeldet bist“ in den Kontoeinstellungen (Art. 6 Abs. 1 lit. f DSGVO)</td></tr>
              <tr><td><strong>Analyse-Cookies</strong></td><td>Google Analytics 4 (_ga, _ga_*)</td><td>Einwilligung des Nutzers (Art. 6 Abs. 1 lit. a DSGVO)</td></tr>
              <tr><td><strong>Funktionale Cookies</strong></td><td>PHP-Sitzung (PHPSESSID), Cookie-Einwilligung</td><td>Für den Betrieb der Website notwendig</td></tr>
            </tbody>
          </table>

          <div class="warning-box">
            <strong>⚠️ Wichtig:</strong> Wir erheben keine sensiblen Daten (Gesundheitsdaten, ethnische Herkunft, politische Meinungen, sexuelle Orientierung). Wir erstellen keine Nutzerprofile für Marketingzwecke.
          </div>
        </section>

        <section>
          <h2>4. Wie lange speichern wir Daten?</h2>
          <table>
            <thead>
              <tr><th>Datenkategorie</th><th>Speicherdauer</th></tr>
            </thead>
            <tbody>
              <tr><td>Nutzerkonto (aktiv)</td><td>Bis zur Löschung des Kontos</td></tr>
              <tr><td>Zahlungsdaten</td><td>5 Jahre (gesetzliche Pflicht - Buchhaltung)</td></tr>
              <tr><td>Systemprotokolle</td><td>30 Tage (Sicherheit)</td></tr>
              <tr><td>Zugriffsprotokolle (Apache/Nginx)</td><td>90 Tage</td></tr>
              <tr><td>Kontoprofil, Avatar und Banner</td><td>Bis zur Löschung des Kontos oder des jeweiligen Feldes; Bilder löschen wir sofort bei Änderung oder Entfernung</td></tr>
              <tr><td>Hosting-Daten (Websites, Mail, Datenbanken)</td><td>Für die Dauer des Dienstes; danach gemäß AGB (Punkt 15) und Auftragsverarbeitungsvertrag</td></tr>
              <tr><td>Login-Sitzungen</td><td>Bis zur Abmeldung oder zum Ablauf der Sitzung; Login-Ereignisse - 12 Monate</td></tr>
              <tr><td>Server-Backups</td><td>7 Tage (Rotation)</td></tr>
              <tr><td>Support-Korrespondenz</td><td>2 Jahre ab der letzten Interaktion</td></tr>
              <tr><td>Analyse-Cookies</td><td>26 Monate (GA4-Standard)</td></tr>
            </tbody>
          </table>
        </section>

        <section>
          <h2>5. An wen geben wir Daten weiter?</h2>
          <p>Wir verkaufen deine personenbezogenen Daten nicht und geben sie nicht <strong>zu Marketingzwecken</strong> an Dritte weiter. Wir können Daten an folgende Stellen weitergeben:</p>

          <h3>5.1. Auftragsverarbeiter</h3>
          <ul>
            <li><strong>Stripe, Inc.</strong> – Zahlungsdienstleister (USA/Irland, Standardvertragsklauseln)</li>
            <li><strong>Google Analytics</strong> – Analyse des Website-Traffics (nur mit Einwilligung des Nutzers)</li>
            <li><strong>Cloudflare, Inc.</strong> – CDN, DDoS-Schutz (USA, Standardvertragsklauseln)</li>
            <li><strong>Mailcow (selbst gehostet)</strong> – Versand von Transaktions-E-Mails und Mail der Webhosting-Kunden (eigener Server in Polen)</li>
            <li><strong>Authentik (selbst gehostet)</strong> – Login mit einem DNCT-Konto (eigener Server in Polen)</li>
            <li><strong>Webhosting und Gameserver (selbst gehostet)</strong> – eigene Infrastruktur in Polen, ohne externe Anbieter</li>
          </ul>

          <h3>5.2. Behörden</h3>
          <p>Wir können Daten auf Anfrage von Strafverfolgungsbehörden oder Gerichten <strong>nur im Rahmen von Gerichtsverfahren</strong> weitergeben (Art. 6 Abs. 1 lit. c DSGVO).</p>
        </section>

        <section>
          <h2>6. Übermittlung außerhalb des EWR</h2>
          <div class="info-box">
            <p><strong>🌍 Internationale Übermittlung:</strong></p>
            <ul>
              <li><strong>Stripe (USA):</strong> Beschluss der Europäischen Kommission + Standardvertragsklauseln (SCC)</li>
              <li><strong>Cloudflare (USA):</strong> Standardvertragsklauseln (SCC)</li>
              <li><strong>Google (USA):</strong> Angemessenheitsbeschluss EU-US Data Privacy Framework (2023)</li>
            </ul>
            <p>Alle Übermittlungen sind durch DSGVO-konforme Mechanismen abgesichert (Kapitel V).</p>
          </div>
        </section>

        <section>
          <h2>7. Deine Rechte (DSGVO)</h2>
          <p>Nach der DSGVO hast du folgende Rechte:</p>

          <table>
            <thead>
              <tr><th>Recht</th><th>Beschreibung</th><th>Wie nutzt du es?</th></tr>
            </thead>
            <tbody>
              <tr><td><strong>Auskunft (Art. 15)</strong></td><td>Eine Kopie deiner personenbezogenen Daten erhalten</td><td>Selbst: <a href="https://account.dnct.pl/ustawienia#rodo">Kontoeinstellungen → Meine Daten herunterladen</a>, oder per E-Mail: support@dnct.pl</td></tr>
              <tr><td><strong>Berichtigung (Art. 16)</strong></td><td>Falsche Daten korrigieren</td><td>Nutzerpanel oder E-Mail</td></tr>
              <tr><td><strong>Löschung (Art. 17)</strong></td><td>„Recht auf Vergessenwerden“ - Löschung des Kontos</td><td>Selbst: <a href="https://account.dnct.pl/ustawienia#rodo">Kontoeinstellungen → Konto löschen</a>, oder Discord / E-Mail</td></tr>
              <tr><td><strong>Einschränkung (Art. 18)</strong></td><td>Sperrung der Verarbeitung während einer Prüfung</td><td>E-Mail: register@dnct.pl</td></tr>
              <tr><td><strong>Übertragbarkeit (Art. 20)</strong></td><td>Datenexport im Format JSON/CSV</td><td>Selbst: JSON-Datei in den Kontoeinstellungen, oder Anfrage per E-Mail</td></tr>
              <tr><td><strong>Widerspruch (Art. 21)</strong></td><td>Widerspruch gegen Marketing/Cookies</td><td>Cookie-Einstellungen auf der Website</td></tr>
              <tr><td><strong>Widerruf der Einwilligung (Art. 7 Abs. 3)</strong></td><td>Einwilligung zu Cookies/Marketing zurückziehen</td><td>Cookie-Banner oder E-Mail</td></tr>
              <tr><td><strong>Beschwerde beim UODO</strong></td><td>Beschwerde bei der Aufsichtsbehörde (polnisches Datenschutzamt)</td><td><a href="https://uodo.gov.pl" target="_blank">uodo.gov.pl</a></td></tr>
            </tbody>
          </table>

          <div class="warning-box">
            <strong>⏱️ Antwortfristen:</strong> Der Verantwortliche hat 30 Kalendertage Zeit, auf deine Anfrage zu antworten (in komplexen Fällen verlängerbar um weitere 60 Tage).
          </div>
        </section>

        <section>
          <h2 id="cookies">8. Cookies und Tracking-Technologien</h2>

          <h3>8.1. Arten von Cookies</h3>
          <table>
            <thead>
              <tr><th>Cookie-Name</th><th>Typ</th><th>Gültigkeit</th><th>Zweck</th></tr>
            </thead>
            <tbody>
              <tr><td><code>PHPSESSID</code></td><td>Funktional (notwendig)</td><td>Sitzung</td><td>Aufrechterhaltung der Anmeldung</td></tr>
              <tr><td><code>dnct_cookie_consent</code></td><td>Funktional (notwendig)</td><td>365 Tage</td><td>Speichern der Cookie-Einwilligung</td></tr>
              <tr><td><code>_ga</code></td><td>Analyse (einwilligungspflichtig)</td><td>2 Jahre</td><td>Google Analytics - Nutzerkennung</td></tr>
              <tr><td><code>_ga_0EK3K0EMFP</code></td><td>Analyse (einwilligungspflichtig)</td><td>2 Jahre</td><td>Google Analytics 4 - Sitzungsstatus</td></tr>
            </tbody>
          </table>

          <h3>8.2. Verwaltung von Cookies</h3>
          <ul>
            <li><strong>Einwilligungsbanner:</strong> Beim ersten Besuch zeigen wir ein Banner, in dem du Analyse-Cookies akzeptieren oder ablehnen kannst</li>
            <li><strong>Widerruf der Einwilligung:</strong> Du kannst deine Cookie-Einstellungen jederzeit ändern, indem du das Cookie <code>dnct_cookie_consent</code> in deinem Browser löschst</li>
            <li><strong>Browser:</strong> Du kannst alle Cookies in den Browsereinstellungen blockieren (das kann die Funktion der Website beeinträchtigen)</li>
          </ul>

          <div class="info-box">
            <strong>🔍 Google Analytics:</strong> Wir nutzen Google Analytics 4 mit <strong>IP-Anonymisierung</strong> (das letzte Oktett der IP wird maskiert). Die Daten werden nicht zur Personalisierung von Werbung verwendet.
          </div>
        </section>

        <section>
          <h2>9. Datensicherheit</h2>
          <p>Wir setzen folgende Sicherheitsmaßnahmen ein:</p>
          <ul>
            <li>✅ <strong>HTTPS/TLS 1.3</strong> – verschlüsselte Kommunikation</li>
            <li>✅ <strong>HSTS</strong> (HTTP Strict Transport Security) – Erzwingen von HTTPS</li>
            <li>✅ <strong>CSP</strong> (Content Security Policy) – Schutz vor XSS</li>
            <li>✅ <strong>Passwort-Hashing</strong> – bcrypt (cost 12)</li>
            <li>✅ <strong>Rate-Limiting</strong> – Schutz vor Brute-Force (8 Versuche/10 Min.)</li>
            <li>✅ <strong>CSRF-Token</strong> – Schutz vor CSRF</li>
            <li>✅ <strong>Prepared Statements</strong> – Schutz vor SQL-Injection</li>
            <li>✅ <strong>Firewall</strong> – Cloudflare WAF + iptables</li>
            <li>✅ <strong>Backups</strong> – täglich (7 Tage Aufbewahrung, verschlüsselt)</li>
            <li>✅ <strong>Monitoring</strong> – Sicherheitsprotokolle, Einbruchswarnungen</li>
          </ul>

          <div class="warning-box">
            <strong>⚠️ Deine Verantwortung:</strong> Nutze ein starkes Passwort (mindestens 8 Zeichen, Buchstaben, Ziffern, Sonderzeichen). Gib dein Passwort nicht an andere weiter. Aktiviere 2FA, sobald es verfügbar ist.
          </div>
        </section>

        <section>
          <h2>10. Automatisierte Entscheidungsfindung</h2>
          <p>Wir <strong>nutzen keine</strong> automatisierte Entscheidungsfindung oder Profiling (Art. 22 DSGVO). Alle Entscheidungen zu deinem Konto (z. B. eine Sperre wegen Verstoßes gegen die AGB) trifft ein Mensch.</p>
        </section>

        <section>
          <h2>11. Daten von Kindern</h2>
          <p>Unsere Dienste richten sich an Personen ab <strong>13 Jahren</strong>. Bist du jünger als 16, ist gemäß Art. 8 DSGVO die Zustimmung der Eltern/Erziehungsberechtigten nötig. Wir erheben nicht wissentlich Daten von Kindern unter 13 Jahren.</p>
        </section>

        <section>
          <h2>12. Änderungen der Datenschutzerklärung</h2>
          <p>Wir behalten uns vor, diese Datenschutzerklärung zu aktualisieren. Bei wesentlichen Änderungen:</p>
          <ul>
            <li>veröffentlichen wir die neue Fassung auf der Website (mit aktualisiertem Datum)</li>
            <li>informieren wir die Nutzer per E-Mail (wenn die Änderung ihre Rechte betrifft)</li>
            <li>bewahren wir ein Archiv früherer Fassungen auf (auf Anfrage verfügbar)</li>
          </ul>
        </section>

        <section>
          <h2>13. Kontakt in Datenschutzfragen</h2>
          <div class="contact-info">
            <p><strong>E-Mail:</strong> register@dnct.pl</p>
            <p><strong>Betreff:</strong> „DSGVO - [deine Anfrage]“</p>
            <p><strong>Discord-Support:</strong> <a href="https://discord.gg/4kcXP5WmQ8" target="_blank">https://discord.gg/4kcXP5WmQ8</a></p>
            <p><strong>Antwortzeit:</strong> bis zu 30 Kalendertage</p>
          </div>
          <p>Wenn du der Meinung bist, dass deine Rechte verletzt wurden, kannst du Beschwerde beim <strong>polnischen Amt für den Schutz personenbezogener Daten (UODO)</strong> einlegen:</p>
          <div class="contact-info">
            <p><strong>UODO</strong><br>
            ul. Stawki 2<br>
            00-193 Warszawa, Polen<br>
            Tel.: +48 22 531 03 00<br>
            <a href="https://uodo.gov.pl" target="_blank">https://uodo.gov.pl</a></p>
          </div>
        </section>

        <section>
          <h2>14. Version und Datum</h2>
          <table>
            <tbody>
              <tr><td><strong>Dokumentversion:</strong></td><td>1.1</td></tr>
              <tr><td><strong>Inkrafttreten:</strong></td><td>8. Februar 2026</td></tr>
              <tr><td><strong>Letzte Aktualisierung:</strong></td><td>29. September 2026</td></tr>
            </tbody>
          </table>
        </section>

        <hr style="margin: 40px 0; border: none; border-top: 2px solid var(--border);">

        <p style="text-align: center; color: #666; font-size: 0.9rem;">
          © 2026 DNCT Hosting. Alle Rechte vorbehalten.<br>
           |
          <a href="/regulamin" class="back-link">AGB</a>
        </p>
HTML,
],
];
