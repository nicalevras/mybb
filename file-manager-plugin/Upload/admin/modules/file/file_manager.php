<?php
if(!defined("IN_MYBB"))
    die("Direct initialization of this file is not allowed");

require_once 'function.php';
require_once 'config.php';

$lang->load('file_manager');

$plugins->add_hook("admin_page_output_header", "fm_set_title");

$fm_title = $lang->file_title;
$page->add_breadcrumb_item($lang->file_title, "index.php?module=file");

if (FILEMANAGER_PASSWORD && (!isset($admin_session['data']['filemanager']) || empty($admin_session['data']['filemanager']))) {
    if ($_SERVER['QUERY_STRING']) {
        $qstring = '?'.preg_replace('#adminsid=(.{32})#i', '', $_SERVER['QUERY_STRING']);
        $qstring = str_replace('action=logout', '', $qstring);
        $qstring = preg_replace('#&+#', '&', $qstring);
        $qstring = str_replace('?&', '?', $qstring);
        $parameters = explode('&', $qstring);

        if (substr($parameters[0], 0, 8) == '?module=') {
            unset($parameters[0]);
        }
        $query_string = '';
        foreach ($parameters as $key => $param) {
            $params = explode("=", $param);
            $query_string .= '&'.htmlspecialchars_uni($params[0])."=".htmlspecialchars_uni($params[1]);
        }
    }
    $password = false;
    if ($mybb->request_method == 'post' && $mybb->input['do_login']) {
        if ($mybb->input['pin'] == FILEMANAGER_PIN) {
            require_once MYBB_ROOT.'inc/datahandlers/login.php';
            $loginhandler = new LoginDataHandler('get');

            $loginhandler->set_data(array(
                'username' => $mybb->user['username'],
                'password' => $mybb->input['password']
            ));

            if ($loginhandler->validate_login() == true) {
                $password = true;
                update_admin_session('filemanager', md5(random_str(50)));
            }
        }
        
        if (!$password) {
            flash_message($lang->file_login_error, 'error');
        }
        admin_redirect('index.php?module=file'.$query_string);
    }
    $page->output_header();

    $form = new Form('index.php?module=file'.$query_string, 'post', 'login');

    $form_container = new FormContainer($lang->file_login, 'tfixed');
    $form_container->output_row($lang->file_password, $lang->file_password_desc, $form->generate_password_box('password'));
    $form_container->output_row($lang->file_pin, '', $form->generate_password_box('pin'));
    $form_container->end();

    $buttons[] = $form->generate_submit_button($lang->file_login_done, array('name' => 'do_login'));
    $form->output_submit_wrapper($buttons);

    $form->end();

    $page->output_footer();
    exit;
}

$dir = $dir_query = '';
if(isset($mybb->input['directory']) && !empty($mybb->input['directory'])) {
	$page->add_breadcrumb_item($lang->file_nav_root, "index.php?module=file");
	$dir = htmlspecialchars_uni(str_replace(array("\\", "//"), "/", $mybb->input['directory']));
    $dir = ltrim($dir, './');
    if (!empty($dir)) {
        $dir_query = '&directory='.$dir;
    }
	$list_dir = explode("/", $dir);
	foreach($list_dir as $d){
		if($d == "") continue;
		$path = substr($dir, 0, strrpos($dir, $d) + strlen($d));
		$page->add_breadcrumb_item($d, "index.php?module=file&amp;directory=$path");
	}
} else {
	$page->add_breadcrumb_item($lang->file_nav_root, "index.php?module=file");
}

if($mybb->input['file'] && !empty($mybb->input['file'])){
	$file = htmlspecialchars_uni($mybb->input['file']);
	if(!empty($dir))
		$file_path = MYBB_ROOT.$dir."/".$file;
	else
		$file_path = MYBB_ROOT.$file;
}

$sub_tabs['file_manager'] = array(
	'title' => $lang->file_sub_name,
	'link' => 'index.php?module=file'.$dir_query,
	'description' => $lang->file_sub_name_desc
);
$sub_tabs['new_folder'] = array(
	'title' => $lang->file_sub_ndir,
	'link' => 'index.php?module=file&action=new_folder'.$dir_query,
	'description' => $lang->file_sub_ndir_desc
);
$sub_tabs['new_file'] = array(
	'title' => $lang->file_sub_nfile,
	'link' => 'index.php?module=file&action=new_file'.$dir_query,
	'description' => $lang->file_sub_nfile_desc
);
$sub_tabs['upload'] = array(
	'title' => $lang->file_sub_upload,
	'link' => 'index.php?module=file&action=upload'.$dir_query,
	'description' => $lang->file_sub_upload_desc
);

if (isset($mybb->input['inline_actions']) && $mybb->request_method == 'post') {
    if (isset($mybb->input['close'])) {
        admin_redirect('index.php?module=file'.$dir_query);
    }
    $files = $mybb->input['files'];
    
    if (!is_array($files) && !empty($files)) {
        $files = array($files);
    }
    
    foreach ($files as $key => $file) {
        if (!file_exists(MYBB_ROOT.$dir.'/'.$file)) {
            unset($files[$key]);
        }
    }
    
    if (!is_array($files) || empty($files)) {
        flash_message($lang->file_multi_empty, 'error');
        admin_redirect('index.php?module=file'.$dir_query);
    }
    
    $files_count = count($files);
    $errors = array();
    
    if ($mybb->input['action'] == 'do_zip') {
        $name = $mybb->input['name'];
        if (file_exists(MYBB_ROOT.$dir.'/'.$name)) {
            $errors[] = $lang->file_error_zip_name;
            $mybb->input['action'] = 'zip';
        } else {
            $zip = new ZipArchive();
            if ($zip->open(MYBB_ROOT.$dir.'/'.$name, ZipArchive::CREATE) !== TRUE) {
                $errors[] = $lang->file_error_zip_unknown;
                $mybb->input['action'] = 'zip';
            } else {
                $root = MYBB_ROOT.$dir;
                foreach ($files as $file) {
                    add_zip($zip, $file, $root);
                }
                if ($zip->close()) {
                    flash_message($lang->file_success_zipped, 'success');
                    admin_redirect('index.php?module=file'.$dir_query);
                } else {
                    $errors[] = $lang->file_error_zip_unknown;
                    $mybb->input['action'] = 'zip';
                }
            }
        }
    } 
    if ($mybb->input['action'] == 'zip') {
        $lang->file_zip_title = $lang->sprintf($lang->file_zip_title, $files_count);
        $page->add_breadcrumb_item($lang->file_zip_title, 'index.php?module=file'.$dir_query);
        $page->output_header();
        
        if (is_array($errors) && !empty($errors)) {
            $page->output_inline_error($errors);
        }
        
        $form = new Form('index.php?module=file'.$dir_query, 'post', 'zip_files');
        echo $form->generate_hidden_field('inline_actions', 1);
        echo $form->generate_hidden_field('action', 'do_zip');
        
        $file_list = '<ul>';
        foreach ($files as $file) {
            $file_list .= '<li style="margin-bottom: 8px"><input type="hidden" name="files[]" value="'.$file.'">'.$file.'</li>';
        }
        $file_list .= '</ul>';
        
        $form_container = new FormContainer($lang->file_zip_title, 'tfixed');
        $form_container->output_row($lang->file_zip_name, $lang->file_zip_name_desc, $form->generate_text_box('name'));
        $form_container->output_row($lang->file_zip_files, $lang->file_zip_files_desc, $file_list);
        $form_container->end();
        
        $buttons[] = $form->generate_submit_button($lang->file_button_zip, array('name' => 'zip'));
        $buttons[] = $form->generate_submit_button($lang->file_button_close, array('name' => 'close'));
        
        $form->output_submit_wrapper($buttons);
        $form->end();
        
        $page->output_footer();
    } elseif ($mybb->input['action'] == 'do_multicopy') {
        $to_dir = ltrim($mybb->input['dest'], './');
        if ($to_dir == "") {
            flash_message($lang->file_error_multimove_dest, 'error');
            admin_redirect('index.php?module=file'.$dir_query);
        }
        
        $copied = 0;
        foreach ($files as $key => $file) {
            $from_path = MYBB_ROOT.$dir.'/'.$file;
            if (file_exists($from_path)) {
                $to_path = $name = MYBB_ROOT.$to_dir.'/'.$file;
                $ext_pos = strrpos($to_path, '.');
                $i = 0;
                while (file_exists($name)) {
                    $i++;
                    $name = substr($from_path, 0, $ext_pos).'_'.$i.substr($from_path, $ext_pos);
                }
                if (@copy($from_path, $name)) {
                    $copied++;
                    unset($files[$key]);
                }
            }
        }
        
        if ($copied == $files_count) {
            flash_message($lang->file_success_multicopy, 'success');
        } else {
            $lang->file_error_multicopy = $lang->sprintf($lang->file_error_multicopy, $copied, $files_count);
            $list = build_file_list($files);
            flash_message($lang->file_error_multicopy.$list, 'error');
        }
        admin_redirect('index.php?module=file'.$dir_query);
    } elseif ($mybb->input['action'] == 'multicopy') {
        $lang->file_multicopy = $lang->sprintf($lang->file_multicopy, $files_count);
        $page->add_breadcrumb_item($lang->file_multicopy, 'index.php?module=file'.$dir_query);
        $page->output_header();
        
        $form = new Form('index.php?module=file'.$dir_query, 'post', 'copy_files');
        echo $form->generate_hidden_field('inline_actions', 1);
        echo $form->generate_hidden_field('action', 'do_multicopy');
        
        $form_container = new FormContainer($lang->file_multicopy, 'tfixed');
        $form_container->output_row($lang->file_list_dest_title, $lang->file_list_dest_desc, file_list_destination($files));
        $form_container->end();
        
        $buttons[] = $form->generate_submit_button($lang->file_button_copy, array('name' => 'copy'));
        $buttons[] = $form->generate_submit_button($lang->file_button_close, array('name' => 'close'));
        
        $form->output_submit_wrapper($buttons);
        
        $form->end();
        
        $page->output_footer();
    } elseif ($mybb->input['action'] == 'do_multimove') {
        $to_dir = ltrim($mybb->input['dest'], './');
        if ($to_dir == "") {
            flash_message($lang->file_error_multimove_dest, 'error');
            admin_redirect('index.php?module=file'.$dir_query);
        }
        
        if ($to_dir == $dir) {
            flash_message($lang->file_error_multimove_dest2, 'error');
            admin_redirect('index.php?module=file'.$dir_query);
        }
        
        $moved = 0;
        foreach ($files as $key => $file) {
            $from_path = MYBB_ROOT.$dir.'/'.$file;
            $to_path = MYBB_ROOT.$to_dir.'/'.$file;
            if (file_exists($from_path) && !file_exists($to_path)) {
                if (@rename($from_path, $to_path)) {
                    $moved++;
                    unset($files[$key]);
                }
            }
        }
        
        if ($moved == $files_count) {
            flash_message($lang->file_success_multimove, 'success');
        } else {
            $lang->file_error_multimove = $lang->sprintf($lang->file_error_multimove, $moved, $files_count);
            $list = build_file_list($files);
            flash_message($lang->file_error_multimove.$list, 'error');
        }
        admin_redirect('index.php?module=file'.$dir_query);
    } elseif ($mybb->input['action'] == 'multimove') {
        $lang->file_multimove = $lang->sprintf($lang->file_multimove, $files_count);
        $page->add_breadcrumb_item($lang->file_multimove, 'index.php?module=file'.$dir_query);
        $page->output_header();
        
        $form = new Form('index.php?module=file'.$dir_query, 'post', 'move_files');
        echo $form->generate_hidden_field('inline_actions', 1);
        echo $form->generate_hidden_field('action', 'do_multimove');
        
        $form_container = new FormContainer($lang->file_multimove, 'tfixed');
        $form_container->output_row($lang->file_list_dest_title, $lang->file_list_dest_desc, file_list_destination($files));
        $form_container->end();
        
        $buttons[] = $form->generate_submit_button($lang->file_button_move, array('name' => 'move'));
        $buttons[] = $form->generate_submit_button($lang->file_button_close, array('name' => 'close'));
        
        $form->output_submit_wrapper($buttons);
        
        $form->end();
        
        $page->output_footer();
    } elseif ($mybb->input['action'] == 'do_multidelete') {
        $deleted = 0;
        foreach ($files as $key => $file) {
            $path = MYBB_ROOT.$dir.'/'.$file;
            if (file_exists($path)) {
                if (is_dir($path)) {
                    if (@removedir($path)) {
                        $deleted++;
                        unset($files[$key]);
                    }
                } else {
                    if (@unlink($path)) {
                        $deleted++;
                        unset($files[$key]);
                    }
                }
            }
        }
        
        if ($deleted == $files_count) {
            flash_message($lang->file_success_multidelete, 'success');
        } else {
            $lang->file_error_multidelete = $lang->sprintf($lang->file_error_multidelete, $deleted, $files_count);
            $list = build_file_list($files);
            flash_message($lang->file_error_multidelete.$list, 'error');
        }
        admin_redirect('index.php?module=file'.$dir_query);
    } elseif ($mybb->input['action'] == 'multidelete') {
        $lang->file_multidelete = $lang->sprintf($lang->file_multidelete, $files_count);
        $page->add_breadcrumb_item($lang->file_multidelete, 'index.php?module=file'.$dir_query);
        
        $page->extra_header .= '
<style>
    .file__icon { height: 40px; width: 40px; box-sizing: content-box; text-align: center; display: inline-block; margin-right: 8px; vertical-align: middle }
    .file__icon img { max-width: 40px; max-height: 40px }
</style>
';
        
        $page->output_header();

        $form = new Form('index.php?module=file'.$dir_query, 'post', 'delete_files');
        echo $form->generate_hidden_field('inline_actions', 1);
        echo $form->generate_hidden_field('action', 'do_multidelete');
        
        $form_container = new FormContainer($lang->file_multidelete, 'tfixed');
        $form_container->output_row($lang->file_multidelete_desc, $lang->file_delete_warn);
        foreach ($files as $file) {
            $icon = get_file_icon($file);
            $file_name = '<div class="file__icon"><img src="'.$icon.'" alt="" /></div>'.$file;
            if (is_dir(MYBB_ROOT.$dir.'/'.$file)) {
                $file_name .= '<span style="font-weight: normal">'.$lang->file_folder_contents.'</span>';
            }
            $form_container->output_row('', '', $form->generate_check_box('files[]', $file, $file_name, array('checked' => 1)));
        }
        $form_container->end();

        $buttons[] = $form->generate_submit_button($lang->file_button_delete, array('name' => 'delete'));
        $buttons[] = $form->generate_submit_button($lang->file_button_close, array('name' => 'close'));

        $form->output_submit_wrapper($buttons);

        $form->end();

        $page->output_footer();
    }
} elseif ($mybb->input['action'] == 'get_folders') {
    $folder = $mybb->input['folder'];
    $data = array('folders' => get_folders($folder));
    echo json_encode($data);
}
elseif ($mybb->input['action'] == 'logout') {
    update_admin_session('filemanager', '');
    admin_redirect('index.php?module=file');
}
elseif($mybb->input['action'] == "upload") {
    if($mybb->request_method == "post") {
        $files = $_FILES['files'];
        $count = count($files['name']);
        if($count == 0) {
            flash_message($lang->file_upload_nofile, "error");
            admin_redirect('index.php?module=file&action=upload'.$dir_query);
        }
        
        for($i=0;$i<$count;$i++) {
            $target = MYBB_ROOT . $dir . "/" . $files['name'][$i];   
            if(file_exists($target)) {
                $lang->file_upload_exists = $lang->sprintf($lang->file_upload_exists, $files['name'][$i]);
                flash_message($lang->file_upload_exists, "error");
                admin_redirect('index.php?module=file&action=upload'.$dir_query);
            }
        }
        
        $up = $er = 0;
        for($i=0;$i<$count;$i++) {
            $target = MYBB_ROOT . $dir . "/" . $files['name'][$i];
            
            if(move_uploaded_file($files["tmp_name"][$i], $target))
                $up++;
        }
        
        if($up == $count) {
            flash_message($lang->file_upload_uploaded, "success");
            admin_redirect('index.php?module=file&action=upload'.$dir_query);
        }
        else {
            $lang->file_upload_part_uploaded = $lang->sprintf($lang->file_upload_part_uploaded, $up, $count);
            flash_message($lang->file_upload_uploaded, "success");
            admin_redirect('index.php?module=file&action=upload'.$dir_query);
        }
    }
    $page->add_breadcrumb_item($lang->file_nav_upload, 'index.php?module=file'.$dir_query);
    
    $page->extra_header .= <<<EOT
<style>
.file__upload__preview { width: 100% }
.file__upload__preview__default  { display: none }
</style>
<script type="text/javascript">
$(document).ready(function() {
    var preview = $(".file__upload__preview");
    var default_row = preview.find(".file__upload__preview__default");
    var button = $(".file__upload__button");
    button.change(function(e) {
        preview.find(".file__upload__preview__file").remove();
        if(this.files && this.files.length > 0) {
            preview.find(".file__upload__preview__empty").hide();
            for(var i=0;i<this.files.length;i++) {
                var file = this.files[i];
                var row = "<tr class=\"file__upload__preview__file\">"+default_row.html()+"</tr>";
                row = row.replace("Name", file.name);
                size = file.size;
                type = 'B';
                if(size > 1024) {
                    size = size / 1024;
                    type = "KB";
                }
                if(size > 1024) {
                    size = size / 1024;
                    type = "MB";
                }
                if(size > 1024) {
                    size = size / 1024;
                    type = "GB";
                }
                row = row.replace("Size", size.toFixed(3)+type);
                preview.find(".file__upload__preview__empty").after(row);
            }
        }
    });
});
</script>
EOT;
    
    $page->output_header();
	$page->output_nav_tabs($sub_tabs, 'upload');
    
    $form = new Form('index.php?module=file&action=upload'.$dir_query, "post", "upload_files", true);
    $form_container = new FormContainer($lang->file_upload_title, 'file__upload tfixed');
    
    $form_container->output_row($lang->file_upload_select, $lang->file_upload_select_desc, '<input name="files[]" class="file__upload__button" type="file" multiple="multiple">');
    $form_container->end(); 

    $buttons[] = $form->generate_submit_button($lang->file_button_upload, array('name' => 'save', 'class' => 'file__submit'));
    
    $table = new Table;
    
    $table->construct_header($lang->file_upload_file_name);
    $table->construct_header($lang->file_upload_file_size);
    
    $table->construct_cell("Name");
    $table->construct_cell("Size");
    $table->construct_row(array("class" => "file__upload__preview__default"));
    
    $table->construct_cell($lang->file_upload_files_no, array("colspan" => "2"));
    $table->construct_row(array("class" => "file__upload__preview__empty"));
    
    $table->output($lang->file_upload_files, 1, "file__upload__preview general");
    
    $form->output_submit_wrapper($buttons);
    
    $form->end();
    
    $page->output_footer();
}
elseif($mybb->input['action'] == "edit") {
    if(isset($file) && !empty($file)) {
        if(file_exists($file_path) && !is_dir($file_path)){
			if(is_editable($file)) {
				if($mybb->request_method == "post"){
					if($mybb->input['close']){
						admin_redirect('index.php?module=file'.$dir_query);
					} else {
						$content = trim($mybb->input['file_content']);

						$fhandle = fopen($file_path, "w");
						fwrite($fhandle, $content);
						fclose($fhandle);

						flash_message($lang->file_success_edit, "success");
						admin_redirect('index.php?module=file'.$dir_query);
					}
				}
				$path_parts = pathinfo($file);
				$content = file_get_contents($file_path);
				
				$lang->file_nav_edit = $lang->sprintf($lang->file_nav_edit, $file);
				$page->add_breadcrumb_item($lang->file_nav_edit, 'index.php?module=file&action=edit'.$dir_query.'file='.$file);

				if($admin_options['codepress'] != 0){
					$page->extra_header .= '
<link href="./jscripts/codemirror/lib/codemirror.css" rel="stylesheet">
<link href="./jscripts/codemirror/theme/mybb.css?ver=1804" rel="stylesheet">
<script src="./jscripts/codemirror/lib/codemirror.js"></script>
<script src="./jscripts/codemirror/mode/xml/xml.js"></script>
<script src="./jscripts/codemirror/mode/javascript/javascript.js"></script>
<script src="./jscripts/codemirror/mode/css/css.js"></script>
<script src="./jscripts/codemirror/mode/htmlmixed/htmlmixed.js"></script>
<script src="./jscripts/codemirror/mode/clike/clike.js"></script>
<script src="./jscripts/codemirror/mode/php/php.js"></script>
<link href="./jscripts/codemirror/addon/dialog/dialog-mybb.css" rel="stylesheet">
<script src="./jscripts/codemirror/addon/dialog/dialog.js"></script>
<script src="./jscripts/codemirror/addon/search/searchcursor.js"></script>
<script src="./jscripts/codemirror/addon/search/search.js"></script>
<script src="./jscripts/codemirror/addon/fold/foldcode.js"></script>
<script src="./jscripts/codemirror/addon/fold/xml-fold.js"></script>
<script src="./jscripts/codemirror/addon/fold/foldgutter.js"></script>
<link href="./jscripts/codemirror/addon/fold/foldgutter.css" rel="stylesheet">
<script type="text/javascript">
var code = 1;
$(document).ready(function() {
    var inputs = $(".file__submit");
	$("input[name=\'continue\']").click(function(event) {
		event.preventDefault();
		if(code == 1)
            content = editor.getValue();
        else
            content = document.getElementById("file_content").value;
        
        var formData = {
			\'my_post_key\' : $(\'input[name="my_post_key"]\').val(),
			\'file_content\' : content
		}
        
        inputs.prop("disabled", true);
		$.ajax({
			url: "./modules/file/quick_save.php?directory='.$dir.'&file='.$file.'",
			type: "post",
			dataType: "json",
			data: formData,
			success: function(data) {
                $.jGrowl(data.msg);
                inputs.prop("disabled", false);
			},
			error: function(data){
				$.jGrowl("'.$lang->file_error_edit.'");
                inputs.prop("disabled", false);
				console.log(data);
			}
		});
	});
});
</script>
';
				}

                $fm_title = $lang->file_edit_text_editor.' - '.$fm_title;
				$page->output_header();

				$form = new Form('index.php?module=file&amp;action=edit'.$dir_query.'&file='.$file, "post", "edit_file");
				
				$lang->file_edit_title = $lang->sprintf($lang->file_edit_title, $dir, $file);
				$form_container = new FormContainer($lang->file_edit_title, 'tfixed');

				$form_container->output_row("", "", $form->generate_text_area("file_content", $content, array('id' => 'file_content', 'style' => 'width: 100%; height: 500px;')));
				$form_container->end();

				$buttons[] = '<input class="submit_button file__submit" type="button" name="continue" value="'.$lang->file_button_quick_save.'" />';
				$buttons[] = $form->generate_submit_button($lang->file_button_save, array('name' => 'save', 'class' => 'file__submit'));
				$buttons[] = $form->generate_submit_button($lang->file_button_close, array('name' => 'close', 'class' => 'file__submit'));
                $buttons[] = '<input class="submit_button file__editor" type="button" name="editorType" value="'.$lang->file_button_text.'" />';

				$form->output_submit_wrapper($buttons);

				$form->end();

                echo '<script type"text/javascript">
                    var editor = CodeMirror.fromTextArea(document.getElementById("file_content"), {
                        lineNumbers: true,
                        lineWrapping: true,
                        foldGutter: true,
                        gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
                        viewportMargin: Infinity,
                        indentWithTabs: true,
                        indentUnit: 4,
                        mode: "'.get_codemirror_type($path_parts['extension']).'",
                        theme: "mybb"
                    });
                    
                    $(".file__editor").click(function(e) {
                        e.preventDefault();
                        if(code == 1) {
                            editor.toTextArea();
                            code = 0;
                        }
                        else {
                            editor = CodeMirror.fromTextArea(document.getElementById("file_content"), {
                                lineNumbers: true,
                                lineWrapping: true,
                                foldGutter: true,
                                gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
                                viewportMargin: Infinity,
                                indentWithTabs: true,
                                indentUnit: 4,
                                mode: "'.get_codemirror_type($path_parts['extension']).'",
                                theme: "mybb"
                            });
                            code = 1;
                        }
                    });
                </script>';

				$page->output_footer();
			} else {
				flash_message($lang->file_error_edit_type, "error");
				admin_redirect('index.php?module=file'.$dir_query);
			}
        } else {
			flash_message($lang->file_error_exists, "error");
			admin_redirect('index.php?module=file');
        }
    } else {
		flash_message($lang->file_error_edit_file, "error");
		admin_redirect('index.php?module=file');
    }
}
elseif($mybb->input['action'] == "delete") {
	if(isset($file) && !empty($file)) {
		if(file_exists($file_path) && !is_dir($file_path)) {
			if($mybb->request_method == "post") {
				if($mybb->input['delete']) {
					if(unlink($file_path)) {
						flash_message($lang->file_success_delete_file, "success");
						admin_redirect('index.php?module=file'.$dir_query);
					} else {
						flash_message($lang->file_error_delete, "error");
						admin_redirect('index.php?module=file&action=delete'.$dir_query.'&file='.$file);
					}
				} else {
					admin_redirect('index.php?module=file'.$dir_query);
				}
			}
			$lang->file_nav_delete_file = $lang->sprintf($lang->file_nav_delete_file, $file);
			$page->add_breadcrumb_item($lang->file_nav_delete_file, 'index.php?module=file&action=delete'.$dir_query.'&file='.$file);
			$page->output_header();

			$form = new Form('index.php?module=file&action=delete'.$dir_query.'&file='.$file, "post", "delete_file");
			
			$lang->file_delete_file_title = $lang->sprintf($lang->file_delete_file_title, $dir, $file);
			$form_container = new FormContainer($lang->file_delete_file_title, 'tfixed');

			$form_container->output_row($lang->file_delete_file_desc, $lang->file_delete_warn);
			$form_container->end();

			$buttons[] = $form->generate_submit_button($lang->file_button_delete, array('name' => 'delete'));
			$buttons[] = $form->generate_submit_button($lang->file_button_close, array('name' => 'close'));

			$form->output_submit_wrapper($buttons);

			$form->end();

			$page->output_footer();
		} else {
			flash_message($lang->file_error_exists, "error");
			admin_redirect('index.php?module=file'.$dir_query);
		}
	} elseif(isset($dir) && !empty($dir) && empty($file)) {
		if($mybb->input['rdir']){
            $rdir = htmlspecialchars_uni(str_replace(array("\\", "//"), "/", $mybb->input['rdir']));
            $rdir = ltrim($dir, '.');
            $rdir = ltrim($dir, '/');
            if (!empty($dir)) {
                $rdir_query = '&directory='.$rdir;
                $rdir_query2 = '&rdir='.$rdir;
            }
		} else {
			$rdir = '';	
		}
        
		if(file_exists(MYBB_ROOT.$dir) && !empty($dir)) {
			if($dir != "/") {
				if($mybb->request_method == "post") {	
					if($mybb->input['delete'])	{
						if(removedir(MYBB_ROOT.$dir)) {
							flash_message($lang->file_success_delete_dir, "success");
						} else {
							flash_message($lang->file_error_delete, "error");
						}
					}
                    admin_redirect('index.php?module=file'.$rdir_query);
				}
				
				$lang->file_nav_delete_dir = $lang->sprintf($lang->file_nav_delete_dir, $dir);
				$page->add_breadcrumb_item($lang->file_nav_delete_dir, 'index.php?module=file&action=delete'.$dir_query);
				$page->output_header();

				$form = new Form('index.php?module=file&action=delete'.$dir_query.$rdir_query2, "post", "delete_dir");
				
				$lang->file_delete_dir_title = $lang->sprintf($lang->file_delete_dir_title, $dir);
				$form_container = new FormContainer($lang->file_delete_dir_title, 'tfixed');

				$form_container->output_row($lang->file_delete_dir_desc, $lang->file_delete_warn);
				$form_container->end();

				$buttons[] = $form->generate_submit_button($lang->file_button_delete, array('name' => 'delete'));
				$buttons[] = $form->generate_submit_button($lang->file_button_close, array('name' => 'close'));

				$form->output_submit_wrapper($buttons);

				$form->end();

				$page->output_footer();
			} else {
				flash_message($lang->file_error_delete_root, "error");
				admin_redirect("index.php?module=file");
			}
		} else {
			flash_message($lang->file_error_exists_dir, "error");
			admin_redirect("index.php?module=file");
		}
	} else {
		flash_message($lang->file_error_delete_input, "error");
		admin_redirect("index.php?module=file");
	}
}
elseif($mybb->input['action'] == "new_folder"){
	if($mybb->request_method == "post"){
    	if($mybb->input['close']){
			admin_redirect('index.php?module=file'.$dir_query);	
		} elseif($mybb->input['create']) {
			if(!empty($mybb->input['folder_name'])){
				$nfolder = htmlspecialchars_uni($mybb->input['folder_name']);
				if(!file_exists(MYBB_ROOT.$dir.$nfolder."/")){
					if(mkdir(MYBB_ROOT.$dir."/".$nfolder, 0777)) {
						flash_message($lang->file_success_newdir, "success");
                        $dir_query .= '/'.$nfolder;
						admin_redirect('index.php?module=file'.$dir_query);
					} else {
						flash_message($lang->file_error_newdir, "error");
						admin_redirect('index.php?module=file&action=new_dir'.$dir_query);
					}
				} else {
					flash_message($lang->file_error_newdir_exists, "error");
					admin_redirect('index.php?module=file&action=new_folder'.$dir_query);	
				}
			} else {
				flash_message($lang->file_error_newdir_name, "error");
				admin_redirect('index.php?module=file&action=new_folder'.$dir_query);
			}
		}
	}
	$page->add_breadcrumb_item($lang->file_nav_newdir, 'index.php?module=file'.$dir_query.'&action=new_folder');
	
	$page->output_header();
	$page->output_nav_tabs($sub_tabs, 'new_folder');
	
	$form = new Form('index.php?module=file&action=new_folder'.$dir_query, "post", "new_folder");
    
	$lang->file_newdir_title = $lang->sprintf($lang->file_newdir_title, $dir);
	$form_container = new FormContainer($lang->file_newdir_title, 'tfixed');

	$form_container->output_row($lang->file_newdir_desc, $lang->file_newdir_subdesc, $form->generate_text_box('folder_name'));
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->file_button_newdir, array('name' => 'create'));
	$buttons[] = $form->generate_submit_button($lang->file_button_close, array('name' => 'close'));

	$form->output_submit_wrapper($buttons);
	$form->end();
	$page->output_footer();
}
elseif($mybb->input['action'] == "new_file"){
	if($mybb->request_method == "post"){
    	if($mybb->input['close']){
			admin_redirect('index.php?module=file'.$dir_query);	
		} elseif($mybb->input['create']) {
			if(!empty($mybb->input['file_name'])){
				$nfile = htmlspecialchars_uni($mybb->input['file_name']);
				if(!file_exists(MYBB_ROOT.$dir."/".$nfile)){
					$newfile = fopen(MYBB_ROOT.$dir."/".$nfile, "w");
					fclose($newfile);
					flash_message($lang->file_success_newfile, "success");
					admin_redirect('index.php?module=file&action=edit'.$dir_query.'&file='.$nfile);
				} else {
					flash_message($lang->file_error_newfile_name, "error");
					admin_redirect('index.php?module=file&action=new_file'.$dir_query);	
				}
			} else {
				flash_message($lang->file_error_newfile_name, "error");
				admin_redirect('index.php?module=file&action=new_file'.$dir_query);
			}
		}
	}
	$page->add_breadcrumb_item($lang->file_nav_newfile, 'index.php?module=file'.$dir_query.'&action=new_file');
	
	$page->output_header();
	$page->output_nav_tabs($sub_tabs, 'new_file');
	
	$form = new Form('index.php?module=file&action=new_file'.$dir_query, "post", "new_file");
    
	$lang->file_newfile_title = $lang->sprintf($lang->file_newfile_title, $dir);
	$form_container = new FormContainer($lang->file_newfile_title, 'tfixed');

	$form_container->output_row($lang->file_newfile_desc, $lang->file_newfile_subdesc, $form->generate_text_box('file_name'));
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->file_button_newfile, array('name' => 'create'));
	$buttons[] = $form->generate_submit_button($lang->file_button_close, array('name' => 'close'));

	$form->output_submit_wrapper($buttons);
	$form->end();
	$page->output_footer();
}

if(!$mybb->input['action']) {
    $page->extra_header .= '
<style>
input.file__search { width: 100%; box-sizing: border-box; margin-bottom: 8px; }
.file__icon { height: 40px; width: 40px; box-sizing: content-box; text-align: center }
.file__icon img { max-width: 40px; max-height: 40px }
.file__actions span+span:before { content: " | " }
.file__actions__delete { color: red; font-weight: bold }
</style>
<script type="text/javascript">
var dir = "'.$dir.'";
lang.go = "'.$lang->file_inline_go.'";
$(document).ready(function() {
    $("input.inline_action, input.check_all").prop("checked", false);
    $("#inline_go").removeAttr("checked");
    
    $(".file__search").attr("placeholder", $(".file__search").attr("value")).val(\'\').focus();
    
    $(".file__search").keyup(function() {
        var search = $(this).val();
        var re = new RegExp(search, "gi");
        
        $(".file").each(function(i) {
            if(i != 0) {
                row = $(this);
                var id = row.find(".file__name__tag").text();
                
                if(id.match(re) === null)
                    row.hide();
                else
                    row.show();
            }
        })
    }).keypress(function (e) {
        if (e.which == 13) {
            return false;
        }
    });
    
	$(".file__actions__rename").click(function(event){
		event.preventDefault();
		var tr = $(this).parents(".file"),
            name = tr.find(".file__name__tag").text();
		tr.find(".file__name__form").remove();
		tr.find(".file__name__tag").hide();
		tr.find(".file__name").append(\'<form action="#" method="post" name="form_rename" class="file__name__form"><input type="text" name="new_name" value="\'+name+\'" class="file__name__form__input text_input"> <input type="button" name="rename"  value="'.$lang->file_button_rename.'" class="file__name__form__submit submit_button"> <input type="button" name="cancel" value="'.$lang->file_button_cancel.'" class="file__name__form__cancel submit_button"></form>\');
        
        tr.find(".file__name__form__cancel").click(function() {
			tr.find(".file__name__tag").show();
			tr.find(".file__name__form").remove();
		});
		
        tr.find(".file__name__form__input").keydown(function(e) {
            if(e.keyCode == 13) {
                e.preventDefault();
                rename(tr);
                return false;
            }
        }).focus();
        
		tr.find(".file__name__form__submit").click(function(e) {
            e.preventDefault();
            rename(tr);
        });
	});
    
    $("input.inline_action").click(function () {
        if ($("input.check_all").is(":checked")) {
            $("input.check_all").prop("checked", false);
        }
        updateInlineAction();
    });
    $("input.check_all").click(function () {
        $("input.inline_action").prop("checked", this.checked);
        updateInlineAction();
    });
});

function updateInlineAction() {
    var i = $("input.inline_action:checked").length;
    $("#inline_go").val(lang.go+" ("+i+")");
    if (i > 0) {
        $("#inline_go").removeAttr("disabled");
    } else {
        $("#inline_go").attr("disabled", "disabled");
    }
}

function rename(tr) {
    var form = tr.find(".file__name__form");
    var dir_query = "";
    var dir_query2 = "";
    var new_name = form.find(".file__name__form__input").val();
    
    if (dir != "") {
        dir_query = "?directory="+dir;
        dir_query2 = "&directory="+dir;
    }
    
    form.find("input").prop(\'disabled\', true);
    $.ajax({
        url: "./modules/file/rename.php"+dir_query,
        type: "post",
        dataType: "json",
        data: {
            \'new_name\' : new_name,
            \'file\' : tr.find(".file__name__tag").text()
        },
        success: function(data) {
            if(data.success) {
                $.jGrowl(data.text);
                tr.find(".file__name__form").remove();
                tr.find(".file__name__tag").show();
                tr.find(".file__icon img").attr("src", data.icon);
                
                if(tr.hasClass("dir")) {
                    tr.find(".file__name__tag").attr("href", "index.php?module=file&directory="+dir+"/"+new_name).html("<strong>"+new_name+"</strong>");
                    tr.find(".file__actions__delete").attr("href", "index.php?module=file&action=delete&directory="+dir+"/"+new_name+"&rdir="+dir);
                }
                else {
                    tr.find(".file__name__tag").text(new_name);
                    tr.find(".file__actions__edit").parent().remove();
                    tr.find(".file__actions").prepend(data.edit);
                    tr.find(".file__actions__delete").attr("href", "index.php?module=file&action=delete"+dir_query+"&file="+new_name);
                }
            }

            if(data.error) {
                $.jGrowl(data.text);
                form.find("input").prop(\'disabled\', false);
            }
        },
        error: function(data){
            $.jGrowl("'.$lang->file_error_rename.'");
            form.find("input").prop(\'disabled\', false);
        }
    });
}
</script>
';
	
    $page->output_header();
	$page->output_nav_tabs($sub_tabs, "file_manager");
    
    $form = new Form('index.php?module=file'.$dir_query, "POST");
	echo $form->generate_text_box('search', $lang->file_search, array('class' => 'file__search'));
    
    $table = new Table;
    
    $table->construct_header($lang->file_file, array("width" => "60%","colspan" => "2"));
    $table->construct_header($lang->file_modified, array("width" => "15%", "class" => "align_center"));
    $table->construct_header($lang->file_actions, array("width" => "25%", "class" => "align_center"));
    $table->construct_header($form->generate_check_box('allfiles', '1', '', array('class' => 'check_all')));
    
    if (!empty($dir) && !in_array($dir, array('/', '\\', '.'))) {
        $back = dirname($dir);
        if (empty($back) || in_array($back, array('/', '\\', '.'))) {
            $back = $lang->file_nav_root;
            $back_dir = '';
        } else {
            $back = basename($back);
            $back_dir = '&directory='.dirname($dir);
        }
        $back = $lang->sprintf($lang->file_goback, $back);
        $table->construct_cell("<img src=\"extension/return.png\">", array('class' => 'file__icon'));
        $table->construct_cell("<a href=\"index.php?module=file".$back_dir."\" class=\"name\"><strong>".$back."</strong></a>", array("width" => "60%", "class" => "file_name"));
        $table->construct_cell("");
        $table->construct_cell("");
        $table->construct_cell("");
        $table->construct_row(array("class" => "file"));
    }
    
    $rdir_query = '';
    if (!empty($dir) && !in_array($dir, array('/', '\\', '.'))) {
        $rdir_query = '&rdir='.$dir;   
    }
    
    $open = new DirectoryIterator(MYBB_ROOT.$dir);
    
    $directory = $files = array();
	foreach($open as $file){
        if(in_array($file->getfilename(), array(".", ".."))) continue;
        
        if($file->isDir()){
            $directory[] = array(
				'name' => $file->getfilename(),
                'path' => $file->getPathname()
			);
        }
		
        if($file->isFile()){
			$files[] = array(
				'name' => $file->getfilename(),
				'ext' => $file->getExtension(),
                'path' => $file->getPathname()
			);
        }
    }
    asort($directory);
    asort($files);
    
	foreach($directory as $d) {
        $editTime = my_date($mybb->settings['dateformat'].' '.$mybb->settings['timeformat'], filemtime($d['path']));
		$table->construct_cell("<img src=\"extension/folder.png\">", array('class' => 'file__icon'));
		$table->construct_cell("<a href=\"index.php?module=file&directory={$dir}/{$d['name']}\" class=\"file__name__tag\"><strong>".$d['name']."</strong></a>", array("width" => "60%", "class" => "file__name"));
        $table->construct_cell($editTime, array("width" => "15%", "class" => "file__modified align_center"));
		$table->construct_cell('<span><a href="#rename" class="file__actions__rename">'.$lang->file_rename.'</a></span> <span><a class="file__actions__delete" href="index.php?module=file&action=delete&directory='.$dir.'/'.$d['name'].$rdir_query.'">'.$lang->file_delete.'</a></span>', array("width" => "25%", "class" => "file__actions align_center"));
        $table->construct_cell('<input type="checkbox" class="checkbox inline_action" name="files[]" value="'.$d['name'].'" value="1" />');
		$table->construct_row(array("class" => "file dir"));
	}
	
	foreach($files as $f) {
        $editTime = my_date($mybb->settings['dateformat'].' '.$mybb->settings['timeformat'], filemtime($f['path']));
        $icon = get_file_icon($f['name']);
		$table->construct_cell("<img src=\"{$icon}\">", array('class' => 'file__icon'));
		$table->construct_cell("<strong class=\"file__name__tag\">{$f['name']}</strong>", array("width" => "60%", "class" => "file__name"));
        $table->construct_cell($editTime, array("width" => "15%", "class" => "file__modified align_center"));
		$action = '<span><a href="'.$mybb->settings['bburl'].'/'.$dir.'/'.$f['name'].'" target="_blank">'.$lang->file_view.'</a></span>';
		if(is_editable($f['name']))
			$action .= '<span><a href="index.php?module=file&action=edit'.$dir_query.'&file='.$f['name'].'" class="file__actions__edit">'.$lang->file_edit.'</a></span> ';
		$action .= '<span><a href="#rename" class="file__actions__rename">'.$lang->file_rename.'</a></span> <span><a class="file__actions__delete" href="index.php?module=file&action=delete'.$dir_query.'&file='.$f['name'].'">'.$lang->file_delete.'</a></span>';
		$table->construct_cell($action, array("width" => "25%", "class" => "file__actions align_center"));
        $table->construct_cell('<input type="checkbox" class="checkbox inline_action" name="files[]" value="'.$f['name'].'" value="1" />');
		$table->construct_row(array("class" => "file"));
	}
	
    $table->output($lang->file_title);
    
    echo '
<div style="text-align:right"><span class="smalltext"><strong>'.$lang->file_inline_actions.'</strong></span>
<select name="action">
	<option value="multidelete">'.$lang->file_delete.'</option>
    <option value="multimove">'.$lang->file_move.'</option>
    <option value="multicopy">'.$lang->file_copy.'</option>
    <option value="zip">'.$lang->file_zip.'</option>
</select>
<input type="submit" class="submit_button inline_element" id="inline_go" name="inline_actions" value="'.$lang->file_inline_go.' (0)" disabled="disabled" />  
</div><br class="clear" />';
    
    $form->end();
    
    $page->output_footer();
}

function fm_set_title($args)
{
    global $fm_title;
    $args['title'] = $fm_title;
}