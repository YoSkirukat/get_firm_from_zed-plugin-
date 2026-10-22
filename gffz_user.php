<?php

// Кодировка UTF-8.

defined('ABSPATH') or die();

//---------------------------------------------------------

function gffz_UserRoutine()
{
	$Res = '';

	if(GFFZ_ALL_USERS_ALLOWED) $Res = gffz_GetForm();

	return $Res;
}

//---------------------------------------------------------
