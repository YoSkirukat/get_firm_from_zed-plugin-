<?php

// Кодировка UTF-8.

defined('ABSPATH') or die();

//---------------------------------------------------------

function gffz_ServiceUserRoutine()
{
//	$Res = gffz_CreateRoboForm();
	$Res .= gffz_CreateInfo();
	$Res .= gffz_CreatePanel_SU();

	if(isset($_POST['CMD']))
		$Res .= gffz_ExecCMD_SU();
	else
		$Res .= gffz_GetSamsungForm();

	return $Res;
}

//---------------------------------------------------------

function gffz_ExecCMD_SU()
{
	$Res = '';

	switch($_POST['CMD'])
	{
		case 'GetStat':
			$Res = gffz_GetUserStat_SU();
			break;

		case 'Samsung':
			$Res = gffz_GetSamsungForm();
			break;

		case 'Epson':
			$Res = gffz_GetEpsonForm();
			break;
	}

	return $Res;
}

//---------------------------------------------------------

function gffz_CreatePanel_SU()
{
	$Res = '<div style="margin: 0px 0px 15px;">';
	$Res .= '<form action="" method="post" style="display: inline-block;"><input type="hidden" name="CMD" value="GetStat" /><input type="submit" value="Статистика" /></form>' . GFFZ_NL;
	$Res .= '<form action="" method="post" style="display: inline-block;"><input type="hidden" name="CMD" value="Samsung" /><input type="submit" value="Samsung" /></form>' . GFFZ_NL;
	$Res .= '<form action="" method="post" style="display: inline-block;"><input type="hidden" name="CMD" value="Epson" /><input type="submit" value="Epson" /></form>' . GFFZ_NL;
	$Res .= '</div>';

	return $Res;
}

//---------------------------------------------------------

function gffz_GetUserStat_SU()
{
	global $wpdb;
	global $gffz_UserID;

	$Login;
	$UserName;
	$Date;
	$WP_User;
	$RecordsCount;
	$First;
	$Next;
	$Cost;
	$Record;
	$UsersData = array();
	$Index = array();
	$Balans = 0;
	$UserTable = $wpdb->prefix . GFFZ_USERS_TBL;

	wp_enqueue_style('gffz_s005', GFFZ_FILES_URL . 's005.css');
	wp_enqueue_script('gffz_j005', GFFZ_FILES_URL . 'j005.js', array('jquery'), null, true);

	$Res = '<div style="margin: 0px 0px 15px;">Отображать последних записей&nbsp;<input type="text" id="gffz_Filter" style="width: 50px; padding: 5px; text-align: right;"/></div>';
	$Res .= "<table class=\"UserStatTbl\"><thead>
			<th>Логин</th>
			<th>Имя</th>
			<th>Модель</th>
			<th>S/N</th>
			<th>CRUM</th>
			<th>Дата</th>
			<th>IP</th>
			<th>Стоимость</th>
			<th>Баланс</th>
			</thead><tbody>" . GFFZ_NL;

	$Records = $wpdb->get_results(GFFZ_STAT_REQ);
	if(! empty($Records))
	{
		$RecordsCount = count($Records);
		for($i = 0; $i < $RecordsCount; ++$i)
		{
			$Index[$Records[$i]->ID] = $i;
			if($Records[$i]->First) $First = $Records[$i]->ID;
		}

		$BeginRecord = gffz_GetRef();
		if($BeginRecord !== false)
		{
			$Next = $BeginRecord['BeginRecID'];
			$UsersData = $BeginRecord['UsersData'];
			if(isset($UsersData[$gffz_UserID])) $Balans = $UsersData[$gffz_UserID];
		}
		else
			$Next = $First;

		for($i = 0; $i < $RecordsCount; ++$i)
		{
			$Record = $Records[$Index[$Next]];

			if($Record->UserID == $gffz_UserID)
			{
				$Login = $wpdb->get_var("SELECT Login FROM {$UserTable} WHERE UserID={$Record->UserID};");
				$WP_User = get_user_by('id', $Record->UserID);
				if($WP_User) $UserName = $WP_User->data->display_name; else $UserName = 'N/A';
				$Date = substr($Record->Date, 0, 10);
				$Cost = -$Record->BaseCost * $Record->Rate / 100;
				$Balans += $Cost;

				$Res .= "<tr><td>{$Login}</td>
						 <td>{$UserName}</td>
						 <td>{$Record->Model}</td>
						 <td>{$Record->SN}</td>
						 <td>{$Record->CRUM}</td>
						 <td>{$Date}</td>
						 <td>{$Record->IP}</td>
						 <td style=\"text-align: right;\">{$Cost}</td>
						 <td style=\"text-align: right;\">{$Balans}</td>
						 </tr>" . GFFZ_NL;
			}

			$Next = $Record->Next;
		}
	}

	$Res .= '</tbody></table>' . GFFZ_NL;

	return $Res;
}

//---------------------------------------------------------
