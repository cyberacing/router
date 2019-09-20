<?php

/**
 * основная функция - роутер
 * @param type $data
 */
function route($data)
{
    // Получаем тарифы для конкретного сервиса
    // GET /users/{user_id}/services/{service_id}/tarifs
    if ($data['method'] === 'GET' &&
            count($data['urlData']) === 4 &&
            $data['urlData'][1] === 'services' &&
            $data['urlData'][3] === 'tarifs') {

        getTarifsByUsersServices($data);
    }

    // Запрос на выставление тарифа 
    // PUT /users/{user_id}/services/{service_id}/tarif
    if ($data['method'] === 'PUT' &&
            count($data['urlData']) === 4 &&
            $data['urlData'][1] === 'services' &&
            $data['urlData'][3] === 'tarif' &&
            isset($data['formData']['tarif_id'])) {

        updateUserServiceTarif($data);
    }

    // Если ни один роутер не отработал
    \Helpers\throwHttpError('invalid_parameters', 'invalid parameters');
}

/**
 * GET /users/{user_id}/services/{service_id}/tarifs
 * @param type $data
 */
function getTarifsByUsersServices($data)
{
    $userId = $data['urlData'][0];
    $serviceId = $data['urlData'][2];

    // если сервис не существует, то выбрасываем ошибку
    if (!isExistsUserServiceById($userId, $serviceId)) {
        \Helpers\throwHttpError('service_not_exists', 'service not exists');
        exit;
    }

    // получаем список тарифов, если список пуст, выбрасываем ошибку
    $records = findTarifsByUserServices($userId, $serviceId);
    if (!count($records)) {
        \Helpers\throwHttpError('tarifs_not_exists', 'tarifs not exists');
        exit;
    }

    // выводим ответ клиенту
    echo json_encode(array(
        'result' => 'ok',
        'tarifs' => prepareTarifs($records)
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * обработка тарифов для ответа клиенту
 * @param type $records
 * @return type
 */
function prepareTarifs($records)
{
    $title = '';
    $link = '';
    $speed = '';
    $tarifs = [];
    foreach ($records as $record) {
        if ($title != $record['title']) {
            $title = $record['title'];
        }
        if ($link != $record['link']) {
            $link = $record['link'];
        }
        if ($speed != $record['speed']) {
            $speed = $record['speed'];
        }

        $tarifs[] = [
            'ID' => $record['ID'],
            'title' => $record['title'],
            'price' => $record['price'],
            'pay_period' => $record['pay_period'],
            'new_payday' => getNewPayDate($record),
            'speed' => $record['speed'],
        ];
    }

    return [
        'title' => $title,
        'link' => $link,
        'speed' => $speed,
        'tarifs' => $tarifs,
    ];
}

/**
 * PUT /users/{user_id}/services/{service_id}/tarif
 * @param type $data
 */
function updateUserServiceTarif($data)
{
    $userId = $data['urlData'][0];
    $serviceId = $data['urlData'][2];
    $tarifId = $data['formData']['tarif_id'];

    // если сервис не существует, то выбрасываем ошибку
    if (!isExistsUserServiceById($userId, $serviceId)) {
        \Helpers\throwHttpError('service_not_exists', 'service not exists');
        exit;
    }

    // если тариф не существует, то выбрасываем ошибку
    if (!isExistsTarifById($tarifId)) {
        \Helpers\throwHttpError('tarif_not_exists', 'tarif not exists');
        exit;
    }

    // получаем тариф по его ID
    $tarif = findTarifById($tarifId);
    saveUserServiceTarif($userId, $serviceId, $tarifId, $tarif);

    // выводим ответ клиенту
    echo json_encode(array(
        'result' => 'ok'));
    exit;
}

/**
 * получение новой даты следующего списания
 * @param type $tarif
 * @param type $timestamp
 * @return type
 */
function getNewPayDate($tarif, $timestamp = true)
{
    $dateTime = new \DateTime(date('Y-m-d 00:00:00'), new \DateTimeZone('Europe/Moscow'));
    $dateTime->add(new \DateInterval('P' . $tarif['pay_period'] . 'M'));
    return ($timestamp) ? $dateTime->format('UO') : $dateTime->format('Y-m-d');
}

/**
 * получение тарифов по сервису
 * @param type $userId
 * @param type $serviceId
 * @return type
 */
function findTarifsByUserServices($userId, $serviceId)
{
    $pdo = \Helpers\connectDB();
    $subquery = 'SELECT `tarifs`.`tarif_group_id`
                 FROM `services` 
                 LEFT JOIN `tarifs` ON `tarifs`.`ID` = `services`.`tarif_id` 
                 WHERE `services`.`user_id`=:userId AND `services`.`ID`=:serviceId';
    $query = 'SELECT * FROM `tarifs` WHERE `tarif_group_id` IN (' . $subquery . ')';
    $data = $pdo->prepare($query);
    $data->bindParam(':userId', $userId);
    $data->bindParam(':serviceId', $serviceId);
    $data->execute();
    return $data->fetchAll();
}

/**
 * получение тарифа по ID
 * @param type $tarifId
 * @return type
 */
function findTarifById($tarifId)
{
    $pdo = \Helpers\connectDB();
    $query = 'SELECT * FROM `tarifs` WHERE `ID`=:tarifId';
    $data = $pdo->prepare($query);
    $data->bindParam(':tarifId', $tarifId);
    $data->execute();
    return $data->fetch();
}

/**
 * обновление тарифа и даты списания для сервиса
 * @param type $userId
 * @param type $serviceId
 * @param type $tarifId
 * @param type $tarif
 */
function saveUserServiceTarif($userId, $serviceId, $tarifId, $tarif)
{
    $pdo = \Helpers\connectDB();
    $query = 'UPDATE `services` SET `tarif_id`=:tarifId, `payday`=:payday WHERE `user_id`=:userId AND `ID`=:serviceId';
    $data = $pdo->prepare($query);
    $data->bindParam(':userId', $userId);
    $data->bindParam(':serviceId', $serviceId);
    $data->bindParam(':tarifId', $tarifId);
    $data->bindParam(':payday', getNewPayDate($tarif, false));
    $data->execute();
}

/**
 * проверка существование сервиса
 * @param type $userId
 * @param type $serviceId
 * @return type
 */
function isExistsUserServiceById($userId, $serviceId)
{
    $pdo = \Helpers\connectDB();
    $query = 'SELECT * FROM `services` WHERE `user_id`=:userId AND `ID`=:serviceId';
    $data = $pdo->prepare($query);
    $data->bindParam(':userId', $userId);
    $data->bindParam(':serviceId', $serviceId);
    $data->execute();
    return $data->rowCount();
}

/**
 * проверка существования тарифа
 * @param type $tarifId
 * @return type
 */
function isExistsTarifById($tarifId)
{
    $pdo = \Helpers\connectDB();
    $query = 'SELECT * FROM `tarifs` WHERE `ID`=:tarifId';
    $data = $pdo->prepare($query);
    $data->bindParam(':tarifId', $tarifId);
    $data->execute();
    return $data->rowCount();
}
