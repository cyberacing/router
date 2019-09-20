<?php

namespace Helpers;

use PDO;
use const DB_HOST;
use const DB_NAME;
use const DB_PASSWORD;
use const DB_USER;

/**
 * получение данных из тела запроса
 * @param type $method
 * @return type
 */
function getFormData($method)
{

    // GET или POST: данные возвращаем как есть
    if ($method === 'GET') {
        $data = $_GET;
    } else if ($method === 'POST') {
        $data = $_POST;
    } else {
        // PUT, PATCH или DELETE
        $data = array();
        $exploded = explode('&', file_get_contents('php://input'));

        foreach ($exploded as $pair) {
            $item = explode('=', $pair);
            if (count($item) == 2) {
                $data[urldecode($item[0])] = urldecode($item[1]);
            }
        }
    }

    // Удаляем параметр q
    unset($data['q']);

    return $data;
}

/**
 * получаем все данные о запросе
 * @return type
 */
function getRequestData()
{
    // Определяем метод запроса
    $method = $_SERVER['REQUEST_METHOD'];

    // Разбираем url
    $url = (isset($_GET['q'])) ? $_GET['q'] : '';
    $url = trim($url, '/');
    $urlData = explode('/', $url);

    return array(
        'method' => $method,
        'formData' => getFormData($method),
        'urlData' => array_slice($urlData, 1),
        'router' => $urlData[0],
    );
}

/**
 * подключение к БД
 * @return PDO
 */
function connectDB()
{
    $host = DB_HOST;
    $user = DB_USER;
    $password = DB_PASSWORD;
    $db = DB_NAME;

    $dsn = "mysql:host=$host;dbname=$db;charset=utf8";
    $options = array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    );

    return new PDO($dsn, $user, $password, $options);
}

/**
 * проверка роутера на валидность
 * @param type $router
 * @return type
 */
function isValidRouter($router)
{
    return in_array($router, array(
        'users',
    ));
}

/**
 * выдаем ошибку с кодом 400. для теста думаю этого достаточно
 * @param type $code
 * @param type $message
 */
function throwHttpError($code, $message)
{
    header('HTTP/1.0 400 Bad Request');
    echo json_encode(array(
        'result' => 'error',
        'code' => $code,
        'message' => $message
    ));
}
