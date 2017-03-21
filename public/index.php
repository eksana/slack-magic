<?php
// подключаем автозагрузчик классов
require '../vendor/autoload.php';
require 'cfg.php';

$commands = ['/info', '/next'];

$token = strip_tags(trim($_POST['token']));
$token_check = ($token == $slack_app_token);

$command = strip_tags(trim($_POST['command']));
$group = strip_tags(trim($_POST['text']));
$status = 404;
$response['text'] = 'Неверная команда!';

if ($token_check && $command && in_array($command, $commands) && $group) {
	// подключаемся к MongoDB при помощи MongoDB URI
	$client = new MongoDB\Client($mongodb_uri);
	// выбираем коллекцию для работы
	$collection = $client->slack_magic->groups;
	// ищем информацию о группе по названию
	$cursor = $collection->find(
		['tag' => $group],
		[
			'projection' => [
				'name' => 1,
				'date_start' => 1,
				'date_end' => 1,
				'days' => 1
				],
			'limit' => 1
		]);

	// если документ найден, переходим к обработке команды
	$find = $cursor->toArray();
	if ($find) {

		$group_info = $find[0];

		switch ($command) {
			case '/info':
				$status = 200;
				$date_start = $group_info->date_start->toDateTime();
				$date_end = $group_info->date_end->toDateTime();
				$response['text']  = "Информация о группе *" . $group_info['name'] . "*.\n";
				$response['text'] .= "Первое занятие: " . $date_start->format('Y-m-d \в H:i') . ".\n";
				$response['text'] .= "Последнее занятие: " . $date_end->format('Y-m-d \в H:i') . ".\n";
				$response['text'] .= "Занятия по " . $group_info['days'] . ".\n";
				break;

			case '/next':
				$response['text'] = "Ближайшее занятие группы *" . $group_info['name'] . "* не найдено.";

				// для поиска по дате в MongoDB нужно преобразовать ее в формат базы
				$date = new MongoDB\BSON\UTCDateTime();
				$cursor = $collection->aggregate([
						['$match' => ["tag" => $group]],
						['$unwind' => '$lessons'],
						['$match' => ["lessons.date" => ['$gte' => $date] ]],
						['$limit' => 1]
				]);
				if ($cursor) {
					$status = 200;
					$lesson_info = $cursor->toArray()[0]['lessons'];
					$date = $lesson_info->date->toDateTime();
					$response['text']  = "Ближайшее занятие группы *" . $group_info['name'] . "* ";
					$response['text'] .= "состоится " . $date->format('Y-m-d \в H:i') . ".\n";
					$response['text'] .= "Тема занятия: _«" . $lesson_info->name . "»_.";
				}
				break;

			default:
				$status = 404;
				$response['text'] = 'Неверная команда!';
				break;
		}

	} else {
		$response['text'] = 'Группа не найдена.';
	}
}
http_response_code($status);
header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE);