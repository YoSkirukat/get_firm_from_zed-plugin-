<?php

// Кодировка UTF-8.

defined('ABSPATH') or die();

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

//---------------------------------------------------------

function gffz_CreateTableUsers()
{
	global $wpdb;

	$TableName = $wpdb->prefix . GFFZ_USERS_TBL;
	$sql = "CREATE TABLE {$TableName} (
		ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		UserID BIGINT UNSIGNED NOT NULL,
		Login VARCHAR(60) NOT NULL,
		Registered DATETIME NOT NULL,
		Rate SMALLINT NOT NULL,
		TrustLimit FLOAT NOT NULL,
		ServiceAccess BOOL NOT NULL,
		Live BOOL NOT NULL,
		PRIMARY KEY  (ID),
		UNIQUE (UserID)
		) DEFAULT CHARACTER SET {$wpdb->charset};";

	dbDelta($sql);
}

//---------------------------------------------------------

function gffz_CreateTableModels()
{
	global $wpdb;

	$TableName = $wpdb->prefix . GFFZ_MODELS_TBL;
	$sql = "CREATE TABLE {$TableName} (
		ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		Model VARCHAR(191) NOT NULL,
		Cost FLOAT NOT NULL,
		Article VARCHAR(64) NOT NULL DEFAULT '',
		PRIMARY KEY  (ID),
		UNIQUE KEY Model (Model),
		KEY Article (Article)
		) DEFAULT CHARACTER SET {$wpdb->charset};";

	dbDelta($sql);
}

//---------------------------------------------------------

function gffz_CreateTableStat()
{
	global $wpdb;

	$TableName = $wpdb->prefix . GFFZ_STAT_TBL;
	$sql = "CREATE TABLE {$TableName} (
		ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		UserID BIGINT UNSIGNED NOT NULL,
		Next BIGINT UNSIGNED NOT NULL,
		Model VARCHAR(255) NOT NULL,
		SN VARCHAR(32) NOT NULL,
		CRUM VARCHAR(32) NOT NULL,
		Date DATETIME NOT NULL,
		IP VARCHAR(32) NOT NULL,
		Rate SMALLINT NOT NULL,
		BaseCost FLOAT NOT NULL,
		AdminCost FLOAT NOT NULL,
		First BOOL NOT NULL,
		Success BOOL NOT NULL,
		Archive BOOL NOT NULL,
		PRIMARY KEY  (ID),
		KEY Date (Date)
		) DEFAULT CHARACTER SET {$wpdb->charset};";

	dbDelta($sql);
}

//---------------------------------------------------------

function gffz_UpdateUsersFromWP()
{
	global $wpdb;

	$Values;
	$UsersCount;
	$ChunkSize = 500;
	$TableName = $wpdb->prefix . GFFZ_USERS_TBL;

	$Users = $wpdb->get_results("SELECT u.ID, u.user_login, u.user_registered FROM {$wpdb->users} AS u
								 LEFT JOIN {$TableName} AS g ON g.UserID = u.ID WHERE g.ID IS NULL;");
	if(empty($Users)) return;

	$UsersCount = count($Users);
	for($i = 0; $i < $UsersCount; $i += $ChunkSize)
	{
		$Values = array();
		for($j = $i; $j < $UsersCount && $j < $i + $ChunkSize; ++$j)
			$Values[] = $wpdb->prepare('(%d, %s, %s, 0, 0, 0, 1)', $Users[$j]->ID, $Users[$j]->user_login, $Users[$j]->user_registered);

		if(! empty($Values))
			$wpdb->query("INSERT INTO {$TableName} (UserID, Login, Registered, Rate, TrustLimit, ServiceAccess, Live) VALUES " . implode(',', $Values) . ';');
	}
}

//---------------------------------------------------------

function gffz_AddStatRecord($UserID, $Model, $SN, $CRUM, $IP, $Rate, $BaseCost, $AdminCost, $Success)
{
	global $wpdb;

	while(!gffz_DB_Lock(1));

	$Res = false;
	$TableName = $wpdb->prefix . GFFZ_STAT_TBL;
	$Prev = $wpdb->get_var("SELECT ID FROM {$TableName} WHERE Next=0;");
	$First = $Prev ? 0 : 1;
	$Date = current_time('mysql');

	$Res = $wpdb->insert($TableName, array('UserID'=>$UserID, 'Next'=>0, 'Model'=>$Model, 'SN'=>$SN, 'CRUM'=>$CRUM, 'Date'=>$Date, 'IP'=>$IP,
										   'Rate'=>$Rate, 'BaseCost'=>$BaseCost, 'AdminCost'=>$AdminCost, 'First'=>$First, 'Success'=>$Success),
						 array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%d', '%d'));
	if($Res && $Prev)
		$Res = $wpdb->update($TableName, array('Next'=>$wpdb->insert_id), array('ID'=>$Prev), array('%d'), array('%d'));

	gffz_DB_Unlock();
	return($Res ? true : false);
}

//---------------------------------------------------------

function gffz_InsertStatRecord($InsertBeforeID, $UserID, $Model, $SN, $CRUM, $IP, $Rate, $BaseCost, $AdminCost, $Success)
{
	global $wpdb;

	while(!gffz_DB_Lock(1));

	$Res = false;
	$TableName = $wpdb->prefix . GFFZ_STAT_TBL;
	$Prev = $wpdb->get_var("SELECT ID FROM {$TableName} WHERE Next={$InsertBeforeID};");
	$First = $Prev ? 0 : 1;
	$Date = current_time('mysql');

	$Res = $wpdb->insert($TableName, array('UserID'=>$UserID, 'Next'=>$InsertBeforeID, 'Model'=>$Model, 'SN'=>$SN, 'CRUM'=>$CRUM, 'Date'=>$Date, 'IP'=>$IP,
										   'Rate'=>$Rate, 'BaseCost'=>$BaseCost, 'AdminCost'=>$AdminCost, 'First'=>$First, 'Success'=>$Success),
						 array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%d', '%d'));
	if($Res)
		if($Prev)
			$Res = $wpdb->update($TableName, array('Next'=>$wpdb->insert_id), array('ID'=>$Prev), array('%d'), array('%d'));
		else
			$Res = $wpdb->update($TableName, array('First'=>0), array('ID'=>$InsertBeforeID), array('%d'), array('%d'));

	gffz_DB_Unlock();
	return($Res ? true : false);
}

//---------------------------------------------------------

function gffz_CreateTableStatFail()
{
	global $wpdb;

	$TableName = $wpdb->prefix . GFFZ_STAT_FAIL_TBL;
	$sql = "CREATE TABLE {$TableName} (
		ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		UserID BIGINT UNSIGNED NOT NULL,
		Next BIGINT UNSIGNED NOT NULL,
		Model VARCHAR(255) NOT NULL,
		SN VARCHAR(32) NOT NULL,
		CRUM VARCHAR(32) NOT NULL,
		Date DATETIME NOT NULL,
		IP VARCHAR(32) NOT NULL,
		First BOOL NOT NULL,
		PRIMARY KEY  (ID)
		) DEFAULT CHARACTER SET {$wpdb->charset};";

	dbDelta($sql);
}

//---------------------------------------------------------

function gffz_AddFailRecord($UserID, $Model, $SN, $CRUM, $IP)
{
	global $wpdb;

	while(!gffz_DB_Lock(1));

	$Res = false;
	$TableName = $wpdb->prefix . GFFZ_STAT_FAIL_TBL;
	$Prev = $wpdb->get_var("SELECT ID FROM {$TableName} WHERE Next=0;");
	$First = $Prev ? 0 : 1;
	$Date = current_time('mysql');

	$Res = $wpdb->insert($TableName, array('UserID'=>$UserID, 'Next'=>0, 'Model'=>$Model, 'SN'=>$SN, 'CRUM'=>$CRUM, 'Date'=>$Date, 'IP'=>$IP, 'First'=>$First),
						 array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d'));
	if($Res && $Prev)
		$Res = $wpdb->update($TableName, array('Next'=>$wpdb->insert_id), array('ID'=>$Prev), array('%d'), array('%d'));

	gffz_DB_Unlock();
	return($Res ? true : false);
}

//---------------------------------------------------------

function gffz_CreateTablePluginConfig()
{
	global $wpdb;

	$TableName = $wpdb->prefix . GFFZ_PLUGIN_CFG_TBL;
	$sql = "CREATE TABLE {$TableName} (
		ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		CFG TEXT NOT NULL,
		PRIMARY KEY  (ID)
		) DEFAULT CHARACTER SET {$wpdb->charset};";

	dbDelta($sql);
}

//---------------------------------------------------------

function gffz_CreateTableRef()
{
	global $wpdb;

	$TableName = $wpdb->prefix . GFFZ_REF_TBL;
	$sql = "CREATE TABLE {$TableName} (
		ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		BeginRecID BIGINT UNSIGNED NOT NULL,
		UsersData BLOB NOT NULL,
		PRIMARY KEY  (ID)
		) DEFAULT CHARACTER SET {$wpdb->charset};";

	dbDelta($sql);
}

//---------------------------------------------------------

function gffz_GetRef()
{
	global $wpdb;
	$Res = false;

	$Records = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . GFFZ_REF_TBL . ';');
	if(! empty($Records))
	{
		$Rec = $Records[0];
		$Res = array('BeginRecID'=>$Rec->BeginRecID, 'UsersData'=>json_decode($Rec->UsersData, true));
	}

	return $Res;
}

//---------------------------------------------------------

function gffz_RefUpdate($BeginRecID, $UsersData)
{
	global $wpdb;

	$TableName = $wpdb->prefix . GFFZ_REF_TBL;
	$Data = json_encode($UsersData);

	$Records = $wpdb->get_results("SELECT * FROM {$TableName};");
	if(empty($Records))
	{
		$wpdb->insert($TableName, array('BeginRecID'=>$BeginRecID, 'UsersData'=>$Data), array('%d', '%s'));
	}
	else
	{
		$Rec = $Records[0];
		$wpdb->update($TableName, array('BeginRecID'=>$BeginRecID, 'UsersData'=>$Data), array('ID'=>$Rec->ID), array('%d', '%s'), array('%d'));
	}
}

//---------------------------------------------------------

function gffz_DB_Lock($aTimeOut)
{
	global $wpdb;

	$Res = $wpdb->get_var("SELECT GET_LOCK('" . GFFZ_DB_LOCK . "',{$aTimeOut});");
	return $Res == 1 ? true : false;
}

//---------------------------------------------------------

function gffz_DB_Unlock()
{
	global $wpdb;

	$Res = $wpdb->get_var("SELECT RELEASE_LOCK('" . GFFZ_DB_LOCK . "');");
	return $Res == 1 ? true : false;
}

//---------------------------------------------------------
// Цены моделей прошивок (колонка Cost в таблице Models List).

function gffz_GetModelCosts()
{
	global $wpdb;

	$Res = array();
	$TableName = $wpdb->prefix . GFFZ_MODELS_TBL;

	$Records = $wpdb->get_results("SELECT Model, Cost FROM {$TableName};");
	if(empty($Records)) return $Res;

	foreach($Records as $Record)
		$Res[$Record->Model] = (float)$Record->Cost;

	return $Res;
}

//---------------------------------------------------------
// Артикулы моделей (значения value в таблице Models List).

function gffz_GetModelArticles()
{
	global $wpdb;

	$Res = array();
	$TableName = $wpdb->prefix . GFFZ_MODELS_TBL;

	$Records = $wpdb->get_results("SELECT Model, Article FROM {$TableName} WHERE Article<>'';");
	if(empty($Records)) return $Res;

	foreach($Records as $Record)
		$Res[$Record->Model] = (string)$Record->Article;

	return $Res;
}

//---------------------------------------------------------
// Карта "артикул -> цена" (значения value в таблице Models List).

function gffz_GetArticleCosts()
{
	global $wpdb;

	$Res = array();
	$TableName = $wpdb->prefix . GFFZ_MODELS_TBL;

	$Records = $wpdb->get_results("SELECT Article, Cost FROM {$TableName} WHERE Article<>'';");
	if(empty($Records)) return $Res;

	foreach($Records as $Record)
		$Res[(string)$Record->Article] = (float)$Record->Cost;

	return $Res;
}

//---------------------------------------------------------
// Разбирает HTML-таблицу Models List в список моделей:
// array(array('ID'=>артикул, 'Name'=>название), ...).

function gffz_ParseModelList($Tbl)
{
	$Res = array();

	while(1)
	{
		if(! is_string($Tbl) || $Tbl === '') break;

		$Pattern = '/<input[^>]*name=["\']MDL["\'][^>]*>.*?<td[^>]*>(.*?)<\/td>/is';
		if(! preg_match_all($Pattern, $Tbl, $Matches, PREG_SET_ORDER)) break;

		foreach($Matches as $Match)
		{
			if(! preg_match('/value=["\']([^"\']*)["\']/i', $Match[0], $Code)) continue;

			$ID = trim(html_entity_decode($Code[1], ENT_QUOTES, 'UTF-8'));
			$Name = trim(html_entity_decode(strip_tags($Match[1]), ENT_QUOTES, 'UTF-8'));

			if($ID === '' && $Name === '') continue;

			$Res[] = array('ID'=>$ID, 'Name'=>$Name);
		}

		break;
	}

	return $Res;
}

//---------------------------------------------------------
// Название модели по её артикулу (код из таблицы Models List).
// Нужно для цен, сохранённых до появления колонки Article:
// позволяет связать артикул вариации товара с названием модели.

function gffz_GetArticleModelName($Article)
{
	$Res = '';

	while(1)
	{
		$Article = is_string($Article) ? trim($Article) : '';
		if($Article === '') break;
		if(! function_exists('gffz_GetTBL')) break;

		$Map = get_transient('gffz_article_map');

		if(! is_array($Map))
		{
			$Map = array();
			$Tbl = gffz_GetTBL();

			if($Tbl !== false)
			{
				foreach(gffz_ParseModelList($Tbl) as $Row)
				{
					$Code = $Row['ID'];
					$Name = $Row['Name'];

					if($Code !== '' && $Name !== '' && ! isset($Map[$Code])) $Map[$Code] = $Name;
				}
			}

			// Пустой результат кэшируется ненадолго, чтобы не тормозить продажи.
			set_transient('gffz_article_map', $Map, empty($Map) ? 300 : 3600);
		}

		if(isset($Map[$Article])) $Res = $Map[$Article];
		break;
	}

	return $Res;
}

//---------------------------------------------------------
// Цена по артикулу без обращения к таблице Models List.
// Возвращает null, если артикул ещё не привязан к цене.

function gffz_GetArticleCost($Article)
{
	global $wpdb;

	$Article = is_string($Article) ? trim($Article) : '';
	if($Article === '') return null;

	$TableName = $wpdb->prefix . GFFZ_MODELS_TBL;
	$Cost = $wpdb->get_var($wpdb->prepare("SELECT Cost FROM {$TableName} WHERE Article=%s AND Article<>'' LIMIT 1;", $Article));

	return is_null($Cost) ? null : (float)$Cost;
}

//---------------------------------------------------------
// Возвращает заданную цену модели или null, если цена не задана.
// Модель ищется по названию, затем (если задан) по артикулу.

function gffz_GetModelCost($Model, $Article = '')
{
	global $wpdb;

	$TableName = $wpdb->prefix . GFFZ_MODELS_TBL;
	$Model = is_string($Model) ? trim($Model) : '';
	$Article = is_string($Article) ? trim($Article) : '';

	if($Model !== '')
	{
		$Cost = $wpdb->get_var($wpdb->prepare("SELECT Cost FROM {$TableName} WHERE Model=%s;", $Model));
		if(! is_null($Cost)) return (float)$Cost;
	}

	if($Article !== '')
	{
		$Cost = $wpdb->get_var($wpdb->prepare("SELECT Cost FROM {$TableName} WHERE Article=%s AND Article<>'' LIMIT 1;", $Article));
		if(! is_null($Cost)) return (float)$Cost;

		// Артикул ещё не сохранён: ищем название модели по коду.
		$Name = gffz_GetArticleModelName($Article);
		if($Name !== '')
		{
			$Cost = $wpdb->get_var($wpdb->prepare("SELECT Cost FROM {$TableName} WHERE Model=%s;", $Name));
			if(! is_null($Cost))
			{
				gffz_SetModelCost($Name, (float)$Cost, $Article);
				return (float)$Cost;
			}
		}
	}

	return null;
}

//---------------------------------------------------------
// Стоимость прошивки для владельца сервиса.
// Если цена модели не задана, используется GFFZ_ADMIN_COST.

function gffz_GetFlashCost($Model, $Article = '')
{
	$Cost = gffz_GetModelCost($Model, $Article);
	if(is_null($Cost) || $Cost <= 0) $Cost = GFFZ_ADMIN_COST;

	return $Cost;
}

//---------------------------------------------------------
// Сохраняет цену модели. Пустая (нулевая) цена удаляет запись,
// после чего снова действует GFFZ_ADMIN_COST.
// Артикул сохраняется вместе с ценой; пустой артикул
// не затирает ранее сохранённый.

function gffz_SetModelCost($Model, $Cost, $Article = '')
{
	global $wpdb;

	if(! is_string($Model)) return false;
	if(is_array($Cost) || is_object($Cost)) return false;

	$Model = trim($Model);
	if($Model === '') return false;

	$ModelLen = function_exists('mb_strlen') ? mb_strlen($Model) : strlen($Model);
	if($ModelLen > 191) return false;

	$Article = is_string($Article) ? trim($Article) : '';
	$ArticleLen = function_exists('mb_strlen') ? mb_strlen($Article) : strlen($Article);
	if($ArticleLen > 64)
		$Article = function_exists('mb_substr') ? mb_substr($Article, 0, 64) : substr($Article, 0, 64);

	$TableName = $wpdb->prefix . GFFZ_MODELS_TBL;
	$Cost = (float)str_replace(',', '.', trim((string)$Cost));

	if($Cost <= 0)
		return $wpdb->delete($TableName, array('Model'=>$Model), array('%s')) !== false;

	$Res = $wpdb->query($wpdb->prepare("INSERT INTO {$TableName} (Model, Cost, Article) VALUES (%s, %f, %s) ON DUPLICATE KEY UPDATE Cost=VALUES(Cost), Article=IF(VALUES(Article)<>'', VALUES(Article), Article);", $Model, $Cost, $Article));

	return $Res !== false;
}

//---------------------------------------------------------
