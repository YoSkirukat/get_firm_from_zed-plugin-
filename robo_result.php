<?php

// Кодировка UTF-8.

//---------------------------------------------------------

require_once('gffz_cfg.php');
require_once(GFFZ_USER_CFG_FILE);

//---------------------------------------------------------

$Res = 'fail';

while(1)
{
	if(! isset($_POST['OutSum'])) break;
	if(! isset($_POST['InvId'])) break;
	if(! isset($_POST['shp_id'])) break;
	if(! isset($_POST['shp_sum'])) break;
	if(! isset($_POST['SignatureValue'])) break;

	$Pass2 = GFFZ_ROBO_PASS2;
	$Sign = md5("{$_POST['OutSum']}:{$_POST['InvId']}:{$Pass2}:shp_id={$_POST['shp_id']}:shp_sum={$_POST['shp_sum']}");
	if($Sign == $_POST['SignatureValue']) $Res = 'OK' . $_POST['InvId'];

	break;
}

echo $Res;
exit;

//---------------------------------------------------------
