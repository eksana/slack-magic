# Командуем слаком при помощи PHP, MongoDB и Heroku

В качестве примера - автоинформирование студентов в слаке об их группе.

Стек взят исходя из бесплатного деплоя, HTTPS, требуемого слаком, ну и нежной любви к PHP, конечно же, не считая преимуществ производительности 7 версии. Сразу отмечу, это пет-проект, с реальной системой он имеет немного общего, создан чтобы показать насколько проста магия в современном мире бэкенда.

Предполагается, что локально уже стоит сервер с PHP 7, MongoDB, Git, Composer и нормальная консоль.

## Что дано

Есть учебные группы, есть расписание занятий и домашние задания, есть слак-каналы со студентами.

## Что требуется

Нужно по запросу выдавать интересующую информацию. Чтобы не растягивать на долгое повествование и большое количество кода (его и так много получилось), информацию упростим до расписания группы и ближайшего занятия (ну мало ли человек устал отдыхать и забыл когда ему на учебу).

## База данных

В боевых условиях конечно же есть уже данные по занятиям группы, но для пет-проекта, назовем его `slack-magic`, мы сгенерируем их и поместим в MongoDB с названием `slack_magic`. Создаем простой JSON:

```
[
  {
    "tag": "html",
    "name": "HTML5+CSS3",
    "date_start": "2017-02-27",
    "date_end": "2017-04-27",
    "days": "пн/чт",
    "lessons": [
      {
        "name": "Введение. Основы HTML5",
        "date": "2017-02-27"
      },
      {
        "name": "Инструменты веб-разработчика",
        "date": "2017-03-02"
      },

      ...

    ]
  }
]
```

Кидаем файл в папку с MongoDB и там же вызываем в консоли:
```
mongoimport --db slack_magic --collection groups --file import.json --jsonArray
```

Заходим и проверяем:
```
mongo
use slack_magic
db.groups.find().pretty()
```

Вжух, есть данные в базе:

![](https://github.com/eveness/slack-magic/blob/master/screenshots/mongo-import-result.png)

Правда, они не в том виде, котором хотелось. Ведь нам надо как-то ориентироваться по датам. Да и про время старта забыли, чтобы показывать ближайшее занятие, нужно отталкиваться не только от даты, но и от времени.

Очищаем коллекцию при помощи `db.groups.drop()` и меняем файл в соответствии с форматом даты для MongoDB:

```
[
  {
    "tag": "html",
    "name": "HTML5+CSS3",
    "date_start": Date("2017-02-27T19:00:00Z"),
    "date_end": Date("2017-04-27T19:00:00Z"),
    "days": "пн/чт",
    "lessons": [
      {
        "name": "Введение. Основы HTML5",
        "date": Date("2017-02-27T19:00:00Z")
      },
      {
        "name": "Инструменты веб-разработчика",
        "date": Date("2017-03-02T19:00:00Z")
      },

      ...

    ]
  }
]
```

Стало лучше:

![](https://github.com/eveness/slack-magic/blob/master/screenshots/mongo-import-result-2.png)

Проверяем на выборку:

```
db.groups.aggregate([
  {$match:{ "tag": "html"}},
    {$unwind: '$lessons'},
    {$match: {"lessons.date":{$gte: new Date()} }},
    {$limit: 1}
]).pretty()
```

Результат:

![](https://github.com/eveness/slack-magic/blob/master/screenshots/mongo-check-result.png)

Работает! Бежим вперед, идем на [mlab.com](https://mlab.com/), регистрируемся, создаем **"Single-node"** и **"Sandbox"**, создаем пользователя и экспортируем наши данные сразу и туда при помощи команды (данные взять свои с mlab.com соответственно):

```
mongoimport --host <host>:<port> -u <dbuser> -p <dbpassword> --db <dbname> --collection groups --file import.json --jsonArray
```

Не забываем сохранить себе оттуда же **MongoDB URI**.

## Heroku

Сервис [Heroku](https://www.heroku.com/) безусловно хорош простым развертыванием окружения и деплоеем из консоли. Как бесплатная песочница он прекрасен, что уж говорить. Для тестирования нашего зоопарка самое то. Регистрация и создание приложения там предельно просты - **"Create New App"** и готово. Приложение назвалось `slack-magic-ru` и соответственно после настройки стало доступно по адресу https://slack-magic-ru.herokuapp.com/

Для работы в консоли устанавливаем [Heroku CLI](https://devcenter.heroku.com/articles/heroku-cli) и проверяем:
```
heroku --version
```

Переходим в папку с нашим проектом и создаем там файл `composer.json`:
```
{
  "require": {
      "mongodb/mongodb": "^1.1",
      "ext-mongodb": "*",
      "php": "^7.1.0"
  }
}
```

И файл `Procfile`:
```
web: vendor/bin/heroku-php-apache2 public/
```

Создаем папку `public` и в ней файл-заглушку `index.php` с нехитрым содержимым:
```php
<?php
echo "Работает!";
```

Устанавливаем зависимости [при помощи команды](https://devcenter.heroku.com/articles/php-support#using-optional-extensions):
```
composer update --ignore-platform-reqs
```

Инициализуем репозиторий:
```
git init
```

Не забываем про файл `.gitignore`:
```
vendor
```

Добавляем и коммитим наше богатство:
```
git add .
git commit -m "поехали"
```

Затем логинимся в Heroku:
```
heroku login
```

Добавляем удаленный репозиторий:
```
heroku git:remote -a slack-magic-ru
```

И пушим все на сервер:
```
git push heroku master
```

![](https://github.com/eveness/slack-magic/blob/master/screenshots/cli-heroku-push.png)

Проверяем по нашей ссылке от Heroku все ли работает. Если нет - читаем логи, молимся, перечитываем все сначала.

## Приложение для Slack

Прежде чем отвечать на запросы пользователей в слаке, нужно понять, как их получать. Для того, чтобы в канале слака можно было вводить свои команды и получать вменяемый (или не очень) ответ, необходимо сначала [добавить приложение](https://api.slack.com/apps?new_app=1).

После создания то, что нам нужно в **"Add features and functionality"** под названием **"Slash Commands"**:

![](https://github.com/eveness/slack-magic/blob/master/screenshots/slack-app.png)

Добавляем для начала команды `/info` и `/next`, в **"Request URL"** указываем наш сайт на heroku:

![](https://github.com/eveness/slack-magic/blob/master/screenshots/slack-create-command.png)

Далее идем в **"Install your app to your team"** и нажимаем **"Install App to Team"**:

![](https://github.com/eveness/slack-magic/blob/master/screenshots/slack-install-app.png)

Идем в любой канал слака и проверяем команду `/info` или `/next` в деле. Успех:

![](https://github.com/eveness/slack-magic/blob/master/screenshots/slack-command-test.png)

## Ответ на команду

Отвечать [принято](https://api.slack.com/slash-commands) в формате JSON и со статусом 200. Если мы будем отвечать тем же текстом "Работает!", то это должно выглядеть так:

```
{
  "text": "Работает!"
}
```

Про возможности форматирования [можно почитать в документации](https://api.slack.com/docs/messages), а пока сделаем ответ таким:
```
{
  "response_type": "in_channel",
  "text": "Работает!",
  "attachments": [
      {
          "text":"Но это не точно..."
      }
  ]
}
```

При указании в `response_type` значения `in_channel` ответ будет виден всем в канале, и команда тоже.

Переписываем наш `index.php` в соответствии с этим:
```php
<?php

$response['response_type'] = 'in_channel';
$response['text'] = 'Работает!';
$response['attachments'][]['text'] = 'Но это не точно...';

http_response_code(200);
header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
```

Пушим на heroku и снова проверяем команду `/info` в слаке:

![](https://github.com/eveness/slack-magic/blob/master/screenshots/slack-command-test-in-channel.png)

## Данные от Slack App

От приложения Slack данные приходят в виде POST-запроса на наш сервер, например, такие:
```
token=gIkuvaNzQIHg97ATvDxqgjtO
team_id=T0001
team_domain=example
channel_id=C2147483705
channel_name=test
user_id=U2147483697
user_name=user
command=/info
text=show me!
response_url=https://hooks.slack.com/commands/1234/5678
```

Пока все, что нас интересует - `command` и `text`. В `command` должно приходить `/info` или `/next`, а в `text` название группы. Будем наивно полагать, что мы живем в идеальном мире и все так и происходит. Переписываем `index.php`, используя в ответе немного [базового форматирования](https://api.slack.com/docs/message-formatting):

```php
<?php

$command = $_POST['command'];
$group = $_POST['text'];

$status = 200;

switch ($command) {
  case '/info':
    $response['text'] = 'Информация о группе *' . $group . '*.';
    break;

  case '/next':
    $response['text'] = 'Ближайшее занятие группы *' . $group . '* состоится завтра, в 19:00. Тема занятия: _«Блочная модель. Выравнивание»_.';
    break;

  default:
    $status = 404;
    $response['text'] = 'Неверная команда!';
    break;
}

http_response_code($status);
header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
```

Тестируем локально при помощи, например, [плагина RESTED для Chrome](https://chrome.google.com/webstore/detail/rested/eelcnbccaccipfolokglfhhmapdchbfg), пушим на heroku, проверяем в слаке:

![](https://github.com/eveness/slack-magic/blob/master/screenshots/slack-command-test-2.png)

Пора браться за базу данных.

## Работа с MongoDB в PHP

Для работы с базой данных в PHP используется библиотека классов MongoDB, которую мы уже установили при помощи Composer в самом начале. Документацию по работе с ней [можно почитать тут](https://docs.mongodb.com/php-library/master/reference/).

Сначала проверим локально, адекватно ли мы наполнили данные и как с ними работать:
```php
<?php
// подключаем автозагрузчик классов
require '../vendor/autoload.php';

$command = $_POST['command'];
$group = $_POST['text'];
$status = 404;
$response['text'] = 'Неверная команда!';

// подключаемся к MongoDB при помощи MongoDB URI
$client = new MongoDB\Client("mongodb://localhost:27017");
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

http_response_code($status);
header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
```

Снова тестируем локально, отправляя разные POST-запросы. Когда все заработает как надо, успокаиваемся и думаем как это залить на heroku. Задача стояла и локально тестить, и на github залить и собственно деплой на heroku сделать. Конфиг выносим отдельно, в файл `cfg.php`:

```php
<?php

$mongodb_uri = 'mongodb://localhost:27017';
```

Дальше там еще прибавится переменных. Добавляем `cfg.php` в `.gitignore`, коммитимся.

Создадим ветку `heroku`, где будет храниться другой конфиг и `.gitignore`.

```
git checkout -b heroku
```

В ветке `heroku` убираем из `.gitignore` файл `cfg.php` и меняем `$mongodb_uri` на **MongoDB URI** из mlab.com, коммитим и пушим на heroku из этой ветки:
```
git push heroku heroku:master
```

Снова проверяем команды в слаке, работает:

![](https://github.com/eveness/slack-magic/blob/master/screenshots/slack-command-test-3.png)

Собственно отсюда пушим и на github. Единственная проблема в таком подходе - при переключении на `master` нужно сохранять файл `cfg.example.php` как `cfg.php`. Другой вариант - использовать переменные окружения и сохранять все в файле `.env`, для heroku [использовать](https://devcenter.heroku.com/articles/config-vars#setting-up-config-vars-for-a-deployed-application) `heroku config:set`, но это отдельный разговор.

## Защищаемся

Конечно полагаться на данные, которые приходят в запросе - нельзя, никогда. Для начала очищаем их:
```php
$command = strip_tags(trim($_POST['command']));
$group = strip_tags(trim($_POST['text']));
```

Добавляем проверку на пустые значения, зачем вхолостую к базе подключаться:
```php
if ($command && $group) {
  // подключение и все остальное
}
```

Тут хорошо бы сразу проверять команды на правильные и только тогда подключаться и получать данные о группе.
```php
$commands = ['/info', '/next'];
if ($command && in_array($command, $commands) && $group) {
  // подключение и все остальное
}
```

Добавляем в `cfg.php` переменную `$slack_app_token`, в которой будет храниться токен приложения Slack:
```php
<?php

$mongodb_uri = 'mongodb://localhost:27017';
$slack_app_token = '123456';
```

Добавляем проверку в `index.php`:
```php
$token = strip_tags(trim($_POST['token']));
$token_check = ($token == $slack_app_token);

if ($token_check && $command && in_array($command, $commands) && $group) {
  // подключение и все остальное
}
```

Тестируем локально, если ничего не сломалось, идем в ветку `heroku`. Теперь возвращаемся к нашему приложению Slack и находим в **Basic Information** токен **Verification Token** и добавляем в `cfg.php`.

## Примитивизм

Конечно можно (да и нужно) спроектировать и реализовать нормальное REST API, прикрутить какой-нибудь микрофреймворк для роутинга и других банальных штук, обновлять данные о группах PUT-запросами из системы и прочие плюшки с блекджеком и балеринами. Как вариант - проверять канал чата и брать название группы оттуда, да и вообще проверять пользователя на соответствие студенту, чтобы "всякие не шастали тут".

Пост не про это, а про то, как быстро и просто запилить магию. А головой думать в качественом смысле можно и своей.