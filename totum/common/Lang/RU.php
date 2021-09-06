<?php

namespace totum\common\Lang;

class RU implements LangInterface
{
    use TranslateTrait;

    public const TRANSLATES = [
        'Deleting' => 'Удаление',
        'Not found: %s' => 'Не найдено: %s',
        'Not found [[%s]] for the [[%s]] parameter.' => 'Не найден [[%s]] для параметра [[%s]].',
        'Template not found.' => 'Шаблон не найден.',
        'No [[%s]] is specified for the [[%s]] parameter.' => 'Не указан [[%s]] для параметра [[%s]].',
        'Parametr [[%s]] is required in [[%s]] function.' => 'Параметр [[%s]] является обязательным в функции [[%s]].',

        'The function is only available for the Creator role.' => 'Функция доступна только роли Создатель.',
        'Password doesn\'t match.' => 'Пароль не подходит.',
        'Scheme string is empty.' => 'Строка схемы пуста.',
        'The function is only available for cycles tables.' => 'Функция доступна только для таблиц циклов.',

        'System error. Action type not specified.' => 'Системная ошибка. Не указан тип действия.',
        'Scheme source not defined.' => 'Не определен источник схемы.',
        'Fill in the parameter [[%s]].' => 'Заполните параметр [[%s]].',
        'Each button must contain [[%s]].' => 'Каждая кнопка должна содержать [[%s]].',
        'The parameter [[%s]] should be of type row/list.' => 'Параметр [[%s]] должен быть типа row/list.',
        'The parameter [[%s]] should be of type true/false.' => 'Параметр [[%s]] должен быть типа true/false.',
        'The parameter [[%s]] should [[not]] be of type row/list.' => 'Параметр [[%s]] [[не]] должен быть типа row/list.',
        'The parameter [[%s]] should be of type string.' => 'Параметр [[%s]] должен быть типа строка.',
        'The cycles table is specified incorrectly.' => 'Таблица циклов указана неверно.',


        'For temporary tables only.' => 'Только для временных таблиц',
        'For simple and cycles tables only.' => 'Только для простых таблиц и таблиц циклов.',
        'The table has no n-sorting.' => 'Таблица не имеет n-сортировки.',
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
        'The row with %s was not found in table %s.' => 'Строка с %s не найдена в таблице %s.',


        'For lists comparisons, only available =, ==, !=.' => 'Для сравнения листов доступны только =, ==, !=.',
        'There should be a date, not a list.' => 'Должна быть дата, а не список.',
        'There must be only one comparison operator in the string.' => 'В строке должен быть только один оператор сравнения.',
        'TOTUM-code format error [[%s]].' => 'Ошибка формата ТОТУМ-кода [[%s]].',
        'XML Format Error.' => 'Ошибка формата XML.',
        'Code format error - no start section.' => 'Ошибка формата кода - нет стартовой секции.',
        'The [[catch]] code of line [[%s]] was not found.' => 'Строка [[catch]] кода [[%s]] не найдена.',
        'ERR!' => 'ОШБК!',
        'Database error: [[%s]]' => 'Ошибка базы данных: [[%s]]',
        'Critical error while processing [[%s]] code.' => 'Критическая ошибка при обработке кода [[%s]].',
        'field [[%s]] of [[%s]] table' => 'поле [[%s]] таблицы [[%s]]',
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
        'The array element does not fit the filtering conditions - the value is not a list.' => 'Элемент массива не соответствует условиям фильтрации - значение не list.',
        'The array element does not fit the filtering conditions - [[item]] is not found.' => 'Элемент массива не соответствует условиям фильтрации - [[item]] не найден.',
        '[[%s]] is not a multiple parameter.' => '[[%s]] - не множественный параметр.',
        'Not found template [[%s]] for parameter [[%s]].' => 'Не найден template [[%s]] для параметра [[%s]].',
        'No template is specified for [[%s]].' => 'Не указан template для параметра [[%s]].',
        'The unpaired closing parenthesis.'=>'Непарная закрывающая скобка.',
        'JSON generation error: [[%s]].'=>'Ошибка формирования JSON: [[%s]].',
        'JSON parsing error: [[%s]].'=>'Ошибка разбора JSON: [[%s]].',
        'The code should return [[%s]].'=>'Код должен возвращать [[%s]].',



        'Format sections'=>'Секции форматирования',

        'Cron error'=>'Ошибка крона',
        'The schema is not connected.'=>'Схема не подключена.',
        'Error accessing the anonymous tables module.'=>'Ошибка доступа к модулю анонимных таблиц.',

        'Page processing time: %s sec.<br/>
    RAM: %sM. of %s.<br/>
    Sql Schema: %s, V %s<br/>.'=>'Время обработки страницы: %s сек.<br/>
    Оперативная память: %sM. из %s.<br/>
    Sql схема: %s, V %s<br/>',

        'Settings for sending mail are not set.'=>'Настройки для отправки почты не заданы.',
        'The path to ssh script %s is not set.'=>'Не задан путь к ssh скрипту %s.',
    ];
}