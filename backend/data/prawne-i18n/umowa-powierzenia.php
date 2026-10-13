<?php
/* Tłumaczenie /umowa-powierzenia (backend/lib/i18n.php, i18n_dokument).
   Informacyjne - wiąże wersja polska w umowa-powierzenia.php. Zmiana tamtej
   wersji = zmiana tego pliku. */
declare(strict_types=1);

return [
'en' => [
    'title' => 'Data processing agreement - DNCT Hosting',
    'opis'  => 'Personal data processing agreement (Art. 28 GDPR) for DNCT web hosting and game server customers.',
    'tresc' => <<<'HTML'
    <h1>Personal data processing agreement</h1>
    <p><strong>Version:</strong> 1.0, 29 September 2026. Forms part of the <a href="/regulamin">Terms of Service</a> (section 17).</p>
    <p>This agreement is concluded between the Customer (<strong>the "Controller"</strong>) and DNCT - Aleks Osiński, unregistered business activity, Puławy, Poland (<strong>the "Processor"</strong>) - when a web hosting or game server service is ordered, under Art. 28(3) of Regulation (EU) 2016/679 (GDPR).</p>

    <section>
        <h2>1. Subject matter and duration</h2>
        <ol>
            <li>The Controller entrusts the Processor with processing personal data to the extent necessary to provide the hosting services (storage, making data available over the network, backups, email handling).</li>
            <li>The agreement applies for as long as the service is provided and for the time needed to delete the data after it ends (section 9).</li>
        </ol>
    </section>

    <section>
        <h2>2. Nature and purpose of processing</h2>
        <p>The processing is technical: data is stored on the Processor's servers and made available according to the configuration set by the Controller. The Processor <strong>does not decide</strong> on the purposes of processing and does not use the data for its own purposes.</p>
    </section>

    <section>
        <h2>3. Types of data and categories of data subjects</h2>
        <ol>
            <li><strong>Types of data:</strong> any data the Controller places in the service - in particular identification and contact data, email content, data in databases and files, IP addresses and website traffic logs.</li>
            <li><strong>Categories of data subjects:</strong> visitors to the Controller's websites, its customers, business partners, employees and correspondents, users of its game servers.</li>
            <li>The Controller should not place special categories of data (Art. 9 GDPR) in the service without adequate safeguards on its side; if it does, it is responsible for assessing whether the security measures described in section 6 are adequate.</li>
        </ol>
    </section>

    <section>
        <h2>4. Obligations of the Processor</h2>
        <ol>
            <li>It processes data only on documented instructions from the Controller - in particular the service configuration in the panel and the Terms of Service count as instructions - unless processing is required by law (in which case it informs the Controller, unless the law prohibits this).</li>
            <li>It ensures that persons with access to the data have committed themselves to confidentiality.</li>
            <li>It accesses the Controller's data only to the extent necessary to maintain the service, for diagnostics, security or at the Controller's request.</li>
            <li>It assists the Controller in fulfilling data subjects' rights (Art. 15-22 GDPR) insofar as this requires action on the infrastructure side.</li>
            <li>It assists in meeting the obligations under Art. 32-36 GDPR, taking into account the nature of processing and the information available.</li>
        </ol>
    </section>

    <section>
        <h2>5. Personal data breaches</h2>
        <p>The Processor notifies the Controller of a detected breach of the entrusted data <strong>without undue delay, no later than 48 hours</strong> after detecting it - to the account's email address - with a description of the breach, its likely consequences and the measures taken. Notifying the supervisory authority is the Controller's responsibility.</p>
    </section>

    <section>
        <h2>6. Security measures (Art. 32 GDPR)</h2>
        <ul>
            <li>servers in our own infrastructure in Poland, in a segregated network (zones behind a firewall), administrative access only via the internal network or VPN;</li>
            <li>HTTPS traffic encryption (SSL certificates) and encryption of mail connections where the other side supports it;</li>
            <li>isolation of customer accounts (separate system users, file access restrictions, resource limits);</li>
            <li>daily backups with 7-day retention, kept on a separate storage medium;</li>
            <li>DDoS protection and traffic filtering (Cloudflare);</li>
            <li>panel login with a DNCT account with optional two-factor authentication, security event log;</li>
            <li>regular security updates of the system and server software.</li>
        </ul>
    </section>

    <section>
        <h2>7. Sub-processing (sub-processors)</h2>
        <ol>
            <li>The Controller gives general consent to the use of the following sub-processors:
                <ul>
                    <li><strong>Cloudflare, Inc.</strong> (USA) - proxying website traffic, DDoS protection, DNS; transfers based on standard contractual clauses and the EU-US Data Privacy Framework.</li>
                </ul>
            </li>
            <li>Hosting, mail, databases and backups run on the Processor's servers - without any other providers.</li>
            <li>The Processor gives at least 14 days' notice of an intended addition or change of a sub-processor (by email or by amending this agreement). During that time the Controller may object and terminate the service with a refund of the unused fee.</li>
            <li>The Processor imposes on sub-processors data protection obligations no less strict than those under this agreement.</li>
        </ol>
    </section>

    <section>
        <h2>8. Audit and information</h2>
        <p>The Processor provides the Controller with the information necessary to demonstrate compliance with Art. 28 GDPR and allows audits - by answering written questions or, with a date agreed at least 14 days in advance, through an inspection at the Controller's expense, no more than once a year, without access to other customers' data.</p>
    </section>

    <section>
        <h2>9. End of processing</h2>
        <ol>
            <li>After the service ends, the Controller can download its data (file manager, SFTP, backup from the panel, mailbox export) until it is deleted.</li>
            <li>The Processor deletes the entrusted data after the service ends, according to the time limits in the Terms of Service (sections 9 and 15). Backups expire through rotation, no later than 7 days after the data is deleted from the service.</li>
            <li>The deletion obligation does not apply to data that the law requires to be retained.</li>
        </ol>
    </section>

    <section>
        <h2>10. Final provisions</h2>
        <ol>
            <li>The parties' liability is governed by the GDPR and the Terms of Service; matters not covered are governed by Polish law.</li>
            <li>In case of conflict between this agreement and the Terms of Service regarding data protection, this agreement prevails.</li>
            <li>Contact on data protection matters: <a href="mailto:support@dnct.pl">support@dnct.pl</a>.</li>
        </ol>
    </section>
HTML,
],
'de' => [
    'title' => 'Auftragsverarbeitungsvertrag - DNCT Hosting',
    'opis'  => 'Vertrag über die Verarbeitung personenbezogener Daten (Art. 28 DSGVO) für Kunden von Webhosting und Gameservern bei DNCT.',
    'tresc' => <<<'HTML'
    <h1>Vertrag über die Auftragsverarbeitung personenbezogener Daten</h1>
    <p><strong>Version:</strong> 1.0, 29. September 2026. Bestandteil der <a href="/regulamin">AGB</a> (Punkt 17).</p>
    <p>Der Vertrag wird zwischen dem Kunden (<strong>„Verantwortlicher“</strong>) und DNCT - Aleks Osiński, nicht eingetragene Tätigkeit, Puławy, Polen (<strong>„Auftragsverarbeiter“</strong>) - mit der Bestellung eines Webhosting- oder Gameserver-Dienstes geschlossen, auf Grundlage von Art. 28 Abs. 3 der Verordnung (EU) 2016/679 (DSGVO).</p>

    <section>
        <h2>1. Gegenstand und Dauer</h2>
        <ol>
            <li>Der Verantwortliche überträgt dem Auftragsverarbeiter die Verarbeitung personenbezogener Daten in dem Umfang, der zur Erbringung der Hosting-Dienste nötig ist (Speicherung, Bereitstellung über das Netz, Backups, E-Mail-Betrieb).</li>
            <li>Der Vertrag gilt für die Dauer der Dienstleistung und für die Zeit, die zur Löschung der Daten nach deren Ende nötig ist (Punkt 9).</li>
        </ol>
    </section>

    <section>
        <h2>2. Art und Zweck der Verarbeitung</h2>
        <p>Die Verarbeitung ist technischer Natur: Die Daten werden auf den Servern des Auftragsverarbeiters gespeichert und entsprechend der vom Verantwortlichen festgelegten Konfiguration bereitgestellt. Der Auftragsverarbeiter <strong>entscheidet nicht</strong> über die Zwecke der Verarbeitung und nutzt die Daten nicht für eigene Zwecke.</p>
    </section>

    <section>
        <h2>3. Datenarten und Kategorien betroffener Personen</h2>
        <ol>
            <li><strong>Datenarten:</strong> alle Daten, die der Verantwortliche im Dienst ablegt - insbesondere Identifikations- und Kontaktdaten, Inhalte von E-Mails, Daten in Datenbanken und Dateien, IP-Adressen und Zugriffsprotokolle von Websites.</li>
            <li><strong>Kategorien betroffener Personen:</strong> Besucher der Websites des Verantwortlichen, seine Kunden, Geschäftspartner, Mitarbeiter und Korrespondenzpartner, Nutzer seiner Gameserver.</li>
            <li>Der Verantwortliche sollte keine besonderen Kategorien von Daten (Art. 9 DSGVO) ohne geeignete Schutzmaßnahmen auf seiner Seite im Dienst ablegen; tut er es, ist er für die Bewertung verantwortlich, ob die in Punkt 6 beschriebenen Maßnahmen angemessen sind.</li>
        </ol>
    </section>

    <section>
        <h2>4. Pflichten des Auftragsverarbeiters</h2>
        <ol>
            <li>Er verarbeitet Daten nur auf dokumentierte Weisung des Verantwortlichen - als Weisung gelten insbesondere die Konfiguration des Dienstes im Panel und die AGB -, es sei denn, eine Verarbeitungspflicht ergibt sich aus dem Gesetz (dann informiert er den Verantwortlichen, sofern das Gesetz dies nicht verbietet).</li>
            <li>Er stellt sicher, dass Personen mit Zugang zu den Daten zur Vertraulichkeit verpflichtet sind.</li>
            <li>Er greift auf Daten des Verantwortlichen nur im Umfang zu, der für den Betrieb des Dienstes, die Diagnose, die Sicherheit oder auf Bitte des Verantwortlichen nötig ist.</li>
            <li>Er unterstützt den Verantwortlichen bei der Wahrung der Rechte betroffener Personen (Art. 15-22 DSGVO), soweit dies ein Handeln auf Seiten der Infrastruktur erfordert.</li>
            <li>Er unterstützt bei der Erfüllung der Pflichten aus Art. 32-36 DSGVO unter Berücksichtigung der Art der Verarbeitung und der verfügbaren Informationen.</li>
        </ol>
    </section>

    <section>
        <h2>5. Verletzungen des Schutzes personenbezogener Daten</h2>
        <p>Der Auftragsverarbeiter meldet dem Verantwortlichen eine festgestellte Verletzung des Schutzes der anvertrauten Daten <strong>unverzüglich, spätestens innerhalb von 48 Stunden</strong> nach Feststellung - an die E-Mail-Adresse des Kontos - mit einer Beschreibung der Verletzung, ihrer wahrscheinlichen Folgen und der ergriffenen Maßnahmen. Die Meldung an die Aufsichtsbehörde obliegt dem Verantwortlichen.</p>
    </section>

    <section>
        <h2>6. Sicherheitsmaßnahmen (Art. 32 DSGVO)</h2>
        <ul>
            <li>Server in eigener Infrastruktur in Polen, in einem abgetrennten Netz (Zonen hinter einer Firewall), administrativer Zugang nur über das interne Netz oder VPN;</li>
            <li>Verschlüsselung des Datenverkehrs per HTTPS (SSL-Zertifikate) und Verschlüsselung von Mailverbindungen, sofern die Gegenseite sie unterstützt;</li>
            <li>Isolierung der Kundenkonten (eigene Systembenutzer, eingeschränkter Dateizugriff, Ressourcenlimits);</li>
            <li>tägliche Backups mit 7 Tagen Aufbewahrung auf einem separaten Datenträger;</li>
            <li>DDoS-Schutz und Traffic-Filterung (Cloudflare);</li>
            <li>Login ins Panel mit DNCT-Konto und optionaler Zwei-Faktor-Authentifizierung, Protokoll von Sicherheitsereignissen;</li>
            <li>regelmäßige Sicherheitsupdates von System und Serversoftware.</li>
        </ul>
    </section>

    <section>
        <h2>7. Unterauftragsverarbeitung (Unterauftragsverarbeiter)</h2>
        <ol>
            <li>Der Verantwortliche erteilt seine allgemeine Zustimmung zum Einsatz folgender Unterauftragsverarbeiter:
                <ul>
                    <li><strong>Cloudflare, Inc.</strong> (USA) - Vermittlung des Datenverkehrs zu Websites, DDoS-Schutz, DNS; Übermittlung auf Grundlage der Standardvertragsklauseln und des EU-US Data Privacy Framework.</li>
                </ul>
            </li>
            <li>Hosting, Mail, Datenbanken und Backups laufen auf Servern des Auftragsverarbeiters - ohne Beteiligung anderer Anbieter.</li>
            <li>Über die beabsichtigte Hinzufügung oder Änderung eines Unterauftragsverarbeiters informiert der Auftragsverarbeiter mindestens 14 Tage im Voraus (per E-Mail oder durch Änderung dieses Vertrags). Der Verantwortliche kann in dieser Zeit widersprechen und den Dienst mit Erstattung der nicht genutzten Gebühr kündigen.</li>
            <li>Der Auftragsverarbeiter verpflichtet Unterauftragsverarbeiter zu Datenschutzpflichten, die nicht geringer sind als die aus diesem Vertrag.</li>
        </ol>
    </section>

    <section>
        <h2>8. Audit und Informationen</h2>
        <p>Der Auftragsverarbeiter stellt dem Verantwortlichen die Informationen zur Verfügung, die zum Nachweis der Pflichten aus Art. 28 DSGVO nötig sind, und ermöglicht Audits - durch Beantwortung schriftlicher Fragen oder nach Terminabsprache (mindestens 14 Tage im Voraus) durch eine Inspektion auf Kosten des Verantwortlichen, höchstens einmal im Jahr, ohne Zugang zu Daten anderer Kunden.</p>
    </section>

    <section>
        <h2>9. Ende der Verarbeitung</h2>
        <ol>
            <li>Nach Ende des Dienstes kann der Verantwortliche seine Daten (Dateimanager, SFTP, Backup aus dem Panel, Export der Postfächer) bis zu ihrer Löschung herunterladen.</li>
            <li>Der Auftragsverarbeiter löscht die anvertrauten Daten nach Ende des Dienstes gemäß den Fristen der AGB (Punkte 9 und 15). Backups laufen im Rahmen der Rotation ab, spätestens 7 Tage nach der Löschung der Daten aus dem Dienst.</li>
            <li>Die Löschpflicht gilt nicht für Daten, deren Aufbewahrung gesetzlich vorgeschrieben ist.</li>
        </ol>
    </section>

    <section>
        <h2>10. Schlussbestimmungen</h2>
        <ol>
            <li>Die Haftung der Parteien richtet sich nach der DSGVO und den AGB; in nicht geregelten Fragen gilt polnisches Recht.</li>
            <li>Bei Widersprüchen zwischen diesem Vertrag und den AGB im Bereich Datenschutz hat dieser Vertrag Vorrang.</li>
            <li>Kontakt in Datenschutzfragen: <a href="mailto:support@dnct.pl">support@dnct.pl</a>.</li>
        </ol>
    </section>
HTML,
],
];
