# kzUploader

## Présentation

KzUploader est un plugin pour le CMS [PluXml](http://www.pluxml.org).

Il vous permet, via l'interface Web sur votre serveur distant, d'installer l'archive zip ou tar.gz d'un plugin stockée localement sur votre ordinateur. Il est accessible depuis le panneau de configuration des plugins non installés. Il suffit ensuite de l'activer le nouveau plugin pour le rendre opérationnel.

Il peut également être utilisé pour installer un nouveau thème à partir de l'interface des thèmes présents sur le serveur.

Il est possible d'installer plusieurs plugins ou archives à la fois. Prenez toute de même conscience des limites imposées par PHP sur votre serveur pour l'envoi d'un lot de fichiers.

Il évite de recourir à un client FTP et assure que les droits en écriture des dossiers soient correctement positionnés.

Certains contrôles sont faits avant d'installer un plugin ou un thème :

* un seul dossier à la racine de l'archive
* un fichier infos.xml à la racine de l'archive
* pour un plugin un script php avec le nom de la class du plugin contenant la déclaration de class, à la racine de l'archive
* pour un thème, un fichier home.php et un dossier lang à la racine de l'archive

Ce plugin est installé dans l'image de Docker.

If your usual language is missing in this plugin, send me this translation from english.