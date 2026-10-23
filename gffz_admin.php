<?php

// Кодировка UTF-8.

defined('ABSPATH') or die();

//---------------------------------------------------------

function gffz_AdminRoutine()
{
	global $gffz_ServiceUser;

	$Res = gffz_CreateInfo();
	$Res .= gffz_CreatePanel_A();

	while(1)
	{
		if(isset($_POST['CMD'])) {$Res .= gffz_ExecCMD_A(); break;}

		if($gffz_ServiceUser) $Res .= gffz_GetSamsungForm();

		break;
	}

	return $Res;
}

//---------------------------------------------------------

function gffz_ExecCMD_A()
{
	global $gffz_ServiceUser;

	$Res = '';

	switch($_POST['CMD'])
	{
		case 'GetUsers':
			$Res = gffz_GetUsers();
			break;

		case 'CleanUsers':
			$Res = gffz_CleanUsers();
			break;

		case 'GetCommStat':
			$Res = gffz_GetCommStat();
			break;

		case 'Report':
			$Res = gffz_GetReport();
			break;

		case 'GetUserStat':
			$Res = gffz_GetUserStat_A();
			break;

		case 'ArchStat':
			$Res = gffz_ArchStat_A();
			break;

		case 'Samsung':
			if($gffz_ServiceUser) $Res = gffz_GetSamsungForm();
			break;

		case 'Epson':
			if($gffz_ServiceUser) $Res = gffz_GetEpsonForm();
			break;
	}

	return $Res;
}

//---------------------------------------------------------

function gffz_CreatePanel_A()
{
	$Res = '<div style="margin: 0px 0px 15px;">';
	$Res .= '<form action="" method="post" style="display: inline-block;"><input type="hidden" name="CMD" value="GetCommStat" /><input type="submit" value="Статистика" /></form>' . GFFZ_NL;
	$Res .= '<form action="" method="post" style="display: inline-block;"><input type="hidden" name="CMD" value="Report" /><input type="submit" value="Отчёт" /></form>' . GFFZ_NL;
	$Res .= '<form action="" method="post" style="display: inline-block;"><input type="hidden" name="CMD" value="GetUsers" /><input type="submit" value="Пользователи" /></form>' . GFFZ_NL;
	$Res .= '<form action="" method="post" style="display: inline-block;"><input type="hidden" name="CMD" value="CleanUsers" /><input type="submit" value="Очистить" /></form>' . GFFZ_NL;
	$Res .= '<form action="" method="post" style="display: inline-block;"><input type="hidden" name="CMD" value="Samsung" /><input type="submit" value="Samsung" /></form>' . GFFZ_NL;
	$Res .= '<form action="" method="post" style="display: inline-block;"><input type="hidden" name="CMD" value="Epson" /><input type="submit" value="Epson" /></form>' . GFFZ_NL;
	$Res .= '</div>';

	return $Res;
}

//---------------------------------------------------------

function gffz_GetUsers()
{
	global $wpdb;

	$Records;
	$Balans;
	$BalansData;
	$Idx;

	wp_enqueue_style('gffz_s001', GFFZ_FILES_URL . 's001.css');
	wp_enqueue_script('gffz_j001', GFFZ_FILES_URL . 'j001.js', array('jquery'), null, true);
	wp_localize_script('gffz_j001', 'WP_Ajax', array('Url'=>admin_url('admin-ajax.php')));

	gffz_UpdateUsersFromWP();

	$Res = '<form action="" method="post" id="SendCMD"><input type="hidden" name="CMD" value="GetUserStat" /><input type="hidden" name="ID" /></form>';
	$Res .= '<table class="UserTbl"><thead>
			<th>Логин</th>
			<th>Имя</th>
			<th>E-Mail</th>
			<th>Доступ</th>
			<th>Коэфф.</th>
			<th>Лимит</th>
			<th>Баланс</th></thead><tbody>' . GFFZ_NL;

	$TableName = $wpdb->prefix . GFFZ_USERS_TBL;
	$Users = $wpdb->get_results("SELECT * FROM {$TableName} WHERE ServiceAccess=1;");
	if(! empty($Users))
	{
		$Records = $wpdb->get_results(GFFZ_BALANS_STAT_REQ);
		$BalansData = empty($Records) ? array() : gffz_CalcUsersBalans($Records);

		foreach($Users as $User)
		{
			if(empty($Records))
				$Balans = 'n/a';
			else
			{
				$Idx = ($User->UserID == GFFZ_ADMIN_USER_ID) ? GFFZ_OWNER_ID : $User->UserID;
				$Balans = isset($BalansData[$Idx]) ? $BalansData[$Idx] : 0;
			}

			if($User->Live)
			{
				$WP_User = get_user_by('id', $User->UserID);
				if(! $WP_User)
				{
					$wpdb->update($TableName, array('Live'=>0, 'ServiceAccess'=>0), array('ID'=>$User->ID), array('%d', '%d'), array('%d'));
					$User->Live = 0;
					$User->ServiceAccess = 0;
				}
			}

			$Res .= "<tr><td class= \"GetUserStat\" id=\"{$User->UserID}\">{$User->Login}</td>";
			if($User->Live)
			{
				$Res .= "<td>{$WP_User->data->display_name}</td>
						 <td>{$WP_User->data->user_email}</td>
						 <td style=\"text-align: center;\"><input type=\"checkbox\" class=\"CB_Access\"";
				$Res .= $User->ServiceAccess ? ' checked' : '';
				$Res .= " /></td><td class=\"edit\" style=\"text-align: right;\">{$User->Rate}</td>
						<td class=\"edit\" style=\"text-align: right;\">{$User->TrustLimit}</td>
						<td style=\"text-align: right;\">{$Balans}</td></tr>" . GFFZ_NL;
			}
			else
			{
				$Res .= "<td>N/A</td>
						 <td>N/A</td>
						 <td>N/A</td>
						 <td style=\"text-align: right;\">{$User->Rate}</td>
						 <td style=\"text-align: right;\">{$User->TrustLimit}</td>
						 <td style=\"text-align: right;\">{$Balans}</td></tr>" . GFFZ_NL;
			}
		}
	}

	$Res .= '</tbody></table>' . GFFZ_NL;
	$Res .= '<input type="button" class="ButtApply" value="Применить" />' . GFFZ_NL;

	return $Res;
}

//---------------------------------------------------------

function gffz_GetCommStat()
{
	global $wpdb;

	$Login;
	$UserName;
	$Date;
	$WP_User;
	$RecordsCount;
	$i;
	$First;
	$Next;
	$Record;
	$Index = array();
	$UsersData = array();

	wp_enqueue_style('gffz_s003', GFFZ_FILES_URL . 's003.css');
	wp_enqueue_script('gffz_j003', GFFZ_FILES_URL . 'j003.js', array('jquery'), null, true);
	wp_localize_script('gffz_j003', 'WP_Ajax', array('Url'=>admin_url('admin-ajax.php')));

	$Res = '<form action="" method="post" id="GetUserStatCMD"><input type="hidden" name="CMD" value="GetUserStat" /><input type="hidden" name="ID" /></form>';
	$Res .= '<form action="" method="post" id="ArchStatCMD"><input type="hidden" name="CMD" value="ArchStat" /><input type="hidden" name="ID" /></form>';
	$Res .= '<div style="margin: 0px 0px 15px;">Отображать последних записей&nbsp;<input type="text" id="gffz_Filter" style="width: 50px; padding: 5px; text-align: right;" value="';
	$Res .= GFFZ_STAT_FILTER;
	$Res .= '" /></div>';
	$Res .= '<table class="CommStatTbl"><thead>
			<th></th>
			<th>Логин</th>
			<th>Имя</th>
			<th>Модель</th>
			<th>S/N</th>
			<th>CRUM</th>
			<th>Дата</th>
			<th>Стоимость</th>
			<th>Баланс</th></thead><tbody>' . GFFZ_NL;

	$UserTable = $wpdb->prefix . GFFZ_USERS_TBL;

	$Records = $wpdb->get_results(GFFZ_STAT_REQ);
	if(! empty($Records))
	{
		$RecordsCount = count($Records);
		for($i = 0; $i < $RecordsCount; $i++)
		{
			$Index[$Records[$i]->ID] = $i;
			if($Records[$i]->First) $First = $Records[$i]->ID;
		}

		$BeginRecord = gffz_GetRef();
		if($BeginRecord !== false)
		{
			$Next = $BeginRecord['BeginRecID'];
			$UsersData = $BeginRecord['UsersData'];
		}
		else
			$Next = $First;

		for($i = 0; $i < $RecordsCount; $i++)
		{
			$Record = $Records[$Index[$Next]];
			$Login = $wpdb->get_var("SELECT Login FROM {$UserTable} WHERE UserID={$Record->UserID};");
			$WP_User = get_user_by('id', $Record->UserID);
			if($WP_User) $UserName = $WP_User->data->display_name; else $UserName = 'N/A';
			$Date = substr($Record->Date, 0, 10);

			$Res .= "<tr><td id=\"{$Record->ID}\">";
			$Res .= ($Record->ID != $BeginRecord['BeginRecID']) ? "<input type=\"button\" class=\"ButtAdd\" value=\"^\" /><input type=\"checkbox\" class=\"CB_Del\" />" : "";
			$Res .= "</td><td class= \"GetUserStat\" id=\"{$Record->UserID}\">{$Login}</td>
					 <td>{$UserName}</td>
					 <td class=\"edit\">{$Record->Model}</td>
					 <td class=\"edit\">{$Record->SN}</td>
					 <td class=\"edit\">{$Record->CRUM}</td>
					 <td>{$Date}</td>
					 <td class=\"edit\" style=\"text-align: right;\">{$Record->AdminCost}</td>
					 <td id=\"{$Record->ID}\" style=\"text-align: right;\"></td></tr>" . GFFZ_NL;

			$Next = $Record->Next;
		}
	}

	$Res .= '<tr><td id="last" colspan="9"><input type="button" class="ButtAdd" value="^" /></td></tr>';
	$Res .= '</tbody></table>' . GFFZ_NL;
	$Res .= '<input type="button" class="ButtApply" value="Применить" />' . GFFZ_NL;
	$Res .= '<input type="button" class="ButtArch" value="В архив" />' . GFFZ_NL;
	$BeginBalans = isset($UsersData[GFFZ_OWNER_ID]) ? $UsersData[GFFZ_OWNER_ID] : 0;
	$Res .= '<input type="hidden" id="gffz_BeginBalans" value="' . $BeginBalans . '" />' . GFFZ_NL;

	return $Res;
}

//---------------------------------------------------------
// Выбирает продажи за период.
// Пополнения баланса не входят: у них отрицательная ставка (Rate < 0),
// а также в модели/S/N встречается слово «Пополнение».

function gffz_GetReportRecords($DateFrom, $DateTo)
{
	global $wpdb;

	$Res = array();
	$StatTable = $wpdb->prefix . GFFZ_STAT_TBL;
	$UserTable = $wpdb->prefix . GFFZ_USERS_TBL;

	$DateNext = DateTime::createFromFormat('!Y-m-d', $DateTo);
	if($DateNext === false) return $Res;
	$DateNext = $DateNext->modify('+1 day')->format('Y-m-d');

	$PopLike = '%' . $wpdb->esc_like('Пополнение') . '%';

	$Records = $wpdb->get_results($wpdb->prepare(
		"SELECT ID, UserID, Model, SN, Date, AdminCost FROM {$StatTable}
		 WHERE Date >= %s AND Date < %s
		   AND AdminCost <> 0
		   AND Rate >= 0
		   AND Model NOT LIKE %s
		   AND SN NOT LIKE %s
		 ORDER BY Date ASC, ID ASC;",
		$DateFrom . ' 00:00:00', $DateNext . ' 00:00:00', $PopLike, $PopLike));
	if(empty($Records)) return $Res;

	$Logins = array();
	$Users = $wpdb->get_results("SELECT UserID, Login FROM {$UserTable};");
	if(! empty($Users))
		foreach($Users as $User) $Logins[(int)$User->UserID] = $User->Login;

	foreach($Records as $Record)
	{
		$UserID = (int)$Record->UserID;
		$User = isset($Logins[$UserID]) ? (string)$Logins[$UserID] : '';

		if($User === '')
		{
			$WP_User = get_user_by('id', $UserID);
			if($WP_User) $User = $WP_User->data->display_name;
		}

		if($User === '') $User = 'ID ' . $UserID;

		$Res[] = array(
			'ID' => (int)$Record->ID,
			'UserID' => $UserID,
			'User' => $User,
			'Model' => $Record->Model,
			'SN' => $Record->SN,
			'Date' => $Record->Date,
			'Cost' => abs((float)$Record->AdminCost),
		);
	}

	return $Res;
}

//---------------------------------------------------------
// Считает итоги и группировки по моделям и пользователям.

function gffz_BuildReport($Records)
{
	$Models = array();
	$Users = array();
	$Count = 0;
	$Sum = 0.0;

	foreach($Records as $Record)
	{
		$Cost = (float)$Record['Cost'];
		$Model = $Record['Model'];
		$UserID = $Record['UserID'];
		$User = $Record['User'];

		++$Count;
		$Sum += $Cost;

		if(! isset($Models[$Model])) $Models[$Model] = array('Model'=>$Model, 'Count'=>0, 'Sum'=>0.0);
		++$Models[$Model]['Count'];
		$Models[$Model]['Sum'] += $Cost;

		if(! isset($Users[$UserID])) $Users[$UserID] = array('User'=>$User, 'Count'=>0, 'Sum'=>0.0);
		++$Users[$UserID]['Count'];
		$Users[$UserID]['Sum'] += $Cost;
	}

	$Models = array_values($Models);
	usort($Models, function($A, $B)
	{
		if(abs($A['Sum'] - $B['Sum']) < 0.005) return strcmp($A['Model'], $B['Model']);
		return ($A['Sum'] > $B['Sum']) ? -1 : 1;
	});

	$Users = array_values($Users);
	usort($Users, function($A, $B)
	{
		if(abs($A['Sum'] - $B['Sum']) < 0.005) return strcmp($A['User'], $B['User']);
		return ($A['Sum'] > $B['Sum']) ? -1 : 1;
	});

	return array('Count'=>$Count, 'Sum'=>$Sum, 'Records'=>$Records, 'Models'=>$Models, 'Users'=>$Users);
}

//---------------------------------------------------------
// Проверяет дату вида ГГГГ-ММ-ДД. Возвращает её или false.

function gffz_ReportDate($Value)
{
	if(! is_string($Value)) return false;

	$Value = trim($Value);
	if(! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $Value, $Match)) return false;
	if(! checkdate((int)$Match[2], (int)$Match[3], (int)$Match[1])) return false;

	return $Value;
}

//---------------------------------------------------------

function gffz_FormatMoney($Value)
{
	return number_format((float)$Value, 2, '.', ' ');
}

//---------------------------------------------------------

function gffz_FormatReportDate($Value)
{
	$Date = DateTime::createFromFormat('!Y-m-d', $Value);
	return $Date ? $Date->format('d.m.Y') : $Value;
}

//---------------------------------------------------------

function gffz_FormatReportDateTime($Value)
{
	$Date = DateTime::createFromFormat('Y-m-d H:i:s', $Value);
	return $Date ? $Date->format('d.m.Y H:i') : $Value;
}

//---------------------------------------------------------
// Отчёт по продажам за выбранный период.

function gffz_GetReport()
{
	wp_enqueue_style('gffz_s006', GFFZ_FILES_URL . 's006.css');

	$DateFrom = isset($_POST['DateFrom']) ? gffz_ReportDate($_POST['DateFrom']) : false;
	$DateTo = isset($_POST['DateTo']) ? gffz_ReportDate($_POST['DateTo']) : false;
	$FromVal = ($DateFrom === false) ? '' : $DateFrom;
	$ToVal = ($DateTo === false) ? '' : $DateTo;
	$Res = '';

	$Res .= '<form action="" method="post" class="ReportFilter">' . GFFZ_NL;
	$Res .= '<span class="ReportFilterTitle">Отчёт по продажам</span>' . GFFZ_NL;
	$Res .= '<label>с&nbsp;<input type="date" name="DateFrom" value="' . esc_attr($FromVal) . '" /></label>' . GFFZ_NL;
	$Res .= '<label>по&nbsp;<input type="date" name="DateTo" value="' . esc_attr($ToVal) . '" /></label>' . GFFZ_NL;
	$Res .= '<input type="submit" value="Сформировать" />' . GFFZ_NL;
	$Res .= '<input type="hidden" name="CMD" value="Report" />' . GFFZ_NL;
	$Res .= '</form>' . GFFZ_NL;

	// Пока период не выбран, отчёт не формируется.
	if(! isset($_POST['DateFrom']) || ! isset($_POST['DateTo']))
		return $Res . '<div class="ReportHint">Выберите период и нажмите «Сформировать».</div>' . GFFZ_NL;

	if($DateFrom === false || $DateTo === false)
		return $Res . '<div class="ReportError">Укажите корректные даты начала и окончания периода.</div>' . GFFZ_NL;

	if($DateFrom > $DateTo)
		return $Res . '<div class="ReportError">Дата начала периода больше даты окончания.</div>' . GFFZ_NL;

	$Records = gffz_GetReportRecords($DateFrom, $DateTo);
	if(empty($Records))
		return $Res . '<div class="ReportHint">За выбранный период продаж не найдено.</div>' . GFFZ_NL;

	$Report = gffz_BuildReport($Records);

	$Res .= '<div class="ReportSummary">';
	$Res .= 'Период: <b>' . esc_html(gffz_FormatReportDate($DateFrom)) . '&nbsp;&mdash;&nbsp;' . esc_html(gffz_FormatReportDate($DateTo)) . '</b>';
	$Res .= '<span class="ReportSep">|</span>Записей: <b>' . $Report['Count'] . '</b>';
	$Res .= '<span class="ReportSep">|</span>Сумма: <b>' . gffz_FormatMoney($Report['Sum']) . '</b>';
	$Res .= '</div>' . GFFZ_NL;

	$Res .= '<h3 class="ReportHdr">Продажи</h3>' . GFFZ_NL;
	$Res .= '<table class="ReportTbl"><thead><tr><th>Дата</th><th>Пользователь</th><th>Модель</th><th>S/N</th><th class="Num">Стоимость</th></tr></thead><tbody>' . GFFZ_NL;

	foreach($Report['Records'] as $Record)
	{
		$Res .= '<tr>';
		$Res .= '<td class="Date">' . esc_html(gffz_FormatReportDateTime($Record['Date'])) . '</td>';
		$Res .= '<td>' . esc_html($Record['User']) . '</td>';
		$Res .= '<td>' . esc_html($Record['Model']) . '</td>';
		$Res .= '<td>' . esc_html($Record['SN']) . '</td>';
		$Res .= '<td class="Num">' . gffz_FormatMoney($Record['Cost']) . '</td>';
		$Res .= '</tr>' . GFFZ_NL;
	}

	$Res .= '<tr class="Total"><td colspan="4">Всего</td><td class="Num">' . gffz_FormatMoney($Report['Sum']) . '</td></tr>' . GFFZ_NL;
	$Res .= '</tbody></table>' . GFFZ_NL;

	$Res .= '<h3 class="ReportHdr">По моделям</h3>' . GFFZ_NL;
	$Res .= '<table class="ReportTbl ReportGroupTbl"><thead><tr><th>Модель</th><th class="Num">Кол-во</th><th class="Num">Сумма</th></tr></thead><tbody>' . GFFZ_NL;

	foreach($Report['Models'] as $Item)
	{
		$Res .= '<tr>';
		$Res .= '<td>' . esc_html($Item['Model']) . '</td>';
		$Res .= '<td class="Num">' . $Item['Count'] . '</td>';
		$Res .= '<td class="Num">' . gffz_FormatMoney($Item['Sum']) . '</td>';
		$Res .= '</tr>' . GFFZ_NL;
	}

	$Res .= '<tr class="Total"><td>Итого</td><td class="Num">' . $Report['Count'] . '</td><td class="Num">' . gffz_FormatMoney($Report['Sum']) . '</td></tr>' . GFFZ_NL;
	$Res .= '</tbody></table>' . GFFZ_NL;

	$Res .= '<h3 class="ReportHdr">По пользователям</h3>' . GFFZ_NL;
	$Res .= '<table class="ReportTbl ReportGroupTbl"><thead><tr><th>Пользователь</th><th class="Num">Кол-во</th><th class="Num">Сумма</th></tr></thead><tbody>' . GFFZ_NL;

	foreach($Report['Users'] as $Item)
	{
		$Res .= '<tr>';
		$Res .= '<td>' . esc_html($Item['User']) . '</td>';
		$Res .= '<td class="Num">' . $Item['Count'] . '</td>';
		$Res .= '<td class="Num">' . gffz_FormatMoney($Item['Sum']) . '</td>';
		$Res .= '</tr>' . GFFZ_NL;
	}

	$Res .= '<tr class="Total"><td>Итого</td><td class="Num">' . $Report['Count'] . '</td><td class="Num">' . gffz_FormatMoney($Report['Sum']) . '</td></tr>' . GFFZ_NL;
	$Res .= '</tbody></table>' . GFFZ_NL;

	return $Res;
}

//---------------------------------------------------------

function gffz_GetUserStat_A()
{
	global $wpdb;

	$Login;
	$UserName;
	$Date;
	$WP_User;
	$RecordsCount;
	$First;
	$Next;
	$Record;
	$Index = array();
	$UsersData = array();
	$UserTable = $wpdb->prefix . GFFZ_USERS_TBL;

	wp_enqueue_style('gffz_s004', GFFZ_FILES_URL . 's004.css');
	wp_enqueue_script('gffz_j004', GFFZ_FILES_URL . 'j004.js', array('jquery'), null, true);
	wp_localize_script('gffz_j004', 'WP_Ajax', array('Url'=>admin_url('admin-ajax.php')));

	$Res = '<div style="margin: 0px 0px 15px;">Отображать последних записей&nbsp;<input type="text" id="gffz_Filter" style="width: 50px; padding: 5px; text-align: right;" value="';
	$Res .= GFFZ_STAT_FILTER;
	$Res .= '" /></div>';
	$Res .= "<table class=\"UserStatTbl\" id=\"{$_POST['ID']}\"><thead>
			<th></th>
			<th>Логин</th>
			<th>Имя</th>
			<th>Модель</th>
			<th>S/N</th>
			<th>CRUM</th>
			<th>Дата</th>
			<th>IP</th>
			<th>Стоимость</th>
			<th>Баланс</th>
			<th>Коэфф.</th>
			<th>База</th></thead><tbody>" . GFFZ_NL;

	$Records = $wpdb->get_results(GFFZ_STAT_REQ);
	if(! empty($Records))
	{
		$RecordsCount = count($Records);
		for($i = 0; $i < $RecordsCount; $i++)
		{
			$Index[$Records[$i]->ID] = $i;
			if($Records[$i]->First) $First = $Records[$i]->ID;
		}

		$BeginRecord = gffz_GetRef();
		if($BeginRecord !== false)
		{
			$Next = $BeginRecord['BeginRecID'];
			$UsersData = $BeginRecord['UsersData'];
		}
		else
			$Next = $First;

		for($i = 0; $i < $RecordsCount; $i++)
		{
			$Record = $Records[$Index[$Next]];
			$Login = $wpdb->get_var("SELECT Login FROM {$UserTable} WHERE UserID={$Record->UserID};");
			$WP_User = get_user_by('id', $Record->UserID);
			if($WP_User) $UserName = $WP_User->data->display_name; else $UserName = 'N/A';
			$Date = substr($Record->Date, 0, 10);

			if($Record->UserID == $_POST['ID'])
			{
				$Res .= "<tr><td id=\"{$Record->ID}\">";
				$Res .= ($Record->ID != $BeginRecord['BeginRecID']) ? "<input type=\"button\" class=\"ButtAdd\" value=\"^\" /><input type=\"checkbox\" class=\"CB_Del\" />" : "";
				$Res .= "</td><td>{$Login}</td>
						 <td>{$UserName}</td>
						 <td class=\"edit\">{$Record->Model}</td>
						 <td class=\"edit\">{$Record->SN}</td>
						 <td class=\"edit\">{$Record->CRUM}</td>
						 <td>{$Date}</td>
						 <td>{$Record->IP}</td>
						 <td style=\"text-align: right;\"></td>
						 <td style=\"text-align: right;\"></td>
						 <td class=\"edit\" style=\"text-align: right;\">{$Record->Rate}</td>
						 <td id=\"{$Record->ID}\" class=\"edit\" style=\"text-align: right;\">{$Record->BaseCost}</td></tr>" . GFFZ_NL;
			}
			$Next = $Record->Next;
		}
	}

	$Res .= '<tr><td id="last" colspan="12"><input type="button" class="ButtAdd" value="^" /></td></tr>';
	$Res .= '</tbody></table>' . GFFZ_NL;
	$Res .= '<input type="button" class="ButtApply" value="Применить" />' . GFFZ_NL;
	$BeginBalans = isset($UsersData[$_POST['ID']]) ? $UsersData[$_POST['ID']] : 0;
	$Res .= '<input type="hidden" id="gffz_BeginBalans" value="' . $BeginBalans . '" />' . GFFZ_NL;

	return $Res;
}

//---------------------------------------------------------

function gffz_ArchStat_A()
{
	global $wpdb;
	$Next;
	$Rec;
	$Idx;
	$Records = array();
	$UsersDataNew = array();

	gffz_UpdateUsersFromWP();
	$TableName = $wpdb->prefix . GFFZ_USERS_TBL;
	$Users = $wpdb->get_results("SELECT * FROM {$TableName};");

	$BeginRecord = gffz_GetRef();
	if($BeginRecord !== false)
	{
		$Next = $BeginRecord['BeginRecID'];
		$Idx = 0;

		while($Next != $_POST['ID'])
		{
			$Rec = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . GFFZ_STAT_TBL . ' WHERE ID=' . $Next . ';');
			if(empty($Rec))
			{
				unset($Records);
				break;
			}

			$Records[$Idx++] = $Rec[0];
			$Next = $Rec[0]->Next;
		}
	}

	if(!empty($Records) && !empty($Users))
	{
		foreach($Users as $User)
		{
			if($User->Live)
			{
				$Balans = gffz_CalcUserBalans($User->UserID, $Records, false);
				$UsersDataNew[$User->UserID] = $Balans;
			}
		}
		$Balans = gffz_CalcUserBalans(GFFZ_ADMIN_USER_ID, $Records);
		$UsersDataNew[GFFZ_OWNER_ID] = $Balans;

		gffz_RefUpdate($_POST['ID'], $UsersDataNew);

		$TableName = $wpdb->prefix . GFFZ_STAT_TBL;
		foreach($Records As $Record)
		{
			$wpdb->update($TableName, array('Archive'=>1), array('ID'=>$Record->ID), array('%d'), array('%d'));
		}
	}

	return gffz_GetCommStat();
}

//---------------------------------------------------------

function gffz_CleanUsers()
{
	global $wpdb;
	$Res = 'Список пользователей очищен.';
	$TableName = $wpdb->prefix . GFFZ_USERS_TBL;

	while(!gffz_DB_Lock(1));

	$wpdb->query("DELETE FROM {$TableName} WHERE NOT EXISTS (SELECT ID FROM wp_users WHERE ID=UserID);");

	gffz_DB_Unlock();

	return $Res;
}

//---------------------------------------------------------
