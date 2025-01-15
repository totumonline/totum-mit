<?php

namespace totum\common\Lang;

use DateTime;

class DE implements LangInterface
{
    use TranslateTrait;
    use SearchTrait;

    public const TRANSLATES = array (
  'Deleting' => 'Entfernung',
  'Not found: %s' => 'Nicht gefunden: %s',
  'User not found' => 'Benutzer nicht gefunden',
  'Not found [[%s]] for the [[%s]] parameter.' => 'Nicht gefunden [[%s]] für Parameter [[%s]].',
  'Template not found.' => 'Vorlage nicht gefunden.',
  'No [[%s]] is specified for the [[%s]] parameter.' => 'Kein [[%s]] für Parameter [[%s]] angegeben.',
  'Parametr [[%s]] is required in [[%s]] function.' => 'Parameter [[%s]] ist in der Funktion [[%s]] erforderlich.',
  'The function is only available for the Creator role.' => 'Funktion nur für die Rolle Ersteller verfügbar.',
  'The function is not available to you.' => 'Funktion nicht verfügbar.',
  'Password doesn\'t match.' => 'Passwort stimmt nicht.',
  'Scheme string is empty.' => 'Schemastring ist leer.',
  'The function is only available for cycles tables.' => 'Funktion nur für Zyklustabellen verfügbar.',
  'Using a comparison type in a filter of list/row is not allowed' => 'Verwendung des Vergleichstyps zum Filtern von list/row ist nicht erlaubt',
  'Using a comparison type in a search in list/row is not allowed' => 'Verwendung des Vergleichstyps bei der Suche in list/row ist nicht erlaubt',
  'Field data type error' => 'Ungültiger Datentyp im Feld',
  'Not correct field name in query to [[%s]] table.' => 'Falscher Feldname in der Abfrage an Tabelle [[%s]].',
  'You see the contents of the table calculated and saved before the last transaction with the error.' => 'Sie sehen den Tabelleninhalt, der vor dem letzten Transaktionsfehler berechnet und gespeichert wurde.',
  'System error. Action type not specified.' => 'Systemfehler. Aktionstyp nicht angegeben.',
  'Field [[%s]] of table [[%s]] in row with id [[%s]] contains non-numeric data' => 'Feld [[%s]] der Tabelle [[%s]] in der Zeile mit ID [[%s]] enthält nicht-numerische Informationen',
  'Scheme source not defined.' => 'Schemaquelle nicht definiert.',
  'Fill in the parameter [[%s]].' => 'Füllen Sie den Parameter [[%s]] aus.',
  'Parametr [[%s]] is required.' => 'Parameter [[%s]] ist erforderlich.',
  'Each button must contain [[%s]].' => 'Jede Taste muss [[%s]] enthalten.',
  'The parameter [[%s]] should be of type row/list.' => 'Parameter [[%s]] muss vom Typ row/list sein.',
  'The parameter [[%s]] of [[%s]] should be of type row/list.' => 'Parameter [[%s]] in [[%s]] muss vom Typ row/list sein.',
  'The parameter [[%s]] should be of type true/false.' => 'Parameter [[%s]] muss vom Typ true/false sein.',
  'The parameter [[%s]] should [[not]] be of type row/list.' => 'Parameter [[%s]] darf nicht vom Typ row/list sein.',
  'The parameter [[%s]] should be of type string.' => 'Parameter [[%s]] muss vom Typ Zeichenkette sein.',
  '[[%s]] should be of type string.' => '[[%s]] muss ein String sein.',
  'The cycles table is specified incorrectly.' => 'Die Zyklustabelle ist falsch.',
  'Language %s not found.' => 'Sprache %s nicht gefunden.',
  'Comparing not numeric string or lists with number field' => 'Vergleich von nicht-numerischen Zeichenfolgen oder Listen mit einem numerischen Feld',
  'You cannot create query to PostgreSql with 65000 and more parameters.' => 'Sie können keine Abfrage an PostgreSQL mit >= 65000 Parametern erstellen.',
  'For temporary tables only.' => 'Nur für temporäre Tabellen',
  'For temporary tables forms only.' => 'Nur für Formulare auf Basis von temporären Tabellen.',
  'For simple and cycles tables only.' => 'Nur für einfache Tabellen und Zyklustabellen.',
  'The table has no n-sorting.' => 'Die Tabelle hat keine n-Sortierung.',
  'The table [[%s]] has no n-sorting.' => 'Tabelle [[%s]] hat keine n-Sortierung.',
  'The %s should be here.' => 'Hier sollte %s sein.',
  'Parametr [[%s]] is required and should be a number.' => 'Parameter [[%s]] ist erforderlich und muss eine Zahl sein.',
  'Parametr [[%s]] is required and should be a string.' => 'Parameter [[%s]] ist erforderlich und muss eine Zeichenkette sein.',
  'The %s parameter is required and must start with %s.' => 'Der Parameter %s ist erforderlich und muss mit %s beginnen.',
  'The %s parameter should not be an array.' => 'Parameter %s darf kein Array sein.',
  'The %s field value should not be an array.' => 'Der Wert des Feldes %s darf kein Array sein.',
  'The value of the number field should not be an array.' => 'Der Wert eines Feldes vom Typ Zahl darf kein Array sein.',
  'The %s parameter must be a number.' => 'Parameter %s muss eine Zahl sein.',
  'The value of key %s is not a number.' => 'Der Wert des Schlüssels %s ist keine Zahl.',
  'The module is not available for this host.' => 'Modul für diesen Host nicht verfügbar.',
  'The [[%s]] parameter is not correct.' => 'Parameter [[%s]] ist nicht korrekt.',
  'Comment field contains incorrect type data as a value.' => 'Kommentarfeld enthält Daten falschen Typs.',
  'The [[%s]] parameter must be plain row/list without nested row/list.' => 'Parameter [[%s]] muss ein einfacher row/list ohne verschachtelte row/list sein.',
  'Calling a third-party script.' => 'Aufruf eines externen Skripts.',
  'Not for the temporary table.' => 'Nicht für temporäre Tabelle.',
  'The [[%s]] field is not found in the [[%s]] table.' => 'Feld [[%s]] nicht in Tabelle [[%s]] gefunden.',
  'Function [[linkToEdit]] not available for [[%s]] field type.' => 'Funktion [[linkToEdit]] nicht verfügbar für Feldtyp [[%s]].',
  'The %s field must be numeric.' => 'Feld %s muss numerisch sein.',
  'The value of the %s field must be numeric.' => 'Der Wert des Feldes %s muss numerisch sein.',
  'For selecting by numeric field [[%s]] you must pass numeric values' => 'Für die Auswahl nach dem Zahlenfeld [[%s]] muss eine Zahl übergeben werden',
  'The value of %s field must match the format: %s' => 'Der Wert des Feldes %s muss dem Format entsprechen: %s',
  'The row with %s was not found in table %s.' => 'Zeile mit %s nicht in Tabelle %s gefunden.',
  'Row not found' => 'Zeile nicht gefunden',
  'Row %s not found' => 'Zeile %s nicht gefunden',
  'The row %s does not exist or is not available for your role.' => 'Zeile %s existiert nicht oder ist für Ihre Rolle nicht zugänglich.',
  'For lists comparisons, only available =, ==, !=, !==.' => 'Für den Listenvergleich sind nur =, ==, !=, !== verfügbar.',
  'There should be a date, not a list.' => 'Es sollte ein Datum sein, keine Liste.',
  'There must be only one comparison operator in the string.' => 'In der Zeile sollte nur ein Vergleichsoperator sein.',
  'TOTUM-code format error [[%s]].' => 'Formatfehler des TOTUM-Codes [[%s]].',
  'XML Format Error.' => 'XML-Formatfehler.',
  'Code format error - no start section.' => 'Codeformatfehler - Startabschnitt fehlt.',
  'The [[catch]] code of line [[%s]] was not found.' => 'Zeile [[catch]] des Codes [[%s]] nicht gefunden.',
  'ERR!' => 'FEHL!',
  'Database error: [[%s]]' => 'Datenbankfehler: [[%s]]',
  'Database connect error. Try later. [[%s]]' => 'Datenbankverbindungsfehler. Versuchen Sie es später erneut. [[%s]]',
  'Critical error while processing [[%s]] code.' => 'Kritischer Fehler bei der Verarbeitung des Codes [[%s]].',
  'field [[%s]] of [[%s]] table' => 'Feld [[%s]] der Tabelle [[%s]]',
  'Error: %s' => 'Fehler %s',
  'You cannot use linktoDataTable outside of actionCode without hide:true.' => 'linktoDataTable kann außerhalb von Aktionscode nicht ohne hide:true verwendet werden.',
  'left element' => 'linkes Element',
  'right element' => 'rechtes Element',
  'Division by zero.' => 'Division durch Null.',
  'Unknown operator [[%s]].' => 'Unbekannter Operator [[%s]].',
  'Non-numeric parameter in the list %s' => 'Nicht-numerischer Parameter in Liste %s',
  'The [[%s]] parameter must be set to one of the following values: %s' => 'Parameter [[%s]] muss einen der Werte annehmen: %s',
  'Function [[%s]]' => 'Funktion [[%s]]',
  'Function [[%s]] is not found.' => 'Funktion [[%s]] nicht gefunden.',
  'Table [[%s]] is not found.' => 'Tabelle [[%s]] nicht gefunden.',
  'Table is not found.' => 'Tabelle nicht gefunden.',
  'Max value of %s is %s.' => 'Maximalwert des Parameters %s ist %s',
  'May be insert row has expired.' => 'Möglicherweise ist die Lebensdauer der Hinzufügungszeile abgelaufen.',
  'The storage time of the temporary object has expired.' => 'Speicherzeit des temporären Objekts abgelaufen.',
  'File [[%s]] is not found.' => 'Datei [[%s]] nicht gefunden.',
  'Cycle [[%s]] is not found.' => 'Zyklus [[%s]] nicht gefunden.',
  'Cycle [[%s]] in table [[%s]] is not found.' => 'Zyklus [[%s]] in Tabelle [[%s]] nicht gefunden.',
  'TOTUM-code format error: missing operator in expression [[%s]].' => 'TOTUM-Code-Formatfehler: Operator im Ausdruck [[%s]] fehlt.',
  'TOTUM-code format error: missing part of parameter.' => 'TOTUM-Code-Formatfehler: Teil des Parameters fehlt.',
  'No key %s was found in the data row.' => 'Schlüssel %s in Datenzeile nicht gefunden',
  'There is no [[%s]] key in the [[%s]] list.' => 'Kein Schlüssel [[%s]] in der Liste [[%s]] vorhanden.',
  'Regular expression error: [[%s]]' => 'Regulärer Ausdrucksfehler: [[%s]]',
  'Parameter [[%s]] returned a non-true/false value.' => 'Parameter [[%s]] hat einen anderen Wert als true/false zurückgegeben.',
  'The [[%s]] parameter must contain 2 elements.' => 'Parameter [[%s]] muss 2 Elemente enthalten.',
  'The [[%s]] parameter must contain 3 elements.' => 'Parameter [[%s]] muss 3 Elemente enthalten.',
  'The %s parameter must contain a comparison element.' => 'Parameter %s muss ein Vergleichselement enthalten.',
  'Variable [[%s]] is not defined.' => 'Variable [[%s]] ist nicht definiert.',
  'Code [[%s]] was not found.' => 'Code [[%s]] nicht gefunden.',
  'Code line [[%s]].' => 'Codezeile [[%s]].',
  'Previous row not found. Works only for calculation tables.' => 'Vorherige Zeile nicht gefunden. Funktioniert nur für Tabellenkalkulationen.',
  'Cannot access the current value of the field from the Code.' => 'Im Code vom Typ Code (Feldwertberechnung) darf nicht auf den aktuellen Feldwert zugegriffen werden.',
  'Field [[%s]] is not found.' => 'Feld [[%s]] nicht gefunden.',
  'The key [[%s]] is not found in one of the array elements.' => 'Schlüssel [[%s]] in einem der Array-Elemente nicht gefunden.',
  'There must be two [[%s]] parameters in the [[%s]] function.' => 'Es sollten zwei Parameter [[%s]] in der Funktion [[%s]] sein.',
  'The [[%s]] parameter must be [[%s]].' => 'Parameter [[%s]] muss [[%s]] sein.',
  'The [[%s]] parameter must [[not]] be [[%s]].' => 'Parameter [[%s]] [[darf nicht]] [[%s]] sein.',
  'The number of the [[%s]] must be equal to the number of [[%s]].' => 'Die Anzahl der [[%s]] muss der Anzahl der [[%s]] entsprechen.',
  'The [[%s]] parameter must be one type with [[%s]] parameter.' => 'Parameter [[%s]] muss vom gleichen Typ wie Parameter [[%s]] sein.',
  'No characters selected for generation.' => 'Keine Zeichen zur Generierung ausgewählt.',
  'For selecting by %s field should be passed only single value or list, not row' => 'Für die Auswahl nach %s sollte das Feld nur einen Wert oder eine Liste übergeben, nicht eine row.',
  'The value by %s key is not a row/list' => 'Wert für Schlüssel %s ist weder row noch list',
  'The key must be an one value' => 'Schlüssel muss ein Einzelwert sein',
  'There is no NowField enabled in this type of code. We\'ll fix it - write us.' => 'In diesem Code-Typ ist nowField nicht verbunden.',
  '[[%s]] is available only for the calculation table in the cycle.' => '[[%s]] nur für Berechnungstabelle im Zyklus verfügbar.',
  'The ExecSSH function is disabled. Enable execSSHOn in Conf.php.' => 'execSSH-Funktion ist deaktiviert. Aktivieren Sie execSSHOn in Conf.php',
  'Ssh:true in exec function is disabled. Enable execSSHOn in Conf.php.' => 'Parameter ssh:true ist aus. Aktivieren Sie execSSHOn in Conf.php',
  'The [[%s]] parameter has not been set in this code.' => 'Parameter [[%s]] wurde in diesem Code nicht gesetzt.',
  'All list elements must be lists.' => 'Alle Listenelemente müssen Listen sein.',
  'None of the elements of the %s parameter array must be a list.' => 'Keines der Elemente im Array des Parameters %s darf eine Liste sein.',
  'Parameter %s must contain list of numbers' => 'Parameter %s muss eine Liste von Zahlen enthalten',
  'The array element does not fit the filtering conditions - the value is not a list.' => 'Array-Element entspricht nicht den Filterbedingungen — Wert ist kein list.',
  'The array element does not fit the filtering conditions - [[item]] is not found.' => 'Array-Element erfüllt die Filterbedingungen nicht — [[item]] nicht gefunden.',
  '[[%s]] is not a multiple parameter.' => '[[%s]] — ist kein Mehrfachparameter.',
  'Not found template [[%s]] for parameter [[%s]].' => 'Template [[%s]] für Parameter [[%s]] nicht gefunden.',
  'No template is specified for [[%s]].' => 'Kein Template für Parameter [[%s]] angegeben.',
  'The unpaired closing parenthesis.' => 'Unpaarige schließende Klammer.',
  'JSON generation error: [[%s]].' => 'JSON-Formationsfehler: [[%s]].',
  'JSON parsing error: [[%s]].' => 'JSON-Parsing-Fehler: [[%s]].',
  'The code should return [[%s]].' => 'Code sollte [[%s]] zurückgeben.',
  'The [[insert]] field should return list - Table [[%s]]' => 'Feld [[insert]] soll eine Liste zurückgeben — Tabelle [[%s]]',
  'The [[insert]] field should return a list with unique values - Table [[%s]]' => 'Feld [[insert]] soll eine Liste mit einzigartigen Werten zurückgeben — Tabelle [[%s]]',
  'This value is not available for entry in field %s.' => 'Dieser Wert kann im Feld %s nicht eingegeben werden.',
  'Format sections' => 'Formatierungsabschnitte',
  'Cron error' => 'Cron-Fehler',
  'The schema is not connected.' => 'Schema nicht verbunden.',
  'Error accessing the anonymous tables module.' => 'Zugriffsfehler auf das Modul für anonyme Tabellen.',
  'Page processing time: %s sec.<br/>
    RAM: %sM. of %s.<br/>
    Sql Schema: %s, V %s<br/>' => 'Bearbeitungszeit der Seite: %s сек.<br/> Arbeitspeicher:%sM. von %s.<br/>     Sql Schema: %s, V %s<br/>',
  'Order field calculation errors' => 'Berechnungsreihenfolgefehler oder Zugriff auf Felder gelöschter Zeilen',
  'in %s table in fields:' => 'in Tabelle %s in Feldern:',
  'Settings for sending mail are not set.' => 'E-Mail-Sendeeinstellungen nicht konfiguriert.',
  'The path to ssh script %s is not set.' => 'Pfad zum SSH-Skript %s nicht festgelegt.',
  'Request processing error.' => 'Fehler bei der Anfragenverarbeitung.',
  'Error generating JSON response to client [[%s]].' => 'Fehler beim Erstellen der JSON-Antwort auf dem Client [[%s]].',
  'Initialization error: [[%s]].' => 'Initialisierungsfehler: [[%s]].',
  'Header' => 'Header',
  'Footer' => 'Fußzeile',
  'Rows part' => 'Zeilenteil',
  'Filters' => 'Filter',
  'Filter' => 'Filter',
  'Row: id %s' => 'Zeile: id %s',
  'ID is empty' => 'ID ist leer',
  'User %s is not configured. Contact your system administrator.' => 'Benutzer %s nicht konfiguriert. Wenden Sie sich an den Systemadministrator.',
  'Table [[%s]] was changed. Update the table to make the changes.' => 'Die Tabelle [[%s]] wurde geändert. Aktualisieren Sie die Tabelle, um die Änderungen zu übernehmen.',
  'Table was changed' => 'Tabelle wurde geändert',
  'The anchor field settings are incorrect.' => 'Ankerfeldeinstellungen sind falsch.',
  'Field type is not defined.' => 'Feldtyp nicht definiert.',
  'Table type is not defined.' => 'Tabellentyp nicht definiert.',
  'The [[%s]] table type is not connected to the system.' => 'Tabellentyp [[%s]] ist nicht mit dem System verbunden.',
  'Unsupported channel [[%s]] is specified.' => 'Nicht unterstützter Kanal [[%s]] angegeben.',
  'Field [[%s]] of table [[%s]] is required.' => 'Feld [[%s]] in Tabelle [[%s]] ist erforderlich.',
  'Authorization lost.' => 'Autorisierung verloren.',
  'Scheme file not found.' => 'Schema-Datei nicht gefunden.',
  'Scheme not found.' => 'Schema nicht gefunden.',
  'Scheme file is empty' => 'Schema-Datei leer',
  'Wrong format scheme file.' => 'Schema-Datei hat falsches Format.',
  'Translates file not found.' => 'Übersetzungsdatei nicht gefunden.',
  'Translates file is empty' => 'Übersetzungsdatei ist leer',
  'Wrong format file' => 'Ungültiges Dateiformat',
  'Administrator' => 'Admin',
  'The type of the loaded table [[%s]] does not match.' => 'Nichtübereinstimmung des hochgeladenen Tabellentyps [[%s]].',
  'The cycles table for the adding calculation table [[%s]] is not set.' => 'Zyklustabelle für die hinzugefügte Berechnungstabelle [[%s]] nicht festgelegt.',
  'The format of the schema name is incorrect. Small English letters, numbers and - _' => 'Schema-Name-Format ist falsch. Kleinbuchstaben, Ziffern und - _',
  'A scheme exists - choose another one to install.' => 'Schema existiert — wählen Sie ein anderes zur Installation.',
  'You can\'t install totum in schema "public"' => 'Totum kann nicht im öffentlichen Schema installiert werden',
  'Category [[%s]] not found for replacement.' => 'Kategorie [[%s]] nicht zum Ersetzen gefunden.',
  'Role [[%s]] not found for replacement.' => 'Rolle [[%s]] nicht zum Ersetzen gefunden.',
  'Branch [[%s]] not found for replacement.' => 'Zweig [[%s]] nicht zum Ersetzen gefunden.',
  'Error saving file %s' => 'Fehler beim Speichern der Datei %s',
  'A nonexistent [[%s]] property was requested.' => 'Nicht vorhandene Eigenschaft angefordert [[%s]].',
  'Import from csv is not available for [[%s]] field.' => 'Import aus CSV ist für das Feld [[%s]] nicht verfügbar.',
  'Export via csv is not available for [[%s]] field.' => 'Export über CSV ist für das Feld [[%s]] nicht verfügbar.',
  'You do not have access to csv-import in this table' => 'Sie haben keinen Zugriff für CSV-Import in dieser Tabelle',
  'Date format error: [[%s]].' => 'Datumsformat ist falsch: [[%s]].',
  '[[%s]] format error: [[%s]].' => 'Format [[%s]] ist falsch: [[%s]].',
  '[[%s]] is reqired.' => '[[%s]] ist erforderlich.',
  'Settings field.' => 'Einstellungsfeld.',
  'You cannot create a [[footer]] field for [[non-calculated]] tables.' => 'Kann kein [[Fußzeilen]]-Feld [[nicht für Berechnung]] Tabellen erstellen.',
  'File > ' => 'Datei größer',
  'File not received. May be too big.' => 'Datei nicht erhalten. Größe könnte zu groß sein.',
  'The data format is not correct for the File field.' => 'Datenformat nicht geeignet für das Feld Datei.',
  'File name search error.' => 'Fehler bei der Dateinamensuche.',
  'The file must have an extension.' => 'Die Datei muss eine Erweiterung haben.',
  'Restricted to add executable files to the server.' => 'Ausführbare Dateien auf dem Server sind verboten.',
  'Failed to copy a temporary file.' => 'Temporäre Datei konnte nicht kopiert werden.',
  'Failed to copy preview.' => 'Vorschau konnte nicht kopiert werden.',
  'Error copying a file to the storage folder.' => 'Fehler beim Kopieren der Datei in den Speicherordner.',
  'Changed' => 'Geändert',
  'Empty' => 'Leer',
  'All' => 'Alle',
  'Nothing' => 'Nichts',
  ' elem.' => 'Elem.',
  'Operation [[%s]] over lists is not supported.' => 'Operation [[%s]] auf Liste ist nicht unterstützt.',
  'Operation [[%s]] over not mupliple select is not supported.' => 'Operation [[%s]] auf nicht-multi Select ist nicht vorgesehen.',
  'Text modified' => 'Text geändert',
  'Text unchanged' => 'Text stimmt überein',
  'The looped tree' => 'Schleifenbaum',
  'Value not found' => 'Wert nicht gefunden',
  'Value format error' => 'Wertformatfehler',
  'Multiselect instead of select' => 'Multiselect statt Select',
  'The value must be unique. Duplication in rows: [[%s - %s]]' => 'Wert muss einzigartig sein. Duplikat in Zeilen: [[%s - %s]]',
  'There is no default version for table %s.' => 'Keine Standardversion für Tabelle %s.',
  '[[%s]] cannot be a table name.' => '[[%s]] kann kein Tabellenname sein.',
  '[[%s]] cannot be a field name. Choose another name.' => '[[%s]] kann kein Feldname sein. Wählen Sie einen anderen Namen.',
  'The name of the field cannot be new_field' => 'Feldname darf nicht new_field sein',
  'Table creation error.' => 'Fehler beim Erstellen der Tabelle.',
  'You cannot delete system tables.' => 'Systemtabellen dürfen nicht gelöscht werden.',
  'You cannot delete system fields.' => 'Systemfelder dürfen nicht gelöscht werden.',
  'The [[%s]] field is already present in the table.' => 'Feld [[%s]] ist bereits in der Tabelle vorhanden.',
  'The [[%s]] field is already present in the [[%s]] table.' => 'Feld [[%s]] existiert bereits in Tabelle [[%s]].',
  'Fill in the field parameters.' => 'Füllen Sie die Feldparameter aus.',
  'You can\'t make a boss of someone who is in a subordinate' => 'Man kann keinen Untergebenen zum Chef machen',
  'Log is empty.' => 'Log ist leer.',
  'Method not specified' => 'Methode nicht angegeben',
  'Method [[%s]] in this module is not defined or has admin level access.' => 'Methode [[%s]] in diesem Modul ist nicht definiert oder hat Admin-Zugriff.',
  'Method [[%s]] in this module is not defined.' => 'Methode [[%s]] in diesem Modul ist nicht definiert.',
  'Your access to this table is read-only. Contact administrator to make changes.' => 'Ihr Zugriff auf diese Tabelle ist nur lesend. Wenden Sie sich an den Administrator, um Änderungen vorzunehmen.',
  'Access to the table is denied.' => 'Zugriff auf Tabelle verweigert.',
  'Access to the form is denied.' => 'Zugriff auf das Formular verweigert.',
  'Form is not found.' => 'Formular nicht gefunden',
  'Invalid link parameters.' => 'Ungültige Link-Parameter.',
  'Access to tables in a cycle through this module is not available.' => 'Zugriff auf Tabellen im Zyklus über dieses Modul ist nicht verfügbar.',
  'For quick forms only.' => 'Nur für schnelle Formulare.',
  '%s table forms' => 'Tabellenformen %s',
  'Add form' => 'Formular hinzufügen',
  'This is not a simple table. Quick forms are only available for simple tables.' => 'Dies ist keine Einfache Tabelle. Schnelle Formulare sind nur für Einfache Tabellen verfügbar.',
  'The quick table is not available in read-only mode.' => 'Schnelltabelle im Nur-Lese-Modus nicht verfügbar.',
  'The form requires link parameters to work.' => 'Link-Parameter sind für das Funktionieren des Formulars erforderlich.',
  'Incorrect link parameters' => 'Ungültige Link-Parameter',
  'Save' => 'Speichern',
  'Access to the cycle is denied.' => 'Zugriff auf den Zyklus verweigert.',
  'Table access error' => 'Tabellenzugriffsfehler',
  'Wrong path to the table' => 'Ungültiger Tabellenpfad',
  'Wrong path to the form' => 'Ungültiger Formularpfad',
  'Write access to the table is denied' => 'Schreibzugriff auf Tabelle verweigert',
  'Login/Email' => 'Login/E-Mail',
  'Log in' => 'Anmelden',
  'Logout' => 'Abmelden',
  'Send new password to email' => 'Neues Passwort an E-Mail senden',
  'Service is optimized for desktop browsers Chrome, Safari, Yandex latest versions. It seems that your version of the browser is not supported. Error - for developers: ' => 'Service für neueste Chrome, Mozilla, Safari optimiert. Ihre Browserversion wird anscheinend nicht unterstützt. Fehlerinfo:',
  'Credentials in %s' => 'Anmeldedaten in %s',
  'Fill in the Login/Email field' => 'Login/E-Mail eingeben',
  'Fill in the %s field' => 'Füllen Sie das %s aus',
  'Fill in the Password field' => 'Passwort eingeben',
  'Password is not correct' => 'Passwort ist falsch',
  'Due to exceeding the number of password attempts, your IP is blocked' => 'Aufgrund zu vieler Passwortversuche ist Ihre IP vorübergehend gesperrt.',
  'Password recovery via email is disabled for this database. Contact the solution administrator.' => 'Passwortwiederherstellung per E-Mail für diese Datenbank ist deaktiviert. Wenden Sie sich an den Lösungsadministrator.',
  'Email for this login is not set' => 'E-Mail für diesen Login nicht festgelegt',
  'Password' => 'Passwort',
  'An email with a new password has been sent to your Email. Check your inbox in a couple of minutes.' => 'Die E-Mail mit Ihrem neuen Passwort wurde gesendet. Überprüfen Sie in ein paar Minuten Ihr Postfach.',
  'Letter has not been sent: %s' => 'E-Mail nicht gesendet: %s',
  'The user with the specified Login/Email was not found' => 'Benutzer mit angegebenem Login/E-Mail nicht gefunden',
  'To work with the system you need to enable JavaScript in your browser settings' => 'Um das System zu nutzen, aktivieren Sie JavaScript in den Browsereinstellungen.',
  'It didn\'t load :(' => 'Nicht geladen :(',
  'Forms user authorization error' => 'Benutzerautorisierungsfehler mit Formularzugriff',
  'Conflicts of access to the table error' => 'Fehler bei gleichzeitigem Tabellenzugriff',
  'Form configuration error - user denied access to the table' => 'Formularfehler - Benutzer hat keinen Tabellenzugriff',
  'The [[%s]] field was not found. The table structure may have changed. Reload the page.' => 'Feld [[%s]] nicht gefunden. Möglicherweise hat sich die Tabellenstruktur geändert. Seite neu laden.',
  'Conf.php was created successfully. Connection to the database is set up, the start scheme is installed. You are authorized under specified login with the role of Creator. Click the link or refresh the page.' => 'Conf.php erfolgreich erstellt. Datenbankverbindung eingerichtet, Startschema installiert. Sie sind mit der Rolle Ersteller angemeldet. Folgen Sie dem Link oder aktualisieren Sie die Seite.',
  'Have a successful use of the system' => 'Erfolgreiche Systemnutzung',
  'Json not received or incorrectly formatted' => 'JSON nicht empfangen oder fehlerhaft',
  'A database transaction was closed before the main process was completed.' => 'Die Datenbanktransaktion wurde geschlossen, bevor der Hauptprozess abgeschlossen war.',
  'No auth section found' => 'Auth-Sektion nicht gefunden',
  'The login attribute of the auth section was not found' => 'Attribut login im auth-Bereich nicht gefunden',
  'The password attribute of the auth section was not found' => 'Attribut password im auth-Abschnitt nicht gefunden',
  'The user with this data was not found. Possibly the xml/json interface is not enabled.' => 'Benutzer mit diesen Daten nicht gefunden. Zugriff auf XML/JSON-Schnittstelle möglicherweise nicht aktiviert.',
  'The recalculate section must contain restrictions in the format [["field":FIELDNAME,"operator":OPERATOR,"value":VALUE]]' => 'Die recalculate-Sektion sollte Einschränkungen im Format [["field":FIELDNAME,"operator":OPERATOR,"value":VALUE]] enthalten.',
  'The field is not allowed to be edited through the api or does not exist in the specified category' => 'Feld ist über API nicht bearbeitbar oder existiert nicht in der angegebenen Kategorie',
  'Multiple/Single value type error' => 'Werttypfehler mehrfach/einfach',
  'In the export section, specify "fields":[] - enumeration of fields to be exported' => 'Im Exportbereich geben Sie "fields":[] an — Felder für den Export auflisten',
  'Incorrect where in the rows-set-where section' => 'Falsch formatiertes where im Abschnitt rows-set-where',
  'Without a table in the path, only the remotes section works' => 'Ohne Angabe der Tabelle im Pfad funktioniert nur der Abschnitt "remotes"',
  'Remote {var} does not exist or is not available to you' => 'Remote {var} existiert nicht oder ist für Ihre Rolle nicht zugänglich',
  'The name for remote is not set' => 'Kein Name für Remote festgelegt',
  'Field [[%s]] is not allowed to be added via Api' => 'Feld [[%s]] darf nicht über die API hinzugefügt werden',
  'Field [[%s]] is not allowed to be edited via Api' => 'Feld [[%s]] ist über API schreibgeschützt',
  'The [[%s]] field must contain multiple select' => 'Feld [[%s]] muss eine Mehrfachauswahl enthalten',
  'The [[%s]] field must contain a string' => 'Feld [[%s]] muss einen String enthalten',
  'The %s field in %s table does not exist' => 'Feld %s in %s Tabelle existiert nicht',
  'You are not allowed to add to this table' => 'Hinzufügen zu dieser Tabelle ist mit Ihrer Rolle nicht möglich',
  'You are not allowed to delete from this table' => 'Löschen aus dieser Tabelle ist mit Ihrer Rolle nicht möglich',
  'You are not allowed to sort in this table' => 'Sortierung in dieser Tabelle ist für Ihre Rolle nicht verfügbar',
  'You are not allowed to duplicate in this table' => 'Duplizieren in dieser Tabelle ist für Ihre Rolle nicht verfügbar',
  'You are not allowed to restore in this table' => 'Wiederherstellung in dieser Tabelle ist für Ihre Rolle nicht verfügbar',
  'Authorization error' => 'Benutzerautorisierungsfehler',
  'Remote is not connected to the user' => 'Remote nicht mit Benutzer verbunden',
  'Remote is not active or does not exist' => 'Remote ist inaktiv oder existiert nicht',
  'Description' => 'Beschreibung',
  'Choose a table' => 'Tabelle wählen',
  'The choice is outdated.' => 'Die vorgeschlagene Wahl ist veraltet.',
  'The proposed input is outdated.' => 'Die Eingabe ist veraltet.',
  'Notifications' => 'Benachrichtigungen',
  'Changing the name of a field' => 'Änderung des Feldnamens',
  'Fill in title' => 'Titel ausfüllen',
  'Select fields' => 'Felder auswählen',
  'Csv download of this table is not allowed for your role.' => 'CSV-Export dieser Tabelle ist für Ihre Rolle nicht erlaubt.',
  'The name of the field is not set.' => 'Feldname nicht gesetzt',
  'Access to the field is denied' => 'Zugriff auf Feld verweigert',
  'Access to edit %s field is denied' => 'Zugriff zum Bearbeiten des Feldes %s verweigert',
  'Interface Error' => 'Schnittstellenfehler',
  'Temporary table storage time has expired' => 'Speicherzeit der temporären Tabelle abgelaufen',
  'Field not of type select/tree' => 'Feld ist kein Auswahl-/Baumfeld',
  'Field not of type comments' => 'Feld ist nicht vom Typ Kommentare',
  'The tree index is not passed' => 'Baumindex nicht übergeben',
  'Access to the logs is denied' => 'Zugriff auf Logs verweigert',
  'No manual changes were made in the field' => 'Keine manuellen Änderungen am Feld vorgenommen',
  'Failed to get branch Id' => 'Fehler beim Abrufen der Zweig-ID',
  'Add row out of date' => 'Zeile hinzufügen ist veraltet',
  'Log of manual changes by field "%s"' => 'Protokoll manueller Änderungen für das Feld "%s"',
  'Calculating the table' => 'Tabellenberechnung',
  'Table is empty' => 'Tabelle ist leer',
  'Table %s. DUPLICATION CODE' => 'Tabelle %s. CODE BEI DOPPLUNG',
  'Incorrect encoding of the file (should be utf-8 or windows-1251)' => 'Falsche Dateicodierung (sollte utf-8 oder windows-1251 sein)',
  'Loading file of table %s into table [[%s]]' => 'Datei %s in Tabelle [[%s]] laden',
  'in row %s' => 'in Zeile %s',
  'no table change code' => 'fehlender Tabellencode',
  'no structure change code' => 'Strukturänderungscode fehlt',
  'The structure of the table was changed. Possibly a field order mismatch.' => 'Tabellenstruktur geändert. Feldreihenfolge kann abweichen.',
  'no indication of a cycle' => 'keine Zyklusangabe',
  'Table from another cycle or out of cycles' => 'Tabelle aus einem anderen Zyklus oder außerhalb von Zyklen',
  'There is no calculation table in [[%s]] cycles table.' => 'In der Zyklustabelle [[%s]] gibt es keine Berechnungstabelle.',
  'Out of cycles' => 'Außerhalb von Zyklen',
  'Manual Values' => 'Manuelle Werte',
  'there is no Manual Values section header' => 'Titel der Sektion Manuelle Werte fehlt',
  'no 0/1/2 edit switch' => 'fehlender 0/1/2 Bearbeitungsschalter',
  'no section header %s' => 'Abschnittstitel %s fehlt',
  'no filter data' => 'Filterdaten fehlen',
  'on the line one line after the Rows part is missing the header of the Footer section' => 'in der Zeile jede zweite nach dem Zeilenteil fehlt die Fußzeilenabschnittsüberschrift',
  '[0: do not modify calculated fields] [1: change values of calculated fields already set to manual] [2: change calculated fields]' => '[0: berechnete Felder nicht verarbeiten] [1: Werte von berechneten Feldern mit manueller Eingabe ändern] [2: berechnete Felder ändern]',
  'More than 20 nesting levels of table changes. Most likely a recalculation loop' => 'Mehr als 20 Ebenen der Verschachtelung von Tabellenänderungen. Wahrscheinlich handelt es sich um eine Neuberechnungsschleife.',
  'The field is not configured.' => 'Feld nicht eingestellt',
  'No select field specified' => 'Feld für Auswahl nicht angegeben',
  'More than one field/sfield is specified' => 'Mehr als ein field/sfield angegeben',
  'The %s function is not provided for this type of tables' => 'Die Funktion %s ist für diesen Tabellentyp nicht vorgesehen',
  'script' => 'Skript',
  'Field [[%s]] in table [[%s]] is not a column' => 'Feld [[%s]] in Tabelle [[%s]] ist keine Tabellenspalte',
  'In the %s parameter you must use a list by the number of rows to be changed or not a list.' => 'Im where-Parameter muss ein list verwendet werden, das mit der Anzahl der änderbaren Zeilen übereinstimmt, oder ein anderer Werttyp.',
  'The function is used to change the rows part of the table.' => 'Die Funktion wird verwendet, um die Zeilen der Tabelle zu ändern.',
  'Incorrect interval [[%s]]' => 'Ungültiger Intervall [[%s]]',
  'The calculation table is not connected to %s cycles table' => 'Berechnungstabelle nicht mit Zyklustabelle verbunden %s',
  'User access' => 'Benutzerzugriff',
  'Button to the cycle' => 'Taste im Zyklus',
  'First you have to delete the cycles table, and then the calculation tables inside it' => 'Zuerst die Zyklustabelle löschen, dann die Berechnungstabellen darin.',
  'No line-by-line updates are provided for the calculation tables. They are recalculated in whole' => 'Für Berechnungstabellen ist kein zeilenweises Update vorgesehen. Sie werden vollständig neu berechnet.',
  'Error processing field insert: [[%s]]' => 'Fehler bei der Verarbeitung des Feldes insert: [[%s]]',
  'Open' => 'Öffnen',
  'The row with id %s in the table already exists. Cannot be added again' => 'Zeile mit ID %s existiert bereits in der Tabelle. Kann nicht erneut hinzugefügt werden',
  'The [[%s]] field in the rows part of table [[%s]] does not exist' => 'Feld [[%s]] im Zeilenteil der Tabelle [[%s]] existiert nicht',
  'Client side error. Received row instead of id' => 'Client-Fehler. String statt ID erhalten.',
  'Client side error' => 'Client-seitiger Fehler',
  'Logic error n: %s' => 'Logikfehler n: %s',
  'Adding row error' => 'Fehler beim Hinzufügen der Zeile',
  'The Parameters field type is valid only for the Tables Fields table' => 'Feldtyp Parameter nur für Tabelle Feldzusammensetzung zulässig',
  'Data parameter  / data values must be numeric.' => 'Parameter data / seine verschachtelten Werte müssen numerisch sein',
  'An invalid value for id filtering was passed to the select function.' => 'Ein ungültiger Wert wurde an die Select-Funktion zur Filterung nach ID übergeben.',
  'Value format error in id %s row field %s' => 'Wertformatfehler in Zeile id %s Feld %s',
  'Value format error in field %s' => 'Wertformatfehler im Feld %s',
  'Select format error in field %s' => 'Select-Formatfehler im Feld %s',
  'Not correct row in files list' => 'Falsches Array in der Liste in files',
  'The field type %s cannot be in the pre-filter' => 'Feld vom Typ %s kann nicht im Prefilter sein',
  'Crypto.key file not exists' => 'Datei Crypto.key existiert nicht',
  'Service does not accept more than 10 files' => 'Dienst akzeptiert nicht mehr als 10 Dateien',
  'Number of elements %s and %s do not match' => 'Anzahl der Elemente in %s und %s stimmt nicht überein',
  'PDF printing for this table is switched off' => 'PDF-Druck für diese Tabelle ist deaktiviert',
  'The code for the specified button is not found. Try again.' => 'Der Knopfcode wurde nicht gefunden. Bitte versuchen Sie es erneut.',
  'Check that the ttm__search field type in table %s is data' => 'Überprüfen Sie, ob das Feld ttm__search in der Tabelle %s vom Typ Daten ist',
  'The file table was not found.' => 'Dateitabelle nicht gefunden.',
  'The file path is not formed correctly.' => 'Dateipfad ist falsch formatiert.',
  'The file is not protected' => 'Datei ist nicht sicher',
  'Access to the file field is denied' => 'Dateifeldzugriff verweigert',
  'Access to the file row is denied or the row does not exist' => 'Zugriff auf die Datei-Zeile verweigert oder Zeile existiert nicht',
  'The file field was not found' => 'Dateifeld nicht gefunden',
  'The file does not exist on the disk' => 'Datei existiert nicht auf der Festplatte',
  'File name parsing error' => 'Fehler beim Parsen des Dateinamens',
  'The fileDuplicateOnCopy option must be enabled for secure files.' => 'Die Option fileDuplicateOnCopy muss für geschützte Dateien aktiviert sein.',
  'DB connection by name %s was not found.' => 'Verbindung zur DB mit Name %s nicht gefunden.',
  'DB connection by hash %s was not found.' => 'Verbindung zur DB mit Hash %s nicht gefunden.',
  'Authorization type' => 'Auth-Typ',
  'Password recovering is not possible for users with special auth types' => 'Passwortwiederherstellung ist für Benutzer mit speziellen Autorisierungstypen nicht möglich.',
  'LDAP extension php not enabled' => 'LDAP-Erweiterung für PHP nicht aktiviert',
  'Set the binding format in the LDAP settings table' => 'Bind-Format in LDAP-Einstellungstabelle festlegen',
  'Set the host in the LDAP settings table' => 'Host in LDAP-Einstellungstabelle festlegen',
  'Set the port in the LDAP settings table' => 'Port in LDAP-Einstellungstabelle festlegen',
  'The function is not available' => 'Funktion nicht verfügbar',
  'Invalid parameter name' => 'Ungültiger Parametername',
  'Min value of %s is %s.' => 'Minimalwert von %s ist %s.',
  'User is switched off or does not have access rights' => 'Benutzer deaktiviert oder keine Zugriffsrechte',
  'The parameter [[%s]] should be of type row.' => 'Parameter [[%s]] muss vom Typ row sein.',
  'The fileDuplicateOnCopy option must be enabled for versioned files.' => 'Der Parameter fileDuplicateOnCopy muss für versionierte Dateien aktiviert sein.',
  'Version adding error - file for version not found' => 'Versionsfehler - Datei nicht gefunden',
  'The time to delete/replace the last file version has expired' => 'Zeit zum Löschen/Ersetzen der letzten Dateiversion ist abgelaufen',
  'File %s versions' => 'Dateiversionen %s',
  'Field [[%s]] is required.' => 'Feld [[%s]] ist erforderlich.',
  'Creator warnings' => 'Admin-Benachrichtigungen',
  'BFL-log is on' => 'Fehler- und Anfragelog aktiviert',
  'list-ubsubscribe-link-text' => 'Abmelden',
  'list-ubsubscribe-Blocked-from-sending' => 'Senden an diese E-Mail blockiert',
  'list-ubsubscribe-done' => 'Fertig',
  'list-ubsubscribe-wrong-link' => 'Falscher Link',
  'There is no access to excel-import in this table' => 'Kein Zugriff auf Excel-Import in dieser Tabelle',
  'The [[%s]] must be equal to the [[%s]].' => '[[%s]] muss gleich [[%s]] sein.',
  'Excel import to %s' => 'Excel-Import in %s',
  'Token is not exists or is expired' => 'Token existiert nicht oder ist abgelaufen',
  'This is a service user. He cannot be authorized by a token' => 'Dies ist ein Servicebenutzer. Kann nicht per Token autorisiert werden.',
  'This user have Creator role. He cannot be authorized by a token' => 'Dieser Benutzer hat die Rolle Ersteller. Er kann nicht per Token autorisiert werden.',
  'This is not web user. He cannot be authorized by a token' => 'Das ist kein Web-Nutzer. Er kann nicht per Token autorisiert werden.',
  'The OnlyOffice service table was successfully created. Repeat the operation.' => 'OnlyOffice-Servicetabelle erfolgreich erstellt. Wiederholen Sie den Vorgang.',
  'File key is not exists or is expired' => 'Dateischlüssel existiert nicht oder ist abgelaufen',
  'OnlyOfficeSaveTimeoutError' => 'Speichern nicht möglich, da keine Änderungen im Dokument vorhanden sind. Wenn Sie eine xlsx oder eine andere Tabelle bearbeiten, drücken Sie zuerst die Eingabetaste, um die Daten in der bearbeiteten Zelle zu speichern, oder verschieben Sie den Fokus auf eine andere Zelle.',
  'Permission is denied for selected user' => 'Ausgewählter Benutzer hat keinen Zugriff',
  'New secret code was sent' => 'Neuer geheimer Code gesendet',
  'You can\'t resend the secret yet.' => 'Erneutes Senden des Geheimcodes derzeit nicht möglich',
  'Wrong secret code' => 'Falscher Geheimcode',
  'Secret code expired' => 'Geheimcode abgelaufen',
  'Resend secret' => 'Geheimcode erneut senden',
  'You can resend a secret via <span></span> sec' => 'Code kann in <span></span> Sek erneut gesendet werden',
  'Secret code' => 'Geheimcode',
  'Recalculate cycle with id %s before export.' => 'Berechnen Sie den Zyklus mit der ID %s vor dem Export neu.',
);
	/**
     * Возвращает сумму прописью
     * @author runcore
     * @uses morph(...)
     */
    public function num2str($num): string
    {
        $nul = 'ноль';
        $ten = array(
            array('', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'),
            array('', 'одна', 'две', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'),
        );
        $a20 = array('десять', 'одиннадцать', 'двенадцать', 'тринадцать', 'четырнадцать', 'пятнадцать', 'шестнадцать', 'семнадцать', 'восемнадцать', 'девятнадцать');
        $tens = array(2 => 'двадцать', 'тридцать', 'сорок', 'пятьдесят', 'шестьдесят', 'семьдесят', 'восемьдесят', 'девяносто');
        $hundred = array('', 'сто', 'двести', 'триста', 'четыреста', 'пятьсот', 'шестьсот', 'семьсот', 'восемьсот', 'девятьсот');
        $unit = array( // Units
            array('копейка', 'копейки', 'копеек', 1),
            array('рубль', 'рубля', 'рублей', 0),
            array('тысяча', 'тысячи', 'тысяч', 1),
            array('миллион', 'миллиона', 'миллионов', 0),
            array('миллиард', 'милиарда', 'миллиардов', 0),
        );
        //
        list($rub, $kop) = explode('.', sprintf("%015.2f", floatval($num)));
        $out = array();
        if (intval($rub) > 0) {
            foreach (str_split($rub, 3) as $uk => $v) { // by 3 symbols
                if (!intval($v)) {
                    continue;
                }
                $uk = sizeof($unit) - $uk - 1; // unit key
                $gender = $unit[$uk][3];
                list($i1, $i2, $i3) = array_map('intval', str_split($v, 1));
                // mega-logic
                $out[] = $hundred[$i1]; # 1xx-9xx
                if ($i2 > 1) {
                    $out[] = $tens[$i2] . ' ' . $ten[$gender][$i3];
                } # 20-99
                else {
                    $out[] = $i2 > 0 ? $a20[$i3] : $ten[$gender][$i3];
                } # 10-19 | 1-9
                // units without rub & kop
                if ($uk > 1) {
                    $out[] = static::morph($v, $unit[$uk][0], $unit[$uk][1], $unit[$uk][2]);
                }
            } //foreach
        } else {
            $out[] = $nul;
        }
        $out[] = static::morph(intval($rub), $unit[1][0], $unit[1][1], $unit[1][2]); // rub
        $out[] = $kop . ' ' . static::morph($kop, $unit[0][0], $unit[0][1], $unit[0][2]); // kop
        return trim(preg_replace('/ {2,}/', ' ', join(' ', $out)));
    }

    public function smallTranslit($s): string
    {
        return strtr(
            $s,
            [
			'ß'=>'ss', 'ä'=>'a', 'ü'=>'u', 'ö'=>'o', 
			'ñ'=>'ny',
			'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'ъ' => '', 'ь' => '']
        );
    }

    public function searchPrepare($string): string
    {
        return str_replace('ё', 'е', mb_strtolower(trim((string)$string)));
    }

    /**
     * Склоняем словоформу
     * @ author runcore
     */
    protected static function morph($n, $f1, $f2, $f5)
    {
        $n = abs(intval($n)) % 100;
        if ($n > 10 && $n < 20) {
            return $f5;
        }
        $n = $n % 10;
        if ($n > 1 && $n < 5) {
            return $f2;
        }
        if ($n === 1) {
            return $f1;
        }
        return $f5;
    }

    public function dateFormat(DateTime $date, $fStr): string
    {
        $result = '';
        foreach (preg_split(
                     '/([DlFfM])/',
                     $fStr,
                     -1,
                     PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
                 ) as $split) {
            $var = null;
            switch ($split) {
                case 'D':
                    $var = 'weekDaysShort';
                // no break
                case 'l':
                    $var = $var ?? 'weekDays';
                    $result .= $this->getConstant($var)[$date->format('N')];
                    break;
                case 'F':
                    $var = 'months';
                // no break
                case 'f':
                    $var = $var ?? 'monthRods';
                // no break
                case 'M':
                    $var = $var ?? 'monthsShort';
                    $result .= $this->getConstant($var)[$date->format('n')];
                    break;
                default:
                    $result .= $date->format($split);
            }
        }
        return $result;
    }

    protected function getConstant($name): array
    {
        return match ($name) {
            'monthsShort' => [
                1 => 'янв',
                'фев',
                'мар',
                'апр',
                'май',
                'июн',
                'июл',
                'авг',
                'сент',
                'окт',
                'ноя',
                'дек'
            ],
            'months' => [
                1 => 'январь',
                'февраль',
                'март',
                'апрель',
                'май',
                'июнь',
                'июль',
                'август',
                'сентябрь',
                'октябрь',
                'ноябрь',
                'декабрь'
            ],
            'weekDays' => [
                1 => 'Понедельник',
                'Вторник',
                'Среда',
                'Четверг',
                'Пятница',
                'Суббота',
                'Воскресенье'
            ],
            'weekDaysShort' => [
                1 => 'Пн',
                'Вт',
                'Ср',
                'Чт',
                'Пт',
                'Сб',
                'Вс'
            ],
            'monthRods' => [
                1 => 'января',
                'февраля',
                'марта',
                'апреля',
                'мая',
                'июня',
                'июля',
                'августа',
                'сентября',
                'октября',
                'ноября',
                'декабря'
            ],
        };
    }
}