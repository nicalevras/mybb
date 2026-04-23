<?php
function get_file_extension($ext)
{
	switch ($ext) {
		case "html":
		case "htm":
			return "html";
		case "php":
			return "php";
		case "css":
			return "css";
		case "js":
			return "js";
		case "xml":
			return "xml";
		case "rar":
			return "rar";
		case "zip":
			return "zip";
        case 'ico': case 'gif': case 'jpg': case 'jpeg': case 'png': case 'bmp':
            return 'image';
		default:
			return "other";
	}
}

function get_file_icon($file)
{
    global $mybb, $dir;
    if (is_dir(MYBB_ROOT.$dir.'/'.$file)) {
        return 'extension/folder.png';
    }
    
    $file = pathinfo($file);
    $ext = get_file_extension($file['extension']);
    if ($ext == 'image') {
        $ext = $mybb->settings['bburl'].'/'.$dir.'/'.$file['basename'];
    } else {
        $ext = 'extension/'.$ext.'.png';
    }
    return $ext;
}

function is_editable($file)
{
	$ext = pathinfo($file);
	$ext = $ext['extension'];
	if (in_array($ext, array("php", "html", "htm", "xml", "htaccess", "js", "css", "txt", ""))) {
		return true;
		exit;
	}

	return false;
}

function get_codemirror_type($ext)
{
	switch ($ext) {
		case "php":
			return "application/x-httpd-php";
		case "css":
			return "text/css";
		case "js":
			return "text/javascript";
		default:
			return "text/html";
	}
}

function removedir($directory)
{
	foreach (@scandir($directory) as $f) {
		if (in_array($f, array(".", ".."))) continue;
		if (is_dir($directory.'/'.$f)) {
			@removedir($directory.'/'.$f);
		} else {
			@unlink($directory.'/'.$f);
		}
	}
	return rmdir($directory);
}

function file_list_destination($files)
{
    global $dir, $lang, $form;
    
    echo <<<EOT
<script type="text/javascript">
    var dirs = [];
    dirs.push("/");
    $(document).ready(function () {
        $('.select_folder').prop('disabled', false);
    })
    .change('.select_folder', function () {
        var folder = $('.select_folder').val();
        
        $('.select_folder').prop('disabled', true);
        $('.select_folder option[value=""]').remove();
        
        if (dirs.indexOf(folder) === -1) {
            $.ajax({
                type: 'post',
                url: 'index.php',
                data: 'module=file&action=get_folders&folder='+encodeURIComponent(folder),
                complete: function (data) {
                    data = JSON.parse(data.responseText);
                    dirs.push(folder);
                    for (var f in data.folders) {
                        $('.select_folder option[value="'+folder+'"]').after('<option value="'+f+'">'+f+'</option>');
                        folder = f;
                    }
                    $('.select_folder').prop('disabled', false);
                }
            });
        } else {
            $('.select_folder').prop('disabled', false);
        }
    });
</script>
EOT;
    
    $table = new Table;
        
    $table->construct_header($lang->file_list_files);
    $table->construct_header($lang->file_list_dir);
    $table->construct_header($lang->file_list_dest);

    $file_list = '<ul>';
    foreach ($files as $file) {
        $file_list .= '<li style="margin-bottom: 8px"><input type="hidden" name="files[]" value="'.$file.'">'.$file.'</li>';
    }
    $file_list .= '</ul>';
    
    $folders = array();
    $folders[''] = $lang->file_list_dest;
    $folders = array_merge($folders, get_folders());
    
    $table->construct_cell($file_list);
    $table->construct_cell('/'.$dir, array('style' => 'vertical-align:top'));
    $table->construct_cell($form->generate_select_box('dest', $folders, array(), array('class' => 'select_folder')), array('style' => 'vertical-align:top'));
    $table->construct_row();

    return $table->output(false, false, false, true);
}

function get_folders($dir='')
{
    $open = new DirectoryIterator(MYBB_ROOT.$dir);
    
    $folders = array();
    
    if ($dir == '') {
        $folders['/'] = '/';
    }
    
	foreach($open as $file){
        if(in_array($file->getfilename(), array(".", ".."))) continue;
        
        if($file->isDir()){
            $name = $dir.'/'.$file->getfilename();
            $folders[$name] = $name;
        }
    }
    asort($folders);
    
    return $folders;
}

function build_file_list($files) {
    $list = '<ul>';
    foreach($files as $file) {
        $list .= '<li>'.$file.'</li>';
    }
    $list .= '</ul>';
    return $list;
}

function add_zip($zip, $file, $root) {
    $path = $root.'/'.$file;
    if (is_dir($path)) {
        $dir = new DirectoryIterator($path);
        $zip->addEmptyDir($file);
        foreach ($dir as $item) {
            if(in_array($item->getfilename(), array(".", ".."))) continue;
            add_zip($zip, $file.'/'.$item->getfilename(), $root);
        }
    } else {
        $zip->addFile($path, $file);
    }
}