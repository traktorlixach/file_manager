<?
$filename = $_SERVER['PHP_SELF'];													// имя скрипта
$root = $_SERVER['DOCUMENT_ROOT'];										        	// корень (относительно расположения скрипта)
$action = $_POST['action'];															// действие: распаковка/запаковка/листинг
$user = get_current_user();															// имя пользователя на сервере. используется в имени архива/каталога_для_распаковки
$bash_log = 'bash.log';


switch ($action) {
    case 'scandir': {																// листинг по каталогам
        $dir = $_POST['dir'].'/';													// текущий каталог
        $tree = scandir($dir);														// список (массив) содержимого текущего каталога
        if (count($tree) == 2) echo "<span class=\"empty\">< empty ></span><br />";
        foreach ($tree as $file) {
            $relative_dir = str_replace($root.'/', '', $dir);
            $breadcrumb = '| --- ';
            $checkbox = '<input type="checkbox" value="'.$relative_dir.$file.'" />';
            $fileperms = substr(sprintf('%o', fileperms($dir.$file)), -4);

            if ($file == '..' || $file == '.') continue;
            if (!is_dir($dir.$file)) {
                $filesize = formatBytes(filesize($dir.$file));
                echo $breadcrumb.$checkbox."<span data-src=\"$dir$file\" class=\"file\">".$file."</span>&ensp;<span class=\"file_size\">/ ".$filesize.' / '.$fileperms."</span><br />";
            }
            else {
                $dirsize = formatBytes(dir_size($dir.$file));
                echo "<div>".$breadcrumb.$checkbox."<span data-src=\"$dir$file\" data-status=\"close\" class=\"dir\" onclick=\"Scan_Dir(this); auto_width();\">".$file."</span>&ensp;<span class=\"file_size\">/ ".$dirsize.' / '.$fileperms."</span><br /><span class=\"listing\"> </span></div>";
            }
        }
        return;
    }
    case 'pack': {																	// запаковка
        $string2ssh = $_POST['string2ssh']." 2>$bash_log";
        $result = exec("$string2ssh");
        echo 'The archive is packed!';
        sleep(1);
        return;
    }
    case 'unpack': {																// распаковка
        $archive = $_POST['archive_name'];
        $ext_src = $_POST['ext_src'];
        if (is_file($archive)) {
            $pathinfo = pathinfo($archive);
            switch ($pathinfo['extension']) {
                case 'tgz': exec("mkdir $ext_src\ntar -xzf $archive -C $ext_src/ 2>$bash_log"); $result = true; break;
                case 'tar': exec("mkdir $ext_src\ntar -xf $archive -C $ext_src/ 2>$bash_log"); $result = true; break;
                case 'zip': exec("mkdir $ext_src\nunzip $archive -d $ext_src/ 2>$bash_log"); $result = true; break;
                default: echo 'file format is not supported';
            }
            if ($result) echo 'The archive(s) is unpacked!';
            else echo 'Error!';
        }
        sleep(1);
        return;
    }
    case 'delete': {																// удаление
        $file = $_POST['src_file'];
        if (!is_dir($file)) {
            $result = unlink($file);
        }
        else $result = r_rmdir($file);
        if ($result) echo 'File/dir '.$file.' deleted';
        else echo 'Error!';
        sleep(1);
        return;
    }
    case 'chmod': {																	// изменение прав
        $file = $_POST['src_file'];
        $chmod = $_POST['chmod'];
        $result = chmod($file, intval($chmod, 8));
        if ($result) echo 'Right applied';
        else echo 'Error!';
        sleep(1);
        return;
    }
	case 'log': {																	// чтение лога и отправка его в браузер через JS и JSON
		$bash_log_array = file($bash_log);
		echo json_encode($bash_log_array);
        return;
	}
    default: {																		// отрисовка интерфейса
        $dirsize = formatBytes(dir_size($root));
        $fileperms = substr(sprintf('%o', fileperms($root)), -4);
        echo "
            <HTML>
            <BODY>
                <!-- кнопки и ответ сервера об операции -->
                <input type=\"button\" value=\"TGZ\" onclick=\"Pack('tgz');\">
                <input type=\"button\" value=\"TAR\" onclick=\"Pack('tar');\">
                <input type=\"button\" value=\"ZIP\" onclick=\"Pack('zip');\">&emsp;&emsp;
                <input type=\"button\" value=\"Unpack\" onclick=\"Unpack();\">&emsp;&emsp;
                <input type=\"button\" value=\"Chmod\" onclick=\"Popup('chmod');\">&emsp;&emsp;
                <input type=\"button\" value=\"Delete\" onclick=\"Delete();\">&emsp;&emsp;
                <input type=\"button\" value=\"Last log\" onclick=\"Popup('log'); Read_Log();\">&emsp;&emsp;
                <br /><br />
                <span id=\"response_action\" class='response_action'>Choose the action</span><br />
                <br />

                <!-- сам файловый менеджер -->
                <div class=\"file_manager\" id=\"file_manager\">
                    <span class=\"refresh\" onclick=\"Refresh(this);\"><img src=\"http://userkill.jelasticloud.com/refresh_24.png\"></span>
                    <input type=\"checkbox\" value=\"$root\"/><span data-src=\"$root\" data-status=\"close\" class=\"dir\" id=\"root\"  onclick=\"Scan_Dir(this); auto_width();\">$root</span>&ensp;<span class=\"file_size\">/ $dirsize / $fileperms</span><br />
                    <span class=\"listing\"> </span>
                </div>

                <!-- всплывающее окно для изменения прав доступа (chmod) -->
                <div id=\"chmod\" class=\"popup\">
                    <span class=\"close\" onclick=\"Close(this);\"> X </span>
                    <form name=\"b\" id=\"b\" action=\"\">
                    <div style=\"width: 420px;\">
                            <span style=\"position: relative; left: 30%;\">Изменение прав доступа</span><br /><br />
                            <div style=\"float: left\">
                                <fieldset>
                                    <legend>Владелец</legend>
                                    <input type=\"checkbox\" id=\"b9\" onclick=\"per()\" checked=\"checked\" />чтение<br />
                                    <input type=\"checkbox\" id=\"b8\" onclick=\"per()\" checked=\"checked\" />запись<br />
                                    <input type=\"checkbox\" id=\"b7\" onclick=\"per()\" />выполнение
                                </fieldset>
                            </div>

                            <div style=\"float: left\">
                                <fieldset>
                                <legend>Группа</legend>
                                    <input type=\"checkbox\" id=\"b6\" onclick=\"per()\" checked=\"checked\" />чтение<br />
                                    <input type=\"checkbox\" id=\"b5\" onclick=\"per()\" />запись<br />
                                    <input type=\"checkbox\" id=\"b4\" onclick=\"per()\" />выполнение
                                </fieldset>
                            </div>

                            <div>
                                <fieldset>
                                <legend>Остальные</legend>
                                    <input type=\"checkbox\" id=\"b3\" onclick=\"per()\" checked=\"checked\" />чтение<br />
                                    <input type=\"checkbox\" id=\"b2\" onclick=\"per()\" />запись<br />
                                    <input type=\"checkbox\" id=\"b1\" onclick=\"per()\" />выполнение
                                </fieldset>
                            </div>
                            <br />
                            &emsp; &emsp; &emsp;
                            <input size=\"3\" maxlength=\"3\" id=\"num\" class=\"new_value\" onkeyup=\"rep()\" value=\"644\" /> числовой формат записи chmod
                    </div>
                    </form>
                    <span class=\"apply\" onclick=\"Apply(this);\"> OK </span>
                </div>
				
                <div id=\"log\" class=\"popup\" style=\"left: 70%; right: 3%; top: 3%; min-width: 400px; min-height: 50px;\">
                    <span class=\"close\" onclick=\"Close(this);\"> X </span>
					<div>
						< empty >
					</div>
                </div>
            </BODY>
            </HTML>
        ";
    }
}


/* форматирует размер файла/каталога в соответствующие единицы */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}

/* считает размер каталога */
function dir_size($dir) {
    $totalsize=0;
    if ($dirstream = @opendir($dir)) {
        while (false !== ($filename = readdir($dirstream))) {
            if ($filename!="." && $filename!="..")
            {
                if (is_file($dir."/".$filename))
                    $totalsize+=filesize($dir."/".$filename);

                if (is_dir($dir."/".$filename))
                    $totalsize+=dir_size($dir."/".$filename);
            }
        }
    }
    closedir($dirstream);
    return $totalsize;
}

/* Рекурсивно удаляет каталог */
function r_rmdir($dir) {
	foreach(glob($dir.'/*') as $file) {
		if(is_dir($file)) r_rmdir($file);
		else unlink($file);
	}
	return rmdir($dir);
}

?>


<HEAD>
    <title>File manager v2.0</title>

    <style>
        span {
            font-size: 16px;
            font-weight: 500;
        }
        .response_action {
            display: inline-block;
            height: 30px;
            font-size: 20;
            font-weight: 600;
            color: #4EEE94;
        }
        .dir {
            color: #0000ff;
        }
        .file {
            color: #000000;
        }
        .empty {
            color: #000000;
            font-style: italic;
        }
        .listing {
            position: relative;
            left: 30px;
        }
        .file_manager {
			position: relative;
            box-shadow: inset 0 0 4px rgba(0, 0, 0, 0.5);   /* Параметры тени */
            padding: 10px;
            background: lightgrey;
            border: solid 1px dimgray;
            width: 30%;
            cursor: pointer;
            -moz-user-select: none;                         /* запрет выделения текста в FF */
            -webkit-user-select: none;                      /* запрет выделения текста в Chrome */
        }
        .file_size {
            font-size: 12;
            font-weight: 100;
        }
        .wait {
            font-size: 20;
            font-weight: 600;
            color: #07cdde;
        }
		.popup {
			display: none;
			position: fixed;
			border: solid 1px #23AAE7;
			left:35%;
			top:25%;
			box-shadow: 7px -8px 18px -4px #23AAE7;
		}
        .apply {
            cursor: pointer;
            margin: 0 45%;
            padding: 1px 10px;
            background: #8DDCFF;
        }
		.close {
            cursor: pointer;
            position: absolute;
			right: 0;
            padding: 0px 5px;
            background: #37A8E6;
		}
		.refresh {
            cursor: pointer;
            position: absolute;
			right: 5px;
			top: 5px;
		}
    </style>

    <!-- подключаем ядро Jquery -->
    <script type="text/javascript" src="http://userkill.jelasticloud.com/jquery/js/jquery-2.0.3.min.js"></script>

    <!-- подключаем модуль cookie -->
    <script type="text/javascript" src="http://userkill.jelasticloud.com/jquery/js/jquery.cookie.js"></script>

    <!-- скрипт для подсчёта значений chmod -->
    <script>
		function truefalse(ft) {
			return (ft == true ? 1 : 0)
		}
		function eslafeurt(ee) {
			return (ee = 1 ? true : false)
		}
		function per() {
			a=(4 * truefalse(document.b.b9.checked) + 2 * truefalse(document.b.b8.checked) + truefalse(document.b.b7.checked)) + "" + (4 * truefalse(document.b.b6.checked) + 2 * truefalse(document.b.b5.checked) + truefalse(document.b.b4.checked)) + "" + (4 * truefalse(document.b.b3.checked) + 2 * truefalse(document.b.b2.checked) + truefalse(document.b.b1.checked))
			document.b.num.value = a
		}
		function rep() {
			var preg=/^[0-7]{0,3}$/
			if (!preg.test(document.b.num.value)) {
				alert("Введите восьмеричное число")
				return 0;
			}
			a = parseInt(document.b.num.value, 8)
			ab = a % 2
			document.b.b1.checked = ab
			a = (a - ab) / 2
			ab = a % 2
			document.b.b2.checked = ab
			a = (a - ab) / 2
			ab = a % 2
			document.b.b3.checked = ab
			a = (a - ab) / 2
			ab = a % 2
			document.b.b4.checked = ab
			a = (a - ab) / 2
			ab = a % 2
			document.b.b5.checked = ab
			a = (a - ab) / 2
			ab = a % 2
			document.b.b6.checked = ab
			a = (a - ab) / 2
			ab = a % 2
			document.b.b7.checked = ab
			a = (a - ab) / 2
			ab = a % 2
			document.b.b8.checked = ab
			a = (a - ab) / 2
			ab = a % 2
			document.b.b9.checked = ab
		}
	</script>

    <script type="text/javascript">
        $(document).ready(function() {
            auto_width();                                   // подгоним ширину при загрузке документа
            Scan_Dir($('#root'));                           // просканируем корневой каталог
        });

        /* функция листинга по каталогам. принимает DOM-объект и передаёт значение аттрибута data-src в php */
        function Scan_Dir(dir) {
            var response_scandir = $(dir).siblings('.listing');
            response_scandir.html('<span class="wait">Scaning. Please wait...</span>');		// текст на время выполнения
            var source_dir = $(dir).data('src');
            var status_dir = $(dir).data('status');

            if (status_dir == 'open') {//&#10023;
                $(dir).data('status', 'close');
                response_scandir.html('');
            }
            else {
                $(dir).data('status', 'open');

                $.post("<? echo $filename; ?>", {
                    action: "scandir",
                    dir: source_dir
                }).done(function(response) {
                        response_scandir.html(response);
                    });
            }
        }

        /* проходит по всем отмеченным чек-боксам и генерит ssh-команды для запаковки архивов */
        function check() {
            var checked_array = new Array();
            var archive_name = '<? echo $user.'_'.date('d-m-Y_H-i'); ?>';
            var string2archive_tgz = 'tar -czf ' + archive_name + '.tgz ';
            var string2archive_tar = 'tar -cf ' + archive_name + '.tar ';
            var string2archive_zip = 'zip -r ' + archive_name + '.zip ';
            var name_files_array = new Array();
            var src_files_array = new Array();

            $('.file_manager input:checkbox:checked').each(function(i) {
                string2archive_tgz += $(this).val() + ' ' + $(this).val() + '/* ' + $(this).val() + '/.??* ' + $(this).val() + '/*/.??* ';
                string2archive_tar += $(this).val() + ' ' + $(this).val() + '/* ' + $(this).val() + '/.??* ' + $(this).val() + '/*/.??* ';
                string2archive_zip += $(this).val() + ' ' + $(this).val() + '/* ' + $(this).val() + '/.??* ';
                name_files_array[i] = $(this).val();
                src_files_array[i] = $(this).next().data('src');
            });

            checked_array['tgz'] = string2archive_tgz;
            checked_array['tar'] = string2archive_tar;
            checked_array['zip'] = string2archive_zip;
            checked_array['archive_name'] = archive_name;
            checked_array['name_files_array'] = name_files_array;
            checked_array['src_files_array'] = src_files_array;
            return checked_array;
        }

        /* подгоняет ширину менеджера под ширину содержимого */
        function auto_width() {
            var width_arr = new Array();
            $('.file, .dir ').each(function(i) {
                var total_width = Math.round($(this).offset().left + $(this).width() + 250);
                width_arr[i] = total_width;
            });
            $('.file_manager').width(Math.max.apply(null, width_arr));
        }

        /* функция запаковки архива */
        function Pack(type_archive) {
            $("#response_action").html('<img src="http://userkill.jelasticloud.com/ajax-loader.gif"> Packing. Please wait...');		// гифка на время выполнения
            var checked_array = check();
			if (checked_array['name_files_array'].length == 0) {
				$("#response_action").html('No files were given!');
				return;
			}
            $.post("<? echo $filename; ?>", {
                                        action: "pack",
                                    string2ssh: checked_array[type_archive],
                                  archive_name: checked_array['archive_name'] + '.' + type_archive
            }).done(function(response) {
                    $("#response_action").html(response);
					Read_Log();
                });
        }

        /* функция распаковки архива */
        function Unpack() {
            $("#response_action").html('<img src="http://userkill.jelasticloud.com/ajax-loader.gif"> Unpacking. Please wait...');		// гифка на время выполнения
            var checked_array = check();
			if (checked_array['name_files_array'].length == 0) {
				$("#response_action").html('No files were given!');
				return;
			}
			var archives_array = checked_array['name_files_array'];
			for (var i = 0; i < archives_array.length; i++) {
				$.post("<? echo $filename; ?>", {
											action: "unpack",
                                      archive_name: archives_array[i],
                                           ext_src: 'unpacked_' + checked_array['archive_name']
				}).done(function(response) {
						$("#response_action").html(response);
						Read_Log();
					});
			}
        }

        /* функция удаления файла */
        function Delete() {
            $("#response_action").html('<img src="http://userkill.jelasticloud.com/ajax-loader.gif"> Deleting. Please wait...');		// гифка на время выполнения
            var checked_array = check();
			if (checked_array['name_files_array'].length == 0) {
				$("#response_action").html('No files were given!');
				return;
			}
            var src_files_array = checked_array['src_files_array'];
            for (var i = 0; i < src_files_array.length; i++) {
                $.post("<? echo $filename; ?>", {
                                            action: "delete",
                                          src_file: src_files_array[i]
                }).done(function(response) {
                        $("#response_action").html(response);
                    });
            }
        }

		/* функция отображения всплывающего окна и затемнения фона */
		function Popup(popup) {
            var checked_array = check();
			if ((checked_array['name_files_array'].length == 0) && (popup != 'log')) {
				$("#response_action").html('No files were given!');
				return;
			}
			$('.file_manager').fadeTo('normal', 0.5);
			$('#' + popup).fadeTo('normal', 1);
		}

		/* функция применения изменений (во всплывающем окне). принимает значение окна, в котором нужно что-то применить */
		function Apply(popup) {
			Close(popup);
            var id = $(popup).parent().attr("id");										// id окна, в котором фызвана эта функция
			var new_value = $(popup).parent().find('.new_value').val();					// значение, которое нужно применить к файлу

			if (id == 'chmod') {
				$("#response_action").html('<img src="http://userkill.jelasticloud.com/ajax-loader.gif"> Applying. Please wait...');		// гифка на время выполнения
				var checked_array = check();
				var src_files_array = checked_array['src_files_array'];
				for (var i = 0; i < src_files_array.length; i++) {
					$.post("<? echo $filename; ?>", {
												action: "chmod",
											  src_file: src_files_array[i],
                                                 chmod: '0' + new_value
					}).done(function(response) {
							$("#response_action").html(response);
						});
				}
			}
			
		}
		
		/* функция закрытия всплывающего окна. принимает значение окна, которое нужно закрыть */
		function Close(popup) {
			$(popup).parent().fadeTo('fast', 0);
			$('.file_manager').fadeTo('fast', 1);
		}

        /* функция обновления открытых каталогов */
		function Refresh(obj) {
            var obj_opened_dir = new Array();
            var src_opened_dir = new Array();
            $('.file_manager span.dir').each(function(i) {
				var value = $(this).html();
				var status = $(this).data('status');
				var src = $(this).data('src');
				if (status == 'open') {
//					console.log(i + ' - ' + value + ' - ' + status + ' - ' + src);
                    src_opened_dir[i] = src;
                    obj_opened_dir[i] = this;
                }
			});


            src_opened_dir.clean(undefined);
            var arr_count = src_opened_dir.length - 1;
            for (var i = 0; i < src_opened_dir.length; i++) {
                console.log('i=' + i + ' ' + src_opened_dir[i]);

//                alert('ololo');
				// '[data-src='+src_opened_dir[i]+']'
                //$('.file_manager span.dir').each(function(j) {
				//console.log($('[data-src="'+src_opened_dir[i]+'"]').data('src') + ' --------- test');
				//$('.file_manager span.dir[data-src="'+src_opened_dir[i]+'"]').each(function(j) {
				//pause(1);
				/*
				try {
					// код
					throw "test";
				}
				catch(e) {
					//alert(e);
					//throw "stop"; // скрипт дальше не должен выполняться.
				}
				*/
				
				/*
				var x = 2;
				while (x != 1) {
					x++;
				}
				*/


                $('.file_manager span.dir').each(function(j) {
                    var src = $(this).data('src');
                    console.log('j=' + j + ' ' + src);

                    if (src_opened_dir[i] == src) {
                        console.log(' ololo ' + src);
                        console.log('---');
                        Scan_Dir(this);
                        Scan_Dir(this);
//                        src_opened_dir[i] = undefined;
                    }
                });
            }
		}

        /* метод удаления из массива ненужных элементов, например, undefined */
        Array.prototype.clean = function(deleteValue) {
            for (var i = 0; i < this.length; i++) {
                if (this[i] == deleteValue) {
                    this.splice(i, 1);
                    i--;
                }
            }
            return this;
        };

		/* функция чтения лога выполнения и вывод на экран */
		function Read_Log() {
			var window_lod = $('#log div');
			$.post("<? echo $filename; ?>", {
										action: "log"
			}).done(function(response) {
					var bash_log = $.parseJSON(response);                                                   // парсим ответ обработчика
					window_lod.html(nl2br(bash_log));
				});
			
			Popup('log');
		}
		
		/* корректное отображение переносов строки в логе операций */
		function nl2br(str) {
			str = (str + '').replace(/\r\n|\r|\n/g, '<br />');                 								// регулярка для корректных переносов строки
			str = (str + '').replace(/,/g, '');																// хз, откуда берутся эти запятые, я просто их удалю
			return str;
		}

		
		function pause( iMilliseconds ) {
			var sDialogScript = 'window.setTimeout( function () { window.close(); }, ' + iMilliseconds + ');';
			window.showModalDialog('javascript:document.writeln("<script>' + sDialogScript + '<' + '/script>")');
		}
		
     </script>
</HEAD>




