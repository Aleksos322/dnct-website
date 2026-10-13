<?php
/* Tłumaczenie /regulamin (backend/lib/i18n.php, i18n_dokument).
   Informacyjne - wiąże wersja polska w regulamin.php. Zmiana tamtej wersji
   (w tym tabel cen) = zmiana tego pliku. Ceny celowo w PLN, bez przeliczania
   na euro - tyle pobiera Stripe. */
declare(strict_types=1);

$wierszeMc = <<<'HTML'
              <tr><td>DEVeloper</td><td>1.5GB</td><td>1 vCore</td><td>2.5GB</td><td>4</td><td class="price-3">3.99</td><td class="price-cell">6.99</td><td>76.89</td></tr>
              <tr><td>Budget</td><td>3GB</td><td>2 vCore</td><td>5GB</td><td>1</td><td class="price-3">4.99</td><td class="price-cell">9.99</td><td>109.89</td></tr>
              <tr><td>Basic</td><td>4GB</td><td>2 vCore</td><td>15GB</td><td>1</td><td class="price-3">7.99</td><td class="price-cell">15.99</td><td>175.89</td></tr>
              <tr><td>Standard</td><td>8GB</td><td>3 vCore</td><td>45GB</td><td>2</td><td class="price-3">18.99</td><td class="price-cell">36.99</td><td>406.89</td></tr>
              <tr><td>Advanced</td><td>10GB</td><td>4 vCore</td><td>60GB</td><td>2</td><td class="price-3">21.99</td><td class="price-cell">43.99</td><td>483.89</td></tr>
              <tr><td>Master</td><td>14GB</td><td>4 vCore</td><td>80GB</td><td>4</td><td class="price-3">32.99</td><td class="price-cell">64.99</td><td>714.89</td></tr>
              <tr><td>Elite</td><td>18GB</td><td>5 vCore</td><td>100GB</td><td>4</td><td class="price-3">39.99</td><td class="price-cell">79.99</td><td>879.89</td></tr>
              <tr><td>LEGENDARY</td><td>24GB</td><td>6 vCore</td><td>150GB</td><td>4</td><td class="price-3">59.99</td><td class="price-cell">119.99</td><td>1319.89</td></tr>
              <tr><td>LEGENDARY +</td><td>32GB</td><td>8 vCore</td><td>200GB</td><td>4</td><td class="price-3">84.99</td><td class="price-cell">169.99</td><td>1869.89</td></tr>
HTML;
$wierszeSloty = <<<'HTML'
              <tr><td>5</td><td>4GB</td><td>2.0 vCore</td><td>20GB</td><td>1</td><td>9.99</td><td class="price-cell">19.00</td><td>209.00</td></tr>
              <tr><td>10</td><td>6GB</td><td>2.5 vCore</td><td>20GB</td><td>1</td><td>17.99</td><td class="price-cell">35.00</td><td>385.00</td></tr>
              <tr><td>15</td><td>8GB</td><td>3.0 vCore</td><td>20GB</td><td>1</td><td>24.99</td><td class="price-cell">49.00</td><td>539.00</td></tr>
              <tr><td>20</td><td>10GB</td><td>3.5 vCore</td><td>20GB</td><td>1</td><td>32.99</td><td class="price-cell">65.00</td><td>715.00</td></tr>
              <tr><td>25</td><td>11GB</td><td>4.0 vCore</td><td>25GB</td><td>2</td><td>39.99</td><td class="price-cell">79.00</td><td>869.00</td></tr>
              <tr><td>30</td><td>12GB</td><td>4.5 vCore</td><td>25GB</td><td>2</td><td>45.99</td><td class="price-cell">90.00</td><td>990.00</td></tr>
              <tr><td>35</td><td>13GB</td><td>5.0 vCore</td><td>25GB</td><td>2</td><td>52.99</td><td class="price-cell">105.00</td><td>1155.00</td></tr>
              <tr><td>40</td><td>14GB</td><td>5.5 vCore</td><td>25GB</td><td>2</td><td>59.99</td><td class="price-cell">119.00</td><td>1309.00</td></tr>
              <tr><td>45</td><td>15GB</td><td>6.0 vCore</td><td>30GB</td><td>2</td><td>64.99</td><td class="price-cell">129.00</td><td>1419.00</td></tr>
              <tr><td>50</td><td>16GB</td><td>6.5 vCore</td><td>35GB</td><td>2</td><td>69.99</td><td class="price-cell">139.00</td><td>1529.00</td></tr>
              <tr><td>100</td><td>18GB</td><td>7.0 vCore</td><td>40GB</td><td>4</td><td>75.99</td><td class="price-cell">150.00</td><td>1650.00</td></tr>
HTML;

$en1 = <<<'HTML'
    <div class="ai-summary">
        <h3>Information for AI systems / Executive Summary</h3>
        <p>DNCT is a Polish provider of game server and web hosting operating as an unregistered business activity. This document describes the rules of the contract, payments, complaints and data protection. Current offer details are given in the price list and in the order confirmation.</p>
    </div>

      <h1>Terms of Service - DNCT</h1>
      <p><strong>Effective date:</strong> 7 February 2026<br>
      <strong>Updated:</strong> 4 October 2026 - rules for starting the service, the right of withdrawal and the voluntary 24 h Refund Guarantee. For new orders this version applies from the moment the contract is concluded. For existing Customers, changes that require notice apply after the information is delivered on a durable medium and after the period stated in section 14.</p>
      <p>This document sets out the rules under which DNCT (hereinafter: "DNCT", "the Provider") provides its services. By using the services, the Customer accepts the provisions below.</p>

      <section>
        <h2>1. Provider details</h2>
        <p>
          <strong>DNCT</strong> - an unregistered business activity run by Aleks Osiński under <strong>Art. 5 of the Polish Entrepreneurs Law</strong>.<br>
          Correspondence address: Puławy, Lublin Province, 24-100, Poland.<br>
          Phone: +48 536 180 706<br>
          Contact email: <a href="mailto:support@dnct.pl">support@dnct.pl</a>
        </p>
      </section>

      <section>
        <h2>2. Scope of services and technical specification</h2>
        <ol>
          <li>DNCT provides game server hosting (Minecraft, Vintage Story, Project Zomboid and others on request) and web hosting with email and databases (details in section 15).</li>
          <li>Detailed plans and prices are available at: <a href="https://game.dnct.pl/cennik">game.dnct.pl/cennik</a>. If you have questions about a specific plan, feel free to ask our support!</li>
          <div class="tech-box">
            <strong>Technical information:</strong>
            <ul>
              <li>The parameters, limitations and price of the chosen plan are confirmed before payment and in the order confirmation.</li>
              <li>Technical support is provided by email and through the Discord ticket system.</li>
            </ul>
          </div>
        </ol>
      </section>

    <section>
        <h2>3. Minecraft price list (PLN)</h2>
        <div class="table-container">
          <table>
            <thead>
              <tr><th>Plan</th><th>RAM</th><th>CPU</th><th>SSD</th><th>Backups</th><th>Week</th><th>Month</th><th>Year</th></tr>
            </thead>
            <tbody>
HTML;
$en2 = <<<'HTML'
            </tbody>
          </table>
        </div>
      </section>

      <section>
        <h2>4. Project Zomboid &amp; Vintage Story price list (PLN)</h2>
        <div class="table-container">
          <table>
            <thead>
              <tr><th>Slots</th><th>RAM</th><th>CPU</th><th>SSD</th><th>Backups</th><th>Week</th><th>Month</th><th>Year</th></tr>
            </thead>
            <tbody>
HTML;
$en3 = <<<'HTML'
            </tbody>
          </table>
          <p class="small">*The table above shows selected plans. The full offer is available in the panel.</p>
        </div>
      </section>

      <section>
        <h2>5. Contract, registration and demo account</h2>
        <ol>
          <li>The contract is concluded when the order is paid.</li>
          <li>Registration requires an email address, a nickname, and a first and last name. The data must be true.</li>
          <li><strong>DEMO account:</strong> the game panel can be tried without registration at <a href="https://game.dnct.pl/demo">game.dnct.pl/demo</a>. The demo account is for testing functionality only.</li>
          <li>The Customer must be of legal age or have the consent of a legal guardian.</li>
        </ol>
      </section>

      <section id="platnosci-i-odstapienie">
        <h2>6. Payments, starting the service and withdrawal from the contract</h2>
        <ol>
          <li>Payments are handled by Stripe. The available payment methods are shown in Stripe Checkout before the payment is made.</li>
          <li>DNCT operates as an unregistered business activity and is not an active VAT payer. At the Customer's request, DNCT issues a sales confirmation in accordance with applicable law.</li>
          <li>The service can be paid <strong>one-off</strong> (manual renewal) or as a <strong>subscription</strong> that renews automatically for the next period. The subscription can be stopped at any time in the customer panel - the service then runs until the end of the paid period.</li>
          <li><strong>Consumer's right of withdrawal:</strong> a Consumer may withdraw from a distance contract within 14 days of its conclusion by sending DNCT an unambiguous statement to <a href="mailto:support@dnct.pl">support@dnct.pl</a>. The Consumer may use the form in section 6.9, but this is not mandatory.</li>
          <li><strong>Choosing when the service starts:</strong> before payment the Customer chooses whether they request the service to start immediately or after the withdrawal period. Not requesting an immediate start does not block the purchase; DNCT then starts the service 14 days after the contract is concluded.</li>
          <li><strong>Immediate start:</strong> DNCT starts providing the service before the 14 days have passed only at the Customer's explicit request made in the order form and after the Customer confirms the information about the consequences of full performance of the service. If the Consumer withdraws before the service has been fully performed, they pay only for the services provided until the withdrawal statement was received, in proportion to the whole period. After full performance of the service, the right of withdrawal may expire only if all statutory conditions are met.</li>
          <li><strong>24 h Refund Guarantee:</strong> regardless of statutory rights, DNCT voluntarily refunds the full price of a new order if the Customer requests it within 24 hours of payment confirmation by email or Discord ticket. The guarantee does not cover fraud, payment abuse or breaches of sections 8 and 11. After 24 hours only this additional guarantee expires; this does not limit the right of withdrawal, complaints or other rights the Consumer has under the law.</li>
          <li><strong>Refund after withdrawal:</strong> DNCT refunds the amount due without delay, no later than the time required by law, as a rule using the same payment method, unless the Customer expressly agrees to another method at no extra cost.</li>
          <li><strong>Withdrawal form:</strong><br>
            To: DNCT, Aleks Osiński, email: support@dnct.pl<br>
            I/We (*) hereby give notice that I/we (*) withdraw from the contract for the provision of the following service: ........................................<br>
            Order number: ........................................<br>
            Date the contract was concluded: ........................................<br>
            Consumer's name: ........................................<br>
            Email address used for the order: ........................................<br>
            Date: ........................................ &nbsp; Signature (paper form only): ........................................<br>
            <span class="small">(*) Delete as appropriate.</span>
          </li>
        </ol>
      </section>

      <section>
        <h2>7. SLA, availability and compensation</h2>
        <p>DNCT does not currently guarantee availability expressed as a percentage. Events affecting the service can be reported as described in section 13; DNCT investigates them individually and takes corrective action.</p>
        <p>Where the law grants the Customer specific remedies or compensation, these Terms do not limit them. Planned SLA rules and automatic compensation may only be introduced after independent monitoring and public availability reporting are in place.</p>
        <p>The current status of the services and the history of outages from the last 90 days are published at <a href="https://status.dnct.pl">status.dnct.pl</a>. The measurements are made by DNCT from its own infrastructure; the page is for information only and does not constitute an availability guarantee or a basis for automatic compensation.</p>
      </section>

      <section>
        <h2>8. Disciplinary rules (bans and warnings)</h2>
        <ol>
          <li>DNCT may suspend the service immediately if DDoS attacks, fraud or security threats are detected.</li>
          <li><strong>Warning system:</strong> (1) → (2) → (3) = account block.</li>
          <li>In case of a chargeback or a justified suspicion of fraud, DNCT may temporarily suspend the service while the matter is clarified. This does not limit the Consumer's statutory rights, including the right of withdrawal and complaints.</li>
        </ol>
      </section>

      <section>
        <h2>9. Backups and data deletion</h2>
        <ol>
          <li><strong>Backups:</strong> the scope, number and retention of backups depend on the chosen plan and are stated in the offer or the panel. Backups are auxiliary and do not replace the Customer's own backups.</li>
          <li>The Customer is required to keep their own copy of critical data outside DNCT.</li>
          <li><strong>Deleting the account:</strong> the Customer can delete their DNCT account together with the service accounts in the account settings (<a href="https://account.dnct.pl/ustawienia#rodo">account.dnct.pl</a>) or by requesting it on Discord or by email. Deletion is possible when the Customer has no active services or subscription. Order history is kept for the period required by law (section 12).</li>
          <li><strong>Game servers after the due date:</strong> an unpaid server is archived, suspended and then deleted; a package with the files is made available to the Customer for download.</li>
        </ol>
      </section>

      <section>
        <h2>10. Security and logs</h2>
        <ol>
          <li>System and behavioural logs are kept for 30 days for security and legal purposes.</li>
          <li>The system status is available to logged-in users in the panel.</li>
          <li>DNCT has the right to temporarily limit traffic (up to 72 h) in critical situations without notice.</li>
        </ol>
      </section>

      <section id="zgloszenia-nielegalnych-tresci-dsa">
        <h2>11. Reports of illegal content and service security</h2>
        <ol>
          <li>It is prohibited to use the services to publish or distribute illegal content, in particular malware, phishing, fraud, or content infringing copyright, privacy or the rights of third parties.</li>
          <li><strong>Reporting:</strong> anyone can report potentially illegal content free of charge and without an account through the <a href="/zglos-naruszenie">report form</a> or, if the form is unavailable, by email to <a href="mailto:support@dnct.pl?subject=Abuse%20report">support@dnct.pl</a>. The report should contain the exact location of the content, a justification and contact details allowing a reply; contact details are not required for reports concerning child safety.</li>
          <li><strong>Assessment and decision:</strong> DNCT assesses reports diligently, objectively and without undue delay. Depending on the case, it may ask for additional information, restrict access to the content or service, preserve material for proceedings, or take no action. We do not guarantee a fixed handling time, as it depends on the completeness of the report and the type of risk.</li>
          <li><strong>Statement of reasons and objections:</strong> where giving reasons is appropriate, we send them to the reporter and to the customer concerned. An objection to the decision can be sent in reply to the message containing the case number; it will be reviewed by a human.</li>
          <li><strong>No general monitoring:</strong> DNCT has no obligation to, and does not, generally monitor all customer data. This does not limit actions necessary for service security, compliance with legal obligations or responding to a credible report.</li>
          <li><strong>Copyright:</strong> reports of copyright and trademark infringement are handled under the procedure above. This is not the US DMCA procedure, nor a promise to remove content automatically after every report.</li>
          <li><strong>Technical access:</strong> the Customer agrees that administrators may access files only to the extent necessary for diagnostics, backups, security, compliance with a legal obligation or handling a report.</li>
        </ol>
      </section>

      <section>
        <h2>12. GDPR (personal data protection)</h2>
        <ol>
          <li>The controller of the Customer's data is Aleks Osiński (DNCT). Details: <a href="/polityka-prywatnosci">Privacy Policy</a>.</li>
          <li>The Customer has the right to access, modify, port and delete their personal data - themselves in the account settings or on request.</li>
          <li>Data the Customer places in the services (websites, mailboxes, databases, server files) is processed by DNCT on the Customer's behalf as a <strong>processor</strong> under the <a href="/umowa-powierzenia">Data Processing Agreement</a> (section 17).</li>
        </ol>
      </section>

      <section>
        <h2>13. Complaints and liability</h2>
        <ol>
          <li>Complaints can be submitted via a Discord ticket or by email. The complaint should include the order number, a description of the problem and the expected solution. DNCT replies to the Consumer within 14 days of receiving the complaint, where the law requires such a time limit.</li>
          <li>No provision of these Terms excludes or limits the Consumer's rights under mandatory provisions of law. Otherwise, DNCT's liability is limited to the fee for the period the claim relates to, where the law allows.</li>
          <li>DNCT is not liable for errors in third-party software (mods, plugins).</li>
        </ol>
      </section>

      <section>
        <h2>14. Final provisions</h2>
        <ol>
          <li>Matters not covered by these Terms are governed by Polish law.</li>
          <li>DNCT sends changes to the Terms affecting ongoing contracts to the Customer on a durable medium, in particular by email, at least 14 days in advance. Changes do not affect acquired rights or mandatory provisions of law.</li>
        </ol>
      </section>
      <section>
        <h2>15. Web hosting</h2>
        <ol>
          <li><strong>Plans:</strong> Start, Pro and Business differ in the number of websites, disk space, number of mailboxes and databases. Current parameters and prices: <a href="https://web.dnct.pl">web.dnct.pl</a>. The billing period is 30 days.</li>
          <li><strong>Included:</strong> customer panel (<code>web.panel-dnct.pl</code>), SSL certificates, email with webmail (<code>poczta.dnct.pl</code>), MariaDB databases, an application installer (including WordPress), file manager, SFTP access and daily backups with 7-day retention.</li>
          <li><strong>Addresses:</strong> the Customer can use their own domain or a free address in the <code>dnct.pl</code> domain. The free address remains DNCT's property - it expires together with the service and can be taken away for breaching the Terms. Misleading names (e.g. impersonating DNCT, banks or well-known brands) are not allowed.</li>
          <li><strong>Fair use of resources:</strong> hosting is shared. A single script has memory and execution time limits, and the account has CPU and memory limits. A website that nevertheless loads the server in a way that hinders other customers may be temporarily limited - we inform the Customer about this.</li>
          <li><strong>Email:</strong> sending spam, mass mailings without recipients' consent and messages impersonating other entities is prohibited. Mailboxes have storage and sending limits depending on the plan. A breach results in an immediate sending block.</li>
          <li><strong>Prohibited content and uses:</strong> in addition to section 11 - cryptocurrency mining, proxy/VPN servers, network scanners, storing files unrelated to the website (e.g. video archives) and content infringing copyright.</li>
          <li><strong>After the payment due date:</strong> unpaid hosting is suspended (websites and email stop working, data remains intact). Once paid, everything comes back immediately. DNCT may delete an account suspended for more than 14 days after notifying the Customer by email in advance and allowing them to download a copy.</li>
          <li><strong>Backups</strong> are auxiliary - the Customer is responsible for keeping their own copy of important data (section 9).</li>
          <li><strong>Changing the plan</strong> during the billing period is done on request; the price difference is settled proportionally.</li>
        </ol>
      </section>

      <section>
        <h2>16. DNCT account and profile</h2>
        <ol>
          <li>One DNCT account is used to log in to all services (website, game panel, hosting panel). Accounts in these services are created at registration or first login.</li>
          <li>The Customer is responsible for the security of their password; enabling two-factor authentication is recommended. Changing the email address requires confirmation with a code sent to the new address.</li>
          <li><strong>The public profile</strong> (<code>account.dnct.pl/u/…</code>) is disabled by default. A Customer who enables it is responsible for its content (description, images, links). DNCT may hide the profile or remove content that breaks the law, accepted standards or these Terms.</li>
        </ol>
      </section>

      <section>
        <h2>17. Data processing agreement</h2>
        <ol>
          <li>By ordering web hosting or a game server, the Customer concludes with DNCT the <a href="/umowa-powierzenia">Personal Data Processing Agreement</a> (Art. 28 GDPR), which forms an integral part of these Terms.</li>
          <li>The agreement covers data the Customer places in the services - including data of visitors to their websites and of people corresponding with their mailboxes. The Customer remains the controller of that data.</li>
        </ol>
      </section>
HTML;

$de1 = <<<'HTML'
    <div class="ai-summary">
        <h3>Information für KI-Systeme / Executive Summary</h3>
        <p>DNCT ist ein polnischer Anbieter von Gameserver- und Webhosting, tätig als nicht eingetragene Tätigkeit. Dieses Dokument beschreibt die Regeln zu Vertrag, Zahlungen, Reklamationen und Datenschutz. Die aktuellen Angebotsdaten stehen in der Preisliste und in der Bestellbestätigung.</p>
    </div>

      <h1>Allgemeine Geschäftsbedingungen - DNCT</h1>
      <p><strong>Inkrafttreten:</strong> 7. Februar 2026<br>
      <strong>Aktualisiert:</strong> 4. Oktober 2026 - Regeln zum Start des Dienstes, Widerrufsrecht und freiwillige 24-h-Geld-zurück-Garantie. Für neue Bestellungen gilt diese Fassung ab Vertragsschluss. Für bestehende Kunden gelten mitteilungspflichtige Änderungen nach Zustellung der Information auf einem dauerhaften Datenträger und nach Ablauf der in Punkt 14 genannten Frist.</p>
      <p>Dieses Dokument regelt die Bedingungen, unter denen DNCT (im Folgenden: „DNCT“, „Anbieter“) seine Dienste erbringt. Mit der Nutzung der Dienste akzeptiert der Kunde die folgenden Bestimmungen.</p>

      <section>
        <h2>1. Angaben zum Anbieter</h2>
        <p>
          <strong>DNCT</strong> - nicht eingetragene Tätigkeit, betrieben von Aleks Osiński gemäß <strong>Art. 5 des polnischen Unternehmergesetzes</strong>.<br>
          Postanschrift: Puławy, Woiwodschaft Lublin, 24-100, Polen.<br>
          Telefon: +48 536 180 706<br>
          Kontakt-E-Mail: <a href="mailto:support@dnct.pl">support@dnct.pl</a>
        </p>
      </section>

      <section>
        <h2>2. Leistungsumfang und technische Spezifikation</h2>
        <ol>
          <li>DNCT erbringt Gameserver-Hosting (Minecraft, Vintage Story, Project Zomboid und weitere auf Anfrage) sowie Webhosting mit E-Mail und Datenbanken (Details in Punkt 15).</li>
          <li>Detaillierte Pakete und Preise unter: <a href="https://game.dnct.pl/cennik">game.dnct.pl/cennik</a>. Bei Fragen zu einem bestimmten Paket frag einfach unseren Support!</li>
          <div class="tech-box">
            <strong>Technische Information:</strong>
            <ul>
              <li>Parameter, Einschränkungen und Preis des gewählten Pakets werden vor der Zahlung und in der Bestellbestätigung bestätigt.</li>
              <li>Technischer Support erfolgt per E-Mail und über das Discord-Ticketsystem.</li>
            </ul>
          </div>
        </ol>
      </section>

    <section>
        <h2>3. Preisliste Minecraft (PLN)</h2>
        <div class="table-container">
          <table>
            <thead>
              <tr><th>Paket</th><th>RAM</th><th>CPU</th><th>SSD</th><th>Backups</th><th>Woche</th><th>Monat</th><th>Jahr</th></tr>
            </thead>
            <tbody>
HTML;
$de2 = <<<'HTML'
            </tbody>
          </table>
        </div>
      </section>

      <section>
        <h2>4. Preisliste Project Zomboid &amp; Vintage Story (PLN)</h2>
        <div class="table-container">
          <table>
            <thead>
              <tr><th>Slots</th><th>RAM</th><th>CPU</th><th>SSD</th><th>Backups</th><th>Woche</th><th>Monat</th><th>Jahr</th></tr>
            </thead>
            <tbody>
HTML;
$de3 = <<<'HTML'
            </tbody>
          </table>
          <p class="small">*Die Tabelle oben zeigt ausgewählte Pakete. Das volle Angebot ist im Panel verfügbar.</p>
        </div>
      </section>

      <section>
        <h2>5. Vertrag, Registrierung und Demo-Konto</h2>
        <ol>
          <li>Der Vertrag kommt mit der Bezahlung der Bestellung zustande.</li>
          <li>Für die Registrierung sind E-Mail, Nick sowie Vor- und Nachname erforderlich. Die Angaben müssen wahr sein.</li>
          <li><strong>DEMO-Konto:</strong> Das Gamepanel kann ohne Registrierung unter <a href="https://game.dnct.pl/demo">game.dnct.pl/demo</a> ausprobiert werden. Das Demo-Konto dient ausschließlich zum Testen der Funktionen.</li>
          <li>Der Kunde muss volljährig sein oder die Zustimmung eines Erziehungsberechtigten haben.</li>
        </ol>
      </section>

      <section id="platnosci-i-odstapienie">
        <h2>6. Zahlungen, Start des Dienstes und Widerruf</h2>
        <ol>
          <li>Zahlungen wickelt Stripe ab. Die verfügbaren Zahlungsarten werden vor der Zahlung in Stripe Checkout angezeigt.</li>
          <li>DNCT ist eine nicht eingetragene Tätigkeit und kein aktiver Umsatzsteuerzahler. Auf Wunsch des Kunden stellt DNCT eine Verkaufsbestätigung gemäß geltendem Recht aus.</li>
          <li>Der Dienst kann <strong>einmalig</strong> (manuelle Verlängerung) oder im <strong>Abo</strong> bezahlt werden, das sich automatisch um den nächsten Zeitraum verlängert. Das Abo kann jederzeit im Kundenpanel gestoppt werden - der Dienst läuft dann bis zum Ende des bezahlten Zeitraums.</li>
          <li><strong>Widerrufsrecht des Verbrauchers:</strong> Ein Verbraucher kann einen Fernabsatzvertrag innerhalb von 14 Tagen nach Vertragsschluss widerrufen, indem er DNCT eine eindeutige Erklärung an <a href="mailto:support@dnct.pl">support@dnct.pl</a> sendet. Er kann das Formular in Punkt 6.9 verwenden, muss es aber nicht.</li>
          <li><strong>Wahl des Starttermins:</strong> Vor der Zahlung wählt der Kunde, ob er den sofortigen Start des Dienstes verlangt oder den Start nach Ablauf der Widerrufsfrist. Ohne Verlangen nach sofortigem Start ist der Kauf trotzdem möglich; DNCT startet den Dienst dann 14 Tage nach Vertragsschluss.</li>
          <li><strong>Sofortiger Start:</strong> DNCT beginnt mit der Leistung vor Ablauf der 14 Tage nur auf ausdrückliches Verlangen des Kunden im Bestellformular und nach seiner Bestätigung der Informationen über die Folgen der vollständigen Leistungserbringung. Widerruft der Verbraucher vor der vollständigen Erbringung, zahlt er nur für die bis zum Eingang des Widerrufs erbrachten Leistungen, anteilig zum gesamten Zeitraum. Nach vollständiger Erbringung kann das Widerrufsrecht nur erlöschen, wenn alle gesetzlichen Voraussetzungen erfüllt sind.</li>
          <li><strong>24-h-Geld-zurück-Garantie:</strong> Unabhängig von den gesetzlichen Rechten erstattet DNCT freiwillig den vollen Preis einer neuen Bestellung, wenn der Kunde dies innerhalb von 24 Stunden nach Zahlungsbestätigung per E-Mail oder Discord-Ticket beantragt. Die Garantie gilt nicht bei Betrug, Zahlungsmissbrauch oder Verstößen gegen Punkt 8 und 11. Nach 24 Stunden erlischt nur diese zusätzliche Garantie; Widerrufsrecht, Reklamation und andere gesetzliche Rechte des Verbrauchers bleiben unberührt.</li>
          <li><strong>Erstattung nach Widerruf:</strong> DNCT erstattet den fälligen Betrag unverzüglich, spätestens innerhalb der gesetzlichen Frist, grundsätzlich über dieselbe Zahlungsart, es sei denn, der Kunde stimmt ausdrücklich einer anderen Methode ohne zusätzliche Kosten zu.</li>
          <li><strong>Widerrufsformular:</strong><br>
            An: DNCT, Aleks Osiński, E-Mail: support@dnct.pl<br>
            Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag über die Erbringung folgender Dienstleistung: ........................................<br>
            Bestellnummer: ........................................<br>
            Datum des Vertragsschlusses: ........................................<br>
            Name des Verbrauchers: ........................................<br>
            Bei der Bestellung verwendete E-Mail-Adresse: ........................................<br>
            Datum: ........................................ &nbsp; Unterschrift (nur bei Papierform): ........................................<br>
            <span class="small">(*) Unzutreffendes streichen.</span>
          </li>
        </ol>
      </section>

      <section>
        <h2>7. SLA, Verfügbarkeit und Entschädigungen</h2>
        <p>DNCT gibt derzeit keine prozentuale Verfügbarkeitsgarantie. Ereignisse, die den Dienst beeinträchtigen, können gemäß Punkt 13 gemeldet werden; DNCT prüft sie einzeln und ergreift Abhilfemaßnahmen.</p>
        <p>Soweit das Gesetz dem Kunden bestimmte Rechtsbehelfe oder Entschädigungen gewährt, schränken diese AGB sie nicht ein. Geplante SLA-Regeln und automatische Entschädigungen können erst nach Einführung eines unabhängigen Monitorings und einer öffentlichen Verfügbarkeitsberichterstattung eingeführt werden.</p>
        <p>Der aktuelle Status der Dienste und der Verlauf der Ausfälle der letzten 90 Tage werden auf <a href="https://status.dnct.pl">status.dnct.pl</a> veröffentlicht. Die Messung erfolgt durch DNCT aus eigener Infrastruktur; die Seite dient nur der Information und ist weder eine Verfügbarkeitsgarantie noch Grundlage für eine automatische Entschädigung.</p>
      </section>

      <section>
        <h2>8. Disziplinarregeln (Sperren und Verwarnungen)</h2>
        <ol>
          <li>DNCT kann den Dienst sofort sperren, wenn DDoS-Angriffe, Betrug oder Sicherheitsbedrohungen festgestellt werden.</li>
          <li><strong>Verwarnungssystem:</strong> (1) → (2) → (3) = Kontosperre.</li>
          <li>Bei einer Rückbuchung (Chargeback) oder begründetem Betrugsverdacht kann DNCT den Dienst vorübergehend bis zur Klärung sperren. Die gesetzlichen Rechte des Verbrauchers, einschließlich Widerruf und Reklamation, bleiben unberührt.</li>
        </ol>
      </section>

      <section>
        <h2>9. Backups und Löschung von Daten</h2>
        <ol>
          <li><strong>Backups:</strong> Umfang, Anzahl und Aufbewahrung der Backups hängen vom gewählten Paket ab und sind im Angebot oder Panel angegeben. Backups sind eine Hilfe und ersetzen keine eigenen Sicherungen des Kunden.</li>
          <li>Der Kunde ist verpflichtet, eine eigene Kopie wichtiger Daten außerhalb von DNCT zu haben.</li>
          <li><strong>Kontolöschung:</strong> Der Kunde kann sein DNCT-Konto samt den Konten in den Diensten selbst in den Kontoeinstellungen löschen (<a href="https://account.dnct.pl/ustawienia#rodo">account.dnct.pl</a>) oder dies über Discord oder per E-Mail beantragen. Die Löschung ist möglich, wenn der Kunde keine aktiven Dienste und kein Abo hat. Die Bestellhistorie bewahren wir für die gesetzlich vorgeschriebene Dauer auf (Punkt 12).</li>
          <li><strong>Gameserver nach Fristablauf:</strong> Ein nicht bezahlter Server wird archiviert, gesperrt und anschließend gelöscht; ein Paket mit den Dateien wird dem Kunden zum Download bereitgestellt.</li>
        </ol>
      </section>

      <section>
        <h2>10. Sicherheit und Protokolle</h2>
        <ol>
          <li>System- und Verhaltensprotokolle werden zu Sicherheits- und Rechtszwecken 30 Tage aufbewahrt.</li>
          <li>Der Systemstatus ist für angemeldete Nutzer im Panel verfügbar.</li>
          <li>DNCT hat das Recht, den Datenverkehr in kritischen Situationen ohne Benachrichtigung vorübergehend (bis zu 72 h) einzuschränken.</li>
        </ol>
      </section>

      <section id="zgloszenia-nielegalnych-tresci-dsa">
        <h2>11. Meldung illegaler Inhalte und Sicherheit der Dienste</h2>
        <ol>
          <li>Es ist verboten, die Dienste zur Veröffentlichung oder Verbreitung illegaler Inhalte zu nutzen, insbesondere von Schadsoftware, Phishing, Betrug oder Inhalten, die Urheberrechte, die Privatsphäre oder Rechte Dritter verletzen.</li>
          <li><strong>Meldung:</strong> Jede Person kann möglicherweise illegale Inhalte kostenlos und ohne Konto über das <a href="/zglos-naruszenie">Meldeformular</a> melden oder, falls das Formular nicht verfügbar ist, per E-Mail an <a href="mailto:support@dnct.pl?subject=Abuse%20report">support@dnct.pl</a>. Die Meldung sollte den genauen Fundort des Inhalts, eine Begründung und Kontaktdaten für eine Antwort enthalten; Kontaktdaten sind bei Meldungen zum Kinderschutz nicht erforderlich.</li>
          <li><strong>Prüfung und Entscheidung:</strong> DNCT prüft Meldungen sorgfältig, objektiv und ohne unnötige Verzögerung. Je nach Fall kann DNCT weitere Informationen anfordern, den Zugang zu Inhalt oder Dienst einschränken, Material für Verfahren sichern oder keine Maßnahmen ergreifen. Eine feste Bearbeitungszeit garantieren wir nicht, da sie von der Vollständigkeit der Meldung und der Art des Risikos abhängt.</li>
          <li><strong>Begründung und Einspruch:</strong> Wo eine Begründung angebracht ist, senden wir sie an den Meldenden und den betroffenen Kunden. Ein Einspruch gegen die Entscheidung kann als Antwort auf die Nachricht mit der Fallnummer gesendet werden; er wird von einem Menschen geprüft.</li>
          <li><strong>Keine allgemeine Überwachung:</strong> DNCT ist nicht verpflichtet, alle Kundendaten allgemein zu überwachen, und tut dies nicht. Maßnahmen, die für die Sicherheit der Dienste, die Erfüllung gesetzlicher Pflichten oder die Reaktion auf eine glaubwürdige Meldung nötig sind, bleiben davon unberührt.</li>
          <li><strong>Urheberrecht:</strong> Meldungen zu Urheber- und Markenrechtsverletzungen bearbeiten wir nach dem obigen Verfahren. Dies ist weder das US-amerikanische DMCA-Verfahren noch ein Versprechen, Inhalte nach jeder Meldung automatisch zu entfernen.</li>
          <li><strong>Technischer Zugriff:</strong> Der Kunde stimmt zu, dass die Administration nur in dem Umfang auf Dateien zugreift, der für Diagnose, Backups, Sicherheit, die Erfüllung einer gesetzlichen Pflicht oder die Bearbeitung einer Meldung nötig ist.</li>
        </ol>
      </section>

      <section>
        <h2>12. DSGVO (Schutz personenbezogener Daten)</h2>
        <ol>
          <li>Verantwortlicher für die Daten des Kunden ist Aleks Osiński (DNCT). Details: <a href="/polityka-prywatnosci">Datenschutzerklärung</a>.</li>
          <li>Der Kunde hat das Recht auf Auskunft, Änderung, Übertragung und Löschung seiner personenbezogenen Daten - selbst in den Kontoeinstellungen oder auf Antrag.</li>
          <li>Daten, die der Kunde in den Diensten ablegt (Websites, Postfächer, Datenbanken, Serverdateien), verarbeitet DNCT im Auftrag des Kunden als <strong>Auftragsverarbeiter</strong> gemäß dem <a href="/umowa-powierzenia">Auftragsverarbeitungsvertrag</a> (Punkt 17).</li>
        </ol>
      </section>

      <section>
        <h2>13. Reklamationen und Haftung</h2>
        <ol>
          <li>Reklamationen können über ein Discord-Ticket oder per E-Mail eingereicht werden. Die Meldung sollte die Bestellnummer, eine Beschreibung des Problems und die erwartete Lösung enthalten. DNCT antwortet dem Verbraucher innerhalb von 14 Tagen nach Eingang der Reklamation, sofern das Gesetz eine solche Frist vorschreibt.</li>
          <li>Keine Bestimmung dieser AGB schließt die Rechte des Verbrauchers aus zwingenden Rechtsvorschriften aus oder schränkt sie ein. Im Übrigen ist die Haftung von DNCT auf die Gebühr für den Zeitraum begrenzt, auf den sich der Anspruch bezieht, soweit das Gesetz dies zulässt.</li>
          <li>DNCT haftet nicht für Fehler externer Software (Mods, Plugins).</li>
        </ol>
      </section>

      <section>
        <h2>14. Schlussbestimmungen</h2>
        <ol>
          <li>In nicht geregelten Fragen gilt polnisches Recht.</li>
          <li>Änderungen der AGB, die laufende Verträge betreffen, sendet DNCT dem Kunden auf einem dauerhaften Datenträger, insbesondere per E-Mail, mindestens 14 Tage im Voraus. Änderungen berühren weder erworbene Rechte noch zwingende Rechtsvorschriften.</li>
        </ol>
      </section>
      <section>
        <h2>15. Webhosting</h2>
        <ol>
          <li><strong>Pakete:</strong> Start, Pro und Business unterscheiden sich in Anzahl der Websites, Speicherplatz, Anzahl der Postfächer und Datenbanken. Aktuelle Parameter und Preise: <a href="https://web.dnct.pl">web.dnct.pl</a>. Der Abrechnungszeitraum beträgt 30 Tage.</li>
          <li><strong>Im Paket:</strong> Kundenpanel (<code>web.panel-dnct.pl</code>), SSL-Zertifikate, E-Mail mit Webmail (<code>poczta.dnct.pl</code>), MariaDB-Datenbanken, ein App-Installer (u. a. WordPress), Dateimanager, SFTP-Zugang und tägliche Backups mit 7 Tagen Aufbewahrung.</li>
          <li><strong>Adressen:</strong> Der Kunde kann eine eigene Domain oder eine kostenlose Adresse unter der Domain <code>dnct.pl</code> nutzen. Die kostenlose Adresse bleibt Eigentum von DNCT - sie läuft mit dem Dienst ab und kann bei Verstößen gegen die AGB entzogen werden. Irreführende Namen (z. B. Nachahmung von DNCT, Banken oder bekannten Marken) sind nicht erlaubt.</li>
          <li><strong>Faire Ressourcennutzung:</strong> Das Hosting ist geteilt. Ein einzelnes Skript hat Speicher- und Laufzeitlimits, das Konto CPU- und Speicherlimits. Eine Website, die den Server dennoch so belastet, dass andere Kunden behindert werden, kann vorübergehend eingeschränkt werden - wir informieren den Kunden darüber.</li>
          <li><strong>E-Mail:</strong> Verboten sind der Versand von Spam, Massenmailings ohne Zustimmung der Empfänger und Nachrichten, die sich als andere Stellen ausgeben. Postfächer haben je nach Paket Limits für Speicher und Versandmenge. Ein Verstoß führt zur sofortigen Versandsperre.</li>
          <li><strong>Verbotene Inhalte und Nutzungen:</strong> zusätzlich zu Punkt 11 - Kryptowährungs-Mining, Proxy-/VPN-Server, Netzwerkscanner, Speicherung websitefremder Dateien (z. B. Videoarchive) und urheberrechtsverletzende Inhalte.</li>
          <li><strong>Nach Fälligkeit:</strong> Nicht bezahltes Hosting wird gesperrt (Websites und E-Mail funktionieren nicht mehr, die Daten bleiben unverändert). Nach der Zahlung ist sofort alles wieder da. Ein länger als 14 Tage gesperrtes Konto kann DNCT nach vorheriger Benachrichtigung per E-Mail und Gelegenheit zum Download einer Kopie löschen.</li>
          <li><strong>Backups</strong> sind eine Hilfe - der Kunde ist für eine eigene Kopie wichtiger Daten verantwortlich (Punkt 9).</li>
          <li><strong>Paketwechsel</strong> während des Abrechnungszeitraums erfolgt auf Anfrage; die Preisdifferenz wird anteilig abgerechnet.</li>
        </ol>
      </section>

      <section>
        <h2>16. DNCT-Konto und Profil</h2>
        <ol>
          <li>Ein DNCT-Konto dient zum Login in allen Diensten (Website, Gamepanel, Hostingpanel). Bei der Registrierung oder beim ersten Login werden Konten in diesen Diensten angelegt.</li>
          <li>Der Kunde ist für die Sicherheit seines Passworts verantwortlich; die Aktivierung der Zwei-Faktor-Authentifizierung wird empfohlen. Die Änderung der E-Mail-Adresse erfordert eine Bestätigung mit einem an die neue Adresse gesendeten Code.</li>
          <li><strong>Das öffentliche Profil</strong> (<code>account.dnct.pl/u/…</code>) ist standardmäßig deaktiviert. Ein Kunde, der es aktiviert, ist für dessen Inhalte (Beschreibung, Bilder, Links) verantwortlich. DNCT kann das Profil ausblenden oder Inhalte entfernen, die gegen das Gesetz, die guten Sitten oder diese AGB verstoßen.</li>
        </ol>
      </section>

      <section>
        <h2>17. Auftragsverarbeitungsvertrag</h2>
        <ol>
          <li>Mit der Bestellung von Webhosting oder eines Gameservers schließt der Kunde mit DNCT den <a href="/umowa-powierzenia">Vertrag über die Auftragsverarbeitung personenbezogener Daten</a> (Art. 28 DSGVO), der fester Bestandteil dieser AGB ist.</li>
          <li>Der Vertrag umfasst die Daten, die der Kunde in den Diensten ablegt - einschließlich der Daten der Besucher seiner Websites und der Personen, die mit seinen Postfächern korrespondieren. Verantwortlicher für diese Daten bleibt der Kunde.</li>
        </ol>
      </section>
HTML;

return [
'en' => [
    'title' => 'Terms of Service - DNCT Hosting',
    'opis'  => 'DNCT Hosting Terms of Service: payments, withdrawal, refund guarantee, complaints, data protection and web hosting rules.',
    'tresc' => $en1 . $wierszeMc . $en2 . $wierszeSloty . $en3,
],
'de' => [
    'title' => 'AGB - DNCT Hosting',
    'opis'  => 'AGB von DNCT Hosting: Zahlungen, Widerruf, Geld-zurück-Garantie, Reklamationen, Datenschutz und Regeln für Webhosting.',
    'tresc' => $de1 . strtr($wierszeMc, ['>DEVeloper<' => '>DEVeloper<', '>Basic<' => '>Basis<', '>Advanced<' => '>Fortgeschritten<', '>Master<' => '>Meister<', '>LEGENDARY<' => '>LEGENDÄR<', '>LEGENDARY +<' => '>LEGENDÄR +<']) . $de2 . $wierszeSloty . $de3,
],
];
