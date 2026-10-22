<?php

// Кодировка UTF-8.

define('GFFZ_SERVICE_USER', 'Printblog'); // Имя пользователя для доступа к сервису герератора прошивок.
define('GFFZ_SERVICE_PASS', '36XNDmfMm'); // Пароль для доступа к сервису герератора прошивок.
// define('GFFZ_SERVICE_HOST', 'http://fixserv.dynvpn.de'); // URL сервиса герератора прошивок. (http://fixserv.dynvpn.de или http://fixserv.cloudns.cc)
define('GFFZ_SERVICE_HOST', 'http://fixserv.cloudns.cc'); // URL сервиса
define('GFFZ_ALL_USERS_ALLOWED', 1); // Разрешает (1) или нет (0) всем пользователям видеть страницу сервиса.
define('GFFZ_ALL_USERS_MSG', 'Сервис не доступен.'); // Сообщение для обычного пользователя, при попытке воспользоваться сервисом.
define('GFFZ_HTTP_TIMEOUT', 30); // Время ожидания (в секундах), прежде чем HTTP запрос завершится с ошибкой при отсутствии данных.
define('GFFZ_BASE_COST', 10); // Базовая стоимость.
define('GFFZ_ADMIN_COST', 3.5); // Стоимость для владельца сервиса.
define('GFFZ_LOW_LIMIT_MSG', 'Баланс ниже допустимого.'); // Строка, отображаемая вместо формы заказа, если баланс меньше лимита доверия.
define('GFFZ_ADMIN_USER_ID', 1); // ID пользователя, являющегося администратором (необходим для расчёта баланса).
define('GFFZ_GET_NOT_AUTHORIZED_MSG', 'Необходимо авторизоваться.'); // Сообщение для неавторизованного пользователя, при попытке отправки запроса.
define('GFFZ_ROBO_PASS1', 'uH021jfYa7Dc5jPsGqOF'); // Пароль1 для Робокассы.
define('GFFZ_ROBO_PASS2', 'Gj2SQHIMPle2cF3XXv63'); // Пароль2 для Робокассы.
define('GFFZ_ROBO_MERCHANT_LOGIN', 'printblog-online'); // ID получателя платежей, зарегистрированного в Робокассе.
define('GFFZ_ROBO_DESCRIPTION', ''); // Описание платежа, передаваемое форме оплаты Робокассы.
define('GFFZ_STAT_FILTER', '50'); // Значение по умолчанию для фильтра статистики.
