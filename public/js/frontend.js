/**
 * Frontend-Logik für [db_barrierefrei_check].
 *
 * Erwartet window.dbCheckAjax (per wp_localize_script bereitgestellt):
 *   { url, nonce, fields: { fieldKey: { label, typ } }, i18n: {...} }
 *
 * Bewusst ohne Framework (kein Vue/React), reines Vanilla JS mit
 * fetch(). DOM-Erzeugung durchgängig über createElement()/textContent
 * statt innerHTML-Strings, damit von der API gelieferte Texte (z.B.
 * Freitext bei hasMobilityService) niemals als HTML interpretiert
 * werden können.
 */
( function () {
    'use strict';

    /**
     * Startet erst, wenn sowohl DOM als auch die lokalisierten Daten
     * bereitstehen. Ohne dbCheckAjax (z.B. Skript aus Cache-Fehler
     * ohne Localize-Daten geladen) bricht die Initialisierung
     * kontrolliert ab, statt Fehler in der Konsole zu werfen.
     */
    function onReady( fn ) {
        if ( 'loading' !== document.readyState ) {
            fn();
        } else {
            document.addEventListener( 'DOMContentLoaded', fn );
        }
    }

    function init() {
        if ( 'undefined' === typeof window.dbCheckAjax ) {
            return;
        }

        var widgets = document.querySelectorAll( '.db-barrierefrei-check-widget' );
        widgets.forEach( setupWidget );
    }

    /**
     * Richtet ein einzelnes Widget ein (mehrere Shortcode-Instanzen auf
     * derselben Seite werden unabhängig voneinander behandelt).
     *
     * Jedes Widget führt seinen eigenen kleinen Zustand (state):
     * aktueller Suchbegriff, Anzahl bereits geladener Treffer und die
     * von der API gemeldete Gesamttrefferzahl. Das genügt, um beim
     * Klick auf "Weitere Ergebnisse laden" den richtigen offset zu
     * berechnen (= Anzahl bereits geladener Karten), ohne die
     * Seitengröße selbst im JS kennen zu müssen – die legt allein der
     * AJAX-Handler serverseitig fest.
     */
    function setupWidget( widget ) {
        var form            = widget.querySelector( '[data-db-check-form]' );
        var statusText      = widget.querySelector( '.db-check-status-text' );
        var resultsList     = widget.querySelector( '.db-check-results' );
        var errorEl         = widget.querySelector( '.db-check-error' );
        var loadMoreButton  = widget.querySelector( '[data-db-check-load-more]' );

        if ( ! form || ! resultsList ) {
            return;
        }

        var state = {
            searchstring: '',
            loadedCount: 0,
            total: 0,
        };

        form.addEventListener( 'submit', function ( event ) {
            event.preventDefault();

            var searchstring = form.querySelector( '[name="searchstring"]' ).value.trim();

            hideError( errorEl );

            if ( '' === searchstring ) {
                showError( errorEl, window.dbCheckAjax.i18n.requiredFieldsMissing );
                return;
            }

            state.searchstring = searchstring;
            state.loadedCount  = 0;
            state.total        = 0;

            resultsList.textContent = '';
            setLoadMoreVisibility( loadMoreButton, false );

            performSearch( state, 0, {
                statusText: statusText,
                resultsList: resultsList,
                errorEl: errorEl,
                loadMoreButton: loadMoreButton,
                append: false,
            } );
        } );

        if ( loadMoreButton ) {
            loadMoreButton.addEventListener( 'click', function () {
                performSearch( state, state.loadedCount, {
                    statusText: statusText,
                    resultsList: resultsList,
                    errorEl: errorEl,
                    loadMoreButton: loadMoreButton,
                    append: true,
                } );
            } );
        }
    }

    /**
     * Führt einen einzelnen Suchdurchlauf aus (erste Suche oder
     * "weitere Ergebnisse laden", je nach options.append) und
     * aktualisiert Zustand, Statusanzeige, Ergebnisliste und
     * Load-More-Button.
     *
     * @param {object} state   Widget-Zustand (wird mutiert).
     * @param {number} offset  An den AJAX-Handler übergebener Offset.
     * @param {object} options { statusText, resultsList, errorEl, loadMoreButton, append }
     */
    function performSearch( state, offset, options ) {
        var statusText     = options.statusText;
        var resultsList     = options.resultsList;
        var errorEl         = options.errorEl;
        var loadMoreButton  = options.loadMoreButton;
        var append          = options.append;

        hideError( errorEl );
        setLoadingState( statusText, resultsList, true, append );
        setLoadMoreBusy( loadMoreButton, true );

        var formData = new FormData();
        formData.append( 'action', 'db_check_suche' );
        formData.append( 'nonce', window.dbCheckAjax.nonce );
        formData.append( 'searchstring', state.searchstring );
        formData.append( 'offset', String( offset ) );

        fetch( window.dbCheckAjax.url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        } )
            .then( function ( response ) {
                return response.json().then( function ( json ) {
                    return { ok: response.ok, json: json };
                } );
            } )
            .then( function ( result ) {
                setLoadingState( statusText, resultsList, false, append );
                setLoadMoreBusy( loadMoreButton, false );

                if ( ! result.ok || ! result.json || ! result.json.success ) {
                    var message = ( result.json && result.json.data && result.json.data.message )
                        ? result.json.data.message
                        : window.dbCheckAjax.i18n.errorGeneric;
                    showError( errorEl, message );

                    if ( ! append ) {
                        renderResults( resultsList, [], false );
                        updateStatusText( statusText, 0, 0 );
                    }

                    setLoadMoreVisibility( loadMoreButton, false );
                    return;
                }

                var data     = result.json.data || {};
                var stations = Array.isArray( data.result ) ? data.result : [];
                var total    = ( 'number' === typeof data.total ) ? data.total : stations.length;

                renderResults( resultsList, stations, append );

                state.loadedCount += stations.length;
                state.total = total;

                updateStatusText( statusText, state.loadedCount, state.total );
                setLoadMoreVisibility( loadMoreButton, state.loadedCount < state.total );
            } )
            .catch( function () {
                setLoadingState( statusText, resultsList, false, append );
                setLoadMoreBusy( loadMoreButton, false );
                showError( errorEl, window.dbCheckAjax.i18n.errorGeneric );

                if ( ! append ) {
                    renderResults( resultsList, [], false );
                    updateStatusText( statusText, 0, 0 );
                }

                setLoadMoreVisibility( loadMoreButton, false );
            } );
    }

    /**
     * @param {boolean} append true = "weitere Ergebnisse laden"
     *                         (Button-Ladezustand, Liste bleibt
     *                         sichtbar), false = neue Suche
     *                         (aria-busy auf der ganzen Liste).
     */
    function setLoadingState( statusText, resultsList, isLoading, append ) {
        if ( ! append ) {
            resultsList.setAttribute( 'aria-busy', isLoading ? 'true' : 'false' );
        }

        if ( isLoading && ! append ) {
            statusText.textContent = window.dbCheckAjax.i18n.loading;
        }
    }

    function setLoadMoreBusy( loadMoreButton, isBusy ) {
        if ( ! loadMoreButton ) {
            return;
        }
        loadMoreButton.disabled = isBusy;
        loadMoreButton.setAttribute( 'aria-busy', isBusy ? 'true' : 'false' );
    }

    function setLoadMoreVisibility( loadMoreButton, isVisible ) {
        if ( ! loadMoreButton ) {
            return;
        }
        loadMoreButton.hidden = ! isVisible;
    }

    function showError( errorEl, message ) {
        if ( ! errorEl ) {
            return;
        }
        errorEl.textContent = message;
        errorEl.hidden = false;
    }

    function hideError( errorEl ) {
        if ( ! errorEl ) {
            return;
        }
        errorEl.textContent = '';
        errorEl.hidden = true;
    }

    /**
     * Setzt den Text im aria-live-Bereich passend zu geladener Anzahl
     * und Gesamttreffern. Wird sowohl nach der ersten Suche als auch
     * nach jedem "weitere Ergebnisse laden" aufgerufen.
     */
    function updateStatusText( statusText, loadedCount, total ) {
        var i18n = window.dbCheckAjax.i18n;

        if ( 0 === total ) {
            statusText.textContent = i18n.noResults;
            return;
        }

        if ( loadedCount >= total ) {
            statusText.textContent = 1 === total
                ? i18n.resultCountSingular
                : i18n.resultCountPlural.replace( '%d', String( total ) );
            return;
        }

        statusText.textContent = i18n.loadedStatus
            .replace( '%1$d', String( loadedCount ) )
            .replace( '%2$d', String( total ) );
    }

    /**
     * Rendert Ergebniskarten. Bei append=false wird die Liste vorher
     * geleert (neue Suche), bei append=true werden die Karten an die
     * bestehende Liste angehängt ("weitere Ergebnisse laden").
     */
    function renderResults( resultsList, stations, append ) {
        if ( ! append ) {
            resultsList.textContent = '';
        }

        stations.forEach( function ( station ) {
            resultsList.appendChild( buildStationCard( station ) );
        } );
    }

    /**
     * Baut eine einzelne Ergebniskarte als <li>: Kopfzeile (Name/
     * Bundesland), darunter die Kern-Felder als sichtbares Mini-Grid,
     * darunter Kontext-/Luxus-Felder gebündelt in einem <details>-
     * Aufklapper (nativ tastaturbedienbar und für Screenreader korrekt
     * als ein-/ausgeklappt annonciert, ganz ohne eigenes ARIA-Handling).
     */
    function buildStationCard( station ) {
        var li = document.createElement( 'li' );
        li.className = 'db-check-card';

        var header = document.createElement( 'div' );
        header.className = 'db-check-card-header';

        var heading = document.createElement( 'h3' );
        heading.textContent = station.name || '';
        header.appendChild( heading );

        if ( station.federalState ) {
            var stateEl = document.createElement( 'span' );
            stateEl.className = 'db-check-card-state';
            stateEl.textContent = station.federalState;
            header.appendChild( stateEl );
        }

        li.appendChild( header );

        var byCategory = { kern: [], kontext: [], luxus: [] };
        var highlighted = false;

        Object.keys( window.dbCheckAjax.fields ).forEach( function ( fieldKey ) {
            var meta = window.dbCheckAjax.fields[ fieldKey ];
            var tile = buildFieldTile( fieldKey, meta, station[ fieldKey ] );

            if ( tile.isPositive ) {
                highlighted = true;
            }

            var kategorie = byCategory.hasOwnProperty( meta.kategorie ) ? meta.kategorie : 'luxus';
            byCategory[ kategorie ].push( tile.element );
        } );

        if ( byCategory.kern.length > 0 ) {
            var kernGrid = document.createElement( 'div' );
            kernGrid.className = 'db-check-field-grid';
            byCategory.kern.forEach( function ( tile ) {
                kernGrid.appendChild( tile );
            } );
            li.appendChild( kernGrid );
        }

        var restTiles = byCategory.kontext.concat( byCategory.luxus );

        if ( restTiles.length > 0 ) {
            var details = document.createElement( 'details' );
            details.className = 'db-check-details';

            var summary = document.createElement( 'summary' );
            summary.textContent = window.dbCheckAjax.i18n.moreInfo;
            details.appendChild( summary );

            var restGrid = document.createElement( 'div' );
            restGrid.className = 'db-check-field-grid';
            restTiles.forEach( function ( tile ) {
                restGrid.appendChild( tile );
            } );
            details.appendChild( restGrid );

            li.appendChild( details );
        }

        if ( highlighted ) {
            li.classList.add( 'db-check-card--positive' );
        }

        return li;
    }

    /**
     * Baut eine einzelne Feld-Kachel (eigene Box mit Hintergrund,
     * Label oben, Badge mit Icon+Text+Farbe darunter) statt einer
     * reinen Label-links/Wert-rechts-Zeile – verhindert, dass ein Wert
     * optisch mit dem Label des nächsten Feldes verschwimmt, weil jede
     * Kachel eine eigene, klar abgegrenzte Fläche ist.
     */
    function buildFieldTile( fieldKey, meta, value ) {
        var tile = document.createElement( 'div' );
        tile.className = 'db-check-tile';

        var labelEl = document.createElement( 'div' );
        labelEl.className = 'db-check-tile-label';
        labelEl.textContent = meta.label;
        tile.appendChild( labelEl );

        var rendered = renderValueByType( meta.typ, meta.kategorie, value );

        var badge = document.createElement( 'div' );
        badge.className = 'db-check-badge db-check-badge--' + rendered.tone;
        badge.appendChild( buildBadgeIcon( rendered.icon ) );

        var badgeText = document.createElement( 'span' );
        badgeText.textContent = rendered.text;
        badge.appendChild( badgeText );

        tile.appendChild( badge );

        if ( rendered.note ) {
            var note = document.createElement( 'div' );
            note.className = 'db-check-tile-note';
            note.textContent = rendered.note;
            tile.appendChild( note );
        }

        return { element: tile, isPositive: rendered.isPositive };
    }

    /**
     * Baut ein kleines Inline-SVG-Icon als zusätzliche, farbunabhängige
     * Kennzeichnung (Häkchen/Kreuz/Kreis) – wichtig für Rot-Grün-
     * Sehschwäche, damit der Status nicht nur über die Badge-Farbe
     * erkennbar ist. aria-hidden, da der Badge-Text die eigentliche,
     * für Screenreader relevante Information trägt.
     */
    function buildBadgeIcon( kind ) {
        var svgNs = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS( svgNs, 'svg' );
        svg.setAttribute( 'width', '12' );
        svg.setAttribute( 'height', '12' );
        svg.setAttribute( 'viewBox', '0 0 12 12' );
        svg.setAttribute( 'aria-hidden', 'true' );
        svg.setAttribute( 'class', 'db-check-badge-icon' );

        var path = document.createElementNS( svgNs, 'path' );
        path.setAttribute( 'fill', 'none' );
        path.setAttribute( 'stroke', 'currentColor' );
        path.setAttribute( 'stroke-width', '2' );
        path.setAttribute( 'stroke-linecap', 'round' );
        path.setAttribute( 'stroke-linejoin', 'round' );

        if ( 'check' === kind ) {
            path.setAttribute( 'd', 'M2 6l3 3 5-6' );
            svg.appendChild( path );
        } else if ( 'cross' === kind ) {
            path.setAttribute( 'd', 'M2 2l8 8M10 2l-8 8' );
            svg.appendChild( path );
        } else if ( 'partial' === kind ) {
            var circle = document.createElementNS( svgNs, 'circle' );
            circle.setAttribute( 'cx', '6' );
            circle.setAttribute( 'cy', '6' );
            circle.setAttribute( 'r', '4.5' );
            circle.setAttribute( 'fill', 'none' );
            circle.setAttribute( 'stroke', 'currentColor' );
            circle.setAttribute( 'stroke-width', '1.5' );
            svg.appendChild( circle );

            path.setAttribute( 'd', 'M6 3.5v3' );
            path.setAttribute( 'stroke-width', '1.5' );
            svg.appendChild( path );
        } else {
            // 'dash' – neutraler Zustand (z.B. unbekannter Zahlenwert).
            path.setAttribute( 'd', 'M2.5 6h7' );
            svg.appendChild( path );
        }

        return svg;
    }

    /**
     * Zentrale Typ- und Kategorie-zu-Darstellung-Logik.
     *
     * Die Farbintensität bei negativen Werten hängt von der Feld-
     * Kategorie ab (nicht nur vom Typ): ein fehlendes Kern-Merkmal
     * (z.B. kein Taxistand) ist direkt barrierefreiheitsrelevant und
     * wird rot markiert; ein fehlendes Kontext-Merkmal (z.B. kein
     * Reisezentrum) bekommt Amber, weil es zwar nützlich, aber kein
     * Ausschlusskriterium ist; ein fehlendes Luxus-Merkmal (z.B. kein
     * WLAN) bleibt neutral grau, da ohne Bezug zu Mobilität/Gesundheit.
     * So bleibt Rot eine knappe, aussagekräftige Ausnahme statt einer
     * Farbe, die durch inflationären Gebrauch ihre Signalwirkung verliert.
     *
     * @return {{text: string, tone: string, icon: string, isPositive: boolean, note: (string|null)}}
     */
    function renderValueByType( typ, kategorie, value ) {
        var i18n = window.dbCheckAjax.i18n;
        var negativeTone = 'kern' === kategorie ? 'red' : ( 'kontext' === kategorie ? 'amber' : 'gray' );

        switch ( typ ) {
            case 'enum': // z.B. hasSteplessAccess: yes | no | partial
                if ( 'yes' === value ) {
                    return { text: i18n.valueYes, tone: 'green', icon: 'check', isPositive: true, note: null };
                }
                if ( 'partial' === value ) {
                    return { text: i18n.valuePartial, tone: 'amber', icon: 'partial', isPositive: false, note: null };
                }
                return { text: i18n.valueNoUnknown, tone: negativeTone, icon: 'cross', isPositive: false, note: null };

            case 'boolean':
                return true === value
                    ? { text: i18n.valuePresent, tone: 'green', icon: 'check', isPositive: true, note: null }
                    : { text: i18n.valueNone, tone: negativeTone, icon: 'cross', isPositive: false, note: null };

            case 'string': // z.B. hasMobilityService: Freitext oder "no"
                var hasValue = 'string' === typeof value
                    && '' !== value.trim()
                    && 'no' !== value.trim().toLowerCase();

                return hasValue
                    ? { text: i18n.valuePresent, tone: 'green', icon: 'check', isPositive: true, note: value }
                    : { text: i18n.valueNone, tone: negativeTone, icon: 'cross', isPositive: false, note: null };

            case 'object':
                var hasObjectValue = value && 'object' === typeof value && Object.keys( value ).length > 0;
                return hasObjectValue
                    ? { text: i18n.valuePresent, tone: 'green', icon: 'check', isPositive: true, note: extractObjectNote( value ) }
                    : { text: i18n.valueNone, tone: negativeTone, icon: 'cross', isPositive: false, note: null };

            case 'integer':
                return {
                    text: ( 'number' === typeof value ) ? String( value ) : i18n.valueNoUnknown,
                    tone: 'gray',
                    icon: 'dash',
                    isPositive: false,
                    note: null,
                };

            default:
                return { text: i18n.valueNoUnknown, tone: 'gray', icon: 'dash', isPositive: false, note: null };
        }
    }

    /**
     * Versucht, aus einem Objekt-Feld (z.B. mobilityServiceStaff) einen
     * kurzen, für Menschen lesbaren Hinweistext zu extrahieren, sofern
     * ein bekanntes Unterfeld vorhanden ist. Bewusst konservativ – bei
     * unbekannter Struktur lieber kein Hinweistext als ein falscher.
     */
    function extractObjectNote( value ) {
        if ( value.meetingPoint && 'string' === typeof value.meetingPoint ) {
            return value.meetingPoint;
        }
        return null;
    }

    onReady( init );
} )();