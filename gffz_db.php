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
		PRIMARY KEY  (ID)
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
