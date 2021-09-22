<?php

namespace totum\common\Lang;

use DateTime;

class RU implements LangInterface
{
    use TranslateTrait;

    public const TRANSLATES = [
        'Deleting' => 'Удаление',
        'Not found: %s' => 'Не найдено: %s',
        'User not found' => 'Пользователь не найден',
        'Not found [[%s]] for the [[%s]] parameter.' => 'Не найден [[%s]] для параметра [[%s]].',
        'Template not found.' => 'Шаблон не найден.',
        'No [[%s]] is specified for the [[%s]] parameter.' => 'Не указан [[%s]] для параметра [[%s]].',
        'Parametr [[%s]] is required in [[%s]] function.' => 'Параметр [[%s]] является обязательным в функции [[%s]].',

        'The function is only available for the Creator role.' => 'Функция доступна только роли Создатель.',
        'The function is not available to you.' => 'Функция вам недоступна.',
        'Password doesn\'t match.' => 'Пароль не подходит.',
        'Scheme string is empty.' => 'Строка схемы пуста.',
        'The function is only available for cycles tables.' => 'Функция доступна только для таблиц циклов.',

        'System error. Action type not specified.' => 'Системная ошибка. Не указан тип действия.',
        'Scheme source not defined.' => 'Не определен источник схемы.',
        'Fill in the parameter [[%s]].' => 'Заполните параметр [[%s]].',
        'Parametr [[%s]] is required.' => 'Параметр [[%s]] обязателен.',
        'Each button must contain [[%s]].' => 'Каждая кнопка должна содержать [[%s]].',
        'The parameter [[%s]] should be of type row/list.' => 'Параметр [[%s]] должен быть типа row/list.',
        'The parameter [[%s]] should be of type true/false.' => 'Параметр [[%s]] должен быть типа true/false.',
        'The parameter [[%s]] should [[not]] be of type row/list.' => 'Параметр [[%s]] [[не]] должен быть типа row/list.',
        'The parameter [[%s]] should be of type string.' => 'Параметр [[%s]] должен быть типа строка.',
        'The cycles table is specified incorrectly.' => 'Таблица циклов указана неверно.',


        'For temporary tables only.' => 'Только для временных таблиц',
        'For simple and cycles tables only.' => 'Только для простых таблиц и таблиц циклов.',
        'The table has no n-sorting.' => 'Таблица не имеет n-сортировки.',
        'The table [[%s]] has no n-sorting.' => 'Таблица [[%s]] не имеет n-сортировки.',
        'The %s should be here.' => 'Здесь должен быть %s.',

        'Parametr [[%s]] is required and should be a number.' => 'Параметр [[%s]] обязателен и должен быть числом.',
        'Parametr [[%s]] is required and should be a string.' => 'Параметр [[%s]] обязателен и должен быть строкой.',
        'The %s parameter is required and must start with %s.' => 'Параметр %s обязателен и должен начитаться с %s.',
        'The %s parameter should not be an array.' => 'Параметр %s не должен быть массивом.',
        'The %s parameter must be a number.' => 'Параметр %s должен быть числом.',
        'The [[%s]] parameter is not correct.' => 'Параметр [[%s]] не корректен.',


        'Calling a third-party script.' => 'Обращение к стороннему скрипту.',
        'Not for the temporary table.' => 'Не для временной таблицы.',
        'The [[%s]] field is not found in the [[%s]] table.' => 'Поле [[%s]] не надено в таблице [[%s]].',
        'The %s field must be numeric.' => 'Поле %s должно быть числовым.',
        'The value of the %s field must be numeric.' => 'Значение поля %s должно быть числовым.',
        'A number must be passed to select by the numeric field [[%s]]' => 'Для выборки по числовому полю [[%s]] должно быть передано число',


        'The value of %s field must match the format: %s' => 'Значение поля %s должно соответствовать формату: %s',
        'The row with %s was not found in table %s.' => 'Строка с %s не найдена в таблице %s.',
        'Row not found' => 'Строка не найдена',
        'Row %s not found' => 'Строка %s не найдена',
        'The string %s does not exist or is not available for your role.' => 'Строка %s не существует или недоступна для вашей роли.',


        'For lists comparisons, only available =, ==, !=, !==.' => 'Для сравнения листов доступны только =, ==, !=, !==.',
        'There should be a date, not a list.' => 'Должна быть дата, а не список.',
        'There must be only one comparison operator in the string.' => 'В строке должен быть только один оператор сравнения.',
        'TOTUM-code format error [[%s]].' => 'Ошибка формата ТОТУМ-кода [[%s]].',
        'XML Format Error.' => 'Ошибка формата XML.',
        'Code format error - no start section.' => 'Ошибка формата кода - нет стартовой секции.',
        'The [[catch]] code of line [[%s]] was not found.' => 'Строка [[catch]] кода [[%s]] не найдена.',
        'ERR!' => 'ОШБК!',
        'Database error: [[%s]]' => 'Ошибка базы данных: [[%s]]',
        'Database connect error. Try later. [[%s]]' => 'Ошибка подключения к базе данных. Попробуйте позже. [[%s]]',
        'Critical error while processing [[%s]] code.' => 'Критическая ошибка при обработке кода [[%s]].',
        'field [[%s]] of [[%s]] table' => 'поле [[%s]] таблицы [[%s]]',
        'Error: %s' => 'Ошибка %s',
        'You cannot use linktoDataTable outside of actionCode without hide:true.' => 'Нельзя использовать linktoDataTable вне actionCode без hide:true.',


        'left element' => 'левый элемент',
        'right element' => 'правый элемент',
        'Division by zero.' => 'Деление на ноль.',
        'Unknown operator [[%s]].' => 'Неизвестный оператор [[%s]].',
        'Non-numeric parameter in the list %s' => 'Нечисловой параметр в листе %s',
        'The [[%s]] parameter must be set to one of the following values: %s' => 'Параметр [[%s]] должен принимать одно из значений: %s',
        'Function [[%s]]' => 'Функция [[%s]]',
        'Function [[%s]] is not found.' => 'Функция [[%s]] не найдена.',
        'Table [[%s]] is not found.' => 'Таблица [[%s]] не найдена.',
        'Table is not found.' => 'Таблица не найдена.',
        'File [[%s]] is not found.' => 'Файл [[%s]] не найден.',
        'Cycle [[%s]] is not found.' => 'Цикл [[%s]] не найден.',
        'Cycle [[%s]] in table [[%s]] is not found.' => 'Цикл [[%s]] в таблице [[%s]] не найден.',
        'TOTUM-code format error: missing operator in expression [[%s]].' => 'Ошибка формата TOTUM-кода: отсутствие оператора в выражении [[%s]].',

        'No key %s was found in the data row.' => 'Ключа %s в строке данных не обраружено',
        'There is no [[%s]] key in the [[%s]] list.' => 'Не существует ключа [[%s]] в листе [[%s]].',
        'Regular expression error: [[%s]]' => 'Ошибка регулярного выражения: [[%s]]',
        'Parameter [[%s]] returned a non-true/false value.' => 'Параметр [[%s]] вернул не true/false.',
        'The [[%s]] parameter must contain 2 elements.' => 'Параметр [[%s]] должен содержать 2 элемента.',
        'The [[%s]] parameter must contain 3 elements.' => 'Параметр [[%s]] должен содержать 3 элемента.',
        'The %s parameter must contain a comparison element.' => 'Параметр %s должен содержать элемент сравнения.',

        'Variable [[%s]] is not defined.' => 'Переменная [[%s]] не определена.',
        'Code [[%s]] was not found.' => 'Код [[%s]] не найден.',
        'Code line [[%s]].' => 'Линия кода [[%s]].',
        'Previous row not found. Works only for calculation tables.' => 'Предыдущая строка не найдена. Работает только для расчетных таблиц.',
        'Cannot access the current value of the field from the Code.' => 'Нельзя из кода Код обращаться к текущему значению поля.',
        'Field [[%s]] is not found.' => 'Поле [[%s]] не найдено.',
        'The key [[%s]] is not found in one of the array elements.' => 'Ключ [[%s]] не обнаружен в одном из элементов массива.',
        'There must be two [[%s]] parameters in the [[%s]] function.' => 'Должно быть два параметра [[%s]] в функции [[%s]].',
        'The [[%s]] parameter must be [[%s]].' => 'Параметр [[%s]] должен быть [[%s]].',
        'The [[%s]] parameter must [[not]] be [[%s]].' => 'Параметр [[%s]] [[не]] должен быть [[%s]].',
        'The number of the [[%s]] must be equal to the number of [[%s]].' => 'Количество [[%s]] должен быть равно количеству [[%s]].',
        'The [[%s]] parameter must be one type with [[%s]] parameter.' => 'Параметр [[%s]] должен быть одного типа с параметром [[%s]].',

        'No characters selected for generation.' => 'Не выбраны символы для генерации.',


        'There is no NowField enabled in this type of code. We\'ll fix it - write us.' => 'В этом типе кода не подключен NowField. Мы исправимся - напишите нам.',
        '[[%s]] is available only for the calculation table in the cycle.' => '[[%s]] доступно только для расчетной таблицы в цикле.',

        'The ExecSSH function is disabled. Enable it in Conf.php.' => 'Функция ExecSSH выключена. Подключите ее в Conf.php',
        'The [[%s]] parameter has not been set in this code.' => 'Параметр [[%s]] не был установлен в этом коде.',
        'All list elements must be lists.' => 'Все элементы списка должны быть списками.',
        'None of the elements of the %s parameter array must be a list.' => 'Ни один из элементов массива параметра %s не должен быть списком.',

        'The array element does not fit the filtering conditions - the value is not a list.' => 'Элемент массива не соответствует условиям фильтрации - значение не list.',
        'The array element does not fit the filtering conditions - [[item]] is not found.' => 'Элемент массива не соответствует условиям фильтрации - [[item]] не найден.',
        '[[%s]] is not a multiple parameter.' => '[[%s]] - не множественный параметр.',
        'Not found template [[%s]] for parameter [[%s]].' => 'Не найден template [[%s]] для параметра [[%s]].',
        'No template is specified for [[%s]].' => 'Не указан template для параметра [[%s]].',
        'The unpaired closing parenthesis.' => 'Непарная закрывающая скобка.',
        'JSON generation error: [[%s]].' => 'Ошибка формирования JSON: [[%s]].',
        'JSON parsing error: [[%s]].' => 'Ошибка разбора JSON: [[%s]].',
        'The code should return [[%s]].' => 'Код должен возвращать [[%s]].',
        'The [[insert]] field should return list - Table [[%s]]' => 'Поле [[insert]] должно возвращать list  - Таблица [[%s]]',
        'The [[insert]] field should return a list with unique values - Table [[%s]]' => 'Поле [[insert]] должно возвращать list с уникальными значениями  - Таблица [[%s]]',


        'Format sections' => 'Секции форматирования',

        'Cron error' => 'Ошибка крона',
        'The schema is not connected.' => 'Схема не подключена.',
        'Error accessing the anonymous tables module.' => 'Ошибка доступа к модулю анонимных таблиц.',

        'Page processing time: %s sec.<br/>
    RAM: %sM. of %s.<br/>
    Sql Schema: %s, V %s<br/>.' => 'Время обработки страницы: %s сек.<br/>
    Оперативная память: %sM. из %s.<br/>
    Sql схема: %s, V %s<br/>',

        'Settings for sending mail are not set.' => 'Настройки для отправки почты не заданы.',
        'The path to ssh script %s is not set.' => 'Не задан путь к ssh скрипту %s.',

        'Request processing error.' => 'Ошибка обработки запроса.',
        'Error generating JSON response to client [[%s]].' => 'Ошибка формирования JSON-ответа на клиент [[%s]].',

        'Initialization error: [[%s]].' => 'Ошибка инициализации: [[%s]].',
        'Header' => 'Хэдер',
        'Footer' => 'Футер',
        'Rows part' => 'Строчная часть',
        'Filters' => 'Фильтры',
        'Filter' => 'Фильтр',
        'Row: id %s' => 'Строка: id %s',
        'ID is empty' => 'ID пуст',

        'User %s is not configured. Contact your system administrator.' => 'Пользователь %s не настроен. Обратитесь к администратору системы.',
        'Table [[%s]] was changed. Update the table to make the changes.' => 'Таблица [[%s]] была изменена. Обновите таблицу для проведения изменений.',
        'Table was changed' => 'Таблица была изменена',
        'The anchor field settings are incorrect.' => 'Настройки якорного поля заданы неверно.',
        'Field type is not defined.' => 'Тип поля не определен.',
        'Table type is not defined.' => 'Тип таблицы не определен.',
        'The [[%s]] table type is not connected to the system.' => 'Тип [[%s]] таблицы не подключен к системе.',
        'Unsupported channel [[%s]] is specified.' => 'Указан не поддерживаемый канал [[%s]].',
        'Field [[%s]] of table [[%s]] is required.' => 'Поле [[%s]] таблицы [[%s]] обязательно для заполнения.',
        'Authorization lost.' => 'Потеряна авторизация.',
        'Scheme file not found.' => 'Файл схемы не найден.',
        'Wrong format scheme file.' => 'Файл схемы неверного формата.',
        'Wrong format file' => 'Файл неверного формата',

        'Administrator' => 'Администратор',
        'The type of the loaded table [[%s]] does not match.' => 'Несовпадение типа загружаемой таблицы [[%s]].',
        'The cycles table for the adding calculation table [[%s]] is not set.' => 'Не задана таблица циклов для добавляемой расчетной таблицы [[%s]].',
        'The format of the schema name is incorrect. Small English letters, numbers and - _' => 'Формат имени схемы неверен. Строчные английские буквы, цифры и - _',
        'A scheme exists - choose another one to install.' => 'Схема существует - выберите другую для установки.',
        'Category [[%s]] not found for replacement.' => 'Категория [[%s]] не найдена для замены.',
        'Role [[%s]] not found for replacement.' => 'Категория [[%s]] не найдена для замены.',
        'Branch [[%s]] not found for replacement.' => 'Ветка [[%s]] не найдена для замены.',
        'Error saving file %s' => 'Ошибка сохранения файла %s',
        'A nonexistent [[%s]] property was requested.' => 'Запрошено несуществующее свойство [[%s]].',
        'Import from csv is not available for [[%s]] field.' => 'Импорт из csv недоступен для поля [[%s]].',
        'Export via csv is not available for [[%s]] field.' => 'Экспорт через csv недоступен для поля [[%s]].',
        'You do not have access to csv-import in this table' => 'У вас нет доступа для csv-импорта в этой таблице',


        'Date format error: [[%s]].' => 'Формат даты неверен: [[%s]].',
        '[[%s]] format error: [[%s]].' => 'Формат [[%s]] неверен: [[%s]].',
        '[[%s]] is reqired.' => '[[%s]] обязателен.',
        'Settings field.' => 'Поле настроек.',
        'You cannot create a [[footer]] field for [[non-calculated]] tables.' => 'Нельзя создать поле [[футера]] [[не для рассчетных]] таблиц.',
        'File > ' => 'Файл больше ',
        'File not received. May be too big.' => 'Файл не получен. Возможно, слишком большой.',
        'The data format is not correct for the File field.' => 'Формат данных не подходит для поля Файл.',
        'File name search error.' => 'Ошибка поиска наименования для файла.',
        'The file must have an extension.' => 'У файла должно быть расширение.',
        'Restricted to add executable files to the server.' => 'Запрещено добавление исполняемых на сервере файлов.',
        'Failed to copy a temporary file.' => 'Не удалось копировать временный файл.',
        'Failed to copy preview.' => 'Не удалось копировать превью.',
        'Error copying a file to the storage folder.' => 'Ошибка копирования файла в папку хранения.',
        'Changed' => 'Изменено',
        'Empty' => 'Пустое',
        'All' => 'Все',
        'Nothing' => 'Ничего',
        ' elem.' => ' элем.',
        'Operation [[%s]] over lists is not supported.' => 'Операция [[%s]] над листами непредусмотрена.',
        'Text modified' => 'Текст изменен',
        'Text unchanged' => 'Текст соответствует',
        'The looped tree' => 'Зацикленное дерево',
        'Value not found' => 'Значение не найдено',
        'Value format error' => 'Ошибка формата значения',
        'Multiselect instead of select' => 'Мультиселект вместо селекта',
        'The value must be unique. Duplication in rows: [[%s - %s]]' => 'Значение должно быть уникальным. Дублирование в строках: [[%s - %s]]',
        'There is no default version for table %s.' => 'Нет версии по-умолчанию для таблицы %s.',
        '[[%s]] cannot be a table name.' => '[[%s]] не может быть названием таблицы.',
        '[[%s]] cannot be a field name. Choose another name.' => '[[%s]] не может быть name поля. Выберите другой name.',
        'The name of the field cannot be new_field' => 'Name поля не может быть new_field',
        'Table creation error.' => 'Ошибка создания таблицы.',
        'You cannot delete system tables.' => 'Нельзя удалять системные таблицы.',
        'You cannot delete system fields.' => 'Нельзя удалять системные поля.',
        'The [[%s]] field is already present in the table.' => 'Поле [[%s]] уже есть в таблице.',
        'Fill in the field parameters.' => 'Заполните параметры поля.',
        'You can\'t make a boss of someone who is in a subordinate' => 'Нельзя сделать начальником того, кто есть в подчиненных',
        'Log is empty.' => 'Log пуст.',
        'Method not specified' => 'Метод не указан',
        'Method [[%s]] in this module is not defined or has admin level access.' => 'Метод [[%s]] в этом модуле не определен или имеет админский уровень доступа.',
        'Method [[%s]] in this module is not defined.' => 'Метод [[%s]] в этом модуле не определен.',
        'Your access to this table is read-only. Contact administrator to make changes.' => 'Ваш доступ к этой таблице - только на чтение. Обратитесь к администратору для внесения изменений.',
        'Access to the table is denied.' => 'Доступ к таблице запрещен.',
        'Access to the cycle is denied.' => 'Доступ к циклу запрещен.',
        'Access via module for temporary tables only' => 'Доступ через модуль только для временных таблиц',
        'Table access error' => 'Ошибка доступа к таблице',
        'Wrong path to the table' => 'Неверный путь к таблице',
        'Write access to the table is denied' => 'Доступ к таблице на запись запрещен',
        'Login/Email' => 'Логин/Email',
        'Log in' => 'Вход',
        'Logout' => 'Выход',
        'Send new password to email' => 'Отправить новый пароль на email',
        'Service is optimized for desktop browsers Chrome, Safari, Yandex latest versions. It seems that your version of the browser is not supported. Error - for developers: '
        => 'Сервис оптимизирован под десктопные броузеры Chrome, Safari, Yandex последних версий. Похоже, ваша версия броузера не поддерживается. Ошибка - для разработчиков: ',
        'Credentials in %s' => 'Учетные данные в %s',
        'Fill in the Login/Email field' => 'Заполните поле Логин/Email',
        'Fill in the %s field' => 'Заполните поле %s',
        'Fill in the Password field' => 'Заполните поле Пароль',
        'Password is not correct' => 'Пароль не верен',
        'Due to exceeding the number of password attempts, your IP is blocked' => 'В связи с превышением количества попыток на ввод пароля ваш IP заблокирован',
        'Password recovery via email is disabled for this database. Contact the solution administrator.' => 'Восстановление пароля через  email для данной базы отключено. Обратитесь к админинстратору решения.',
        'Email for this login is not set' => 'Email для этого login не задан',
        'Password' => 'Пароль',
        'An email with a new password has been sent to your Email. Check your inbox in a couple of minutes.' => 'Письмо с новым паролем отправлено на ваш Email. Проверьте ваш ящик через пару минут.',
        'Letter has not been sent: %s' => 'Письмо не отправлено: %s',
        'The user with the specified Login/Email was not found' => 'Пользователь с указанным Логин/Email не найден',
        'To work with the system you need to enable JavaScript in your browser settings' => 'Для работы с системой необходимо включить JavaScript в настройках броузера',
        'It didn\'t load :(' => 'Не загрузилось :(',
        'Forms user authorization error' => 'Ошибка авторизации пользователя форм',
        'Conflicts of access to the table error' => 'Ошибка одновременного доступа к таблице',
        'Form configuration error - user denied access to the table' => 'Ошибка настройки формы - пользователю запрещен доступ к таблице',
        'The [[%s]] field was not found. The table structure may have changed. Reload the page.'
        => 'Не найдено поле [[%s]]. Возможно изменилась структура таблицы. Перегрузите страницу',
        'Conf.php was created successfully. Connection to the database is set up, the start scheme is installed. You are authorized under specified login with the role of Creator. Click the link or refresh the page.'
        => 'Conf.php создан успешно. Настроено подключение к базе данных, установлена стартовая схема. Вы авторизованы под указанным логином с ролью Создатель. Перейдите по ссылке или обновите страницу.',
        'Have a successful use of the system' => 'Успешного пользования системой',


        'Json not received or incorrectly formatted' => 'Json не получен или неверно оформлен',
        'No auth section found' => 'Секция auth не найдена',
        'The login attribute of the auth section was not found' => 'Атрибут login секции auth не найден',
        'The password attribute of the auth section was not found' => 'Атрибут password секции auth не найден',
        'The user with this data was not found. Possibly the xml/json interface is not enabled.' => 'Пользователь с такими данными не найден. Возможно, ему не включен доступ к xml/json-интерфейсу',
        'The recalculate section must contain restrictions in the format [["field":FIELDNAME,"operator":OPERATOR,"value":VALUE]]' => 'Секция recalculate должна содержать ограничения в формате [["field":FIELDNAME,"operator":OPERATOR,"value":VALUE]]',
        'The field is not allowed to be edited through the api or does not exist in the specified category' => 'Поле запрещено для редактирования через api или не существует в указанной категории',
        'Multiple/Single value type error' => 'Ошибка типа значения множественный/одинарный',
        'In the export section, specify "fields":[] - enumeration of fields to be exported' => 'В секции export укажете "fields":[] - перечисление полей для вывода в экспорт',
        'Incorrect where in the rows-set-where section' => 'Неверно оформлено where в секции rows-set-where',
        'Without a table in the path, only the remotes section works' => 'Без указания таблицы в пути работает только секция remotes',
        'Remote {var} does not exist or is not available to you' => 'Remote {var} не существует или не доступен для вас',
        'The name for remote is not set' => 'Не задан  name для remote',
        'Field [[%s]] is not allowed to be added via Api' => 'Поле [[%s]] запрещено для добавления через Api',
        'Field [[%s]] is not allowed to be edited via Api' => 'Поле [[%s]] запрещено для редактирования через Api',
        'The [[%s]] field must contain multiple select' => 'Поле [[%s]] должно содержать множественный селект',
        'The [[%s]] field must contain a string' => 'Поле [[%s]] должно содержать строку',
        'The %s field in %s of the table does not exist' => 'Поля %s в %s таблицы не существует',
        'You are not allowed to add to this table' => 'Добавление в эту таблицу вам запрещено',
        'You are not allowed to delete from this table' => 'Удаление из этой таблицы вам запрещено',
        'You are not allowed to sort in this table' => 'Сортировка в этой таблице вам запрещена',
        'You are not allowed to duplicate in this table' => 'Дублирование в этой таблице вам запрещено',
        'You are not allowed to restore in this table' => 'Восстановление в этой таблице вам запрещено',
        'Authorization error' => 'Ошибка авторизации пользователя',
        'Remote is not connected to the user' => 'Remote не подключен к пользователю',
        'Remote is not active or does not exist' => 'Remote не активен или не существует',
        'Description' => 'Описание',
        'Choose a table' => 'Выберите таблицу',
        'The choice is outdated.' => 'Предложенный выбор устарел.',
        'The proposed input is outdated.' => 'Предложенный ввод устарел.',
        'Notifications' => 'Нотификации',
        'Changing the name of a field' => 'Изменение name поля',
        'Fill in title' => 'Заполните название',
        'Select fields' => 'Выберите поля',
        'Csv download of this table is not allowed for your role.' => 'Csv-выгрузка этой таблицы не разрешена для вашей роли.',
        'The name of the field is not set.' => 'Не задано имя поля',
        'Access to the field is denied' => 'Доступ к полю запрещен',
        'Interface Error' => 'Ошибка интерфейса',
        'Temporary table storage time has expired' => 'Время хранения временной таблицы истекло',
        'Field not of type select/tree' => 'Поле не типа селект/дерево',
        'The tree index is not passed' => 'Не передан индекс дерева',
        'Access to the logs is denied' => 'Доступ к логам запрещен',
        'No manual changes were made in the field' => 'Ручных изменений по полю не производилось',
        'Failed to get branch Id' => 'Ошибка получения Id ветки',
        'Add row out of date' => 'Строка добавления устрарела',
        'Log of manual changes by field "%s"' => 'Лог ручных изменений по полю "%s"',
        'Calculating the table' => 'Расчет таблицы',
        'Table is empty' => 'Таблица пуста',
        'Table %s. DUPLICATION CODE' => 'Таблица %s. КОД ПРИ ДУБЛИРОВАНИИ',
        'Incorrect encoding of the file (should be utf-8 or windows-1251)' => 'Неверная кодировка файла (должно быть utf-8 или windows-1251)',
        'Loading file of table %s into table [[%s]]' => 'Загрузка файла таблицы %s в таблицу [[%s]]',
        'in row %s' => 'в строке %s',
        'no table change code' => 'отсутствует код изменения таблицы',
        'no structure change code' => 'отсутствует код изменения структуры',
        'The structure of the table was changed. Possibly a field order mismatch.' => 'Была изменена структура таблицы. Возможно несовпадение порядка полей.',
        'no indication of a cycle' => 'отсутствует указание на цикл',
        'Table from another cycle or out of cycles' => 'Таблица из другого цикла или вне циклов',
        'Out of cycles' => 'Вне циклов',
        'Manual Values' => 'Ручные значения',
        'there is no Manual Values section header' => 'отсутствует заголовок секции Ручные значения',
        'no 0/1/2 edit switch' => 'отсутствует 0/1/2 переключатель редактирования',
        'no section header %s' => 'отсутствует заголовок секции %s',
        'no filter data' => 'отсутствуют данные о фильтрах',
        'on the line one line after the Rows part is missing the header of the Footer section' => 'в строке через одну после Строчной части отсутствует заголовок секции Футер',
        '[0: do not modify calculated fields] [1: change values of calculated fields already set to manual] [2: change calculated fields]' => '[0: рассчитываемые поля не обрабатываем] [1: меняем значения рассчитываемых полей уже выставленных в ручное] [2: меняем рассчитываемые поля]',

        'More than 20 nesting levels of table changes. Most likely a recalculation loop' => 'Больше 20 уровней вложенности изменения таблиц. Скорее всего зацикл пересчета',
        'The field is not configured.' => 'Поле не настроено',
        'No select field specified' => 'Не указано поле для выборки',
        'More than one field/sfield is specified' => 'Указано больше одного поля field/sfield',
        'The %s function is not provided for this type of tables' => 'Функция %s не предусмотрена для этого типа таблиц',
        'script' => 'скрипт',
        'Field [[%s]] in table [[%s]] is not a column' => 'Полe [[%s]] в таблице [[%s]] не колонка',
        'In the %s parameter you must use a list by the number of rows to be changed or not a list.' => 'В параметре where необходимо использовать лист по количеству изменяемых строк либо не лист.',
        'The function is used to change the rows part of the table.' => 'Функция используется для изменения строчной части таблицы.',
        'Incorrect interval [[%s]]' => 'Некорректный интервал [[%s]]',
        'The calculation table is not connected to %s cycles table' => 'Рассчетная таблица не подключена к таблице циклов %s',
        'User access' => 'Доступ пользователю',
        'Button to the cycle' => 'Кнопка в цикл',
        'First you have to delete the cycles table, and then the calculation tables inside it' => 'Сначала нужно удалить таблицу циклов, а потом расчетные таблицы внутри нее',
        'No line-by-line updates are provided for the calculation tables. They are recalculated in whole' => 'Для расчетных таблиц не предусмотрено построчное обновление. Они пересчитываются целиком',
        'Error processing field insert: [[%s]]' => 'Ошибка обработки поля insert: [[%s]]',
        'Open' => 'Открыть',
        'The row with id %s in the table already exists. Cannot be added again' => 'Строка с id %s в таблице уже существует. Нельзя добавить повторно',
        'The [[%s]] field in the rows part of table [[%s]] does not exist' => 'Поля [[%s]] в строчной части таблицы [[%s]] не существует',
        'Client side error. Received row instead of id' => 'Ошибка клиентской части. Получена строка вместо id',
        'Logic error n: %s' => 'Ошибка логики n: %s',
        'Adding row error' => 'Ошибка добавления строки',
        'The Parameters field type is valid only for the Tables Fields table' => 'Тип поля Параметры допустим только для таблицы Состав полей'


    ];
    protected const monthRods = [
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
    ];
    protected const months = [
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
    ];
    protected const monthsShort = [
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
    ];
    protected const weekDays = [
        1 => 'Понедельник',
        'Вторник',
        'Среда',
        'Четверг',
        'Пятница',
        'Суббота',
        'Воскресенье'
    ];
    protected const weekDaysShort = [
        1 => 'Пн',
        'Вт',
        'Ср',
        'Чт',
        'Пт',
        'Сб',
        'Вс'
    ];

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

    public function translit($s): string
    {
        $s = (string)$s;
        $s = strip_tags($s);
        $s = str_replace(array("\n", "\r"), ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        $s = trim($s);
        $s = mb_strtolower($s);
        $s = strtr(
            $s,
            array('а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'ъ' => '', 'ь' => '')
        );
        $s = preg_replace('/[^0-9a-z_ ]/i', '', $s);
        $s = str_replace(' ', '_', $s);
        return $s;
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

    /**
     * @param int $number
     * @param array $ImRodRoded ['день', 'дней', 'дня']
     * @return string
     */
    public function numbersRusPadegRod(int $number, array $ImRodRoded)
    {
        $ostDel = $number % 10;
        if ($number > 5 && $number < 21 || in_array($ostDel, [5, 6, 7, 8, 9, 0])) {
            return $ImRodRoded[1];
        } elseif ($ostDel === 1) {
            return $ImRodRoded[0];
        } elseif (in_array($ostDel, [2, 3, 4])) {
            return $ImRodRoded[2];
        }
    }

    protected function getConstant($name): array
    {
        return match ($name) {
            'monthsShort' => static::monthsShort,
            'months' => static::months,
            'weekDays' => static::weekDays,
            'weekDaysShort' => static::weekDaysShort,
            'monthRods' => static::monthRods,
        };
    }
}