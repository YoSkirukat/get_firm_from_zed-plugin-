<?php

/*
Plugin Name: get_firm_from_zed
Description: Plugin for getting firmware from Zed.
Version: 1.16
Author: Optimus
*/

// Кодировка UTF-8.

//---------------------------------------------------------

defined('ABSPATH') or die();

require_once('gffz_cfg.php');
require_once(GFFZ_USER_CFG_FILE);
require_once('gffz_db.php');
require_once('gffz_admin.php');
require_once('gffz_service_user.php');
require_once('gffz_user.php');

if(defined('GFFZ_SERVICE_USER') && defined('GFFZ_SERVICE_PASS'))
	define('GFFZ_SERVICE_REQUEST', '/cgi-bin/module.exe?ID1=' . GFFZ_SERVICE_USER . '&ID2=' . GFFZ_SERVICE_PASS);

define('GFFZ_SERVICE_REQUEST_POST', '/cgi-bin/module.exe');

$gffz_Admin = false;
$gffz_ServiceUser = false;
$gffz_UserID = 0;

//---------------------------------------------------------

add_filter('gffz_MakeTicket',
function($param)
{
	$ret = '';

	while(1)
	{
		$Url = gffz_GetURL();
		if($Url === false) break;

		$response = wp_remote_post($Url . GFFZ_SERVICE_REQUEST_POST, array('timeout'=>GFFZ_HTTP_TIMEOUT, 'httpversion'=>'1.1', 'method'=>'POST', 'headers'=>array('Content-type'=>'application/x-www-form-urlencoded'), 'body'=>array('EPSON'=>$param, ID1=>GFFZ_SERVICE_USER, ID2=>GFFZ_SERVICE_PASS)));
		if(is_wp_error($response)) break;

		$ret = $response['body'];
		break;
	}

	return $ret;
});

add_filter('the_content', 'gffz_ContentFilter');

add_action('wp_ajax_gffz_ModifyUsers', 'gffz_ModifyUsers');
add_action('wp_ajax_gffz_ModifyModels', 'gffz_ModifyModels');
add_action('wp_ajax_gffz_SendRequest', 'gffz_SendRequest');
add_action('wp_gffz_SendRequest_Action', 'gffz_SendRequest_Action');
add_action('wp_ajax_nopriv_gffz_SendRequest', 'gffz_nopriv_SendRequest');
add_action('wp_ajax_gffz_RemoveStat', 'gffz_RemoveStat');
add_action('wp_ajax_gffz_ModifyStat', 'gffz_ModifyStat');
add_action('wp_ajax_gffz_ModifyUserStat', 'gffz_ModifyUserStat');
add_action('wp_ajax_gffz_RoboCalcSign', 'gffz_Robo_CalcSign');
add_action('plugins_loaded', 'gffz_Upgrade');

register_activation_hook(__FILE__, 'gffz_PluginActivate');
register_deactivation_hook(__FILE__, 'gffz_PluginDeactivation');

//---------------------------------------------------------

function gffz_ContentFilter($content)
{
	global $gffz_Admin;
	global $gffz_ServiceUser;

	$Res = false;

	while(1)
	{
		if(strpos($content, GFFZ_PLUGIN_PAGE_TAG) === false) break;

		if(isset($_POST['InvId'])) gffz_Robo_ProcessReq();

		if(! gffz_PermittedUser()) {$Res = gffz_UserRoutine(); break;}

		if($gffz_Admin) {$Res = gffz_AdminRoutine(); break;}

		if($gffz_ServiceUser) {$Res = gffz_ServiceUserRoutine(); break;}

		$Res = gffz_UserRoutine();
		break;
	}

	if($Res === false) $Res = $content; else $Res = str_replace(GFFZ_PLUGIN_PAGE_TAG, $Res, $content);

	return $Res;
}

//---------------------------------------------------------

function gffz_GetURL()
{
	$UrlBeg = '<frame src="';
	$UrlEnd = '"';
	$Res = false;
	$Attempts = 0;

	while(1)
	{
		$response = wp_remote_get(GFFZ_SERVICE_HOST, array('timeout'=>GFFZ_HTTP_TIMEOUT, 'httpversion'=>'1.1'));
		while (is_wp_error($response) && $Attempts <10) {$Attempts++; sleep (5); $response = wp_remote_get(GFFZ_SERVICE_HOST, array('timeout'=>GFFZ_HTTP_TIMEOUT, 'httpversion'=>'1.1'));}
		if(is_wp_error($response)) break;
/*
		$Str = $response['body'];
		$Pos = stripos($Str, $UrlBeg);
		if($Pos === false) break;

		$Pos += strlen($UrlBeg);
		$Pos1 = stripos($Str, $UrlEnd, $Pos);
		if($Pos1 === false) break;

		$UrlLen = $Pos1 - $Pos;
		if($UrlLen < 1) break;

		$Url = substr($Str, $Pos, $UrlLen);
		if($Url === false) break;
*/
		$Url = GFFZ_SERVICE_HOST;
		$Res = $Url;
		break;
	}

	return $Res;
}

//---------------------------------------------------------

function gffz_GetTBL()
{
	$TblBeg = '<table class="MdlTbl">';
	$TblEnd = '</table>';
	$Res = false;

	while(1)
	{
		$Url = gffz_GetURL();
		if($Url === false) break;

		$Response = wp_remote_get($Url . GFFZ_SERVICE_REQUEST . '&LOGIN=TRUE', array('timeout'=>GFFZ_HTTP_TIMEOUT, 'httpversion'=>'1.1'));
		if(is_wp_error($Response)) break;

		$Str = iconv('windows-1251', 'utf-8', $Response['body']);

		$Pos = stripos($Str, $TblBeg);
		if($Pos === false) break;

		$Pos1 = stripos($Str, $TblEnd, $Pos);
		if($Pos1 === false) break;

		$TblLen = $Pos1 - $Pos + strlen($TblEnd);

		$Tbl = substr($Str, $Pos, $TblLen);
		if($Tbl === false) break;

		$Res = $Tbl;
		break;
	}

	return $Res;
}

//---------------------------------------------------------

function gffz_PermittedUser()
{
	global $wpdb;
	global $gffz_Admin;
	global $gffz_ServiceUser;
	global $gffz_UserID;
	$Res = false;

	while(1)
	{
		$gffz_Admin = false;
		$gffz_ServiceUser = false;
		$gffz_UserID = 0;

		$User = wp_get_current_user();
		$Res = $User->exists();
		if(! $Res) break;
		$gffz_UserID = $User->ID;

		if(user_can($User, 'manage_options')) $gffz_Admin = true;

		$Table = $wpdb->prefix . GFFZ_USERS_TBL;
		$ServiceAccess = $wpdb->get_var("SELECT ServiceAccess FROM {$Table} WHERE UserID={$User->ID};");
		if($ServiceAccess) $gffz_ServiceUser = true;

		break;
	}

	return $Res;
}

//---------------------------------------------------------

function gffz_GetSamsungForm()
{
	global $gffz_UserID;
	global $gffz_Admin;
	gffz_Upgrade();
	if(!gffz_CheckUserLimit($gffz_UserID)) return gffz_GetLowLimitMsg();

	$Res = '';

	while(1)
	{
		$Tbl = gffz_GetTBL();
		if($Tbl === false) break;

		wp_enqueue_style('gffz_mdltbls', GFFZ_FILES_URL . 'mdltbl.css');
		wp_enqueue_script('gffz_mdltbl', GFFZ_FILES_URL . 'mdltbl.js', array('jquery'), null, true);
		wp_enqueue_script('gffz_filter', GFFZ_FILES_URL . 'filter.js', array('jquery'), null, true);

		// Колонка Cost доступна только администратору.
		if($gffz_Admin)
		{
			wp_enqueue_script('gffz_mdltbl_cost', GFFZ_FILES_URL . 'mdltbl_cost.js', array('jquery', 'gffz_mdltbl'), null, true);
			wp_localize_script('gffz_mdltbl_cost', 'gffz_ModelCost', array('Url'=>admin_url('admin-ajax.php'), 'Costs'=>(object)gffz_GetModelCosts(), 'Default'=>GFFZ_ADMIN_COST));
		}

		$Res .= '<form action="" method="post" id="SendForm"><br />' . GFFZ_NL;
		$Res .= 'Machine serail number<br />' . GFFZ_NL;
		$Res .= '<input type="text" name="SN" maxlength="15" value="" id="SN"/><br />' . GFFZ_NL;
		$Res .= 'CRM<br />' . GFFZ_NL;
		$Res .= '<input type="text" name="CRM" maxlength="11" value="" id="CRUM"/><br /><br />' . GFFZ_NL;
		$Res .= '<input type="submit" value="Получить" class="button-primary"/><br /><br />' . GFFZ_NL;
		$Res .= 'Filter: &nbsp; &nbsp; <input type="text" id="filter" maxlength="30" /><br /><br />' . GFFZ_NL;
		$Res .= $Tbl . GFFZ_NL;
		if($gffz_Admin) $Res .= '<input type="button" class="ButtApplyCost" value="Применить" />' . GFFZ_NL;
		$Res .= '</form>' . GFFZ_NL;

		wp_enqueue_script('gffz_j002', GFFZ_FILES_URL . 'j002.js', array('jquery'), null, true);
		wp_localize_script('gffz_j002', 'WP_Ajax', array('Url'=>admin_url('admin-ajax.php')));

		break;
	}

	return $Res;
}

//---------------------------------------------------------

function gffz_GetEpsonForm()
{
	global $gffz_UserID;
	if(!gffz_CheckUserLimit($gffz_UserID)) return gffz_GetLowLimitMsg();

	$Res = '';

	while(1)
	{
		$Res .= '<form action="" method="post" id="SendForm"><br />' . GFFZ_NL;
		$Res .= '<textarea name="ReqData" id="ReqData" cols="20" rows="4" placeholder="Введите ключ-запрос Epson Tools"></textarea></p>' . GFFZ_NL;
		$Res .= '<textarea name="Ticket" id="Ticket" cols="20" rows="4" placeholder="Здесь будет ключ-ответ"></textarea></p>' . GFFZ_NL;
//		$Res .= '<input type="text" name="ReqData" id="ReqData"/><br />' . GFFZ_NL;
//		$Res .= '<input type="text" name="Ticket" id="Ticket"/><br />' . GFFZ_NL;
		$Res .= '<input type="submit" value="Получить" id="ButtSend"/><br />' . GFFZ_NL;
		$Res .= '</form>' . GFFZ_NL;

		wp_enqueue_script('gffz_j002e', GFFZ_FILES_URL . 'j002e.js', array('jquery'), null, true);
		wp_localize_script('gffz_j002e', 'WP_Ajax', array('Url'=>admin_url('admin-ajax.php')));

		break;
	}

	return $Res;
}

//---------------------------------------------------------
function gffz_SendRequest()
{
	global $wpdb;

	$User;
	$UserID = 0;
	$msg = 'Wrong request.';
	$res = false;
	$Cost = 0;
	$response;
	$sn = 'Unknown';
	$crm = 'Unknown';
	$mdl = 'Unknown';
	$mdlName = 'Unknown';
	$brand;

	while(1)
	{
		$User = wp_get_current_user();
		if(!$User->exists()) break;
		$ExtMail = $User->user_email;
		$UserID = $User->ID;

		if(!gffz_IsServiceUser()) {$msg = GFFZ_ALL_USERS_MSG; break;}
		if(!gffz_CheckUserLimit($UserID)) {$msg = GFFZ_LOW_LIMIT_MSG; break;}

		if(!isset($_POST['BRAND'])) break;
		$brand = $_POST['BRAND'];

		if($brand != 'Samsung' && $brand != 'Epson') break;

		if($brand == 'Samsung')
		{
			if(!isset($_POST['SN'])) break;
			if(!isset($_POST['CRM'])) break;
			if(!isset($_POST['MDL'])) break;
			if(!isset($_POST['MDLNAME'])) break;

$SLNN = strlen ($_POST['SN']);
       if (strpos ($_POST['MDLNAME'], "3550") ==false && strpos ($_POST['MDLNAME'], "4620") ==false )
if ($SLNN<11) for($SLNN=15-$SLNN;$SLNN>0;$SLNN--) $_POST['SN'] .= ".";		

			$sn = $_POST['SN'];
			$crm = $_POST['CRM'];
			$mdl = $_POST['MDL'];
			$mdlName = $_POST['MDLNAME'];

		}
		else
		{
			if(!isset($_POST['REQUESTDATA'])) break;
			$sn = 'Epson';
			$crm = 'Epson';
			$mdl = 'Epson';
			$mdlName = 'Epson';
		}

		$Url = gffz_GetURL();
		if($Url === false) {$msg = 'Resolving service failed.'; break;}

		if($brand == 'Samsung')
			$response = wp_remote_get($Url . GFFZ_SERVICE_REQUEST . '&SN=' . $sn . '&CRM=' . $crm . '&MDL=' . $mdl . '&MAIL=' . $ExtMail, array('timeout'=>GFFZ_HTTP_TIMEOUT, 'httpversion'=>'1.1'));
		else
			$response = wp_remote_post($Url . GFFZ_SERVICE_REQUEST_POST, array('timeout'=>GFFZ_HTTP_TIMEOUT, 'httpversion'=>'1.1', 'method'=>'POST', 'headers'=>array('Content-type'=>'application/x-www-form-urlencoded'), 'body'=>array('EPSON'=>$_POST['REQUESTDATA'], ID1=>GFFZ_SERVICE_USER, ID2=>GFFZ_SERVICE_PASS)));
		if(is_wp_error($response)) {$msg = 'Sending request failed.'; break;}

		if($brand == 'Samsung')
		{
			$Str = iconv('windows-1251', 'utf-8', $response['body']);
			$Pos = stripos($Str, GFFZ_SUCCESS_PAGE_TAG);
			if($Pos === false) {$msg = 'Unknown service response.'; break;}
			$msg = '';
		}
		else
		{
			if($response['body'] == '') break;
			$msg = $response['body'];
		}

		$res = true;
		break;
	}

	if($res)
	{
		$TableName = $wpdb->prefix . GFFZ_USERS_TBL;
		$Rate = $wpdb->get_var("SELECT Rate FROM {$TableName} WHERE UserID={$UserID};");
		$AdminCost = -gffz_GetFlashCost($mdlName);
		$Res1 = gffz_AddStatRecord($UserID, $mdlName, $sn, $crm, $_SERVER['REMOTE_ADDR'], $Rate, GFFZ_BASE_COST, $AdminCost, 1);
		if($UserID == GFFZ_ADMIN_USER_ID)
			$Cost = $AdminCost;
		else
			$Cost = -GFFZ_BASE_COST * $Rate / 100;
	}
	else
		$Res1 = gffz_AddFailRecord($UserID, $mdlName, $sn, $crm, $_SERVER['REMOTE_ADDR']);

	echo json_encode(array('Result'=>$res, 'Msg'=>$msg, 'Cost'=>$Cost));
	exit;
}

//---------------------------------------------------------

function gffz_SendRequest_Action(&$Args)
{
	global $wpdb;

	$UserID = GFFZ_ADMIN_USER_ID;
	$Res = false;

	$attempts = 0;
	
	while(1)
	{
		if(! isset($Args['SN'])) break;
		if(! isset($Args['CRM'])) break;
		if(! isset($Args['MDL'])) break;
		if(! isset($Args['MDLNAME'])) break;
		if(! isset($Args['RES'])) break;
		if(! isset($Args['MAIL'])) break;

		if(! gffz_CheckUserLimit($UserID)) break;

		$Url = gffz_GetURL();
		if($Url === false) break;

		$response = wp_remote_get($Url . GFFZ_SERVICE_REQUEST . '&SN=' . $Args['SN'] . '&CRM=' . $Args['CRM'] . '&MDL=' . $Args['MDL'] . '&MAIL=' . $Args['MAIL'], array('timeout'=>GFFZ_HTTP_TIMEOUT, 'httpversion'=>'1.1'));
		
		
		if(is_wp_error($response) && $attempts  < 40)
		{
			$attempts++;
			sleep(5);
			continue;
		}
		
		if(is_wp_error($response)) break;
		
		
		

		$Str = iconv('windows-1251', 'utf-8', $response['body']);
		$Pos = stripos($Str, GFFZ_SUCCESS_PAGE_TAG);
		if($Pos === false) break;

		$Res = true;
		$Args['RES'] = $Res;
		break;
	}

	if($Res)
	{
		$TableName = $wpdb->prefix . GFFZ_USERS_TBL;
		$Rate = $wpdb->get_var("SELECT Rate FROM {$TableName} WHERE UserID={$UserID};");
		$AdminCost = -gffz_GetFlashCost($Args['MDLNAME']);
		$Res1 = gffz_AddStatRecord($UserID, $Args['MDLNAME'], $Args['SN'], $Args['CRM'], $_SERVER['REMOTE_ADDR'], $Rate, GFFZ_BASE_COST, $AdminCost, 1);
		
		$order = new WC_Order( $Args['ORDER_ID'] );
		if(	$order)
		{
			$order->update_status('completed');
		}
		
		
	}
	else
		$Res1 = gffz_AddFailRecord($UserID, $Args['MDLNAME'], $Args['SN'], $Args['CRM'], $_SERVER['REMOTE_ADDR']);
}

//---------------------------------------------------------

function gffz_PluginActivate()
{
	gffz_CreateTableUsers();
	gffz_CreateTableModels();
	gffz_CreateTableStat();
	gffz_CreateTableStatFail();
	gffz_CreateTablePluginConfig();
	gffz_CreateTableRef();

	update_option('gffz_db_version', GFFZ_PLUGIN_VERSION);

	gffz_LoadConfig();
}

//---------------------------------------------------------
// Создаёт/обновляет таблицы БД после обновления плагина.

function gffz_Upgrade()
{
	if(get_option('gffz_db_version') === GFFZ_PLUGIN_VERSION) return;

	gffz_CreateTableModels();
	gffz_CreateTableStat();

	update_option('gffz_db_version', GFFZ_PLUGIN_VERSION);
}

//---------------------------------------------------------

function gffz_ModifyUsers()
{
	global $wpdb;

	$Res = false;
	$TableName = $wpdb->prefix . GFFZ_USERS_TBL;

	if(isset($_POST['data']))
		foreach($_POST['data'] as $User)
		{
			$Res = $wpdb->update($TableName,
								 array('ServiceAccess'=>$User['ServiceAccess'], 'Rate'=>$User['Rate'], 'TrustLimit'=>$User['TrustLimit']),
								 array('UserID'=>$User['ID']), array('%d', '%d', '%f'), array('%d'));
			if($Res === false) break;
		}
	if($Res !== false) echo 'success'; else echo 'fail';

	exit;
}

//---------------------------------------------------------

function gffz_ModifyModels()
{
	$Res = true;

	while(1)
	{
		if(! current_user_can('manage_options')) {$Res = false; break;}
		if(! isset($_POST['data']) || ! is_array($_POST['data'])) {$Res = false; break;}

		foreach($_POST['data'] as $Model)
		{
			if(! is_array($Model) || ! isset($Model['Model'])) {$Res = false; break;}

			$Cost = isset($Model['Cost']) ? $Model['Cost'] : '';

			if(! gffz_SetModelCost($Model['Model'], $Cost)) {$Res = false; break;}
		}

		break;
	}

	if($Res) echo 'success'; else echo 'fail';

	exit;
}

//---------------------------------------------------------

function gffz_RemoveStat()
{
	global $wpdb;

	$First;
	$Next;
	$UpdateID;
	$Res = false;
	$TableName = $wpdb->prefix . GFFZ_STAT_TBL;

	while(isset($_POST['ID']))
	{
		if(! $_POST['ID']) {$Res = true; break;}

		$First = $wpdb->get_var("SELECT First FROM {$TableName} WHERE ID={$_POST['ID']};");
		if(is_null($First)) break;

		$Next = $wpdb->get_var("SELECT Next FROM {$TableName} WHERE ID={$_POST['ID']};");
		if(is_null($Next)) break;

		if(! $First)
		{
			$UpdateID = $wpdb->get_var("SELECT ID FROM {$TableName} WHERE Next={$_POST['ID']};");
			if(is_null($UpdateID)) break;

			$Res = $wpdb->update($TableName, array('Next'=>$Next), array('ID'=>$UpdateID), array('%d'), array('%d'));
			if(! $Res) break;

			$Res = $wpdb->delete($TableName, array('ID'=>$_POST['ID']), array('%d'));
			break;
		}

		if(! $Next)
		{
			$Res = $wpdb->delete($TableName, array('ID'=>$_POST['ID']), array('%d'));
			break;
		}

		$Res = $wpdb->update($TableName, array('First'=>1), array('ID'=>$Next), array('%d'), array('%d'));
		if(! $Res) break;

		$Res = $wpdb->delete($TableName, array('ID'=>$_POST['ID']), array('%d'));
		break;
	}

	if($Res) echo 'success'; else echo 'fail';

	exit;
}

//---------------------------------------------------------

function gffz_ModifyStat()
{
	global $wpdb;

	$User;
	$Res = false;
	$TableStat = $wpdb->prefix . GFFZ_STAT_TBL;
	$TableUser = $wpdb->prefix . GFFZ_USERS_TBL;

	while(1)
	{
		if(! isset($_POST['ID'])) break;
		if(! isset($_POST['ID1'])) break;
		if(! isset($_POST['Model'])) break;
		if(! isset($_POST['SN'])) break;
		if(! isset($_POST['CRUM'])) break;
		if(! isset($_POST['AdminCost'])) break;

		$User = wp_get_current_user();
		if(! $User->exists()) break;

		if($_POST['ID'] && $_POST['ID1'])
		{
			$Res = $wpdb->update($TableStat,
								 array('Model'=>$_POST['Model'], 'SN'=>$_POST['SN'], 'CRUM'=>$_POST['CRUM'], 'AdminCost'=>$_POST['AdminCost']),
								 array('ID'=>$_POST['ID']), array('%s', '%s', '%s', '%f'), array('%d'));
			break;
		}

		$Rate = $wpdb->get_var("SELECT Rate FROM {$TableUser} WHERE UserID={$User->ID};");

		if(! $_POST['ID'] && ! $_POST['ID1'])
		{

			$Res = gffz_AddStatRecord($User->ID, $_POST['Model'], $_POST['SN'], $_POST['CRUM'], $_SERVER['REMOTE_ADDR'], $Rate, GFFZ_BASE_COST, $_POST['AdminCost'], 1);
			break;
		}

		$Res = gffz_InsertStatRecord($_POST['ID1'], $User->ID, $_POST['Model'], $_POST['SN'], $_POST['CRUM'], $_SERVER['REMOTE_ADDR'], $Rate, GFFZ_BASE_COST, $_POST['AdminCost'], 1);
		break;
	}

	if($Res !== false) echo 'success' . $wpdb->insert_id; else echo 'fail';

	exit;
}

//---------------------------------------------------------

function gffz_ModifyUserStat()
{
	global $wpdb;

	$User;
	$Res = false;
	$AdminCost = 0;
	$TableStat = $wpdb->prefix . GFFZ_STAT_TBL;
	$TableUser = $wpdb->prefix . GFFZ_USERS_TBL;

	while(1)
	{
		if(! isset($_POST['ID'])) break;
		if(! isset($_POST['ID1'])) break;
		if(! isset($_POST['Model'])) break;
		if(! isset($_POST['SN'])) break;
		if(! isset($_POST['CRUM'])) break;
		if(! isset($_POST['Rate'])) break;
		if(! isset($_POST['BaseCost'])) break;
		if(! isset($_POST['UserID'])) break;

		$User = wp_get_current_user();
		if(! $User->exists()) break;

		$AdminCost = -gffz_GetFlashCost($_POST['Model']);
		if($_POST['Rate'] < 0) $AdminCost = 0;

		if($_POST['ID'] && $_POST['ID1'])
		{
			if($_POST['Rate'] < 0)
				$Res = $wpdb->update($TableStat,
									 array('Model'=>$_POST['Model'], 'SN'=>$_POST['SN'], 'CRUM'=>$_POST['CRUM'], 'BaseCost'=>$_POST['BaseCost'], 'Rate'=>$_POST['Rate'], 'AdminCost'=>0),
									 array('ID'=>$_POST['ID']), array('%s', '%s', '%s', '%f', '%d', '%f'), array('%d'));
			else
				$Res = $wpdb->update($TableStat,
									 array('Model'=>$_POST['Model'], 'SN'=>$_POST['SN'], 'CRUM'=>$_POST['CRUM'], 'BaseCost'=>$_POST['BaseCost'], 'Rate'=>$_POST['Rate']),
									 array('ID'=>$_POST['ID']), array('%s', '%s', '%s', '%f', '%d'), array('%d'));

			break;
		}

		if(! $_POST['ID'] && ! $_POST['ID1'])
		{

			$Res = gffz_AddStatRecord($_POST['UserID'], $_POST['Model'], $_POST['SN'], $_POST['CRUM'], $_SERVER['REMOTE_ADDR'], $_POST['Rate'], $_POST['BaseCost'], $AdminCost, 1);
			break;
		}

		$Res = gffz_InsertStatRecord($_POST['ID1'], $_POST['UserID'], $_POST['Model'], $_POST['SN'], $_POST['CRUM'], $_SERVER['REMOTE_ADDR'], $_POST['Rate'], $_POST['BaseCost'], $AdminCost, 1);
		break;
	}

	if($Res !== false) echo 'success' . $wpdb->insert_id; else echo 'fail';

	exit;
}

//---------------------------------------------------------

function gffz_CheckUserLimit($UserID)
{
	global $wpdb;

	$Balans;
	$TrustLimit;
	$Res = true;
	$UserTable = $wpdb->prefix . GFFZ_USERS_TBL;

	$Records = $wpdb->get_results(GFFZ_BALANS_STAT_REQ);
	if(! empty($Records))
	{
		$Balans = gffz_CalcUserBalans($UserID, $Records);
		$TrustLimit = $wpdb->get_var("SELECT TrustLimit FROM {$UserTable} WHERE UserID={$UserID};");
		if(! is_null($TrustLimit) && $Balans <= $TrustLimit) $Res = false;
	}

	return $Res;
}

//---------------------------------------------------------

function gffz_CreateInfo()
{
	global $gffz_UserID;
	global $wpdb;

	$Balans = 'n/a';
	$TrustLimit = 'n/a';
	$UserTable = $wpdb->prefix . GFFZ_USERS_TBL;

	$Records = $wpdb->get_results(GFFZ_BALANS_STAT_REQ);
	if(! empty($Records)) $Balans = gffz_CalcUserBalans($gffz_UserID, $Records);
	$k = $wpdb->get_var("SELECT TrustLimit FROM {$UserTable} WHERE UserID={$gffz_UserID};");
	if(! is_null($k)) $TrustLimit = $k;

	$Res = '<div style="margin: 0px 0px 15px; background-color: #b2dbf6; padding: 5px;">';
	$Res .= "баланс: <span id=\"Balans\">{$Balans}</span> | кредитный лимит: {$TrustLimit}";
	$Res .= '</div>';

	return $Res;
}

//---------------------------------------------------------

function gffz_CalcUserBalans($UserID, $Stat, $CheckAdmin = true)
{
	$Cost;
	$Record;
	$RecordsCount;
	$Next;
	$First;
	$Idx;
	$UsersData = array();
	$Index = array();
	$Balans = 0;

	$RecordsCount = count($Stat);
	for($i = 0; $i < $RecordsCount; ++$i)
	{
		$Index[$Stat[$i]->ID] = $i;
		if($Stat[$i]->First) $First = $Stat[$i]->ID;
	}

	$BeginRecord = gffz_GetRef();
	if($BeginRecord !== false)
	{
		$Next = $BeginRecord['BeginRecID'];
		$UsersData = $BeginRecord['UsersData'];
	}
	else
		$Next = $First;

	for($i = 0; $i < $RecordsCount; ++$i)
	{
		$Record = $Stat[$Index[$Next]];

		if($CheckAdmin && $UserID == GFFZ_ADMIN_USER_ID)
		{
			$Cost = $Record->AdminCost;
		}
		else
			if($Record->UserID == $UserID)
			{
				$Cost = -$Record->BaseCost * $Record->Rate / 100;
			}
			else
				$Cost = 0;

		$Balans += $Cost;

		$Next = $Record->Next;
	}

	if($CheckAdmin && $UserID == GFFZ_ADMIN_USER_ID)
		$Idx = GFFZ_OWNER_ID;
	else
		$Idx = $UserID;

	if(isset($UsersData[$Idx])) $Balans += $UsersData[$Idx];
	return $Balans;
}

//---------------------------------------------------------

function gffz_CalcUsersBalans($Stat)
{
	$Record;
	$RecordsCount;
	$Next;
	$First = 0;
	$AdminCost = 0;
	$Index = array();
	$Balans = array();
	$UsersData = array();

	$RecordsCount = count($Stat);
	if(! $RecordsCount) return $Balans;

	for($i = 0; $i < $RecordsCount; ++$i)
	{
		$Index[$Stat[$i]->ID] = $i;
		if($Stat[$i]->First) $First = $Stat[$i]->ID;
	}

	$BeginRecord = gffz_GetRef();
	if($BeginRecord !== false)
	{
		$Next = $BeginRecord['BeginRecID'];
		$UsersData = $BeginRecord['UsersData'];
	}
	else
		$Next = $First;

	// Один проход по цепочке записей вместо отдельного прохода на каждого пользователя.
	for($i = 0; $i < $RecordsCount; ++$i)
	{
		if(! isset($Index[$Next])) break;
		$Record = $Stat[$Index[$Next]];

		if(! isset($Balans[$Record->UserID])) $Balans[$Record->UserID] = 0;
		$Balans[$Record->UserID] += -$Record->BaseCost * $Record->Rate / 100;
		$AdminCost += $Record->AdminCost;

		$Next = $Record->Next;
	}

	foreach($Balans as $UserID => $Value)
		$Balans[$UserID] = $Value + (isset($UsersData[$UserID]) ? $UsersData[$UserID] : 0);

	$Balans[GFFZ_OWNER_ID] = $AdminCost + (isset($UsersData[GFFZ_OWNER_ID]) ? $UsersData[GFFZ_OWNER_ID] : 0);

	return $Balans;
}

//---------------------------------------------------------

function gffz_IsAdmin()
{
	global $wpdb;
	$Res = false;

	while(1)
	{
		$User = wp_get_current_user();
		if(! $User->exists()) break;
		if(user_can($User, 'manage_options')) $Res = true;

		break;
	}

	return $Res;
}

//---------------------------------------------------------

function gffz_IsServiceUser()
{
	global $wpdb;
	$Res = false;

	while(1)
	{
		$User = wp_get_current_user();
		if(! $User->exists()) break;

		$Table = $wpdb->prefix . GFFZ_USERS_TBL;
		$ServiceAccess = $wpdb->get_var("SELECT ServiceAccess FROM {$Table} WHERE UserID={$User->ID};");
		if($ServiceAccess) $Res = true;

		break;
	}

	return $Res;
}

//---------------------------------------------------------

function gffz_nopriv_SendRequest()
{
	echo GFFZ_GET_NOT_AUTHORIZED_MSG;
	exit;
}

//---------------------------------------------------------

function gffz_SaveConfig()
{
	global $wpdb;

	$CFG_File = GFFZ_FILES_PATH . GFFZ_USER_CFG_FILE;
	$TableName = $wpdb->prefix . GFFZ_PLUGIN_CFG_TBL;

	while(1)
	{
		if(! is_readable($CFG_File)) break;

		$Str = file_get_contents($CFG_File);
		if($Str === false) break;

		$Row = $wpdb->get_row("SELECT * FROM {$TableName};");
		if(! is_null($Row))
			$wpdb->update($TableName, array('CFG'=>$Str), array('ID'=>$Row->ID), array('%s'), array('%d'));
		else
			$wpdb->insert($TableName, array('CFG'=>$Str), array('%s'));

		break;
	}
}

//---------------------------------------------------------

function gffz_LoadConfig()
{
	global $wpdb;

	$Value;
	$QuotationMarks;
	$ConfigLoaded = false;
	$CFG_File = GFFZ_FILES_PATH . GFFZ_USER_CFG_FILE;
	$Temp_CFG_File = GFFZ_FILES_PATH . 'tmp_' . GFFZ_USER_CFG_FILE;
	$TableName = $wpdb->prefix . GFFZ_PLUGIN_CFG_TBL;
	$Config = "<?php\n\n// Кодировка UTF-8.\n\n";
	$Params = array(
		array('Name' => 'GFFZ_SERVICE_USER', 'Val' => '', 'Str' => 1, 'Descr' => 'Имя пользователя для доступа к сервису герератора прошивок.'),
		array('Name' => 'GFFZ_SERVICE_PASS', 'Val' => '', 'Str' => 1, 'Descr' => 'Пароль для доступа к сервису герератора прошивок.'),
		array('Name' => 'GFFZ_SERVICE_HOST', 'Val' => 'http://fixserv.dynns.com', 'Str' => 1, 'Descr' => 'URL сервиса герератора прошивок.'),
		array('Name' => 'GFFZ_ALL_USERS_ALLOWED', 'Val' => 0, 'Str' => 0, 'Descr' => 'Разрешает (1) или нет (0) всем пользователям видеть страницу сервиса.'),
		array('Name' => 'GFFZ_ALL_USERS_MSG', 'Val' => 'Сервис не доступен.', 'Str' => 1, 'Descr' => 'Сообщение для обычного пользователя, при попытке воспользоваться сервисом.'),
		array('Name' => 'GFFZ_HTTP_TIMEOUT', 'Val' => 30, 'Str' => 0, 'Descr' => 'Время ожидания (в секундах), прежде чем HTTP запрос завершится с ошибкой при отсутствии данных.'),
		array('Name' => 'GFFZ_BASE_COST', 'Val' => 1, 'Str' => 0, 'Descr' => 'Базовая стоимость.'),
		array('Name' => 'GFFZ_ADMIN_COST', 'Val' => 1, 'Str' => 0, 'Descr' => 'Стоимость для владельца сервиса.'),
		array('Name' => 'GFFZ_LOW_LIMIT_MSG', 'Val' => 'Баланс ниже допустимого.', 'Str' => 1, 'Descr' => 'Строка, отображаемая вместо формы заказа, если баланс меньше лимита доверия.'),
		array('Name' => 'GFFZ_ADMIN_USER_ID', 'Val' => 1, 'Str' => 0, 'Descr' => 'ID пользователя, являющегося администратором (необходим для расчёта баланса).'),
		array('Name' => 'GFFZ_GET_NOT_AUTHORIZED_MSG', 'Val' => 'Необходимо авторизоваться.', 'Str' => 1, 'Descr' => 'Сообщение для неавторизованного пользователя, при попытке отправки запроса.'),
		array('Name' => 'GFFZ_ROBO_PASS1', 'Val' => '', 'Str' => 1, 'Descr' => 'Пароль1 для Робокассы.'),
		array('Name' => 'GFFZ_ROBO_PASS2', 'Val' => '', 'Str' => 1, 'Descr' => 'Пароль2 для Робокассы.'),
		array('Name' => 'GFFZ_ROBO_MERCHANT_LOGIN', 'Val' => '', 'Str' => 1, 'Descr' => 'ID получателя платежей, зарегистрированного в Робокассе.'),
		array('Name' => 'GFFZ_ROBO_DESCRIPTION', 'Val' => '', 'Str' => 1, 'Descr' => 'Описание платежа, передаваемое форме оплаты Робокассы.'),
		array('Name' => 'GFFZ_STAT_FILTER', 'Val' => '50', 'Str' => 1, 'Descr' => 'Значение по умолчанию для фильтра статистики.')
	);

	if(is_writable($CFG_File))
	{
		foreach($Params as $Param)
			if(defined($Param['Name']))
			{
				$ConfigLoaded = true;
				break;
			}

		if(! $ConfigLoaded)
		{
			$Row = $wpdb->get_row("SELECT * FROM {$TableName};");
			if(! is_null($Row))
			{
				file_put_contents($Temp_CFG_File, $Row->CFG);
				include($Temp_CFG_File);
				unlink($Temp_CFG_File);
			}
		}

		foreach($Params as $Param)
		{
			if(defined($Param['Name']))
				$Value = constant($Param['Name']);
			else
				$Value = $Param['Val'];

			$QuotationMarks = $Param['Str'] ? "'" : "";
			$Config .= "define('" . $Param['Name'] . "', " . $QuotationMarks . $Value . $QuotationMarks . "); // " . $Param['Descr'] . "\n";
		}

		file_put_contents($CFG_File, $Config);
	}
}

//---------------------------------------------------------

function gffz_PluginDeactivation()
{
	gffz_SaveConfig();
}

//---------------------------------------------------------

function gffz_CreateRoboForm()
{
	global $gffz_UserID;

	$WP_User = get_user_by('id', $gffz_UserID);
	$Email = $WP_User ? $WP_User->data->user_email : '';
	$MerchantLogin = GFFZ_ROBO_MERCHANT_LOGIN;
	$Description = GFFZ_ROBO_DESCRIPTION;

	wp_enqueue_script('gffz_Robo_j001', GFFZ_FILES_URL . 'robo_j001.js', array('jquery'), null, true);
	wp_localize_script('gffz_Robo_j001', 'WP_Ajax', array('Url'=>admin_url('admin-ajax.php')));

	$Res = '<div style="margin: 0px 0px 15px; padding: 5px;">';
	$Res .= '<form style="display: inline-block;" action="https://auth.robokassa.ru/Merchant/Index.aspx" method="post" id="gffz_RoboSendReq">';
	$Res .= '<input type="hidden" name="OutSum" id="gffz_RoboOutSumm" />';
	$Res .= '<input type="hidden" name="SignatureValue" id="gffz_RoboSign" />';
	$Res .= '<input type="hidden" name="shp_sum" id="gffz_RoboShpSumm" />';
	$Res .= '<input type="hidden" name="InvoiceID" value="0" />';
	$Res .= '<input type="hidden" name="Culture" value="ru" />';
	$Res .= '<input type="hidden" name="Encoding" value="utf-8" />';
	$Res .= '<input type="hidden" name="OutSumCurrency" value="USD" />';
	$Res .= "<input type=\"hidden\" name=\"shp_id\" value=\"{$gffz_UserID}\" />";
	$Res .= "<input type=\"hidden\" name=\"MerchantLogin\" value=\"{$MerchantLogin}\" />";
	$Res .= "<input type=\"hidden\" name=\"Description\" value=\"{$Description}\" />";
	$Res .= "<input type=\"hidden\" name=\"Email\" value=\"{$Email}\" />";
	$Res .= '</form>';
	$Res .= 'Пополнение баланса через ROBOKASSA&nbsp;';
	$Res .= '<input type="text" id="gffz_RoboSumm" style="width: 50px; padding: 5px; text-align: right;" />&nbsp;$&nbsp;';
	$Res .= '<input type="button" value="Оплатить" id="gffz_ButtSubmit" />';
	$Res .= '</div>';

	return $Res;
}

//---------------------------------------------------------

function gffz_Robo_ProcessReq()
{
	while(1)
	{
		if(! isset($_POST['SignatureValue'])) break;
		if(! isset($_POST['InvId'])) break;
		if(! isset($_POST['shp_id'])) break;
		if(! isset($_POST['shp_sum'])) break;
		if(! isset($_POST['OutSum'])) break;

		$Pass1 = GFFZ_ROBO_PASS1;
		$Sign = md5("{$_POST['OutSum']}:{$_POST['InvId']}:{$Pass1}:shp_id={$_POST['shp_id']}:shp_sum={$_POST['shp_sum']}");
		if($Sign != $_POST['SignatureValue']) break;

		$UserID = $_POST['shp_id'];
		$Model = 'Пополнение через ROBOKASSA';
		$SN = 'Номер счёта: ' . $_POST['InvId'];
		$CRUM = 'Дата: ' . current_time('mysql');
		$IP = $_SERVER['REMOTE_ADDR'];
		$Rate = -100;
		$BaseCost = $_POST['shp_sum'];
		$AdminCost = 0;
		gffz_AddStatRecord($UserID, $Model, $SN, $CRUM, $IP, $Rate, $BaseCost, $AdminCost, 1);

		break;
	}

	return;
}

//---------------------------------------------------------

function gffz_Robo_CalcSign()
{
	global $gffz_UserID;
	global $gffz_ServiceUser;

	$Res = false;
	$MerchantLogin = GFFZ_ROBO_MERCHANT_LOGIN;
	$Pass1 = GFFZ_ROBO_PASS1;

	gffz_PermittedUser();

	if($gffz_ServiceUser && isset($_POST['Summ']))
	{
		$Sign = md5("{$MerchantLogin}:{$_POST['Summ']}:0:USD:{$Pass1}:shp_id={$gffz_UserID}:shp_sum={$_POST['Summ']}");
		$Res = true;
	}

	if($Res) echo 'success' . $Sign; else echo 'fail';
	exit;
}

//---------------------------------------------------------

function gffz_GetLowLimitMsg()
{
	return '<font color="red">' . GFFZ_LOW_LIMIT_MSG . '</font>';
}
//---------------------------------------------------------