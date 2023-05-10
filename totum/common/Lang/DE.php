<?php

namespace totum\common\Lang;

use DateTime;

class DE implements LangInterface
{
    use TranslateTrait;
    use SearchTrait;

    public const TRANSLATES = array (
  'Deleting' => 'Löschen',
  'Not found: %s' => 'Nicht gefunden: %s',
  'User not found' => 'Benutzer nicht gefunden',
  'Not found [[%s]] for the [[%s]] parameter.' => 'Nicht gefunden [[%s]] für den Parameter [[%s]].',
  'Template not found.' => 'Vorlage nicht gefunden.',
  'No [[%s]] is specified for the [[%s]] parameter.' => 'Keine Angabe [[%s]] für den Parameter [[%s]].',
  'Parametr [[%s]] is required in [[%s]] function.' => 'Der Parameter [[%s]] ist in der Funktion [[%s]] zwingend erforderlich.',
  'The function is only available for the Creator role.' => 'Die Funktion ist nur für die Rolle des Erstellers verfügbar.',
  'The function is not available to you.' => 'Die Funktion ist für Sie nicht verfügbar.',
  'Password doesn\'t match.' => 'Das Passwort passt nicht.',
  'Scheme string is empty.' => 'Die Zeile des Schemas ist leer.',
  'The function is only available for cycles tables.' => 'Die Funktion ist nur für Zyklustabellen verfügbar.',
  'Using a comparison type in a filter of list/row is not allowed' => 'Verwendung des Vergleichstyps beim Filtern von Liste/Zeile ist nicht zulässig',
  'Using a comparison type in a search in list/row is not allowed' => 'Die Verwendung des Vergleichstyps in Listen-/Zeilensuchen ist nicht zulässig',
  'Field data type error' => 'Falscher Datentyp im Feld',
  'Not correct field name in query to [[%s]] table.' => 'Falscher Feldname in Abfrage für Tabelle [[%s]].',
  'You see the contents of the table calculated and saved before the last transaction with the error.' => 'Sie sehen den Inhalt der Tabelle, die vor der letzten Transaktion mit dem Fehler berechnet und gespeichert wurde.',
  'System error. Action type not specified.' => 'Systemfehler. Der Aktionstyp ist nicht angegeben.',
  'Field [[%s]] of table [[%s]] in row with id [[%s]] contains non-numeric data' => 'Das Feld [[%s]] der Tabelle [[%s]] in der Zeile mit der ID [[%s]] enthält nicht-numerische Informationen',
  'Scheme source not defined.' => 'Die Quelle des Schemas ist nicht definiert.',
  'Fill in the parameter [[%s]].' => 'Füllen Sie den Parameter [[%s]] aus.',
  'Parametr [[%s]] is required.' => 'Der Parameter [[%s]] ist zwingend erforderlich.',
  'Each button must contain [[%s]].' => 'Jede Taste muss [[%s]] enthalten.',
  'The parameter [[%s]] should be of type row/list.' => 'Der Parameter [[%s]] muss vom Typ Zeile/Liste sein.',
  'The parameter [[%s]] of [[%s]] should be of type row/list.' => 'Der Parameter [[%s]] in [[%s]] muss vom Typ Zeile/Liste sein.',
  'The parameter [[%s]] should be of type true/false.' => 'Der Parameter [[%s]] muss vom Typ true/false sein.',
  'The parameter [[%s]] should [[not]] be of type row/list.' => 'Der Parameter [[%s]] darf nicht vom Typ Zeile/Liste sein.',
  'The parameter [[%s]] should be of type string.' => 'Der Parameter [[%s]] muss vom Typ String sein.',
  '[[%s]] should be of type string.' => '[[%s]] sollte vom Typ String sein.',
  'The cycles table is specified incorrectly.' => 'Die Zyklentabelle ist falsch angegeben.',
  'Language %s not found.' => 'Sprache %s nicht gefunden.',
  'Comparing not numeric string or lists with number field' => 'Vergleich von nichtnumerischen Zeichenketten oder Listen mit einem numerischen Feld',
  'You cannot create query to PostgreSql with 65000 and more parameters.' => 'Sie können keine Abfrage an PostgreSql >= 65000 Parameter erstellen.',
  'For temporary tables only.' => 'Nur für temporäre Tabellen',
  'For temporary tables forms only.' => 'Nur für temporäre Tabellenformulare.',
  'For simple and cycles tables only.' => 'Nur für einfache Tabellen und Zyklustabellen.',
  'The table has no n-sorting.' => 'Die Tabelle ist nicht n-sortiert.',
  'The table [[%s]] has no n-sorting.' => 'Tabelle [[%s]] hat keine n-Sortierung.',
  'The %s should be here.' => 'Die %s sollten hier stehen.',
  'Parametr [[%s]] is required and should be a number.' => 'Der Parameter [[%s]] ist zwingend erforderlich und muss eine Zahl sein.',
  'Parametr [[%s]] is required and should be a string.' => 'Der Parameter [[%s]] ist zwingend erforderlich und muss eine Zeichenkette sein.',
  'The %s parameter is required and must start with %s.' => 'Der Parameter %s ist zwingend erforderlich und muss mit %s beginnen.',
  'The %s parameter should not be an array.' => 'Der Parameter %s sollte kein Array sein.',
  'The %s field value should not be an array.' => 'Der Wert des Feldes %s darf kein Array sein.',
  'The value of the number field should not be an array.' => 'Der Wert des Zahlenfeldes darf kein Array sein.',
  'The %s parameter must be a number.' => 'Der Parameter %s muss eine Zahl sein.',
  'The value of key %s is not a number.' => 'Der Schlüsselwert %s ist keine Zahl.',
  'The module is not available for this host.' => 'Das Modul ist für diesen Host nicht verfügbar.',
  'The [[%s]] parameter is not correct.' => 'Der Parameter [[%s]] ist nicht korrekt.',
  'Comment field contains incorrect type data as a value.' => 'Das Kommentarfeld enthält den falschen Datentyp als Wert.',
  'The [[%s]] parameter must be plain row/list without nested row/list.' => 'Der Parameter [[%s]] sollte eine einfache Zeile/Liste ohne verschachtelte Zeilen/Listen sein.',
  'Calling a third-party script.' => 'Verweis auf ein Skript eines Drittanbieters.',
  'Not for the temporary table.' => 'Nicht für den Temporere Tabelle.',
  'The [[%s]] field is not found in the [[%s]] table.' => 'Das Feld [[%s]] wird in der Tabelle [[%s]] nicht gefunden.',
  'Function [[linkToEdit]] not available for [[%s]] field type.' => 'Die Funktion [[linkToEdit]] ist für den Feldtyp [[%s]] nicht verfügbar.',
  'The %s field must be numeric.' => 'Das Feld %s muss numerisch sein.',
  'The value of the %s field must be numeric.' => 'Der Wert des Feldes %s muss numerisch sein.',
  'For selecting by numeric field [[%s]] you must pass numeric values' => 'Es muss eine Zahl übergeben werden, um das numerische Feld [[%s]] abzutasten',
  'The value of %s field must match the format: %s' => 'Der Wert des Feldes %s muss das folgende Format haben: %s',
  'The row with %s was not found in table %s.' => 'Die Zeile mit %s wurde in der Tabelle %s nicht gefunden.',
  'Row not found' => 'Zeile nicht gefunden',
  'Row %s not found' => 'Zeile %s nicht gefunden',
  'The row %s does not exist or is not available for your role.' => 'Die Zeile %s existiert nicht oder ist für Ihre Rolle nicht verfügbar.',
  'For lists comparisons, only available =, ==, !=, !==.' => 'Nur =, ==, !=, !== sind für den Blattvergleich verfügbar.',
  'There should be a date, not a list.' => 'Es sollte ein Datum angegeben werden, nicht eine Liste.',
  'There must be only one comparison operator in the string.' => 'In einer Zeichenkette darf es nur einen Vergleichsoperator geben.',
  'TOTUM-code format error [[%s]].' => 'TOTUM-Code-Formatfehler [[%s]].',
  'XML Format Error.' => 'XML-Formatfehler.',
  'Code format error - no start section.' => 'Code-Formatfehler - kein Startabschnitt.',
  'The [[catch]] code of line [[%s]] was not found.' => 'Zeile [[catch]] Code [[%s]] nicht gefunden.',
  'ERR!' => 'ERR!',
  'Database error: [[%s]]' => 'Datenbankfehler: [[%s]]',
  'Database connect error. Try later. [[%s]]' => 'Fehler bei der Datenbankverbindung. Versuchen Sie es später noch einmal. [[%s]]',
  'Critical error while processing [[%s]] code.' => 'Kritischer Fehler bei der Verarbeitung von Code [[%s]].',
  'field [[%s]] of [[%s]] table' => 'Feld [[%s]] der Tabelle [[%s]]',
  'Error: %s' => 'Fehler %s',
  'You cannot use linktoDataTable outside of actionCode without hide:true.' => 'Sie können linktoDataTable außerhalb von actionCode nicht ohne hide:true verwenden.',
  'left element' => 'linkes Element',
  'right element' => 'rechtes Element',
  'Division by zero.' => 'Division durch Null.',
  'Unknown operator [[%s]].' => 'Unbekannter Operator [[%s]]',
  'Non-numeric parameter in the list %s' => 'Nicht-numerischer Parameter in Blatt %s',
  'The [[%s]] parameter must be set to one of the following values: %s' => 'Der Parameter [[%s]] muss einen der folgenden Werte annehmen: %s',
  'Function [[%s]]' => 'Funktion [[%s]]',
  'Function [[%s]] is not found.' => 'Funktion [[%s]] wurde nicht gefunden.',
  'Table [[%s]] is not found.' => 'Die Tabelle [[%s]] wurde nicht gefunden.',
  'Table is not found.' => 'Tabelle nicht gefunden.',
  'Max value of %s is %s.' => 'Maximaler %s-Parameterwert - %s',
  'May be insert row has expired.' => 'Möglicherweise ist die Gültigkeitsdauer der Addendum-Linie abgelaufen.',
  'The storage time of the temporary object has expired.' => 'Die Speicherzeit der temporären Einrichtung ist abgelaufen.',
  'File [[%s]] is not found.' => 'Die Datei [[%s]] wurde nicht gefunden.',
  'Cycle [[%s]] is not found.' => 'Der Zyklus [[%s]] wird nicht gefunden.',
  'Cycle [[%s]] in table [[%s]] is not found.' => 'Der Zyklus [[%s]] wird in der Tabelle [[%s]] nicht gefunden.',
  'TOTUM-code format error: missing operator in expression [[%s]].' => 'TOTUM-Code-Formatfehler: fehlender Operator in Ausdruck [[%s]].',
  'TOTUM-code format error: missing part of parameter.' => 'TOTUM-Code-Formatfehler: Teil eines Parameters fehlt.',
  'No key %s was found in the data row.' => 'Der Schlüssel %s in der Datenzeile wird nicht erkannt',
  'There is no [[%s]] key in the [[%s]] list.' => 'Der Schlüssel [[%s]] existiert nicht im Blatt [[%s]].',
  'Regular expression error: [[%s]]' => 'Fehler beim regulären Ausdruck: [[%s]]',
  'Parameter [[%s]] returned a non-true/false value.' => 'Der Parameter [[%s]] hat nicht true/false zurückgegeben.',
  'The [[%s]] parameter must contain 2 elements.' => 'Der Parameter [[%s]] muss 2 Elemente enthalten.',
  'The [[%s]] parameter must contain 3 elements.' => 'Der Parameter [[%s]] muss 3 Elemente enthalten.',
  'The %s parameter must contain a comparison element.' => 'Der Parameter %s muss ein Vergleichselement enthalten.',
  'Variable [[%s]] is not defined.' => 'Die Variable [[%s]] ist undefiniert.',
  'Code [[%s]] was not found.' => 'Code [[%s]] nicht gefunden.',
  'Code line [[%s]].' => 'Code-Zeile [[%s]]',
  'Previous row not found. Works only for calculation tables.' => 'Vorherige Zeile nicht gefunden. Funktioniert nur bei Berechnungstabellen.',
  'Cannot access the current value of the field from the Code.' => 'Sie können nicht vom Code aus auf den aktuellen Wert eines Feldes zugreifen.',
  'Field [[%s]] is not found.' => 'Das Feld [[%s]] wurde nicht gefunden.',
  'The key [[%s]] is not found in one of the array elements.' => 'Der Schlüssel [[%s]] wird in einem der Array-Elemente nicht gefunden.',
  'There must be two [[%s]] parameters in the [[%s]] function.' => 'Es müssen zwei Parameter [[%s]] in der [[%s]]-Funktion vorhanden sein.',
  'The [[%s]] parameter must be [[%s]].' => 'Der Parameter [[%s]] muss [[%s]] sein.',
  'The [[%s]] parameter must [[not]] be [[%s]].' => 'Der Parameter [[%s]] [[nicht]] muss [[%s]] sein.',
  'The number of the [[%s]] must be equal to the number of [[%s]].' => 'Die Anzahl der [[%s]] muss gleich der Anzahl der [[%s]] sein.',
  'The [[%s]] parameter must be one type with [[%s]] parameter.' => 'Der Parameter [[%s]] muss vom gleichen Typ sein wie der Parameter [[%s]].',
  'No characters selected for generation.' => 'Die zu erzeugenden Symbole sind nicht ausgewählt.',
  'For selecting by %s field should be passed only single value or list, not row' => 'Es sollte nur ein Wert oder eine Liste übertragen werden, um das Feld %s auszuwählen, nicht die Zeile',
  'The value by %s key is not a row/list' => 'Der Schlüsselwert %s ist keine Zeile/Liste',
  'The key must be an one value' => 'Der Schlüssel muss ein einzelner Wert sein',
  'There is no NowField enabled in this type of code. We\'ll fix it - write us.' => 'Bei dieser Art von Code ist kein NowField angeschlossen. Wir werden es korrigieren - mailen Sie uns.',
  '[[%s]] is available only for the calculation table in the cycle.' => '[[%s]] ist nur für die Berechnungstabelle in der Schleife verfügbar.',
  'The ExecSSH function is disabled. Enable execSSHOn in Conf.php.' => 'ie Funktion ExecSSH ist deaktiviert. Einbindung von execSSHOn in Conf.php',
  'Ssh:true in exec function is disabled. Enable execSSHOn in Conf.php.' => 'Der Parameter ssh:true ist deaktiviert. execSSHOn in Conf.php einbinden',
  'The [[%s]] parameter has not been set in this code.' => 'Der Parameter [[%s]] wurde in diesem Code nicht gesetzt.',
  'All list elements must be lists.' => 'Alle Listenelemente müssen Listen sein.',
  'None of the elements of the %s parameter array must be a list.' => 'Keines der Elemente des %s-Parameterarrays darf eine Liste sein.',
  'Parameter %s must contain list of numbers' => 'Der Parameter %s sollte eine Liste von Zahlen enthalten',
  'The array element does not fit the filtering conditions - the value is not a list.' => 'Das Array-Element erfüllt die Filterbedingungen nicht - der Wert ist keine Liste.',
  'The array element does not fit the filtering conditions - [[item]] is not found.' => 'Array-Element entspricht nicht den Filterbedingungen - [[item]] nicht gefunden.',
  '[[%s]] is not a multiple parameter.' => '[[%s]] ist kein Mehrfachparameter.',
  'Not found template [[%s]] for parameter [[%s]].' => 'Es wurde keine Vorlage [[%s]] für den Parameter [[%s]] gefunden.',
  'No template is specified for [[%s]].' => 'Für [[%s]] ist keine Vorlage angegeben.',
  'The unpaired closing parenthesis.' => 'Ungepaarte schließende Klammern.',
  'JSON generation error: [[%s]].' => 'JSON-Bildungsfehler: [[%s]].',
  'JSON parsing error: [[%s]].' => 'JSON-Parsing-Fehler: [[%s]].',
  'The code should return [[%s]].' => 'Der Code sollte [[%s]] zurückgeben.',
  'The [[insert]] field should return list - Table [[%s]]' => 'Das Feld [[insert]] sollte Liste - Tabelle [[%s]] zurückgeben.',
  'The [[insert]] field should return a list with unique values - Table [[%s]]' => 'Das [[insert]]-Feld sollte eine Liste mit eindeutigen Werten zurückgeben - Tabelle [[%s]]',
  'This value is not available for entry in field %s.' => 'Dieser Wert ist für die Eingabe in das Feld %s nicht verfügbar.',
  'Format sections' => 'Formatierung der Abschnitte',
  'Cron error' => 'Cron Fehler',
  'The schema is not connected.' => 'Der Schema ist nicht angeschlossen.',
  'Error accessing the anonymous tables module.' => 'Zugriffsfehler auf das Modul anonyme Tabellen.',
  'Page processing time: %s sec.<br/>
    RAM: %sM. of %s.<br/>
    Sql Schema: %s, V %s<br/>' => 'Bearbeitungszeit der Seite: %s сек.<br/> Arbeitspeicher:%sM. von %s.<br/>     Sql Schema: %s, V %s<br/>',
  'Order field calculation errors' => 'Fehler in der Feldberechnungsreihenfolge',
  'in %s table in fields:' => 'in der Tabelle %s in den Feldern: ',
  'Settings for sending mail are not set.' => 'Die Einstellungen für den E-Mail-Versand sind nicht festgelegt.',
  'The path to ssh script %s is not set.' => 'Der Pfad zum ssh-Skript %s ist nicht festgelegt.',
  'Request processing error.' => 'Fehler bei der Bearbeitung der Anfrage.',
  'Error generating JSON response to client [[%s]].' => 'Fehler beim Generieren der JSON-Antwort für den Client [[%s]].',
  'Initialization error: [[%s]].' => 'Initialisierungsfehler: [[%s]].',
  'Header' => 'Kopfzeile',
  'Footer' => 'Fußzeile',
  'Rows part' => 'Linearer Teil',
  'Filters' => 'Filtern',
  'Filter' => 'Filter',
  'Row: id %s' => 'Zeile: id %s',
  'ID is empty' => 'ID ist leer',
  'User %s is not configured. Contact your system administrator.' => 'Der Benutzer %s ist nicht konfiguriert. Wenden Sie sich an Ihren Systemadministrator.',
  'Table [[%s]] was changed. Update the table to make the changes.' => 'Die Tabelle [[%s]] wurde geändert. Aktualisieren Sie die Tabelle, um die Änderungen auszuführen.',
  'Table was changed' => 'Die Tabelle wurde geändert',
  'The anchor field settings are incorrect.' => 'Die Einstellungen des Ankerfeldes sind falsch.',
  'Field type is not defined.' => 'Der Feldtyp ist nicht definiert.',
  'Table type is not defined.' => 'Der Tabellentyp ist nicht definiert.',
  'The [[%s]] table type is not connected to the system.' => 'Der Tabellentyp [[%s]] ist nicht mit dem System verbunden.',
  'Unsupported channel [[%s]] is specified.' => 'Es wird ein nicht unterstützter Kanal [[%s]] angezeigt.',
  'Field [[%s]] of table [[%s]] is required.' => 'Das Feld [[%s]] der Tabelle [[%s]] ist obligatorisch.',
  'Authorization lost.' => 'Berechtigung verloren.',
  'Scheme file not found.' => 'Schema-Datei nicht gefunden.',
  'Scheme not found.' => 'Schema nicht gefunden.',
  'Scheme file is empty' => 'Schemadatei leer',
  'Wrong format scheme file.' => 'Die Diagrammdatei hat das falsche Format.',
  'Translates file not found.' => 'Übersetzungsdatei nicht gefunden.',
  'Translates file is empty' => 'Die Übersetzungsdatei ist leer',
  'Wrong format file' => 'Falsches Dateiformat',
  'Administrator' => 'Administrator',
  'The type of the loaded table [[%s]] does not match.' => 'Der geladene Tabellentyp stimmt nicht mit [[%s]] überein.',
  'The cycles table for the adding calculation table [[%s]] is not set.' => 'Die Zyklustabelle für die hinzuzufügende Berechnungstabelle [[%s]] ist nicht gesetzt.',
  'The format of the schema name is incorrect. Small English letters, numbers and - _' => 'Das Format des Systemnamens ist nicht korrekt. Kleine englische Buchstaben, Zahlen und - _',
  'A scheme exists - choose another one to install.' => 'Es gibt ein Schema - wählen Sie ein anderes zur Installation.',
  'You can\'t install totum in schema "public"' => 'Sie können Totum nicht in einem public Schema installieren',
  'Category [[%s]] not found for replacement.' => 'Die Kategorie [[%s]] wurde für die Ersetzung nicht gefunden.',
  'Role [[%s]] not found for replacement.' => 'Die Kategorie [[%s]] wurde für die Ersetzung nicht gefunden.',
  'Branch [[%s]] not found for replacement.' => 'Der [[%s]]-Zweig wurde nicht zum Ersetzen gefunden.',
  'Error saving file %s' => 'Fehler beim Speichern der Datei %s',
  'A nonexistent [[%s]] property was requested.' => 'Es wurde eine nicht existierende Eigenschaft [[%s]] angefordert.',
  'Import from csv is not available for [[%s]] field.' => 'Import aus csv ist für das Feld [[%s]] nicht verfügbar.',
  'Export via csv is not available for [[%s]] field.' => 'Der Export über csv ist für das Feld [[%s]] nicht verfügbar.',
  'You do not have access to csv-import in this table' => 'Sie haben keinen Zugriff auf den csv-Import in dieser Tabelle',
  'Date format error: [[%s]].' => 'Das Datumsformat ist falsch: [[%s]].',
  '[[%s]] format error: [[%s]].' => 'Das Format [[%s]] ist falsch: [[%s]].',
  '[[%s]] is reqired.' => '[[%s]] ist zwingend erforderlich.',
  'Settings field.' => 'Feld für die Einstellung.',
  'You cannot create a [[footer]] field for [[non-calculated]] tables.' => 'Sie können kein [[Fußzeile]]-Feld erstellen Tabellen [[nicht für Berechnungen]].',
  'File > ' => 'Datei mehr ',
  'File not received. May be too big.' => 'Datei nicht erhalten. Möglicherweise zu groß.',
  'The data format is not correct for the File field.' => 'Das Datenformat ist nicht für das Feld Datei geeignet.',
  'File name search error.' => 'Ein Fehler bei der Suche nach dem Namen der Datei.',
  'The file must have an extension.' => 'Die Datei muss eine Erweiterung haben.',
  'Restricted to add executable files to the server.' => 'Es ist verboten, dem Server ausführbare Dateien hinzuzufügen.',
  'Failed to copy a temporary file.' => 'Die temporäre Datei konnte nicht kopiert werden.',
  'Failed to copy preview.' => 'Die Vorschau konnte nicht kopiert werden.',
  'Error copying a file to the storage folder.' => 'Fehler beim Kopieren einer Datei in den Speicherordner.',
  'Changed' => 'Geändert',
  'Empty' => 'Leere',
  'All' => 'Alle',
  'Nothing' => 'Nichts',
  ' elem.' => ' elem.',
  'Operation [[%s]] over lists is not supported.' => 'Operation [[%s]] über Blätter ist nicht möglich.',
  'Operation [[%s]] over not mupliple select is not supported.' => 'Operation [[%s]] über nicht mupliple select wird nicht unterstützt.',
  'Text modified' => 'Text geändert',
  'Text unchanged' => 'Der Text ist konsistent',
  'The looped tree' => 'Ein Baum mit Schleifen',
  'Value not found' => 'Wert nicht gefunden',
  'Value format error' => 'Fehler im Werteformat',
  'Multiselect instead of select' => 'Multiselect statt Select',
  'The value must be unique. Duplication in rows: [[%s - %s]]' => 'Der Wert muss eindeutig sein. Doppelung in Zeichenketten: [[%s - %s]]',
  'There is no default version for table %s.' => 'Es gibt keine Standardversion für die Tabelle %s.',
  '[[%s]] cannot be a table name.' => '[[%s]] kann kein Tabellenname sein.',
  '[[%s]] cannot be a field name. Choose another name.' => '[[%s]] kann nicht der Name des Feldes sein. Wählen Sie einen anderen Namen.',
  'The name of the field cannot be new_field' => 'Der Feldname kann nicht new_field sein',
  'Table creation error.' => 'Fehler bei der Tabellenerstellung.',
  'You cannot delete system tables.' => 'Systemtabellen dürfen nicht gelöscht werden.',
  'You cannot delete system fields.' => 'Systemfelder dürfen nicht gelöscht werden.',
  'The [[%s]] field is already present in the table.' => 'Das Feld [[%s]] ist bereits in der Tabelle enthalten.',
  'The [[%s]] field is already present in the [[%s]] table.' => 'Das Feld [[%s]] existiert bereits in der Tabelle [[%s]].',
  'Fill in the field parameters.' => 'Füllen Sie die Parameter des Feldes aus.',
  'You can\'t make a boss of someone who is in a subordinate' => 'Sie können niemanden zum Chef machen, der eine untergeordnete Position innehat.',
  'Log is empty.' => 'Das Protokoll ist leer.',
  'Method not specified' => 'Methode nicht angegeben',
  'Method [[%s]] in this module is not defined or has admin level access.' => 'Die Methode [[%s]] in diesem Modul ist nicht definiert oder hat Zugriff auf die Administratorebene.',
  'Method [[%s]] in this module is not defined.' => 'Die Methode [[%s]] ist in diesem Modul nicht definiert.',
  'Your access to this table is read-only. Contact administrator to make changes.' => 'Ihr Zugriff auf diese Tabelle ist schreibgeschützt. Wenden Sie sich an Ihren Administrator, um Änderungen vorzunehmen.',
  'Access to the table is denied.' => 'Der Zugang zum Tabelle ist verboten.',
  'Access to the form is denied.' => 'Der Zugriff auf das Formular ist verboten.',
  'Form is not found.' => 'Formular nicht gefunden',
  'Invalid link parameters.' => 'Falsche Link-Parameter.',
  'Access to tables in a cycle through this module is not available.' => 'Der Zugriff auf die Tabellen in der Schleife ist über dieses Modul nicht möglich.',
  'For quick forms only.' => 'Nur für Schnellformulare.',
  '%s table forms' => 'Tabellenformulare %s',
  'Add form' => 'Formular hinzufügen',
  'This is not a simple table. Quick forms are only available for simple tables.' => 'Es handelt sich nicht um eine einfache Tabelle. Schnellformulare sind nur für einfache Tabellen verfügbar.',
  'The quick table is not available in read-only mode.' => 'Die Schnelltabelle ist im schreibgeschützten Modus nicht verfügbar.',
  'The form requires link parameters to work.' => 'Die Link-Parameter sind erforderlich, damit das Formular funktioniert.',
  'Incorrect link parameters' => 'Falsche Link-Parameter',
  'Save' => 'Speichern',
  'Access to the cycle is denied.' => 'Der Zugang zur Schleife ist verboten.',
  'Table access error' => 'Fehler beim Tabellenzugriff',
  'Wrong path to the table' => 'Falscher Tabellenpfad',
  'Wrong path to the form' => 'Falscher Pfad zum Formular',
  'Write access to the table is denied' => 'Schreibzugriff auf die Tabelle ist verboten',
  'Login/Email' => 'Anmeldung/E-Mail',
  'Log in' => 'Einloggen',
  'Logout' => 'Abmeldung',
  'Send new password to email' => 'Neues Passwort an E-Mail senden',
  'Service is optimized for desktop browsers Chrome, Safari, Yandex latest versions. It seems that your version of the browser is not supported. Error - for developers: ' => 'Der Dienst ist für die neuesten Versionen von Chrome, Safari und Yandex Desktop-Browsern optimiert. Es scheint, dass Ihre Browserversion nicht unterstützt wird. Der Fehler ist für Entwickler: ',
  'Credentials in %s' => 'Anmeldeinformationen in %s',
  'Fill in the Login/Email field' => 'Füllen Sie das Feld Anmeldung/E-Mail aus',
  'Fill in the %s field' => 'Füllen Sie das Feld %s aus',
  'Fill in the Password field' => 'Füllen Sie das Feld Passwort aus',
  'Password is not correct' => 'Das Passwort ist falsch',
  'Due to exceeding the number of password attempts, your IP is blocked' => 'Aufgrund der Überschreitung der Anzahl der Passwortversuche wird Ihre IP gesperrt',
  'Password recovery via email is disabled for this database. Contact the solution administrator.' => 'Die Passwortwiederherstellung per E-Mail ist für diese Datenbank deaktiviert. Wenden Sie sich an den Administrator der Lösung.',
  'Email for this login is not set' => 'E-Mail für diese Anmeldung ist nicht eingestellt',
  'Password' => 'Passwort',
  'An email with a new password has been sent to your Email. Check your inbox in a couple of minutes.' => 'Eine E-Mail mit einem neuen Passwort wurde an Ihre E-Mail-Adresse gesendet. Prüfen Sie Ihren Posteingang in ein paar Minuten.',
  'Letter has not been sent: %s' => 'Email nicht gesendet: %s',
  'The user with the specified Login/Email was not found' => 'Benutzer mit Benutzernamen/E-Mail nicht gefunden',
  'To work with the system you need to enable JavaScript in your browser settings' => 'JavaScript muss in den Einstellungen Ihres Browsers aktiviert sein, damit Sie das System nutzen können.',
  'It didn\'t load :(' => 'Es wurde nicht geladen :(',
  'Forms user authorization error' => 'Fehler bei der Benutzerberechtigung für Formulare',
  'Conflicts of access to the table error' => 'Fehler bei gleichzeitigem Tabellenzugriff',
  'Form configuration error - user denied access to the table' => 'Fehler in der Formularkonfiguration - Benutzer hat keinen Zugriff auf die Tabelle',
  'The [[%s]] field was not found. The table structure may have changed. Reload the page.' => 'Das Feld [[%s]] wird nicht gefunden. Die Tabellenstruktur hat sich möglicherweise geändert. Laden Sie die Seite neu',
  'Conf.php was created successfully. Connection to the database is set up, the start scheme is installed. You are authorized under specified login with the role of Creator. Click the link or refresh the page.' => 'Conf.php wurde erfolgreich erstellt. Die Verbindung zur Datenbank wurde hergestellt und das Startschema wurde installiert. Sie sind mit dem angegebenen Login als Creator eingeloggt. Klicken Sie auf den Link oder aktualisieren Sie die Seite.',
  'Have a successful use of the system' => 'Erfolgreiche Nutzung des Systems',
  'Json not received or incorrectly formatted' => 'Json nicht empfangen oder nicht richtig formatiert',
  'A database transaction was closed before the main process was completed.' => 'Die Datenbanktransaktion wurde geschlossen, bevor der Hauptprozess abgeschlossen war.',
  'No auth section found' => 'Kein Auth-Bereich gefunden',
  'The login attribute of the auth section was not found' => 'Das Login-Attribut des auth-Abschnitts wurde nicht gefunden',
  'The password attribute of the auth section was not found' => 'Das Passwort-Attribut des auth-Abschnitts wurde nicht gefunden',
  'The user with this data was not found. Possibly the xml/json interface is not enabled.' => 'Es wurde kein Benutzer mit diesen Daten gefunden. Möglicherweise nicht für den Zugriff auf die xml/json-Schnittstelle aktiviert',
  'The recalculate section must contain restrictions in the format [["field":FIELDNAME,"operator":OPERATOR,"value":VALUE]]' => 'Der Abschnitt "Neuberechnung" muss Einschränkungen im Format [["field":FIELDNAME, "operator":OPERATOR, "value":VALUE]] enthalten.',
  'The field is not allowed to be edited through the api or does not exist in the specified category' => 'Das Feld darf nicht über die API bearbeitet werden oder ist in der angegebenen Kategorie nicht vorhanden',
  'Multiple/Single value type error' => 'Werttypfehler Plural/Einzelperson',
  'In the export section, specify "fields":[] - enumeration of fields to be exported' => 'Im Abschnitt "Export" geben Sie "fields" an:[] - Auflistung der Felder, die exportiert werden sollen',
  'Incorrect where in the rows-set-where section' => 'Falsches Wo im Abschnitt rows-set-where',
  'Without a table in the path, only the remotes section works' => 'Ohne eine Tabelle im Pfad funktioniert nur der Abschnitt "Fernbedienungen"',
  'Remote {var} does not exist or is not available to you' => 'Remote {var} existiert nicht oder ist für Sie nicht verfügbar',
  'The name for remote is not set' => 'Kein Name für die Fernbedienung festgelegt',
  'Field [[%s]] is not allowed to be added via Api' => 'Das Feld [[%s]] darf nicht über die Api hinzugefügt werden',
  'Field [[%s]] is not allowed to be edited via Api' => 'Das Feld [[%s]] darf nicht über die Api bearbeitet werden',
  'The [[%s]] field must contain multiple select' => 'Feld [[%s]] muss Mehrfachauswahlen enthalten',
  'The [[%s]] field must contain a string' => 'Feld [[%s]] muss die Zeichenfolge',
  'The %s field in %s table does not exist' => 'Das Feld %s in der Tabelle %s existiert nicht',
  'You are not allowed to add to this table' => 'Es ist nicht erlaubt, diese Tabelle zu ergänzen',
  'You are not allowed to delete from this table' => 'Es ist nicht erlaubt, aus dieser Tabelle zu löschen',
  'You are not allowed to sort in this table' => 'Sie dürfen in dieser Tabelle nicht sortieren',
  'You are not allowed to duplicate in this table' => 'Sie dürfen in dieser Tabelle nicht duplizieren',
  'You are not allowed to restore in this table' => 'Sie dürfen sich in dieser Tabelle nicht erholen',
  'Authorization error' => 'Fehler bei der Benutzerberechtigung',
  'Remote is not connected to the user' => 'Remote ist nicht mit einem Benutzer verbunden',
  'Remote is not active or does not exist' => 'Remote ist nicht aktiv oder existiert nicht',
  'Description' => 'Beschreibung',
  'Choose a table' => 'Wählen Sie eine Tabelle',
  'The choice is outdated.' => 'Die vorgeschlagene Auswahl ist veraltet.',
  'The proposed input is outdated.' => 'Die vorgeschlagene Eingabe ist veraltet.',
  'Notifications' => 'Mitteilungen',
  'Changing the name of a field' => 'Ändern des name eines Feldes',
  'Fill in title' => 'Tragen Sie den Namen ein',
  'Select fields' => 'Wählen Sie die Felder aus',
  'Csv download of this table is not allowed for your role.' => 'Csv-выгрузка этой таблицы не разрешена для вашей роли.',
  'The name of the field is not set.' => 'Feldname nicht gesetzt',
  'Access to the field is denied' => 'Der Zugang zum Feld ist verboten',
  'Access to edit %s field is denied' => 'Zugriff auf Bearbeitungsfeld %s verweigert',
  'Interface Error' => 'Interface-Fehler',
  'Temporary table storage time has expired' => 'Die Speicherzeit der temporären Tabelle ist abgelaufen',
  'Field not of type select/tree' => 'Das Feld ist kein Select/Tree-Typ',
  'Field not of type comments' => 'Das Feld ist kein Kommentartyp',
  'The tree index is not passed' => 'Der Baumindex ist nicht übertragen worden',
  'Access to the logs is denied' => 'Der Zugang zu den Protokollen ist verboten',
  'No manual changes were made in the field' => 'Es wurden keine manuellen Änderungen an dem Feld vorgenommen.',
  'Failed to get branch Id' => 'Fehler beim Abrufen der Zweigstellen-ID',
  'Add row out of date' => 'Die Zusatzzeile wurde gestrichen.',
  'Log of manual changes by field "%s"' => 'Protokoll der manuellen Änderungen am Feld "%s"',
  'Calculating the table' => 'Berechnung der Tabelle',
  'Table is empty' => 'Die Tabelle ist leer',
  'Table %s. DUPLICATION CODE' => 'Tabelle %s. DUPLIKATIONSCODE',
  'Incorrect encoding of the file (should be utf-8 or windows-1251)' => 'Falsche Kodierung der Datei (sollte utf-8 oder windows-1251 sein)',
  'Loading file of table %s into table [[%s]]' => 'Laden der Tabellendatei %s in die Tabelle [[%s]]',
  'in row %s' => 'in Zeile %s',
  'no table change code' => 'es gibt keinen Code zur Änderung der Tabelle',
  'no structure change code' => 'kein Strukturänderungscode',
  'The structure of the table was changed. Possibly a field order mismatch.' => 'Die Struktur der Tabelle wurde geändert. Die Reihenfolge der Felder ist möglicherweise nicht dieselbe.',
  'no indication of a cycle' => 'es gibt keinen Hinweis auf einen Zyklus',
  'Table from another cycle or out of cycles' => 'Tabelle aus einem anderen Zyklus oder aus den Zyklen',
  'There is no calculation table in [[%s]] cycles table.' => 'Es gibt keine Berechnungstabelle in der Zyklustabelle [[%s]].',
  'Out of cycles' => 'Außerhalb der Zyklen',
  'Manual Values' => 'Manuelle Werte',
  'there is no Manual Values section header' => 'keine Abschnittsüberschrift Manuelle Werte',
  'no 0/1/2 edit switch' => 'es gibt keinen Schalter 0/1/2 bearbeiten',
  'no section header %s' => 'Abschnittsüberschrift %s fehlt',
  'no filter data' => 'keine Filterdaten verfügbar',
  'on the line one line after the Rows part is missing the header of the Footer section' => 'in der Zeile eine Zeile nach dem Zeilenteil gibt es keine Überschrift für den Fußzeilenabschnitt',
  '[0: do not modify calculated fields] [1: change values of calculated fields already set to manual] [2: change calculated fields]' => '[0: berechnete Felder nicht bearbeiten] [1: Werte von berechneten Feldern ändern, die bereits auf manuell eingestellt sind] [2: berechnete Felder ändern]',
  'More than 20 nesting levels of table changes. Most likely a recalculation loop' => 'Mehr als 20 Verschachtelungsebenen von Tabellenänderungen. Höchstwahrscheinlich eine Neuberechnungsschleife',
  'The field is not configured.' => 'Das Feld ist nicht konfiguriert',
  'No select field specified' => 'Kein Probenahmefeld angegeben',
  'More than one field/sfield is specified' => 'Es wird mehr als ein field/sfield angegeben',
  'The %s function is not provided for this type of tables' => 'Die Funktion %s ist für diese Art von Tabelle nicht vorgesehen',
  'script' => 'skript',
  'Field [[%s]] in table [[%s]] is not a column' => 'Feld [[%s]] in Tabelle [[%s]] ist keine Spalte',
  'In the %s parameter you must use a list by the number of rows to be changed or not a list.' => 'Der Parameter where sollte ein Blatt nach der Anzahl der zu ändernden Zeilen oder kein Blatt verwenden.',
  'The function is used to change the rows part of the table.' => 'Die Funktion wird verwendet, um den Zeilenteil der Tabelle zu ändern.',
  'Incorrect interval [[%s]]' => 'Falsches Intervall [[%s]]',
  'The calculation table is not connected to %s cycles table' => 'Die Berechnungstabelle ist nicht mit der Zyklustabelle %s verbunden',
  'User access' => 'Zugang für Benutzer',
  'Button to the cycle' => 'Taste in der Schleife',
  'First you have to delete the cycles table, and then the calculation tables inside it' => 'Sie müssen zuerst die Zyklustabelle und dann die darin enthaltenen Berechnungstabellen löschen',
  'No line-by-line updates are provided for the calculation tables. They are recalculated in whole' => 'Die Berechnungstabellen werden nicht Zeile für Zeile aktualisiert. Sie werden als Ganzes neu berechnet',
  'Error processing field insert: [[%s]]' => 'Fehler beim bearbeiten Field insert: [[%s]]',
  'Open' => 'Öffnen',
  'The row with id %s in the table already exists. Cannot be added again' => 'Die Zeile mit der ID %s in der Tabelle existiert bereits. Kann nicht erneut hinzugefügt werden',
  'The [[%s]] field in the rows part of table [[%s]] does not exist' => 'Das Feld [[%s]] existiert nicht in dem Zeilenteil der Tabelle [[%s]]',
  'Client side error. Received row instead of id' => 'Client-seitiger Fehler. Empfangener String anstelle von id',
  'Client side error' => 'Client-seitiger Fehler',
  'Logic error n: %s' => 'Logikfehler n: %s',
  'Adding row error' => 'Fehler im String hinzufügen',
  'The Parameters field type is valid only for the Tables Fields table' => 'Feldtyp Parameter sind nur für die Tabelle Feldzusammensetzung zulässig',
  'Data parameter  / data values must be numeric.' => 'Der Parameter data / seine Teilwerte müssen numerisch sein',
  'An invalid value for id filtering was passed to the select function.' => 'Ein ungültiger Wert wurde an die select-Funktion zum Filtern nach id übergeben.',
  'Value format error in id %s row field %s' => 'Werteformatfehler in der id-Zeile %s des Feldes %s',
  'Value format error in field %s' => 'Wertformatfehler im Feld %s',
  'Select format error in field %s' => 'Selektorformatfehler im Feld %s',
  'Not correct row in files list' => 'Falsches Array in der Liste der Datei-Parameter',
  'The field type %s cannot be in the pre-filter' => 'Ein Feld vom Typ %s kann nicht im Vorfilter enthalten sein',
  'Crypto.key file not exists' => 'Die Datei Crypto.key existiert nicht',
  'Service does not accept more than 10 files' => 'Der Dienst akzeptiert nicht mehr als 10 Dateien',
  'Number of elements %s and %s do not match' => 'Anzahl der Elemente %s und %s stimmen nicht überein',
  'PDF printing for this table is switched off' => 'Der PDF-Druck für diese Tabelle ist ausgeschaltet',
  'The code for the specified button is not found. Try again.' => 'Der Code für die angegebene Schaltfläche wird nicht angewendet. Versuchen Sie es erneut.',
  'Check that the ttm__search field type in table %s is data' => 'Prüfen Sie, ob der Feldtyp ttm__search in Tabelle %s Daten ist',
  'The file table was not found.' => 'Tabelle der Datei nicht gefunden.',
  'The file path is not formed correctly.' => 'Der Dateipfad ist nicht korrekt gebildet.',
  'The file is not protected' => 'Die Datei ist nicht geschützt',
  'Access to the file field is denied' => 'Der Zugriff auf das Dateifeld ist verboten',
  'Access to the file row is denied or the row does not exist' => 'Der Zugriff auf die Zeichenkette wird verweigert oder die Zeichenkette existiert nicht',
  'The file field was not found' => 'Datei-Feld nicht gefunden',
  'The file does not exist on the disk' => 'Die Datei existiert nicht auf dem Datenträger',
  'File name parsing error' => 'Fehler beim Parsen von Dateinamen',
  'The fileDuplicateOnCopy option must be enabled for secure files.' => 'Die Option fileDuplicateOnCopy muss für geschützte Dateien aktiviert sein.',
  'DB connection by name %s was not found.' => 'Es wurde keine Verbindung zu der Datenbank mit dem Namen %s gefunden.',
  'DB connection by hash %s was not found.' => 'Es wurde keine Verbindung zu dem Datenbank-Hash %s gefunden.',
  'Authorization type' => 'Art der Autorisierung',
  'Password recovering is not possible for users with special auth types' => 'Die Wiederherstellung des Passworts ist für Benutzer mit speziellen Berechtigungen nicht möglich',
  'LDAP extension php not enabled' => 'LDAP php-Erweiterung nicht aktiviert',
  'Set the binding format in the LDAP settings table' => 'Legen Sie das Bin-Format in der LDAP-Einstellungstabelle fest',
  'Set the host in the LDAP settings table' => 'Einstellen des Hosts in der LDAP-Konfigurationstabelle',
  'Set the port in the LDAP settings table' => 'Legen Sie den Anschluss in der LDAP-Konfigurationstabelle fest',
  'The function is not available' => 'Die Funktion ist nicht verfügbar',
  'Invalid parameter name' => 'Ungültiger Parametername',
  'Min value of %s is %s.' => 'Der Mindestwert von %s - %s.',
  'User is switched off or does not have access rights' => 'Der Benutzer ist deaktiviert oder hat keine Zugriffsrechte',
  'The parameter [[%s]] should be of type row.' => 'Der Parameter [[%s]] muss vom Typ row sein.',
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