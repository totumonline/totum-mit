<?php

namespace totum\common\Lang;

class RU implements LangInterface
{
    public const TRANSLATES = [
        'Not found: %s' => 'Не найдено: %s',
        'Not found [[%s]] for the [[%s]] parameter.' => 'Не найден [[%s]] для параметра [[%s]].',
        'Template not found.' => 'Шаблон не найден.',
        'No [[%s]] is specified for the [[%s]] parameter.' => 'Не указан [[%s]] для параметра [[%s]].',

        'The function is only available for the Creator role.' => 'Функция доступна только роли Создатель.',
        'Password doesn\'t match.' => 'Пароль не подходит.',
        'Scheme string is empty.' => 'Строка схемы пуста.',
        'The function is only available for cycles tables.' => 'Функция доступна только для таблиц циклов.',

        'System error. Action type not specified.' => 'Системная ошибка. Не указан тип действия.',
        'Scheme source not defined.' => 'Не определен источник схемы.',
        'The [[%s]] parameter must contain an array.' => 'Параметр [[%s]] должен содержать массив.',
        'Fill in the parameter [[%s]].' => 'Заполните параметр [[%s]].',
        'Each button must contain [[%s]].' => 'Каждая кнопка должна содержать [[%s]].',
        'The parameter [[%s]] should be of type row/list.' => 'Параметр [[%s]] должен быть типа row/list.',
        'The cycles table is specified incorrectly.' => 'Таблица циклов указана неверно.',


        'For temporary tables only.' => 'Только для временных таблиц',
        'For simple and cycles tables only.' => 'Только для простых таблиц и таблиц циклов.',
        'The table has no n-sorting.' => 'Таблица не имеет n-сортировки.',
        'The %s should be here.' => 'Здесь должен быть %s.',
        'Parametr [[%s]] is required and should be a number.' => 'Параметр [[%s]] обязателен и должен быть числом.',
        'The %s parameter is required and must start with %s.' => 'Параметр %s обязателен и должен начитаться с %s.',
        'Calling a third-party script.' => 'Обращение к стороннему скрипту.',
        'Not for the temporary table.' => 'Не для временной таблицы.',
        'The [[%s]] field is not found in the [[%s]] table.' => 'Поле [[%s]] не надено в таблице [[%s]].',
        'The %s field must be numeric.' => 'Поле %s должно быть числовым.',
        'The row with %s was not found in table %s.' => 'Строка с %s не найдена в таблице %s.',
    ];

    public function translate(string $str, array|string $vars = []): string
    {
        if (!is_array($vars)) {
            $vars = [$vars];
        }

        if (key_exists($str, static::TRANSLATES)) {
            if (empty($vars)) return static::TRANSLATES[$str];
            return sprintf(static::TRANSLATES[$str], ...$vars);
        }
        if (empty($vars)) return $str;
        return sprintf($str, ...$vars);
    }
}