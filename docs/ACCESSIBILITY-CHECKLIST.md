# Accessibility-Checkliste – DB Barrierefrei-Check Plugin

Ziel: WCAG 2.1 Level AA als verbindliches Minimum, AAA-Kriterien wo technisch sinnvoll umsetzbar.
Referenz: https://www.w3.org/WAI/WCAG21/quickref/

Diese Liste ist nach den vier WCAG-Prinzipien (POUR) gegliedert und jedem Punkt ist zugeordnet,
in welcher Datei aus der Plugin-Struktur er umgesetzt wird.

---

## 1. Wahrnehmbar (Perceivable)

- [ ] **Farbkontrast Text**: mind. 4.5:1 (AA), Ziel 7:1 (AAA) für alle Badge-Texte und Fließtext
      → `public/css/frontend.css`
- [ ] **Status nie nur über Farbe**: "Ja"/"Nein"/"Vorhanden"/"Keine" bleibt immer als Text sichtbar,
      nicht nur als Farbcode
      → `public/js/frontend.js` (Badge-Rendering-Funktionen)
- [ ] **Kontrast Fokus-Indikator**: mind. 3:1 gegenüber Hintergrund
      → `public/css/frontend.css`
- [ ] **Responsive / Zoom**: Inhalt bleibt bis 200% Browser-Zoom ohne horizontalen Scroll nutzbar
      → `public/css/frontend.css`, `admin/css/admin.css`
- [ ] **Kein reiner Sinneseindruck** ("die grüne Karte" o.ä.) in Texten oder Tooltips verwenden
      → `public/views/shortcode-template.php`

## 2. Bedienbar (Operable)

- [ ] **Volle Tastaturbedienbarkeit**: Suchformular, Filter-Auswahl, Submit-Button ohne Maus nutzbar
      → `public/js/frontend.js`, `public/views/shortcode-template.php`
- [ ] **Sichtbarer Fokus-Ring**: kein `outline: none` ohne gleichwertigen Ersatz
      → `public/css/frontend.css`
- [ ] **Logische Tab-Reihenfolge**: folgt der DOM-Reihenfolge, kein manuelles `tabindex` > 0
      → `public/views/shortcode-template.php`
- [ ] **Skip-Link** falls das Widget viele Ergebnisse liefert (z. B. "Zu den Suchergebnissen springen")
      → `public/views/shortcode-template.php`
- [ ] **Keine Tastaturfallen**: Fokus kann jedes Element verlassen (relevant falls später ein Modal/
      Dialog für Detailansichten dazukommt)
      → `public/js/frontend.js`

## 3. Verständlich (Understandable)

- [ ] **Echte `<label for="...">`** für jedes Formularfeld, Placeholder nur als Zusatz, nie als Ersatz
      → `public/views/shortcode-template.php`
- [ ] **`<fieldset>` + `<legend>`** wenn mehrere zusammengehörige Filter angezeigt werden
      (z. B. Suchtyp-Auswahl je nach aktivierten Feldern)
      → `public/views/shortcode-template.php`
- [ ] **Fehlermeldungen programmatisch verknüpft**: `aria-describedby` zeigt auf die Fehlermeldung,
      Meldung ist klar formuliert ("Bitte einen Bahnhofsnamen eingeben" statt nur rot markiert)
      → `public/js/frontend.js`
- [ ] **Konsistente Struktur** der Ergebniskarten – gleiche Feld-Reihenfolge bei jeder Karte
      → `public/js/frontend.js` (renderCard-Funktion)
- [ ] **`lang="de"`** korrekt gesetzt (i. d. R. durch WordPress-Theme automatisch, prüfen)
      → Theme-Ebene, nicht Plugin, aber vor Release verifizieren

## 4. Robust

- [ ] **Semantisches HTML statt reiner `<div>`-Struktur**:
      `<ul role="list">` / `<li>` für die Ergebniskarten statt `<div>`-Grid ohne Listensemantik
      → `public/views/shortcode-template.php`, `public/js/frontend.js`
- [ ] **Überschriften-Hierarchie korrekt**: z. B. `<h2>` "Suchergebnisse" → `<h3>` je Bahnhofsname,
      keine Ebene überspringen
      → `public/js/frontend.js`
- [ ] **ARIA nur ergänzend, nicht als Ersatz** für native HTML-Semantik ("No ARIA is better than bad ARIA")
      → durchgängig
- [ ] **`aria-live="polite"`** auf dem Ergebnis-Container, damit neue Suchergebnisse und die
      Trefferzahl ("8 Bahnhof/Bahnhöfe gefunden") automatisch vom Screenreader angesagt werden
      → `public/views/shortcode-template.php`
- [ ] **`aria-busy="true"`** während des AJAX-Requests gesetzt, danach wieder entfernt;
      zusätzlich sichtbarer Ladehinweis (nicht nur visueller Spinner)
      → `public/js/frontend.js`
- [ ] **Gültiges HTML**: Struktur validiert (z. B. via W3C Validator)
      → `public/views/shortcode-template.php`

---

## Admin-Bereich

- [ ] Eigene Einstellungsfelder (API-Keys, Feld-Kategorien-Checkboxen) folgen denselben Label-/
      Fieldset-Regeln wie das Frontend-Formular
      → `admin/views/settings-page.php`, `admin/views/felder-auswahl.php`
- [ ] WordPress-Standard-Admin-Komponenten bevorzugen (`register_setting`, Standard-Checkbox-Markup),
      da diese bereits barrierefrei-getestet sind, statt eigene Custom-Controls zu bauen

---

## Spezifisch für die drei Feld-Kategorien (Kern / Kontext / Luxus)

- [ ] Kategorie-Gruppierung im Admin-Formular mit `<fieldset><legend>Kern</legend>...</fieldset>` usw.,
      damit Screenreader-Nutzer die Gruppierung ebenfalls wahrnehmen
      → `admin/views/felder-auswahl.php`
- [ ] Im Frontend: Freitext-Felder (`hasMobilityService`, `mobilityServiceStaff.meetingPoint`) klar
      als ergänzende Information ausgezeichnet, nicht nur visuell kleiner/grau dargestellt
      → `public/js/frontend.js`

---

## Testing vor Release

- [ ] **Automatisiert**: axe DevTools oder WAVE Browser-Extension auf Such-Seite + Admin-Settings-Seite
      (findet erfahrungsgemäß nur ca. 30–40% der tatsächlichen Barrieren – kein Ersatz für manuelle Tests)
- [ ] **Tastatur-only-Durchlauf**: komplette Suche ohne Maus durchführen (Formular ausfüllen,
      absenden, Ergebnisse erreichen)
- [ ] **Screenreader-Test**: mindestens ein Durchlauf mit NVDA (Windows, kostenlos, Praxisstandard
      in Deutschland); optional zusätzlich VoiceOver (Mac/iOS) und TalkBack (Android)
- [ ] **Zoom-Test**: Seite bei 200% Browser-Zoom prüfen
- [ ] **Farbkontrast-Check**: z. B. mit WebAIM Contrast Checker für alle Badge-Farbkombinationen

---

## Rechtliche Einordnung (Deutschland)

- **WCAG 2.1/2.2 AA** ist der internationale Referenzstandard
- **BITV 2.0** ist die deutsche gesetzliche Umsetzung, bindend für öffentliche Stellen
- Das **Barrierefreiheitsstärkungsgesetz (BFSG)**, seit Juni 2025 wirksam, erweitert die Pflicht auf
  viele digitale Produkte/Dienstleistungen auch privater Anbieter – relevant, falls das Plugin
  kommerziell an Kunden vertrieben wird. Im Zweifel juristisch prüfen lassen, ob/wie das BFSG auf
  den konkreten Anwendungsfall zutrifft; diese Checkliste ersetzt keine Rechtsberatung.
