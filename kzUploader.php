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

	private function __printForm($field) {
		switch($field) {
			case 'plugin':	$label = $this->getLang('L_NEW_PLUGIN'); break;
			case 'thema':	$label = $this->getLang('L_NEW_THEMA'); break;
			default:		$label = 'Unkown';
		}
	# Pour télécharger un fichier la balise <form..> doit avoir un attribut enctype adéquat
?>
	<div class="kzUploader">
		<form method="post" enctype="multipart/form-data">
			<?php echo plxToken::getTokenPostMethod(); ?>
			<input type="hidden" name="zip" value="<?php echo $field; ?>">
			<label for="id_kzUploader"><?php  echo $label; ?></label>
			<input type="file" name="kzUploader" id="id_kzUploader" accept="application/zip" required />
			<input type="submit" value="<?php $this->lang('L_UPLOAD'); ?>"/>
		</form>
	</div>
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

	public function AdminPrepend() {
		global $plxAdmin, $lang;

		$this->__themesRoot = PLX_ROOT.$plxAdmin->aConf['racine_themes'];

		if(
			!empty($_FILES['kzUploader']) and
			!empty($_FILES['kzUploader']['tmp_name']) and
			($_FILES['kzUploader']['size'] > 0)  and
			($_FILES['kzUploader']['type'] == 'application/zip') and
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

			$redirect = 'index';

			$zip = new ZipArchive();
			if($zip->open($_FILES['kzUploader']['tmp_name']) === true) {
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
					#$this->__rmDir($tmpDir);
				}
				$zip->close();
			}

			unlink($_FILES['kzUploader']['tmp_name']);

			header("Location: ${redirect}.php");
			exit;
		}

	}

}
?>