<?php

// Подключаем конфиги и хелперы
include_once 'helpers/db_cfg.php';
include_once 'helpers/helpers.php';

// Получаем данные из запроса
$data = \Helpers\getRequestData();
$router = $data['router'];

// Проверяем роутер на валидность
if (\Helpers\isValidRouter($router)) {

    // Подключаем файл-роутер
    include_once 'routers/' . $router . '.php';

    // Запускаем главную функцию
    route($data);
} else {
    // Выбрасываем ошибку
    \Helpers\throwHttpError('invalid_router', 'router not found');
}
