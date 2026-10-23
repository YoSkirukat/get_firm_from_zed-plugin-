<?php

// Кодировка UTF-8.

global $wpdb;

define('GFFZ_PLUGIN_VERSION', '1.18');
define('GFFZ_USER_CFG_FILE', 'gffz_user_cfg.php');
define('GFFZ_FILES_URL', WP_PLUGIN_URL . '/get_firm_from_zed/');
define('GFFZ_FILES_PATH', __DIR__ . '/');
define('GFFZ_NL', "\r\n");
define('GFFZ_OWNER_ID', 0);
define('GFFZ_PLUGIN_PAGE_TAG', '<!-- gffz_plugin -->');
define('GFFZ_USERS_TBL', 'gffz_users');
define('GFFZ_MODELS_TBL', 'gffz_models');
define('GFFZ_STAT_TBL', 'gffz_stat');
define('GFFZ_STAT_FAIL_TBL', 'gffz_stat_fail');
define('GFFZ_PLUGIN_CFG_TBL', 'gffz_plugin_cfg');
define('GFFZ_REF_TBL', 'gffz_ref');
define('GFFZ_DB_LOCK', 'gffz_db_lock');
define('GFFZ_BALANS_STAT_REQ', 'SELECT ID,UserID,First,Next,Rate,BaseCost,AdminCost,SN FROM ' . $wpdb->prefix . GFFZ_STAT_TBL . ' WHERE Archive=0;');
define('GFFZ_STAT_REQ', 'SELECT * FROM ' . $wpdb->prefix . GFFZ_STAT_TBL . ' WHERE Archive=0;');
define('GFFZ_SUCCESS_PAGE_TAG', '<!-- success -->');
