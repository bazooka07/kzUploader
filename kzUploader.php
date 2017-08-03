<?php

if (!defined('PLX_ROOT')) exit;

// http://php.net/manual/fr/features.file-upload.post-method.php

/**
 * Installe un plugin ou un thème avec le navigateur Internet.
 * La librairie ZipArchive est requise pour ce plugin.
 * @Author J.P. Pourrez <kazimentou@gmail.com>
 * @version 1.1.0
 * */
class kzUploader extends plxPlugin {

	private $__themesRoot = false; // initialisé par le hook 'AdminPrepend'
	private $__mime_types = false;

	const INPUT_NAME = 'kzUploader';
	const UPLOAD_ERRORS =
		'upload_err_ok upload_err_ini_size upload_err_form_size '.
		'upload_err_partial upload_err_no_file upload_err_no_tmp_dir '.
		'upload_err_cant_write upload_err_extension';

	public function __construct($default_lang) {

		$mime_types = array();
		if(class_exists('ZipArchive')) $mime_types[] = 'application/zip';
		if(class_exists('PharData')) $mime_types[] = 'application/gzip';

		if(!empty($mime_types)) {
			# Appel du constructeur de la classe plxPlugin (obligatoire)
			parent::__construct($default_lang);

			$this->setConfigProfil(PROFIL_ADMIN);

			# Accès au menu admin réservé au profil administrateur
			$this->setAdminProfil(PROFIL_ADMIN);

			# Ajouts des hooks
			$this->addHook('AdminSettingsPluginsFoot', 'AdminSettingsPluginsFoot');
			$this->addHook('AdminThemesDisplayFoot', 'AdminThemesDisplayFoot');
			$this->addHook('AdminFootEndBody', 'AdminFootEndBody');
			$this->addHook('AdminPrepend', 'AdminPrepend');
			$this->__mime_types = $mime_types;
		} else {
			plxMsg::Error($this->getLang('L_MISSING_LIBRARIES'));
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
			case 'plugin':	$label = $this->getLang('L_NEW_PLUGINS'); break;
			case 'thema':	$label = $this->getLang('L_NEW_THEMAS'); break;
			default:		$label = 'Unknown';
		}
		$limits = json_encode(
			array(
				'max_file_uploads'		=> $this->ini_get('max_file_uploads'),
				'post_max_size'			=> $this->ini_get('post_max_size'),
				'upload_max_filesize'	=> $this->ini_get('upload_max_filesize')
			)
		);
		# Pour télécharger un fichier la balise <form..> doit avoir un attribut enctype adéquat
?>
	<div class="<?php echo __CLASS__; ?>">
		<form method="post" enctype="multipart/form-data" id="form-<?php echo __CLASS__; ?>">
			<?php echo plxToken::getTokenPostMethod(); ?>
			<input type="hidden" name="zip" value="<?php echo $field; ?>">
			<label for="id_<?php echo $this::INPUT_NAME; ?>"><?php  echo $label; ?></label>
			<input
				type="file"
				name="<?php echo $this::INPUT_NAME; ?>[]"
				id="id_<?php echo $this::INPUT_NAME; ?>"
				accept="<?php echo implode(',', $this->__mime_types); ?>"
				multiple="multiple"
				required
			/>
			<input type="submit" value="<?php $this->lang('L_UPLOAD'); ?>"/>
		</form>
	</div>
	<script type="text/javascript">
		(function() { /* ------- kzUploader plugin ------- */
			 // Prévient des limites imposées par PHP sur le serveur

			'use strict';

			const limits = <?php echo $limits; ?>;
			const messages = {
				max_file_uploads:		'<?php echo addslashes($this->getLang('JS_MAX_FILE_UPLOADS')); ?>',
				post_max_size:			'<?php echo addslashes($this->getLang('JS_POST_MAX_SIZE')); ?>',
				upload_max_filesize:	'<?php echo addslashes($this->getLang('JS_UPLOAD_MAX_FILESIZE')); ?>'
			};
			const form = document.getElementById('form-<?php echo __CLASS__; ?>');

			form.addEventListener('submit', function(event) {
				var	files = form.querySelector('input[type="file"]').files,
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
					alert('<?php $this->lang('JS_LIMITS_UPLOAD'); ?>:\n\n' + msg);
					return false;
				}
			});
		})();
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

	private function __is_writable_folder() {
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

	/**
	 * Affiche un panneau si des erreurs se sont produites avec le Post.
	 * */
	public function AdminFootEndBody() {
		// PlxMsg::Error gère mal les erreurs multiples

		if(!empty($_SESSION[__CLASS__.'_errors'])) {
			$errors = unserialize($_SESSION[__CLASS__.'_errors']);
?>
		<div class="<?php echo __CLASS__; ?>-error visible">
			<div>
<?php
			foreach($errors as $filename=>$message) {
?>
				<p class="filename"><?php echo $filename; ?> :</p>
				<p><?php echo $message; ?></p>
<?php
			}
?>
			<p><input type="button" value="Close" /></p>
			</div>
		</div>
		<script type="text/javascript">
			document.querySelector('.<?php echo __CLASS__; ?>-error input[type="button"]').addEventListener('click', function(event) {
				document.querySelector('.<?php echo __CLASS__; ?>-error.visible').classList.remove('visible');
			});
		</script>
<?php
			unset($_SESSION[__CLASS__.'_errors']);
		}
	}

	/**
	 * Traite les fichiers envoyés depuis le formulaire.
	 * */
	public function AdminPrepend() {
		global $plxAdmin, $lang;

		$this->__themesRoot = PLX_ROOT.$plxAdmin->aConf['racine_themes'];

		if(!empty($_SERVER['CONTENT_LENGTH'])) {
			$post_max_size = $this->ini_get('post_max_size');
			if($_SERVER['CONTENT_LENGTH'] > $post_max_size) {
				plxMsg::error(sprintf($this->getLang('L_POST_MAX_SIZE'), ($post_max_size / 1024)));
			} elseif(
				!empty($_FILES[$this::INPUT_NAME])  and
				$this->__is_writable_folder()
			) {

				# On finit l'exécution de prepend.php pour installer les phrases dans la langue choisie
				global $plxAdmin, $lang;

				loadLang(PLX_CORE.'lang/'.$lang.'/admin.php');
				loadLang(PLX_CORE.'lang/'.$lang.'/core.php');
				$_SESSION['admin_lang'] = $lang;

				# Control du token du formulaire
				plxToken::validateFormToken($_POST);

				# Control de l'accès à la page en fonction du profil de l'utilisateur connecté
				$plxAdmin->checkProfil(PROFIL_ADMIN);

				$tmpDir = '';
				switch($_POST['zip']) {
					case 'plugin': $tmpDir = PLX_PLUGINS; break;
					case 'thema':	$tmpDir = $this->__themesRoot; break;
				}
				$tmpDir .= __CLASS__.'.XXXXX';
				$errors = array();
				# on boucle sur les fichiers
				for($i=0, $iMax = count($_FILES[$this::INPUT_NAME]['name']); $i <$iMax; $i++) {
					$genuine_name = $_FILES[$this::INPUT_NAME]['name'][$i]; // uniquement pour gérer les erreurs
					if(
						($_FILES[$this::INPUT_NAME]['error'][$i] == UPLOAD_ERR_OK) and
						($_FILES[$this::INPUT_NAME]['size'][$i] > 0)  and
						in_array($_FILES[$this::INPUT_NAME]['type'][$i], $this->__mime_types) and
						!empty($_FILES[$this::INPUT_NAME]['tmp_name'][$i])
					) {
						if(is_dir($tmpDir)) {
							$this->__rmDir($tmpDir);
						}
						mkdir($tmpDir, 0750);

						$filename = $_FILES[$this::INPUT_NAME]['tmp_name'][$i];
						try {
							// Dépliage de l'archive
							switch($_FILES[$this::INPUT_NAME]['type'][$i]) {
								case 'application/zip':
									$zip = new ZipArchive();
									if($zip->open($filename) === true) {
										$zip->extractTo($tmpDir);
									}
									break;
								case 'application/gzip':
									// PharData a besoin d'une extension de fichier correcte
									if(empty(pathinfo($filename, PATHINFO_EXTENSION))) {
										$extension = preg_replace('@^.*(.tar.gz|.tar.bz2|.tar)$@i', '$1', $_FILES[$this::INPUT_NAME]['name'][$i]);
										rename($filename, $filename.$extension);
										$filename .= $extension;
									}
									$phar = new PharData($filename);
									if($phar !== false) {
										$phar->extractTo($tmpDir);
									}
									break;
								default:
									$errors[$genuine_name] = 'Bad mime-type'; // En théorie, cette erreur n'arrivera jamais
							}

							// On commence le traitement de l'archive dépliée
							$folders = glob($tmpDir.'/*', GLOB_ONLYDIR);
							if(count($folders) == 1) {
								if(file_exists($folders[0].'/infos.xml')) {
									$folder = $folders[0];
									switch($_POST['zip']) {
										case 'plugin':
											# On renommera le dossier pour les archives issues de Github
											$target = PLX_PLUGINS.preg_replace('@^([a-z_]\w*).*@i', '\1', basename($folder));
											$className = basename($target);
											$script_name = $folders[0]."/$className.php";
											if(
												file_exists($script_name) and
												(count(preg_grep('@\bclass\s+'.$className.'\s+extends\s+plxPlugin\b@', file($script_name))) == 1)
											) {
												if(!is_dir($target)) {
													rename($folder, $target);
												} else {
													$errors[$genuine_name] = $this->getLang('L_PLUGIN_ALREADY_EXISTS');
												}
											} else {
												$errors[$genuine_name] = $this->getLang('L_INVALIDATE_PLUGIN');
											}
											break;
										case 'thema':
											$target = $this->__themesRoot.basename($folders[0]);
											if(
												file_exists($folders[0].'/home.php') and
												is_dir($folders[0].'/lang')
											) {
												if(!is_dir($target)) {
													rename($folders[0], $target);
													$plxAdmin->editConfiguration($plxAdmin->aConf, array('style' => basename($folder)));
												} else {
													$errors[$genuine_name] = $this->getLang('L_THEMA_ALREADY_EXISTS');
												}
											} else {
												$errors[$genuine_name] = $this->getLang('L_INVALIDATE_THEMA');
											}
											break;
									}
								} else {
									$errors[$genuine_name] = $this->getLang('L_MISSING_INFOS_FILE');
								}
							} else {
								$errors[$genuine_name] = $this->getLang('L_NOT_JUST_ONE_FOLDER');
							}

						} catch(Exception $e) {
							$errors[$genuine_name] = $e->getMessage();
						} finally {
							if(isset($zip)) {
								$zip->close();
							}
							$this->__rmDir($tmpDir);
							if(file_exists($filename)) {
								unlink($filename);
							}
						}
					} else {
						// mauvais fichier
						if($_FILES[$this::INPUT_NAME]['error'][$i] != UPLOAD_ERR_OK) {
							$error_code = $_FILES[$this::INPUT_NAME]['error'][$i];
							$errors[$genuine_name] = ucfirst(explode(' ', $this::UPLOAD_ERRORS)[$error_code]);
						} elseif($_FILES[$this::INPUT_NAME]['size'][$i] == 0) {
							$errors[$genuine_name] = 'Empty file';
						} elseif(!in_array($_FILES[$this::INPUT_NAME]['type'][$i], $this->__mime_types)) {
							$errors[$genuine_name] = $this->getLang('L_BAD_TYPE_FILE').' '.$_FILES[$this::INPUT_NAME]['type'][$i];
						} else {
							$errors[$genuine_name] = 'No temporary file (stolen ?)';
						}
					}
				} // fin de boucle sur les fichiers

				if(!empty($errors)) {
					$_SESSION[__CLASS__.'_errors'] = serialize($errors);
				} else {
					PlxMsg::Info(sprintf($this->getLang('L_DOWNLOADED_PLUGINS'), count($_FILES[$this::INPUT_NAME]['name'])));
				}

				header('Location: '.$_SERVER['PHP_SELF']);
				exit;

			} // fin de if(!empty($_FILES['kzUploader'])...
		}
	}

}
?>