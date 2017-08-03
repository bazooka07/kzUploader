<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">

<head>
	<title>Test upload fichiers</title>
	<meta http-equiv="content-type" content="text/html;charset=utf-8" />
	<style type="text/css">
		* { margin: 0; padding: 0; box-sizing: border-box; }
		body { background-color: #e8e8e8; font: 14pt ubuntu,sans-serif; }
		div { min-height: 8em; }
		#limit-post { min-height: initial; }
		div, form { border: 1px solid #666; width: 40rem; margin: 1rem auto; padding: 0.8rem; background-color: #fff; }
		#detail { padding: 0 0.5rem; }
		span { display: inline-block; width: 6em; text-align: right; margin-right: 0.5rem; color: #888; }
		#limit-post span,
		#php-server span { width: 15rem; }
		#php-server h2 { text-align: center; padding: 1rem 0; }
		#detail > ul > li:nth-of-type(even) { background-color: #eee; border: none; margin: 0.5rem 0; width: 100%; }
		ul { list-style: none; padding: 0; margin: 0; }
		form li:last-of-type { margin-top: 1rem; text-align: center; }
		input[type="file"] { width: 100%; }
		.alert { color: red; }
	</style>
</head>
<body>
	<div id="php-server">
		<h2>Limites maximales imposées par&nbsp;PHP&nbsp;sur&nbsp;le&nbsp;serveur</h2>
		<ul>
<?php
	// http://php.net/manual/fr/features.file-upload.post-method.php
	error_reporting(E_ALL);
	// ini_set("display_errors", true);
	const INPUT_NAME = 'myFiles';
	const KBYTES = '*1024';
	$captions = array(
		'max_file_uploads'		=> 'Nb de fichiers par lot',
		'post_max_size'			=> 'Taille du lot',
		'upload_max_filesize'	=> 'Taille pour chaque fichier'
	);

	function unities($value, $unit='') {
		eval('$result=('.preg_replace(
			array(
				'@M$@i',
				'@K$@i'),
			array(
				KBYTES.KBYTES,
				KBYTES
			),
			$value).');'
		);
		return $result.$unit;
	}

	foreach($captions as $field=>$caption) {
		$value = unities(ini_get($field), ($field != 'max_file_uploads' ? ' octets' : ''));
		echo "<li><span>$caption :</span> $value</li>\n";
	}

	$post_max_size = unities(ini_get('post_max_size'));
?>
		</ul>
	</div>
	<div id="limit-post"<?php if(!empty($_SERVER['CONTENT_LENGTH']) and ($_SERVER['CONTENT_LENGTH'] > $post_max_size)) echo ' class="alert"'; ?>>
		<span>Taille de l'entête :</span> <?php echo (!empty($_SERVER['CONTENT_LENGTH'])) ? $_SERVER['CONTENT_LENGTH'].' octets (maxi: '.$post_max_size.')' : ''; ?>
	</div>
	<div id="detail">
<?php
	if(isset($_FILES[INPUT_NAME])) {
?>
		<ul>
<?php
		$msg = array(
			'NONE',
			'UPLOAD_ERR_INI_SIZE',
			'UPLOAD_ERR_FORM_SIZE',
			'UPLOAD_ERR_PARTIAL',
			'UPLOAD_ERR_NO_FILE',
			'UPLOAD_ERR_NO_TMP_DIR',
			'UPLOAD_ERR_CANT_WRITE',
			'UPLOAD_ERR_EXTENSION'
		);

		if(is_array($_FILES[INPUT_NAME]['name'])) {
			for($i=0, $iMax = count($_FILES[INPUT_NAME]['name']); $i<$iMax; $i++) {
?>
		<li><ul>
<?php
				foreach($_FILES[INPUT_NAME] as $field=>$value) {
					// $value is an array
					$caption = ($field != 'error') ? $value[$i] : strtolower($msg[$value[$i]]);
					echo "<li><span>$field</span>: $caption</li>\n";
				}
?>
		</ul></li>
<?php
			}
		} else {
			foreach($_FILES[INPUT_NAME] as $field=>$value) {
				$caption = ($field != 'error') ? $value : strtolower($msg[$value]);
				echo "<li><span>$field</span>: $caption</li>\n";
			}
		}
?>
		</ul>
<?php
	}
?>
	</div>
	<form method="post" enctype="multipart/form-data">
		<ul>
			<!-- input type="hidden" name="MAX_FILE_SIZE" value="30000" / -->
			<li><input type="file" name="<?php echo INPUT_NAME; ?>[]" multiple="multiple" placeholder="Sélectionnez un ou plusieurs fichiers" accept="image/*,*/*" required /></li>
			<li><input type="submit" /></li>
		</ul>
	</form>
</body>
</html>
