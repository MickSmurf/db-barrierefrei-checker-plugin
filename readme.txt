DB Barrierefrei-Check

Ein WordPress-Plugin zur Prüfung der Barrierefreiheit deutscher Bahnhöfe, basierend auf der offiziellen StaDa (Station Data) API v2 der Deutschen Bahn. Besucher können per Shortcode nach einem Bahnhof suchen und sehen auf einen Blick, ob stufenloser Zugang, Mobilitätsservice, Taxistand und weitere Merkmale vorhanden sind.

Voraussetzungen
WordPress 6.5 oder neuer (getestet mit 7.1)
PHP 8.0 oder neuer
Ein kostenloser Zugang zum DB API Marketplace (für Client ID + Client Secret)
Installation
Diesen Ordner nach wp-content/plugins/db-barrierefrei-check/ kopieren
Im WordPress-Backend unter Plugins aktivieren
Unter Barrierefrei-Check → Einstellungen die API-Zugangsdaten eintragen (siehe unten)
Konfiguration
API-Zugangsdaten

Auf developers.deutschebahn.com registrieren, ein Produkt mit der StaDa-API abonnieren und Client ID sowie Client Secret im Plugin-Backend eintragen. Beide werden verschlüsselt (AES-256-CBC, Schlüssel abgeleitet aus AUTH_KEY + wp_salt()) in der Datenbank gespeichert, nie im Klartext.

Angezeigte Informationen

Im Backend lässt sich auswählen, welche Merkmale im Suchergebnis erscheinen, gruppiert in drei Kategorien:

Kategorie	Bedeutung
Kern	Direkt barrierefreiheitsrelevant (stufenloser Zugang, Mobilitätsservice, Taxistand, …)
Kontext	Relevant für mobilitätseingeschränkte Reisende, aber kein Ausschlusskriterium (ÖPNV-Anschluss, Parkplätze, Bahnhofsmission, …)
Luxus	Allgemeine Ausstattung ohne Bezug zu Mobilität/Gesundheit (WLAN, DB Lounge, Mietwagen, …)

Kern-Felder werden immer angezeigt, Kontext/Luxus stehen im Suchergebnis hinter einem "Weitere Informationen"-Aufklapper.

Verwendung

Shortcode auf einer beliebigen Seite oder in einem Beitrag platzieren:

[db_barrierefrei_check]

Optional mit eigener Überschrift:

[db_barrierefrei_check ueberschrift="Ist mein Bahnhof barrierefrei?"]

Besucher geben einen Bahnhofsnamen ein (Teilstring-Suche, z. B. "Biele" findet "Bielefeld Hbf"); bei vielen Treffern lassen sich über "Weitere Ergebnisse laden" zusätzliche Bahnhöfe nachladen (10 pro Ladevorgang).

Barrierefreiheit

Das Plugin selbst folgt WCAG 2.1 Level AA als Zielvorgabe – naheliegend bei einem Barrierefreiheits-Tool. Details und Testverfahren stehen in docs/ACCESSIBILITY-CHECKLIST.md. Kurz zusammengefasst:

Status wird nie nur über Farbe vermittelt: jedes Ergebnis zeigt Icon + Farbe + Text (wichtig bei Rot-Grün-Sehschwäche)
Vollständige Tastaturbedienbarkeit, sichtbare Fokus-Indikatoren
Semantisches HTML (<label>, <fieldset>, <details>), aria-live-Regionen für dynamische Suchergebnisse
Architektur
Kein Vue/React im Frontend – reines Vanilla JavaScript mit fetch(), DOM-Erzeugung über createElement()/textContent (keine innerHTML-Strings) zum Schutz vor XSS über API-Daten
Caching über die WordPress-Transients-API (Standard 6 Stunden, filterbar über db_barrierefrei_check_cache_ttl)
Ein zentraler Options-Eintrag (db_barrierefrei_check_settings) für alle Plugin-Einstellungen
Vollständige Activation/Deactivation/Uninstall-Hooks, inkl. Multisite-Unterstützung
Bekannte Einschränkungen
Der Bundesland-Filter (federalstate) der API wird aktuell nicht im Frontend angeboten – die von der API akzeptierten Werte für zusammengesetzte Bundesland-Namen (z. B. Nordrhein-Westfalen) waren nicht zuverlässig zu verifizieren. Die Suche funktioniert zuverlässig über den Bahnhofsnamen.
Die eingebettete szentrale-Information (3-S-Zentrale/Betriebsleitstelle) aus der API-Antwort wird aktuell nicht im Frontend dargestellt.
Lizenz

GPL v2 or later
