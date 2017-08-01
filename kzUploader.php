<?php

if (!defined('PLX_ROOT')) exit;

/**
 * Installe un plugin ou un thème avec le navigateur Internet.
 * La librairie ZipArchive est requise pour ce plugin.
 * @Author J.P. Pourrez <kazimentou@gmail.com>
 * */
class kzUploader extends plxPlugin {

	private $__themesRoot = false;

	public function __construct($default_lang) {

		# on ne récupère que des archives Zip
		if(class_exists('ZipArchive')) {
			# Appel du constructeur de la classe plxPlugin (obligatoire)
			parent::__construct($default_lang);

			$this->setConfigProfil(PROFIL_ADMIN);

			# Accès au menu admin réservé au profil administrateur
			$this->setAdminProfil(PROFIL_ADMIN);

			# Ajouts des hooks
			$this->addHook('AdminSettingsPluginsFoot', 'AdminSettingsPluginsFoot');
			$this->addHook('AdminPrepend', 'AdminPrepend');
			$this->addHook('AdminThemesDisplayFoot', 'AdminThemesDisplayFoot');
		} else {
			plxMsg::Error($this->getLang('L_MISSING_LIBRARY'));
		}
	}

	# the missing function in PHP: "rm -R foo"
	private function __rmDir($item) {
		if(is_dir($item)) {
			if($dh = opendir($item)) {
				while(($entry = readdir($dh)) !== false) {
					if(!preg_match('@\A\.\.?\z@', $entry)) {
						$p = $item.DIRECTORY_SEPARATOR.$entry;
						if(is_dir($p)) {
							$this->__rmDir($p);
						} else {
							unlink($p);
						}
					}
				}
				closedir($dh);
				rmdir($item);
				return true;
			} else {
				return false;
			}
		} elseif(is_writable($item)) {
			unlink($item);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Retourne une variable d'environnement sous forme numérique.
	 * */
	private function ini_get($a) {
		$k = '*1024';
		$b = preg_replace(
			array('@M$@i', '@K$@i'),
			array($k.$k, $k),
			ini_get($a)
		);
		eval('$result=('.$b.');');
		return $result;
	}

	private function __printForm($field) {
		switch($field) {
			case 'plugin':	$label = $this->getLang('L_NEW_PLUGIN'); break;
			case 'thema':	$label = $this->getLang('L_NEW_THEMA'); break;
			default:		$label = 'Unknow';
		}
		$limits = json_encode(
			array(
				'max_file_uploads'		=> $this->ini_get('max_file_uploads'),
				'post_max_size'			=> $this->ini_get('post_max_size'),
				'upload_max_filesize'	=> $this->ini_get('upload_max_filesize')
			)
		);
	# Pour télécharger un fichier la balise <form..> doit avoir un attribut enctype adéquat
	# la valeur de data-post doir être entre guillemets simple (JSON !)
?>
	<div class="kzUploader">
		<form method="post" enctype="multipart/form-data" id="form-kzUploader">
			<?php echo plxToken::getTokenPostMethod(); ?>
			<input type="hidden" name="zip" value="<?php echo $field; ?>">
			<label for="id_kzUploader"><?php  echo $label; ?></label>
			<input type="file" name="kzUploader" id="id_kzUploader" accept="application/zip,application/gzip" required />
			<input type="submit" value="<?php $this->lang('L_UPLOAD'); ?>"/>
		</form>
	</div>
	<script type="text/javascript">
		const limits = <?php echo $limits; ?>;
		const messages = {
			max_file_uploads:		'<?php $this->lang('L_MAX_FILE_UPLOADS'); ?>',
			post_max_size:			'<?php $this->lang('L_POST_MAX_SIZE'); ?>',
			upload_max_filesize:	'<?php $this->lang('L_UPLOAD_MAX_FILESIZE'); ?>'
		};
		const form = document.getElementById('form-kzUploader');

		form.addEventListener('submit', function(event) {
			var	files = form.elements['kzUploader[]'].files,
				controls = {
					max_file_uploads: files.length,
					post_max_size: 0,
					upload_max_filesize: 0
				}

			for(var i=0, iMax=files.length; i<iMax; i++) {
				controls.post_max_size += files.item(i).size;
				if(files.item(i).size > limits.upload_max_filesize) {
					controls.upload_max_filesize++;
				}
			}

			var msg = '';
			for(var k in controls) {
				if((k == 'upload_max_filesize' && controls.upload_max_filesize > 0) || controls[k] > limits[k]) {
					var value = (k == 'post_max_size') ? (controls[k] / 1024).toFixed(0) : controls[k];
					var limit = (k != 'max_file_uploads') ? (limits[k] / 1024).toFixed(0) : limits[k];
					msg += messages[k].replace(/##1##/, value).replace(/##2##/, limit) + '\n';
				}
			}

			if(msg.length > 0) {
				event.preventDefault();
				alert(msg);
				return false;
			}
		});
	</script>
<?php
	}

	private function __unwritableFolder($folder) {
		$message = sprintf($this->getLang('L_UNWRITABLE_FOLDER'), realpath($folder));
?>
	<div class="kzUploader kz-error">
		<p><?php echo $message; ?></p>
	</div>
<?php
	}

	private function is_writable_folder() {
		$result = false;
		if(!empty($_POST['zip'])) {
			switch($_POST['zip']) {
				case 'plugin':
					if(is_writable(PLX_PLUGINS))
						$result = true;
					else
						plxMsg::Error(sprintf($this->getLang('L_UNWRITABLE_FOLDER'), realpath(PLX_PLUGINS)));
					break;
				case 'thema':
					if(is_writable($this->__themesRoot))
						$result = true;
					else
						plxMsg::Error(sprintf($this->getLang('L_UNWRITABLE_FOLDER'), realpath($this->__themesRoot)));
					break;
			}
		}
		return $result;
	}

 	public function AdminSettingsPluginsFoot() {
		if(empty($_SESSION['selPlugins'])) {
			if(is_writable(PLX_PLUGINS)) {
				$this->__printForm('plugin');
			} else {
				# Trop tard pour envoyer un cookie avec plxMsg::error()
				$this->__unwritableFolder(PLX_PLUGINS);
			}
		}
	}

	public function AdminThemesDisplayFoot() {
		if(is_writable($this->__themesRoot)) {
			$this->__printForm('thema');
		} else {
			# Trop tard pour envoyer un cookie avec plxMsg::error()
			$this->__unwritableFolder($this->__themesRoot);
		}
	}

	private function __unzip($filename) {
		global $plxAdmin;

		$redirect = 'index';
		$zip = new ZipArchive();
		if($zip->open($filename) === true) {
			$tmpDir = '';
			switch($_POST['zip']) {
				case 'plugin': $tmpDir = PLX_PLUGINS; break;
				case 'thema':	$tmpDir = $this->__themesRoot; break;
			}
			$tmpDir .= __CLASS__.'.XXXXX';
			if(is_dir($tmpDir)) {
				$this->__rmDir($tmpDir);
			}
			mkdir($tmpDir, 0750);
			try {
				$zip->extractTo($tmpDir);
				$folders = glob($tmpDir.'/*', GLOB_ONLYDIR);
				if(count($folders) == 1) {
					switch($_POST['zip']) {
						case 'plugin':
							# On corrige le nom du dossier pour les plugins issus de Github
							$target = PLX_PLUGINS.preg_replace('@^([a-z_]\w*).*@i', '\1', basename($folders[0]));
							if(!is_dir($target)) {
								rename($folders[0], $target);
							} else {
								plxMsg::Error($this->getLang('L_PLUGIN_ALREADY_EXISTS'));
							}
							$redirect = 'parametres_plugins';
							break;
						case 'thema':
							$target = $this->__themesRoot.basename($folders[0]);
							if(!is_dir($target)) {
								rename($folders[0], $target);
								$plxAdmin->editConfiguration($plxAdmin->aConf, array('style' => basename($folders[0])));
							} else {
								plxMsg::Error($this->getLang('L_THEMA_ALREADY_EXISTS'));
							}
							$redirect = 'parametres_themes';
							break;
					}
				} else {
					plxMsg::Error($this->getLang('L_INVALIDATE_ZIP'));
				}
			} catch(Exception $e) {
				plxMsg::Error($e->getMessage());
			} finally {
				$this->__rmDir($tmpDir);
			}
			$zip->close();
		}

		return $redirect;
	}

	private function __gunzip($filename) {
		global $plxAdmin;

		$redirect = 'index';
		$gz = gzopen($filename, 'r');
		gzclose($gz);

		return $redirect;
	}

	public function AdminPrepend() {
		global $plxAdmin, $lang;

		$this->__themesRoot = PLX_ROOT.$plxAdmin->aConf['racine_themes'];

		# Signature d'un fichier Zip: https://www.iana.org/assignments/media-types/application/zip
		if(!empty($_FILES['kzUploader'])) {
			if(
				!empty($_FILES['kzUploader']['tmp_name']) and
				($_FILES['kzUploader']['size'] > 0)  and
				(in_array(
					$_FILES['kzUploader']['type'],
					array(
						'application/zip',
						'application/gzip'
					))
				) and
				$this->is_writable_folder()
			) {
				# On finit l'execution de prepend.php pour installer les phrases dans la langue choisie
				global $plxAdmin, $lang;

				loadLang(PLX_CORE.'lang/'.$lang.'/admin.php');
				loadLang(PLX_CORE.'lang/'.$lang.'/core.php');
				$_SESSION['admin_lang'] = $lang;

				# Control du token du formulaire
				plxToken::validateFormToken($_POST);

				# Control de l'accès à la page en fonction du profil de l'utilisateur connecté
				$plxAdmin->checkProfil(PROFIL_ADMIN);

				$msg = false;
				switch($_FILES['kzUploader']['type']) {
					case 'application/zip':
						$redirect = $this->__unzip($_FILES['kzUploader']['tmp_name']);
						break;
					case 'application/gzip':
						$redirect = $this->__ungzip($_FILES['kzUploader']['tmp_name']);
						break;
					default:
						$redirect = 'index';
						$msg = 'Bad mime-type';
				}
				if(!empty($msg)) {
					PlxMsg::Error($msg);
				} else {
					PlxMsg::Info(sprintf($this->getLang('L_DOWNLOADED_PLUGIN'), $_FILES['kzUploader']['name']));
				}

				unlink($_FILES['kzUploader']['tmp_name']);

				header("Location: ${redirect}.php");
				exit;
			} else {
				$content = '';
				foreach(explode(' ', 'name type size error') as $field) {
					if(array_key_exists($field, $_FILES['kzUploader'])) {
						$value = $_FILES['kzUploader'][$field];
						$content .= "<br /><span class=\"kz-error kzLabel\">$field</span>: <span class=\"kz-error\">$value</span>";
					}
				}
				PlxMsg::Error($this->getLang('L_BAD_TYPE_FILE').$content);
			}
		} // fin de if(!empty($_FILES['kzUploader'])...

	}

}
?>