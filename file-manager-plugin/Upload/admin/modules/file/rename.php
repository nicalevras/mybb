<?php
define("IN_MYBB", 1);
define("IN_ADMINCP", 1);
require_once "../../../inc/init.php";
require_once 'config.php';

if($mybb->request_method != "post") {
	die("Direct initialization of this file is not allowed.");	
}

if (isset($mybb->cookies['adminsid'])) {
    $query = $db->simple_select('adminsessions', '*', 'sid="'.$db->escape_string($mybb->cookies['adminsid']).'"');
    $admin_session = $db->fetch_array($query);
    if ($admin_session['sid']) {
        $admin_session['data'] = my_unserialize($admin_session['data']);
    }
}

if (!$admin_session || (FILEMANAGER_PASSWORD && (!isset($admin_session['data']['filemanager']) || empty($admin_session['data']['filemanager'])))) {
    die('Session expired.');
    exit;
}

if(!isset($cp_language))
{
	if(!file_exists(MYBB_ROOT."inc/languages/".$mybb->settings['cplanguage']."/admin/file_manager.lang.php"))
	{
		$mybb->settings['cplanguage'] = "english";
	}
	$lang->set_language($mybb->settings['cplanguage'], "admin");
}

$lang->load("file_manager");

$data = array();

$dir = '';
if($mybb->input['directory']) {
	$dir = htmlspecialchars_uni($mybb->input['directory']);
}

if (isset($mybb->input['file']) && !empty($mybb->input['file'])) {
	$file = htmlspecialchars_uni($mybb->input['file']);
	if (isset($mybb->input['new_name']) && !empty($mybb->input['new_name'])) {
		$new_name = htmlspecialchars_uni($mybb->input['new_name']);
		if (file_exists(MYBB_ROOT.$dir."/".$file)) {
			if (!file_exists(MYBB_ROOT.$dir."/".$new_name)) {
				if (rename(MYBB_ROOT.$dir."/".$file, MYBB_ROOT.$dir."/".$new_name)) {
                    require_once 'function.php';
					$data['success'] = true;
					if (is_dir($dir."/".$new_name)) {
						$data['text'] = $lang->file_success_rename_dir;
					} else {
						$data['text'] = $lang->file_success_rename;
					}
                    $data['icon'] = get_file_icon($new_name);
				} else {
					$data['error'] = true;
					$data['text'] = $lang->file_error_rename;
				}
			} else {
				$data['error'] = true;
				$data['text'] = $lang->file_error_rename_exists;
			}
		} else {
			$data['error'] = true;
			$data['text'] = $lang->file_error_rename_not_exists;
		}
	} else {
		$data['error'] = true;
		$data['text'] = $lang->file_error_rename_name;
	}
} else {
	$data['error'] = true;
	$data['text'] = $lang->file_error_rename_input;
}

echo json_encode($data);