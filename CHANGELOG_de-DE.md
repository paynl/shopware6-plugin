# 1.4.13

# 1.4.12
- Der Prozess zum Senden von Kundennamen an PAY. wurde optimiert
- der JS-Fehler in den Konfigurationseinstellungen des Plugins behoben
- Fehler in den Konfigurationseinstellungen von Shopware 6.4.10 behoben. Das Paynl-Plugin wurde in den Konfigurationseinstellungen deaktiviert
- Refactoring des Storefront-Codes

# 1.4.11
- Javascript-Fehler "HTML-Element nicht gefunden" behoben

# 1.4.10
- Automatisierung des Auftragsstatus basierend auf dem Zahlungsstatus hinzugefügt

# 1.4.9
- Die Möglichkeit per Kartenterminal zu bezahlen hinzugefügt
- Aktualisierung des Zahlungsstatus behoben
- Zahlungsbenachrichtigung behoben

# 1.4.8
- Überschreiben von Zahlungsstatus wurde behoben

# 1.4.7
- Multisaleschannel-Unterstützung hinzugefügt
- Schaltfläche "Speichern" für iDEAL-Zahlungsmethode entfernt

# 1.4.6
- Problem mit Profilvorlagen behoben

# 1.4.5
- Erstattungsstatus hinzugefügt

# 1.4.4
- Standardkalender ist zurückgekehrt
- Fehler beim Ändern der Zahlungsmethode behoben, für die deutsche Version der Website

# 1.4.2
- Der Fehler bei der Auswahl nativer Zahlungsmethoden wurde behoben
- Fehler im Registrierungsformular für die Handelskammer behoben

# 1.4.1
- Verbesserte Benutzerrechte
- Fehler bei teilweiser Bezahlung behoben
- Hinweis fur verweigerte Zahlung behoben
- Spracheinstellungen für den Zahlungsbildschirm hinzugefügt

# 1.4.0
- Plugin ist jetzt kompatibel mit Shopware 6.4

# 1.3.5
- Bug bei den Versandkosten für nicht eingeloggte Kunden behoben
- Bug beim Ändern des Bezahlstatus für nicht PAY.-Zahlungsarten behoben
- Bug bei der Änderung der Standard-Zahlungsmethode nach dem Speichern der Konfiguration oder der Neuinstallation von Zahlungsmethoden behoben
- Bug bei der automatischen Aktivierung der Felder "Telefonnummer" und "Geburtstag" nach dem Speichern der Plugin-Einstellungen behoben
- Code-Verbesserung durch Code-Qualitätsanalyse

# 1.3.4
- Behebt ein Problem das auftritt wenn ein Benutzer ein neues Kennwort anfordert

# 1.3.3
- Löschung der Plugin-Anmeldedaten nach Plugin-Deinstallation behoben
- E-Mail-Versand nach Änderung des Bestellstatus behoben
- CustomerRegisterSubscriber korrigiert, so dass es korrekt mit CLI funktioniert
- KVK/CoC-Eingabefeld wurde von 'Adresse' in den 'Persönlich'-Block verschoben
- Templates Verbesserungen

# 1.3.2
- Geburtsdatum und Telefonnummer sind jetzt Pflichtfelder (für Später bezahlen)
- aktualisierter Datums-Picker

# 1.3.1
- die Funktionalität hinzugefügt, den IHK-Nummer erforderlich oder nicht erforderlich zu machen
- die Funktionalität hinzugefügt zum Deaktivieren/Aktivieren der PAY.styles
- Code-Verbesserungen und Code-Refactoring

# 1.3.0
- Den Fehler bei der Berechnung des Mehrwertsteuer behoben
- Den Fehler bei der Bearbeitung der Rückerstattung behoben
- Snippet hinzugefügt "Auftragsbestätigungs-E-Mail wurde gesendet" für die Erfolgreichen Zahlungen
- Validierung für das Feld "Telefonnummer" hinzugefügt
- verbesserte mobile Reaktionsfähigkeit

# 0.3.3
- die Funktion einer obligatorischen Auswahl von iDEAL Bankaussteller hinzugefügt
- das Feature einer einheitlichen Zahlungsmethode hinzugefügt (hosted payment pages)
- die Vererbung von Vorlagen verbessert
- GmbH-Nummer bug behoben

# 0.3.2
- Bug mit leeren brand ID behoben
- Bug mit der Änderung der Versandmethode behoben, auf der Bestellseite

# 0.3.1
- Korrigierte die Auftragsstornierungsmeldung für die niederländische Version
- Das reaktionsfähige Design für die Felder DoB und Telefonnummer korrigiert
- PM-Titel von 'Name' in 'Sichtbarer Name' geändert
- Einschränkung für die Darstellung von PMs in der Fußzeile hinzugefügt (jetzt max. 5)
- Admin-Funktionalität hinzugefügt, mit der gewählt werden kann, ob die Beschreibung der PMs angezeigt werden soll
- Code-Verbesserungen (Refactoring)
- Kleinere CSS-Fixes

# 0.3.0
- Beschreibung hinzugefügt für Bestellseite: Pending - "Zahlung wird vom Administrator kontrolliert"; Paid - "Zahlung erfolgreich"
- API Beschreibung hinzugefügt für spezifische PMs
- Funktionalität zur Auswahl eines Bankausstellers für iDeal hinzugefügt
- "GmbH Nummer" Feld hinzugefügt und Platzhalter "geben Sie Ihre GmbH Nummer ein" für eine Standard-Rechnungsadresse (belgien und niederlande)
- Funktionalität hinzugefügt um eine Bestellung zu stornieren. Danacht erscheint ein Link mit "Zahlungsart anpassen"
- Übersetzungen für das Label "Order" in den Transaktionsdaten für die niederländische Sprache hinzugefügt, Übersetzungen für die englische und deutsche Sprache angepasst
- Neue Icons für PMs hinzugefügt
- Funktionalität hinzugefügt, um PM-Icons aus dem Shopware-Speicher zu löschen, wenn das Plugin deinstalliert wird
- Die Möglichkeit hinzugefügt, den Bestelltransaktionsstatus von "In Progress" zu "Authorize" oder "Verify" zu ändern
- Funktionalität hinzugefügt, wenn PAY. Der Transaktionsstatus ist "Pending" - den Auftrags-Transaktionsstatus ist "In Progress"
- Geburtsdatum zu den Transaktionsdaten hinzugefügt
- Etiketten für die Schaltflächen zum Speichern/Ändern der Zahlungsmethode korrigiert
- Den Fehler auf der Seite "Bestellung bearbeiten" im Admin-Panel behoben
- Fehlerbehebungen, kleinere Code-Verbesserungen

# 0.2.3
- Shopware- und Plugin-Version zu den Transaktionsdaten hinzugefügt
- Klarstellung Text zur Auftragsbeschreibung hinzugefügt

# 0.2.2
- Hochladen von Zahlungsmethoden-Icons zu Mediendateien hinzugefügt
- Für das Checkout-Formular haben wir auf der rechten Seite der Zahlungsmethoden die Schaltflächen Speichern/Schließen hinzugefügt 
- PAY. Transaktionen Modul als Einstiegspunkt für bestellungen 
- Hervorhebungen von Validierungsfeldern für erforderliche Plugin-Einstellungsfelder hinzugefügt; außerdem wird geprüft, ob die Anmeldeinformationen gültig sind

# 0.2.1
- Hersteller-Präfixe für alle benutzerdefinierten Komponenten hinzugefügt
- Eine separate Seite für die Plugin-Konfiguration hinzugefügt
- Einige unnötige Dateien wurden entfernt
- Kleinere Code-Stil-Fixes

# 0.1.0
- Implementierte Unterstützung für alle Zahlungsmethoden durch PAY. für Shopware v6.1
- Getestet auf diesen Shopware-Versionen: 6.1.0, 6.1.1, 6.1.2, 6.1.3, 6.1.4, 6.1.5
