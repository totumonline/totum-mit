<?php

namespace totum\common\Lang;

use DateTime;

class EN implements LangInterface
{
    use TranslateTrait;
    use SearchTrait;

    public const TRANSLATES = array (
  'Deleting' => 'Removal',
  'Not found [[%s]] for the [[%s]] parameter.' => 'Not found [[%s]] for parameter [[%s]].',
  'No [[%s]] is specified for the [[%s]] parameter.' => 'No [[%s]] specified for parameter [[%s]].',
  'Parametr [[%s]] is required in [[%s]] function.' => 'Parameter [[%s]] is required in function [[%s]].',
  'The function is only available for the Creator role.' => 'Function available only to Creator role.',
  'The function is not available to you.' => 'Function unavailable.',
  'Scheme string is empty.' => 'Schema string is empty.',
  'The function is only available for cycles tables.' => 'Function available only for cycle tables.',
  'Using a comparison type in a filter of list/row is not allowed' => 'Using comparison type for filtering list/row is not allowed',
  'Using a comparison type in a search in list/row is not allowed' => 'Using comparison type in list/row search is not allowed',
  'Field data type error' => 'Invalid data type in field',
  'Not correct field name in query to [[%s]] table.' => 'Incorrect field name in query to table [[%s]].',
  'You see the contents of the table calculated and saved before the last transaction with the error.' => 'You see the table contents calculated and saved before the last transaction error.',
  'Field [[%s]] of table [[%s]] in row with id [[%s]] contains non-numeric data' => 'Field [[%s]] of table [[%s]] in row with id [[%s]] contains non-numeric information',
  'Scheme source not defined.' => 'Source of schema not defined.',
  'Parametr [[%s]] is required.' => 'Parameter [[%s]] is required.',
  'The parameter [[%s]] should be of type row/list.' => 'Parameter [[%s]] must be of type row/list.',
  'The parameter [[%s]] of [[%s]] should be of type row/list.' => 'Parameter [[%s]] in [[%s]] must be of type row/list.',
  'The parameter [[%s]] should be of type true/false.' => 'Parameter [[%s]] must be of type true/false.',
  'The parameter [[%s]] should [[not]] be of type row/list.' => 'Parameter [[%s]] must not be of type row/list.',
  'The parameter [[%s]] should be of type string.' => 'Parameter [[%s]] must be of type string.',
  '[[%s]] should be of type string.' => '[[%s]] must be a string.',
  'The cycles table is specified incorrectly.' => 'The cycle table is incorrect.',
  'Comparing not numeric string or lists with number field' => 'Comparison of non-numeric strings or lists with a numeric field',
  'You cannot create query to PostgreSql with 65000 and more parameters.' => 'You cannot create a query to PostgreSQL with >= 65000 parameters.',
  'For temporary tables only.' => 'For temporary tables only',
  'For temporary tables forms only.' => 'Only for forms based on temporary tables.',
  'For simple and cycles tables only.' => 'Only for simple tables and cycle tables.',
  'The table has no n-sorting.' => 'The table lacks n-sorting.',
  'The table [[%s]] has no n-sorting.' => 'Table [[%s]] lacks n-sorting.',
  'The %s should be here.' => '%s should be here.',
  'Parametr [[%s]] is required and should be a number.' => 'Parameter [[%s]] is required and must be a number.',
  'Parametr [[%s]] is required and should be a string.' => 'Parameter [[%s]] is required and must be a string.',
  'The %s parameter should not be an array.' => 'Parameter %s must not be an array.',
  'The %s field value should not be an array.' => 'The value of field %s must not be an array.',
  'The value of the number field should not be an array.' => 'The value of a field with type Number should not be an array.',
  'The %s parameter must be a number.' => 'Parameter %s must be a number.',
  'The module is not available for this host.' => 'Module unavailable for this host.',
  'The [[%s]] parameter is not correct.' => 'Parameter [[%s]] is incorrect.',
  'Comment field contains incorrect type data as a value.' => 'Comment field contains data of incorrect type.',
  'The [[%s]] parameter must be plain row/list without nested row/list.' => 'Parameter [[%s]] must be a simple row/list without nested row/list.',
  'Calling a third-party script.' => 'External script call.',
  'Not for the temporary table.' => 'Not for temporary table.',
  'The [[%s]] field is not found in the [[%s]] table.' => 'Field [[%s]] not found in table [[%s]].',
  'Function [[linkToEdit]] not available for [[%s]] field type.' => 'Function [[linkToEdit]] not available for field type [[%s]].',
  'The %s field must be numeric.' => 'Field %s must be numeric.',
  'The value of the %s field must be numeric.' => 'The value of the field %s must be numeric.',
  'For selecting by numeric field [[%s]] you must pass numeric values' => 'A number must be provided for filtering by numeric field [[%s]]',
  'The value of %s field must match the format: %s' => 'The value of the field %s must match the format: %s',
  'The row with %s was not found in table %s.' => 'Row with %s not found in table %s.',
  'The row %s does not exist or is not available for your role.' => 'Row %s does not exist or is not accessible for your role.',
  'For lists comparisons, only available =, ==, !=, !==.' => 'Only =, ==, !=, !== are available for list comparison.',
  'There should be a date, not a list.' => 'It should be a date, not a list.',
  'There must be only one comparison operator in the string.' => 'There should be only one comparison operator in the line.',
  'TOTUM-code format error [[%s]].' => 'TOTUM code format error [[%s]].',
  'XML Format Error.' => 'XML format error.',
  'Code format error - no start section.' => 'Code format error - missing start section.',
  'The [[catch]] code of line [[%s]] was not found.' => 'Line [[catch]] of code [[%s]] not found.',
  'Database connect error. Try later. [[%s]]' => 'Database connection error. Try again later. [[%s]]',
  'Critical error while processing [[%s]] code.' => 'Critical error processing code [[%s]].',
  'field [[%s]] of [[%s]] table' => 'field [[%s]] of table [[%s]]',
  'Error: %s' => 'Error %s',
  'You cannot use linktoDataTable outside of actionCode without hide:true.' => 'Cannot use linktoDataTable outside action code without hide:true.',
  'Non-numeric parameter in the list %s' => 'Non-numeric parameter in list %s',
  'The [[%s]] parameter must be set to one of the following values: %s' => 'Parameter [[%s]] must take one of the values: %s',
  'Function [[%s]] is not found.' => 'Function [[%s]] not found.',
  'Table [[%s]] is not found.' => 'Table [[%s]] not found.',
  'Table is not found.' => 'Table not found.',
  'Max value of %s is %s.' => 'Maximum value of parameter %s is %s',
  'May be insert row has expired.' => 'The addition row\'s lifespan may have expired.',
  'The storage time of the temporary object has expired.' => 'Temporary object storage time expired.',
  'File [[%s]] is not found.' => 'File [[%s]] not found.',
  'Cycle [[%s]] is not found.' => 'Cycle [[%s]] not found.',
  'Cycle [[%s]] in table [[%s]] is not found.' => 'Cycle [[%s]] in table [[%s]] not found.',
  'TOTUM-code format error: missing operator in expression [[%s]].' => 'TOTUM code format error: missing operator in expression [[%s]].',
  'TOTUM-code format error: missing part of parameter.' => 'TOTUM code format error: missing part of parameter.',
  'No key %s was found in the data row.' => 'Key %s not found in data string',
  'There is no [[%s]] key in the [[%s]] list.' => 'No key [[%s]] exists in list [[%s]].',
  'Parameter [[%s]] returned a non-true/false value.' => 'Parameter [[%s]] returned a value other than true/false.',
  'The [[%s]] parameter must contain 2 elements.' => 'Parameter [[%s]] must contain 2 elements.',
  'The [[%s]] parameter must contain 3 elements.' => 'Parameter [[%s]] must contain 3 elements.',
  'The %s parameter must contain a comparison element.' => 'Parameter %s must include a comparison element.',
  'Code [[%s]] was not found.' => 'Code [[%s]] not found.',
  'Code line [[%s]].' => 'Line of code [[%s]].',
  'Previous row not found. Works only for calculation tables.' => 'Previous row not found. Works only for spreadsheets.',
  'Cannot access the current value of the field from the Code.' => 'In code of type Code (field value calculation), you cannot refer to the current field value.',
  'Field [[%s]] is not found.' => 'Field [[%s]] not found.',
  'The key [[%s]] is not found in one of the array elements.' => 'Key [[%s]] not found in one of the array elements.',
  'There must be two [[%s]] parameters in the [[%s]] function.' => 'There should be two parameters [[%s]] in the function [[%s]].',
  'The [[%s]] parameter must be [[%s]].' => 'Parameter [[%s]] must be [[%s]].',
  'The [[%s]] parameter must [[not]] be [[%s]].' => 'Parameter [[%s]] [[must not]] be [[%s]].',
  'The number of the [[%s]] must be equal to the number of [[%s]].' => 'The number of [[%s]] must equal the number of [[%s]].',
  'The [[%s]] parameter must be one type with [[%s]] parameter.' => 'Parameter [[%s]] must be of the same type as parameter [[%s]].',
  'For selecting by %s field should be passed only single value or list, not row' => 'For selection by %s, the field should pass only one value or a list, not a row.',
  'The value by %s key is not a row/list' => 'Value by key %s is not a row or list',
  'The key must be an one value' => 'Key must be a single value',
  'There is no NowField enabled in this type of code. We\'ll fix it - write us.' => 'In this type of code, nowField is not connected.',
  '[[%s]] is available only for the calculation table in the cycle.' => '[[%s]] available only for calculation table in cycle.',
  'The ExecSSH function is disabled. Enable execSSHOn in Conf.php.' => 'execSSH function is disabled. Enable execSSHOn in Conf.php',
  'Ssh:true in exec function is disabled. Enable execSSHOn in Conf.php.' => 'Parameter ssh:true is off. Enable execSSHOn in Conf.php',
  'The [[%s]] parameter has not been set in this code.' => 'Parameter [[%s]] was not set in this code.',
  'None of the elements of the %s parameter array must be a list.' => 'None of the elements in the %s parameter array should be a list.',
  'Parameter %s must contain list of numbers' => 'Parameter %s must contain a list of numbers',
  'The array element does not fit the filtering conditions - the value is not a list.' => 'Array element does not meet filter conditions — value is not a list.',
  'The array element does not fit the filtering conditions - [[item]] is not found.' => 'Array element does not meet filter conditions — [[item]] not found.',
  '[[%s]] is not a multiple parameter.' => '[[%s]] — is not a multiple parameter.',
  'Not found template [[%s]] for parameter [[%s]].' => 'Template [[%s]] not found for parameter [[%s]].',
  'No template is specified for [[%s]].' => 'Template not specified for parameter [[%s]].',
  'The unpaired closing parenthesis.' => 'Unmatched closing bracket.',
  'JSON generation error: [[%s]].' => 'JSON formation error: [[%s]].',
  'The code should return [[%s]].' => 'Code should return [[%s]].',
  'The [[insert]] field should return list - Table [[%s]]' => 'Field [[insert]] should return a list — Table [[%s]]',
  'The [[insert]] field should return a list with unique values - Table [[%s]]' => 'Field [[insert]] should return a list with unique values — Table [[%s]]',
  'This value is not available for entry in field %s.' => 'This value is not available for input in the %s field.',
  'Format sections' => 'Formatting Sections',
  'The schema is not connected.' => 'Scheme not connected.',
  'Error accessing the anonymous tables module.' => 'Access error to anonymous tables module.',
  'Order field calculation errors' => 'Calculation order errors or accessing fields of deleted rows',
  'in %s table in fields:' => 'in table %s in fields:',
  'Settings for sending mail are not set.' => 'Mail sending settings not configured.',
  'The path to ssh script %s is not set.' => 'SSH script path %s not set.',
  'Error generating JSON response to client [[%s]].' => 'Error generating JSON response on client [[%s]].',
  'Rows part' => 'Row part',
  'User %s is not configured. Contact your system administrator.' => 'User %s not configured. Contact system admin.',
  'Table [[%s]] was changed. Update the table to make the changes.' => 'The table [[%s]] has been modified. Update the table to apply the changes.',
  'Table was changed' => 'Table was modified',
  'The anchor field settings are incorrect.' => 'Anchor field settings are incorrect.',
  'Field type is not defined.' => 'Field type not defined.',
  'Table type is not defined.' => 'Table type not defined.',
  'The [[%s]] table type is not connected to the system.' => 'Table type [[%s]] is not connected to the system.',
  'Unsupported channel [[%s]] is specified.' => 'Unsupported channel [[%s]] specified.',
  'Field [[%s]] of table [[%s]] is required.' => 'Field [[%s]] in table [[%s]] is required.',
  'Scheme file not found.' => 'Schema file not found.',
  'Scheme file is empty' => 'Schema file empty',
  'Wrong format scheme file.' => 'Schema file is in wrong format.',
  'Translates file not found.' => 'Translation file not found.',
  'Translates file is empty' => 'Translation file is empty',
  'Wrong format file' => 'Invalid file format',
  'Administrator' => 'Admin',
  'The type of the loaded table [[%s]] does not match.' => 'Mismatch of uploaded table type [[%s]].',
  'The cycles table for the adding calculation table [[%s]] is not set.' => 'Cycle table not set for the added calculation table [[%s]].',
  'The format of the schema name is incorrect. Small English letters, numbers and - _' => 'Schema name format is incorrect. Lowercase Latin letters, digits, and - _',
  'A scheme exists - choose another one to install.' => 'Scheme exists — choose another for installation.',
  'You can\'t install totum in schema "public"' => 'Totum cannot be installed in the public schema',
  'Error saving file %s' => 'File save error %s',
  'A nonexistent [[%s]] property was requested.' => 'Requested non-existent property [[%s]].',
  'Import from csv is not available for [[%s]] field.' => 'Import from CSV is unavailable for field [[%s]].',
  'Export via csv is not available for [[%s]] field.' => 'Export via CSV is unavailable for field [[%s]].',
  'You do not have access to csv-import in this table' => 'You don\'t have access for CSV import in this table',
  'Date format error: [[%s]].' => 'Date format is incorrect: [[%s]].',
  '[[%s]] format error: [[%s]].' => 'Format [[%s]] is incorrect: [[%s]].',
  '[[%s]] is reqired.' => '[[%s]] is required.',
  'You cannot create a [[footer]] field for [[non-calculated]] tables.' => 'Cannot create a [[footer]] field [[not for calculation]] tables.',
  'File > ' => 'File larger',
  'File not received. May be too big.' => 'File not received. Size might be too large.',
  'The data format is not correct for the File field.' => 'Data format not suitable for File field.',
  'Restricted to add executable files to the server.' => 'Executable files on the server are prohibited.',
  'Failed to copy a temporary file.' => 'Failed to copy temp file.',
  'Error copying a file to the storage folder.' => 'File copy error to storage folder.',
  'Changed' => 'Modified',
  ' elem.' => 'elem.',
  'Operation [[%s]] over lists is not supported.' => 'Operation [[%s]] on list is not supported.',
  'Operation [[%s]] over not mupliple select is not supported.' => 'Operation [[%s]] on non-multi Select is not supported.',
  'Text modified' => 'Text changed',
  'Text unchanged' => 'Text matches',
  'The looped tree' => 'Looped tree',
  'The value must be unique. Duplication in rows: [[%s - %s]]' => 'Value must be unique. Duplicate in rows: [[%s - %s]]',
  'There is no default version for table %s.' => 'No default version for table %s.',
  'The name of the field cannot be new_field' => 'Field name cannot be new_field',
  'You cannot delete system tables.' => 'System tables cannot be deleted.',
  'You cannot delete system fields.' => 'System fields cannot be deleted.',
  'The [[%s]] field is already present in the table.' => 'Field [[%s]] already exists in the table.',
  'The [[%s]] field is already present in the [[%s]] table.' => 'Field [[%s]] already exists in table [[%s]].',
  'You can\'t make a boss of someone who is in a subordinate' => 'You can\'t make a subordinate a boss',
  'Method [[%s]] in this module is not defined or has admin level access.' => 'Method [[%s]] in this module is not defined or has admin-level access.',
  'Your access to this table is read-only. Contact administrator to make changes.' => 'Your access to this table is read-only. Contact the administrator to make changes.',
  'Access to the table is denied.' => 'Access to table denied.',
  'Form is not found.' => 'Form not found',
  'Access to tables in a cycle through this module is not available.' => 'Access to tables in the cycle through this module is unavailable.',
  '%s table forms' => 'Table forms %s',
  'This is not a simple table. Quick forms are only available for simple tables.' => 'This is not a Simple table. Quick forms are only available for Simple tables.',
  'The quick table is not available in read-only mode.' => 'Quick table unavailable in read-only mode.',
  'The form requires link parameters to work.' => 'Link parameters are required for the form to work.',
  'Incorrect link parameters' => 'Invalid link parameters',
  'Access to the cycle is denied.' => 'Access to the loop is denied.',
  'Wrong path to the table' => 'Invalid table path',
  'Wrong path to the form' => 'Invalid form path',
  'Write access to the table is denied' => 'Write access to table denied',
  'Service is optimized for desktop browsers Chrome, Safari, Yandex latest versions. It seems that your version of the browser is not supported. Error - for developers: ' => 'Service optimized for latest Chrome, Mozilla, Safari. Your browser version seems unsupported. Error info:',
  'Fill in the Login/Email field' => 'Enter Login/Email',
  'Fill in the %s field' => 'Fill in the %s',
  'Fill in the Password field' => 'Enter Password',
  'Password is not correct' => 'Password is incorrect',
  'Due to exceeding the number of password attempts, your IP is blocked' => 'Due to exceeding the number of password attempts, your IP is temporarily blocked.',
  'Password recovery via email is disabled for this database. Contact the solution administrator.' => 'Password recovery via email for this database is disabled. Contact the solution administrator.',
  'Email for this login is not set' => 'Email for this login not set',
  'An email with a new password has been sent to your Email. Check your inbox in a couple of minutes.' => 'The email with your new password has been sent. Check your inbox in a few minutes.',
  'Letter has not been sent: %s' => 'Email not sent: %s',
  'The user with the specified Login/Email was not found' => 'User with specified Login/Email not found',
  'To work with the system you need to enable JavaScript in your browser settings' => 'To use the system, enable JavaScript in your browser settings.',
  'It didn\'t load :(' => 'Didn\'t load :(',
  'Forms user authorization error' => 'User authorization error with Forms access',
  'Conflicts of access to the table error' => 'Concurrent table access error',
  'Form configuration error - user denied access to the table' => 'Form setup error - user denied table access',
  'The [[%s]] field was not found. The table structure may have changed. Reload the page.' => 'Field [[%s]] not found. The table structure might have changed. Reload the page.',
  'Conf.php was created successfully. Connection to the database is set up, the start scheme is installed. You are authorized under specified login with the role of Creator. Click the link or refresh the page.' => 'Conf.php created successfully. Database connection set up, initial schema installed. You are logged in with the role Creator. Follow the link or refresh the page.',
  'Have a successful use of the system' => 'Successful system use',
  'Json not received or incorrectly formatted' => 'JSON not received or malformed',
  'A database transaction was closed before the main process was completed.' => 'The database transaction was closed before the main process completed.',
  'No auth section found' => 'Auth section not found',
  'The login attribute of the auth section was not found' => 'Attribute login in auth section not found',
  'The password attribute of the auth section was not found' => 'Attribute password in auth section not found',
  'The user with this data was not found. Possibly the xml/json interface is not enabled.' => 'User with such data not found. Access to XML/JSON interface might not be enabled.',
  'The recalculate section must contain restrictions in the format [["field":FIELDNAME,"operator":OPERATOR,"value":VALUE]]' => 'The recalculate section should contain constraints in the format [["field":FIELDNAME,"operator":OPERATOR,"value":VALUE]]',
  'The field is not allowed to be edited through the api or does not exist in the specified category' => 'Field is not editable via API or does not exist in the specified category',
  'Multiple/Single value type error' => 'Value type error multiple/single',
  'In the export section, specify "fields":[] - enumeration of fields to be exported' => 'In the export section, specify "fields":[] — list fields for export output',
  'Incorrect where in the rows-set-where section' => 'Incorrectly formatted where in rows-set-where section',
  'Without a table in the path, only the remotes section works' => 'Only the remotes section works without specifying the table in the path',
  'Remote {var} does not exist or is not available to you' => 'Remote {var} does not exist or is not accessible for your role',
  'The name for remote is not set' => 'No name set for remote',
  'Field [[%s]] is not allowed to be added via Api' => 'Field [[%s]] is not allowed for addition via API',
  'Field [[%s]] is not allowed to be edited via Api' => 'Field [[%s]] is read-only via API',
  'The [[%s]] field must contain multiple select' => 'Field [[%s]] must contain a multi-select',
  'The [[%s]] field must contain a string' => 'Field [[%s]] must contain a string',
  'The %s field in %s table does not exist' => 'Field %s in %s table does not exist',
  'You are not allowed to add to this table' => 'You can\'t add to this table with your role',
  'You are not allowed to delete from this table' => 'You can\'t delete from this table with your role',
  'You are not allowed to sort in this table' => 'Sorting in this table is unavailable for your role',
  'You are not allowed to duplicate in this table' => 'Duplication in this table is unavailable for your role',
  'You are not allowed to restore in this table' => 'Restoration in this table is unavailable for your role',
  'Authorization error' => 'User authorization error',
  'Remote is not connected to the user' => 'Remote not connected to user',
  'Remote is not active or does not exist' => 'Remote is inactive or doesn\'t exist',
  'Choose a table' => 'Select table',
  'The choice is outdated.' => 'The proposed choice is outdated.',
  'The proposed input is outdated.' => 'The input is outdated.',
  'Changing the name of a field' => 'Changing field name',
  'Fill in title' => 'Fill in the title',
  'Csv download of this table is not allowed for your role.' => 'CSV export of this table is not allowed for your role.',
  'The name of the field is not set.' => 'Field name not set',
  'Access to the field is denied' => 'Access to field denied',
  'Access to edit %s field is denied' => 'Access to edit field %s is denied',
  'Interface Error' => 'Interface error',
  'Temporary table storage time has expired' => 'Temporary table storage time expired',
  'Field not of type select/tree' => 'Field is not a Select/Tree type field',
  'Field not of type comments' => 'Field is not of type Comments',
  'The tree index is not passed' => 'Tree index not provided',
  'Access to the logs is denied' => 'Access to logs denied',
  'No manual changes were made in the field' => 'No manual changes made to the field',
  'Failed to get branch Id' => 'Branch ID retrieval error',
  'Add row out of date' => 'Add line is outdated',
  'Log of manual changes by field "%s"' => 'Manual change log for field "%s"',
  'Calculating the table' => 'Table calculation',
  'Table %s. DUPLICATION CODE' => 'Table %s. CODE FOR DUPLICATION',
  'Incorrect encoding of the file (should be utf-8 or windows-1251)' => 'Incorrect file encoding (should be utf-8 or windows-1251)',
  'Loading file of table %s into table [[%s]]' => 'Loading file %s into table [[%s]]',
  'in row %s' => 'in line %s',
  'no table change code' => 'missing table change code',
  'no structure change code' => 'missing structure change code',
  'The structure of the table was changed. Possibly a field order mismatch.' => 'Table structure changed. Field order may differ.',
  'no indication of a cycle' => 'no cycle indication',
  'Table from another cycle or out of cycles' => 'Table from another cycle or outside cycles',
  'There is no calculation table in [[%s]] cycles table.' => 'In the cycles table [[%s]], there is no calculation table.',
  'Out of cycles' => 'Outside cycles',
  'Manual Values' => 'Manual values',
  'there is no Manual Values section header' => 'Manual Values section title missing',
  'no 0/1/2 edit switch' => 'missing 0/1/2 edit switch',
  'no section header %s' => 'section title %s missing',
  'no filter data' => 'missing filter data',
  'on the line one line after the Rows part is missing the header of the Footer section' => 'in the row every other after the Rows part, the Footer section header is missing',
  '[0: do not modify calculated fields] [1: change values of calculated fields already set to manual] [2: change calculated fields]' => '[0: do not process calculated fields] [1: change values of calculated fields with manual input] [2: change calculated fields]',
  'More than 20 nesting levels of table changes. Most likely a recalculation loop' => 'More than 20 levels of table modification nesting. Most likely, this is a recalculation loop.',
  'The field is not configured.' => 'Field not set',
  'No select field specified' => 'Field for selection not specified',
  'More than one field/sfield is specified' => 'More than one field/sfield specified',
  'The %s function is not provided for this type of tables' => 'The %s function is not supported for this type of tables',
  'Field [[%s]] in table [[%s]] is not a column' => 'Field [[%s]] in table [[%s]] is not a table column',
  'In the %s parameter you must use a list by the number of rows to be changed or not a list.' => 'In the where parameter, use a list matching the number of modifiable rows or another value type.',
  'The function is used to change the rows part of the table.' => 'The function is used to modify the rows of the table.',
  'Incorrect interval [[%s]]' => 'Invalid interval [[%s]]',
  'The calculation table is not connected to %s cycles table' => 'Calculation table not linked to cycle table %s',
  'Button to the cycle' => 'Button in cycle',
  'First you have to delete the cycles table, and then the calculation tables inside it' => 'First, delete the cycle table, then the calculation tables within it.',
  'No line-by-line updates are provided for the calculation tables. They are recalculated in whole' => 'Row-by-row updates are not available for calculation tables. They are recalculated entirely.',
  'The row with id %s in the table already exists. Cannot be added again' => 'Row with id %s already exists in the table. Cannot add again',
  'The [[%s]] field in the rows part of table [[%s]] does not exist' => 'Field [[%s]] in the row part of the table [[%s]] does not exist',
  'Client side error. Received row instead of id' => 'Client-side error. String received instead of id.',
  'Client side error' => 'Client-side error',
  'Adding row error' => 'Row addition error',
  'The Parameters field type is valid only for the Tables Fields table' => 'Field type Parameters allowed only for table Field composition',
  'Data parameter  / data values must be numeric.' => 'Parameter data / its nested values must be numeric',
  'An invalid value for id filtering was passed to the select function.' => 'An invalid value was passed to the select function for filtering by id.',
  'Value format error in id %s row field %s' => 'Value format error in row id %s field %s',
  'Not correct row in files list' => 'Incorrect array in list in files',
  'The field type %s cannot be in the pre-filter' => 'Field of type %s cannot be in prefilter',
  'Crypto.key file not exists' => 'File Crypto.key does not exist',
  'Service does not accept more than 10 files' => 'Service doesn\'t accept more than 10 files',
  'Number of elements %s and %s do not match' => 'Number of elements in %s and %s do not match',
  'PDF printing for this table is switched off' => 'PDF printing for this table is disabled',
  'The code for the specified button is not found. Try again.' => 'The button code is not found. Please try again.',
  'Check that the ttm__search field type in table %s is data' => 'Check that the ttm__search field in the %s table is of type Data',
  'The file table was not found.' => 'File table not found.',
  'The file path is not formed correctly.' => 'File path is incorrectly formatted.',
  'The file is not protected' => 'File is not secure',
  'Access to the file field is denied' => 'File field access denied',
  'Access to the file row is denied or the row does not exist' => 'Access to the file line is denied or the line does not exist',
  'The file field was not found' => 'File field not found',
  'The file does not exist on the disk' => 'File does not exist on disk',
  'The fileDuplicateOnCopy option must be enabled for secure files.' => 'The fileDuplicateOnCopy option must be enabled for secured files.',
  'DB connection by name %s was not found.' => 'Connection to DB with name %s not found.',
  'DB connection by hash %s was not found.' => 'Connection to DB with hash %s not found.',
  'Authorization type' => 'Auth Type',
  'Password recovering is not possible for users with special auth types' => 'Password recovery is not possible for users with special authorization types.',
  'LDAP extension php not enabled' => 'LDAP extension for PHP not enabled',
  'Set the binding format in the LDAP settings table' => 'Set bind format in LDAP settings table',
  'Set the host in the LDAP settings table' => 'Set host in LDAP settings table',
  'Set the port in the LDAP settings table' => 'Set port in LDAP settings table',
  'The function is not available' => 'Function unavailable',
  'Min value of %s is %s.' => 'Minimum value of %s is %s.',
  'User is switched off or does not have access rights' => 'User disabled or no access rights',
  'The parameter [[%s]] should be of type row.' => 'Parameter [[%s]] must be of type row.',
  'The fileDuplicateOnCopy option must be enabled for versioned files.' => 'The fileDuplicateOnCopy parameter must be enabled for versioned files.',
  'Version adding error - file for version not found' => 'Version add error - file not found',
  'The time to delete/replace the last file version has expired' => 'Time to delete/replace the latest file version has expired',
  'File %s versions' => 'File versions %s',
  'Creator warnings' => 'Admin Notifications',
  'BFL-log is on' => 'Error and external request log enabled',
  'list-ubsubscribe-link-text' => 'Unsubscribe',
  'list-ubsubscribe-Blocked-from-sending' => 'Sending to this email is blocked',
  'list-ubsubscribe-done' => 'Done',
  'list-ubsubscribe-wrong-link' => 'Invalid link',
  'There is no access to excel-import in this table' => 'No access to Excel import in this table',
  'The [[%s]] must be equal to the [[%s]].' => '[[%s]] must equal [[%s]].',
  'Token is not exists or is expired' => 'Token does not exist or has expired',
  'This is a service user. He cannot be authorized by a token' => 'This is a service user. Cannot be authorized by token.',
  'This user have Creator role. He cannot be authorized by a token' => 'This user has the Creator role. They cannot be authorized by token.',
  'This is not web user. He cannot be authorized by a token' => 'This is not a web user. He cannot be authorized by token.',
  'The OnlyOffice service table was successfully created. Repeat the operation.' => 'OnlyOffice service table successfully created. Repeat the operation.',
  'File key is not exists or is expired' => 'File key does not exist or has expired',
  'OnlyOfficeSaveTimeoutError' => 'Unable to save due to no changes in the document. If you\'re editing an xlsx or another table, first press enter to save data in the edited cell or move the focus to another cell.',
  'Permission is denied for selected user' => 'Selected user denied access',
  'New secret code was sent' => 'New secret code sent',
  'You can\'t resend the secret yet.' => 'Resending the secret code is currently unavailable',
  'Wrong secret code' => 'Incorrect secret code',
  'Resend secret' => 'Resend secret code',
  'You can resend a secret via <span></span> sec' => 'You can resend the code in <span></span> sec',
  'Recalculate cycle with id %s before export.' => 'Recalculate the cycle with id %s before exporting.',
  'TOTUM-HELP-LINKS' => '[["📕 Documentation","https://docs.totum.online/"],["📗 User Guide Basics","https://docs.totum.online/user-guide"],["🚀 PRO Version Licenses","https://totum.online/pro"],["🤖 Totum AI","https://totum.online/ai"]]',
  'Tree nesting error' => 'Tree nesting error. A child element cannot be a parent.',
  'Wrong [[%s]] value' => 'Invalid value in [[%s]]',
);
	public function dateFormat(DateTime $date, $fStr): string
    {
        $result = '';
        foreach (preg_split(
                     '/([f])/',
                     $fStr,
                     -1,
                     PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
                 ) as $split) {
            switch ($split) {
                /** @noinspection PhpMissingBreakStatementInspection */ case 'f':
                $result .= ' of ';
                $split = 'F';
                default:
                    $result .= $date->format($split);
            }
        }
        return $result;
    }

    public function num2str($num): string
	{
    return (string) $num;
	}


    public function smallTranslit($s): string
    {
        return strtr(
            $s,
            [
			'ß'=>'ss', 'ä'=>'a', 'ü'=>'u', 'ö'=>'o',
			'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
			'ñ'=>'ny',
			'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'ъ' => '', 'ь' => '']
        );
    }
}