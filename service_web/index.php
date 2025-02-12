<?php
/**
 * Roadmap : un webservice modulaire pour améliorer l'accessibilité de document PDF "Image"
 * 
 * Structure : 
 * _scripts : scripts bash, pouvant être appelés avec des paramètres ou sans, et répondant à un besoin précis, ie. extraire le texte, analyser une image, etc.
 * _data : tous les fichiers : source, artefacts intermédiaires, sorties
 * 
 */

// Fonctions/classes utilisées 
require_once('functions.php');

$debug = false; // @debug enable only for debug

// Définir le chemin absolu du script Bash à exécuter
$baseDir = dirname(__FILE__) . '/'; // Répertoire parent de celui où se trouve ce script PHP

$path_scripts = $baseDir . '_scripts/';
$path_source = $baseDir . '_data/source/';
$path_temp = $baseDir . '_data/temp/';
$path_output = $baseDir . '_data/output/';

$module_ocr_script = $path_scripts . 'Enrich_PDF.sh'; // Chemin absolu du script Bash


/* Envoi du fichier généré si demandé (seulement depuis des emplacements autorisés) */
$served_file = enrichpdf_serve_file_if_request($baseDir);
/*
if (isset($_REQUEST['serve_file'])) {
	$serve_file = strip_tags(base64_decode(urldecode($_REQUEST['serve_file'])));
	$allowed_path = $baseDir . '_data/source/';
	if (!empty($serve_file)) {
		$serve_file = $allowed_path . $serve_file;
		// Vérification que le fichier se trouve bien dans le répertoire autorisé
		$realPath = realpath($serve_file); // Obtenir le chemin absolu du fichier demandé
		if ($realPath && strpos($realPath, realpath($allowed_path)) === 0 && file_exists($realPath)) {
			// Envoi du fichier via la fonction enrichpdf_serve_generated_file
			enrichpdf_serve_generated_file($realPath);
		} else {
			// Gestion des erreurs en cas de tentative d'accès non autorisé ou fichier inexistant
			header("HTTP/1.1 404 Not Found");
			echo "Fichier introuvable ou accès interdit.";
			exit;
		}
	}
}
*/



// # Récupération des paramètres du formulaire
$action = false;
if (isset($_REQUEST['action']) && !empty($_REQUEST['action'])) {
	$action = strip_tags($_REQUEST['action']);
	if ($action == 'process') { $action = true; } else { $action = false; }
}

// Input file name (we'll check and build path)
$inputFile = 'Page_de_garde_PDF.pdf';
if (isset($_REQUEST['input']) && !empty($_REQUEST['input'])) {
	$inputFile = strip_tags($_REQUEST['input']);
}
$inputFile_path = $baseDir . '_data/source/' . $inputFile;

// Output file name
$outputFile = str_replace('.pdf', '_PDFUA.pdf', $inputFile);
if (isset($_REQUEST['output']) && !empty($_REQUEST['output'])) {
	$outputFile = strip_tags($_REQUEST['output']);
}
$outputFile_path = $baseDir . '_data/source/' . $outputFile;

// Unused - rather for integration than user-definable parameter
/*
$temp_path = '';
if (isset($_REQUEST['input']) && !empty($_REQUEST['temp_path'])) {
	$temp_path = strip_tags($_REQUEST['temp_path']);
}
$temp_path = $baseDir . '_data/source/' . $temp_path;
*/

// Language of text to OCR
$OCRLanguage = 'fra';
if (isset($_REQUEST['ocr_lang']) && !empty($_REQUEST['ocr_lang'])) {
	$OCRLanguage = strip_tags($_REQUEST['ocr_lang']);
}

// MODULES

// Basic audit of PDF file
$module_audit = 'yes';
if (isset($_REQUEST['module_audit']) && !empty($_REQUEST['module_audit'])) {
	$module_audit = strip_tags($_REQUEST['module_audit']);
	if (!empty($module_audit) && $module_audit != 'no') { $module_audit = 'yes'; } else { $module_audit = 'no'; }
}

// Add OCR as overlay (raw text)
$module_ocr = 'yes';
if (isset($_REQUEST['module_ocr']) && !empty($_REQUEST['module_ocr'])) {
	$module_ocr = strip_tags($_REQUEST['module_ocr']);
	if (!empty($module_ocr) && $module_ocr != 'no') { $module_ocr = 'yes'; } else { $module_ocr = 'no'; }
}

// Add structured text
$module_struct_text = 'yes';
if (isset($_REQUEST['module_struct_text']) && !empty($_REQUEST['module_struct_text'])) {
	$module_struct_text = strip_tags($_REQUEST['module_struct_text']);
	if (!empty($module_struct_text) && $module_struct_text != 'no') { $module_struct_text = 'yes'; } else { $module_struct_text = 'no'; }
}

// Add table detection and export
$module_table = 'no';
if (isset($_REQUEST['module_table']) && !empty($_REQUEST['module_table'])) {
	$module_table = strip_tags($_REQUEST['module_table']);
	if (!empty($module_table) && $module_table != 'no') { $module_table = 'yes'; } else { $module_table = 'no'; }
}

// Add image description
$module_image = 'no';
if (isset($_REQUEST['module_image']) && !empty($_REQUEST['module_image'])) {
	$module_image = strip_tags($_REQUEST['module_image']);
	if (!empty($module_image) && $module_image != 'no') { $module_image = 'yes'; } else { $module_image = 'no'; }
}

// Add a summary
$module_summary = 'no';
if (isset($_REQUEST['module_summary']) && !empty($_REQUEST['module_summary'])) {
	$module_summary = strip_tags($_REQUEST['module_summary']);
	if (!empty($module_summary) && $module_summary != 'no') { $module_summary = 'yes'; } else { $module_summary = 'no'; }
}

// Add an abstract
$module_abstract = 'no';
if (isset($_REQUEST['module_abstract']) && !empty($_REQUEST['module_abstract'])) {
	$module_abstract = strip_tags($_REQUEST['module_abstract']);
	if (!empty($module_abstract) && $module_abstract != 'no') { $module_abstract = 'yes'; } else { $module_abstract = 'no'; }
}

// Export in multiple formats
$module_export = 'yes';
if (isset($_REQUEST['module_export']) && !empty($_REQUEST['module_export'])) {
	$module_export = strip_tags($_REQUEST['module_export']);
	if (!empty($module_export) && $module_export != 'no') { $module_export = 'yes'; } else { $module_export = 'no'; }
}




// Formulaire pour utilisation en ligne (GET car on veut une web-API)
$html = '';
$html .= '
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" lang="fr">
<title>' . "Service d'enrichissement et de mise en accessibilité de fichiers PDF image" . '</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta name="description" value="Ajoute des données textuelles d\'accessibilité et d\'indexation à des fichiers PDF scannés" />
	<meta name="tags" value="PDF, PDF/UA, accessible, OCR, indexation, reconnaissance de caractères" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">

	<meta name="mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=0.1, maximum-scale=10.0, user-scalable=1">
	
  <link rel="stylesheet" href="style.css">
</head>
<body>';
$html .= '<h2>Présentation du service</h2>';
$html .= "<p>Ce service web s'appuie sur différents composants open source pour fournir un service d'amélioration de l'accessibilité de documents PDF.</p>";
$html .= "<p>Il s'adresse aux utilisateurs de ces documents, et vise à pallier des manques de documents tels qu'ils sont reçus. Conçu pour rester d'un usage simple, il vous permet de charger un document PDF image, ou très peu accessible, afin d'en extraire des informations utiles pour la compréhension du contenu du document, d'enrichir le document initial avec ces données textuelles structurées, pour récupérer un fichier PDF/UA enrichi avec ces éléments.</p>";
$html .= "<p>Modulaire, il est conçu pour pouvoir permettre de réaliser différentes opérations selon le type de document source&nbsp;:</p>
<ul>
	<li>une analyse rapide qui permet d'identifier un niveau d'accessibilité élémentaire du document (PDF image, avec ou sans texte, avec ou sans structure, etc.),</li>
	<li>de l'extraction de texte à partir d'image (OCR), afin de pouvoir proposer une alternative textuelle,</li>
	<li>de générer des titres et de structurer son contenu</li>
	<li>d'ajouter des métadonnées nécessaire à un rendu correct du texte alternatif</li>
	<li>d'identifier et d'exporter des élements kdu document : images, blocs de textes, tableaux, etc.</li>
</ul>";
$html .= "<p>ATTENTION&nbsp;: Cette page est un prototype qui vise à valider le principe de fonctionnement de cette chaîne d'amélioration de l'accessibilité de PDF : certaines des fonctionnalités décrites peuvent être disponibles et opérationnelles, ou encore en projet.</p>";


// # Formulaire HTML
$html .= '<h2>' . "Utilisation du service" . '</h2>';

//$html .= '<form id="enrich-pdf" method="GET">';
$html_form = '';
$html_form .= '<form id="enrich-pdf" method="POST" enctype="multipart/form-data">';

$html_form .= '<input type="hidden" name="action" value="process" />
	<fieldset><legend>PDF source et options</legend>	
	
		<div><label>Choississez un fichier PDF à traiter <input type="file" name="uploaded_file" id="file" /></label><br /><em>Faites glisser le PDF à enrichir, ou cliquez pour le choisir parmi vos fichiers.</em></div>';

//$html .= '<div><label>Nom du fichier source <input type="text" name="input" placeholder="Nom_du_scan_PDF.pdf" value="' . $inputFile . '" /></label><br /><em>Si le fichier existe déjà dans le répertoire, indiquer son  (démo, envoi précédent)</em></div>

$html_form .= '<div><label>Choississez la langue pour la reconnaissance du texte <select type="text" name="ocr_lang" id="ocr_lang" value="' . $ocr_lang . '">
		<option value="fra">Français</option>
		<option value="eng">Englais</option>
		<option value="rus">Russe</option>
		<option value="ara">Arabe</option>
		<option value="tha">Thaï</option>
		<option value="deu">Allemand</option>
		<option value="spa">Espagnol (Castillan)</option>
		<option value="ita">Italien</option>';
/*
$html_form .= '
		<option value="afr">Afrikaans</option>
		<option value="amh">Amharic</option>
		<option value="asm">Assamese</option>
		<option value="aze">Azerbaijani</option>
		<option value="aze_cyrl">Azerbaijani - Cyrilic</option>
		<option value="bel">Belarusian</option>
		<option value="ben">Bengali</option>
		<option value="bod">Tibetan</option>
		<option value="bos">Bosnian</option>
		<option value="bre">Breton</option>
		<option value="bul">Bulgarian</option>
		<option value="cat">Catalan; Valencian</option>
		<option value="ceb">Cebuano</option>
		<option value="ces">Czech</option>
		<option value="chi_sim">Chinese - Simplified</option>
		<option value="chi_tra">Chinese - Traditional</option>
		<option value="chr">Cherokee</option>
		<option value="cos">Corsican</option>
		<option value="cym">Welsh</option>
		<option value="dan">Danish</option>
		<option value="dan_frak">Danish - Fraktur (contrib)</option>
		<option value="deu_frak">German - Fraktur (contrib)</option>
		<option value="deu_latf">German (Fraktur Latin)</option>
		<option value="dzo">Dzongkha</option>
		<option value="ell">Greek, Modern (1453-)</option>
		<option value="enm">English, Middle (1100-1500)</option>
		<option value="epo">Esperanto</option>
		<option value="equ">Math / equation detection module</option>
		<option value="est">Estonian</option>
		<option value="eus">Basque</option>
		<option value="fao">Faroese</option>
		<option value="fas">Persian</option>
		<option value="fil">Filipino (old - Tagalog)</option>
		<option value="fin">Finnish</option>
		<option value="frk">German - Fraktur (now deu_latf)</option>
		<option value="frm">French, Middle (ca.1400-1600)</option>
		<option value="fry">Western Frisian</option>
		<option value="gla">Scottish Gaelic</option>
		<option value="gle">Irish</option>
		<option value="glg">Galician</option>
		<option value="grc">Greek, Ancient (to 1453) (contrib)</option>
		<option value="guj">Gujarati</option>
		<option value="hat">Haitian; Haitian Creole</option>
		<option value="heb">Hebrew</option>
		<option value="hin">Hindi</option>
		<option value="hrv">Croatian</option>
		<option value="hun">Hungarian</option>
		<option value="hye">Armenian</option>
		<option value="iku">Inuktitut</option>
		<option value="ind">Indonesian</option>
		<option value="isl">Icelandic</option>
		<option value="ita_old">Italian - Old</option>
		<option value="jav">Javanese</option>
		<option value="jpn">Japanese</option>
		<option value="kan">Kannada</option>
		<option value="kat">Georgian</option>
		<option value="kat_old">Georgian - Old</option>
		<option value="kaz">Kazakh</option>
		<option value="khm">Central Khmer</option>
		<option value="kir">Kirghiz; Kyrgyz</option>
		<option value="kmr">Kurmanji (Kurdish - Latin Script)</option>
		<option value="kor">Korean</option>
		<option value="kor_vert">Korean (vertical)</option>
		<option value="kur">Kurdish (Arabic Script)</option>
		<option value="lao">Lao</option>
		<option value="lat">Latin</option>
		<option value="lav">Latvian</option>
		<option value="lit">Lithuanian</option>
		<option value="ltz">Luxembourgish</option>
		<option value="mal">Malayalam</option>
		<option value="mar">Marathi</option>
		<option value="mkd">Macedonian</option>
		<option value="mlt">Maltese</option>
		<option value="mon">Mongolian</option>
		<option value="mri">Maori</option>
		<option value="msa">Malay</option>
		<option value="mya">Burmese</option>
		<option value="nep">Nepali</option>
		<option value="nld">Dutch; Flemish</option>
		<option value="nor">Norwegian</option>
		<option value="oci">Occitan (post 1500)</option>
		<option value="ori">Oriya</option>
		<option value="osd">Orientation and script detection module</option>
		<option value="pan">Panjabi; Punjabi</option>
		<option value="pol">Polish</option>
		<option value="por">Portuguese</option>
		<option value="pus">Pushto; Pashto</option>
		<option value="que">Quechua</option>
		<option value="ron">Romanian; Moldavian; Moldovan</option>
		<option value="san">Sanskrit</option>
		<option value="sin">Sinhala; Sinhalese</option>
		<option value="slk">Slovak</option>
		<option value="slk_frak">Slovak - Fraktur (contrib)</option>
		<option value="slv">Slovenian</option>
		<option value="snd">Sindhi</option>
		<option value="spa_old">Spanish; Castilian - Old</option>
		<option value="sqi">Albanian</option>
		<option value="srp">Serbian</option>
		<option value="srp_latn">Serbian - Latin</option>
		<option value="sun">Sundanese</option>
		<option value="swa">Swahili</option>
		<option value="swe">Swedish</option>
		<option value="syr">Syriac</option>
		<option value="tam">Tamil</option>
		<option value="tat">Tatar</option>
		<option value="tel">Telugu</option>
		<option value="tgk">Tajik</option>
		<option value="tgl">Tagalog (new - Filipino)</option>
		<option value="tir">Tigrinya</option>
		<option value="ton">Tonga</option>
		<option value="tur">Turkish</option>
		<option value="uig">Uighur; Uyghur</option>
		<option value="ukr">Ukrainian</option>
		<option value="urd">Urdu</option>
		<option value="uzb">Uzbek</option>
		<option value="uzb_cyrl">Uzbek - Cyrilic</option>
		<option value="vie">Vietnamese</option>
		<option value="yid">Yiddish</option>
		<option value="yor">Yoruba</option>';
*/
$html_form .= '
  </select></label></div>';
$html_form .= '</fieldset>';	


$html_form .= '<fieldset><legend>Choix des opérations à effectuer sur ce document</legend>';

$html_form .= '<div><label>' . "Analyse niveau d'accessibilité du document source (à venir) " . '<select type="text" name="module_audit" id="module_audit" value="' . $module_audit . '">
	<option value="yes">Oui</option>
	<option value="no">Non</option>
	</select></label></div>';

	$html_form .= '<div><label>' . "Extraire le texte (opérationnel) " . '<select type="text" name="module_ocr" id="module_ocr" value="' . $module_ocr . '">
	<option value="yes">Oui</option>
	<option value="no">Non</option>
	</select></label></div>';

	$html_form .= '<div><label>Reconstruire du texte structuré (à venir) <select type="text" name="module_struct_text" id="module_struct_text" value="' . $module_struct_text . '">
	<option value="yes">Oui</option>
	<option value="no">Non</option>
	</select></label></div>';
	
	$html_form .= '<div><label>' . "Extraire les données d'un tableau (à venir) " . '<select type="text" name="module_table" id="module_table" value="' . $module_table . '">
	<option value="no">Non</option>
	<option value="yes">Oui</option>
	</select></label></div>';

	$html_form .= '<div><label>' . "Description d'une image (à venir) " . '<select type="text" name="module_image" id="module_image" value="' . $module_image . '">
	<option value="no">Non</option>
	<option value="yes">Oui</option>
	</select></label></div>';

	$html_form .= '<div><label>' . "Générer un sommaire (à venir) " . '<select type="text" name="module_summary" id="module_summary" value="' . $module_summary . '">
	<option value="no">Non</option>
	<option value="yes">Oui</option>
	</select></label></div>';

	$html_form .= '<div><label>' . "Générer un résumé (à venir) " . '<select type="text" name="module_abstract" id="module_abstract" value="' . $module_abstract . '">
	<option value="no">Non</option>
	<option value="yes">Oui</option>
	</select></label></div>';

	$html_form .= '<div><label>' . "Exporter dans divers formats nureaituqes (opérationnel) " . '<select type="text" name="module_export" id="module_export" value="' . $module_export . '">
	<option value="yes">Oui</option>
	<option value="no">Non</option>
	</select></label></div>';


$html_form .= '</fieldset>';	

$html_form .= '<div class="clear-flex"></div>';

$html_form .= '<p><button type="submit">Envoyer le PDF et réaliser les opérations</button></p>';

$html_form .= '</form>';

// Ajout au HTML global
$html .= $html_form;



/* Traitement du PDF : application des séries de manipulation et génération de sorties utiles */
if ($action) {
	$generation_log = ''; // Informations utiles au traitement du fichier (hors pur debug) : échec, motifs d'interruption, etc.

	// Traitement fichier envoyé ssi l'un est indiqué, sinon fallback sur le nom de fichier indiqué
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['uploaded_file']['name'])) {
		// Traitement du fichier envoyé
		$can_process = enrichpdf_handle_uploaded_file($baseDir . '_data/source/', $debug);
		if ($can_process) {
			$inputFile_path = $can_process;
			$inputFile = basename($inputFile_path);
			// Generate output file name and path
			$outputFile = str_replace('.pdf', '_PDFUA.pdf', $inputFile);
			$outputFile_path = $baseDir . '_data/source/' . $outputFile;
			$can_process = true;
		}
	}
	// Block processing if no file name and no file sent
	if (empty($inputFile)) { $can_process = false; $generation_log .= "ERREUR : fichier source vide<br />"; if ($debug) $html .= "DEBUG : empty inputFile"; }
	
	// Vérification que le fichier existe, sinon on ne peut pas continuer
	if (!file_exists($inputFile_path)) { $can_process = false; if ($debug) $html .= "DEBUG : inputFile don't exist at $inputFile_path"; }
	
	if ($debug) { $html .= "DEBUG : file sent and uploaded to $inputFile_path<br />"; }

	// @TODO Permettre de différer l'envoi du fichier et les traitements : pour cela on s'appuierait sur un hash du contenu, 
	// de manière à pouvoir effectuer des opérations sans charger de nouveaux fichiers
	$source_hash = hash_file('sha256', $inputFile_path);
	$generation_log .= "Hash du fichier source : $source_hash<br />";


	// @TODO : exécuter les modules selon les demandes

	// Audit rapide de l'accessibilité du fichier source
	if ($module_audit == 'yes') {
		$generation_log .= "Module activé : audit<br />";
		$command = sprintf(
			'bash %s %s',
			$path_scripts . 'audit_pdf.sh',
			escapeshellarg($inputFile_path),
		);
		$return = accessible_documents_proc_open($command);
		if ($return['return_code'] === 0) {
			$module_audit_html .= '<pre>' . htmlentities($return['stdout']) . '</pre>';
		} else {
			$module_audit_html .= '<pre>' . print_r($return, true) . '</pre>';
		}
		//$module_audit_html .= accessible_documents_proc_open_return($command);
	}

	// Océrisation simple
	if ($module_ocr == 'yes') {
		$generation_log .= "Module activé : OCR<br />";
		// Construction de la commande à exécuter - les paramètres doivent être dans l'ordre
		$command = sprintf(
			'bash %s %s %s %s %s',
			$module_ocr_script,
			escapeshellarg($inputFile_path),
			escapeshellarg($OCRLanguage),
			escapeshellarg($outputFile_path),
			escapeshellarg($module_summary),
			escapeshellarg($useLLM)
		);

		// Debug only - sensitive information
		if ($debug) {
			$html .= '<h3>DEBUG</h3>';
			$html .= '<ul>';
				$html .= '<li>baseDir : <code>' . $baseDir . '</code></li>';
				$html .= '<li>bashScript : <code>' . $module_ocr_script . '</code></li>';
				$html .= '<li>inputFile : <code>' . $inputFile . '</code></li>';
				$html .= '<li>inputFile_path : <code>' . $inputFile_path . '</code></li>';
				$html .= '<li>outputFile : <code>' . $outputFile . '</code></li>';
				$html .= '<li>outputFile_path : <code>' . $outputFile_path . '</code></li>';
				$html .= '<li>module_audit : <code>' . $module_audit . '</code></li>';
				$html .= '<li>module_ocr : <code>' . $module_ocr . '</code></li>';
				$html .= '<li>module_table : <code>' . $module_table . '</code></li>';
				$html .= '<li>module_image : <code>' . $module_image . '</code></li>';
				$html .= '<li>module_summary : <code>' . $module_summary . '</code></li>';
				$html .= '<li>module_abstract : <code>' . $module_abstract . '</code></li>';
				$html .= '<li>can_process : <code>' . $can_process . '</code></li>';
			$html .= '</ul>';
			$html .= '<p>Commande : <code>' . $command . '</code></p>';
		}

		/* @TODO use more modular code structure
		$return = accessible_documents_proc_open($command);
		if ($return['return_code'] === 0) {
			$generated_file_name = basename($result['enriched_pdf_path']);
			$encodedFileName = urlencode(base64_encode($generated_file_name));			
			$module_ocr_html .= 'Fichier PDF avec alternartive textuelle généré&nbsp;: ';
			$module_ocr_html .= '<a class="link-download" href="?serve_file=' . $encodedFileName . '" target="_blank">Télécharger le fichier ' . $generated_file_name . '</a>';
			// Debug data
			$module_audit_html .= '<pre class="toggable" style="display: none;">' . $return['stdout'] . '</pre>';
		} else {
			$module_audit_html .= '<pre>' . print_r($return, true) . '</pre>';
		}
		*/

		if ($debug) $module_ocr_html .= "DEBUG : file sent and uploaded to $inputFile_path<br />";
		if ($can_process) {
			if ($debug) $module_ocr_html .= "DEBUG : processing file $inputFile_path<br />";
			$result = enrichpdf_process($command, $inputFile_path, $outputFile_path, $baseDir, $OCRLanguage, $module_summary, $useLLM, $debug);
			if ($debug) { $module_ocr_html .= '<pre>' . print_r($result, true) . '</pre>'; }

			if ($result['status']) {
				$generated_file_name = basename($result['enriched_pdf_path']);
				$encodedFileName = urlencode(base64_encode($generated_file_name));
				
				$module_ocr_html .= 'Fichier PDF avec alternative textuelle généré&nbsp;: ';
				$module_ocr_html .= '<a class="link-download" href="?serve_file=' . $encodedFileName . '" target="_blank">Télécharger le fichier ' . $generated_file_name . '</a>';
			} else {
				$module_ocr_html .= 'ECHEC';
			}
			if (!empty(trim($result['message']))) {
				$module_ocr_html .= '<p>Message : ' . $result['message'] . '</p>';
			}
		}

	}
	
	if ($module_table == 'yes') {
		$generation_log .= "Module activé : Texte structuré<br />";

		/*
		$command = sprintf(
			'bash %s %s',
			$path_scripts . 'module_struct_text.sh',
			escapeshellarg($inputFile_path),
		);

		//$module_struct_text_html .= accessible_documents_proc_open_return($command);
		$return = accessible_documents_proc_open($command);
		if ($return['return_code'] === 0) {
			$module_struct_text_html .= '<pre>' . $return['stdout'] . '</pre>';
		} else {
			$module_struct_text_html .= '<pre>' . print_r($return, true) . '</pre>';
		}
		*/
	}

	// Extraction des tableaux
	if ($module_table == 'yes') {
		$generation_log .= "Module activé : Table<br />";
		/*
		$command = sprintf(
			'bash %s %s',
			$path_scripts . 'module_table.sh',
			escapeshellarg($inputFile_path),
		);

		//$module_table_html .= accessible_documents_proc_open_return($command);
		$return = accessible_documents_proc_open($command);
		if ($return['return_code'] === 0) {
			$module_table_html .= '<pre>' . $return['stdout'] . '</pre>';
		} else {
			$module_table_html .= '<pre>' . print_r($return, true) . '</pre>';
		}
		*/
	}

	// Traitement des images
	if ($module_image == 'yes') {
		$generation_log .= "Module activé : Image<br />";
		/*
		$command = sprintf(
			'bash %s %s',
			$path_scripts . 'module_image.sh',
			escapeshellarg($inputFile_path),
		);

		//$module_image_html .= accessible_documents_proc_open_return($command);
		$return = accessible_documents_proc_open($command);
		if ($return['return_code'] === 0) {
			$module_image_html .= '<pre>' . $return['stdout'] . '</pre>';
		} else {
			$module_image_html .= '<pre>' . print_r($return, true) . '</pre>';
		}
		*/

	}

	// Génération d'un sommaire
	if ($module_summary == 'yes') {
		$generation_log .= "Module activé : Sommaire<br />";
		/*
		$command = sprintf(
			'bash %s %s',
			$path_scripts . 'module_summary.sh',
			escapeshellarg($inputFile_path),
		);

		//$module_summary_html .= accessible_documents_proc_open_return($command);
		$return = accessible_documents_proc_open($command);
		if ($return['return_code'] === 0) {
			$module_summary_html .= '<pre>' . $return['stdout'] . '</pre>';
		} else {
			$module_summary_html .= '<pre>' . print_r($return, true) . '</pre>';
		}
		*/
	}

	// Génération d'un résumé
	if ($module_abstract == 'yes') {
		$generation_log .= "Module activé : Résumé<br />";
		/*
		$command = sprintf(
			'bash %s %s',
			$path_scripts . 'module_abstract.sh',
			escapeshellarg($inputFile_path),
		);

		//$module_abstract_html .= accessible_documents_proc_open_return($command);
		$return = accessible_documents_proc_open($command);
		if ($return['return_code'] === 0) {
			$module_abstract_html .= '<pre>' . $return['stdout'] . '</pre>';
		} else {
			$module_abstract_html .= '<pre>' . print_r($return, true) . '</pre>';
		}
		*/
	}

	// Génération d'un résumé
	if ($module_export == 'yes') {
		$generation_log .= "Module activé : Export<br />";
		$command = sprintf(
			'bash %s %s %s',
			$path_scripts . 'module_export.sh',
			escapeshellarg($inputFile_path),
			escapeshellarg($source_hash),
		);

		//$module_abstract_html .= accessible_documents_proc_open_return($command);
		$return = accessible_documents_proc_open($command);
		if ($return['return_code'] === 0) {
			$module_abstract_html .= '<pre>' . $return['stdout'] . '</pre>';
		} else {
			$module_abstract_html .= '<pre>' . print_r($return, true) . '</pre>';
		}
			/*
		*/
	}


	// @TODO à mieux structurer et mettre en forme
	// Ajout des résultats à la page
	$html .= '<h2>Résultats des opérations sur le fichier PDF</h2>';
	$html .= '<div class="generation-log"><h3>Informations techniques</h3>' . $generation_log . '</div>';

	if (!empty($module_audit_html)) {
		$html .= '<h3>Résultat du module : Audit du PDF</h3>';
		$html .= $module_audit_html;
	}

	if (!empty($module_ocr_html)) {
		$html .= '<h3>Résultat du module : OCR</h3>';
		$html .= $module_ocr_html;
	}

	if (!empty($module_image_html)) {
		$html .= '<h3>Résultat du module : Image</h3>';
		$html .= $module_image_html;
	}

	if (!empty($module_table_html)) {
		$html .= '<h3>Résultat du module : Table</h3>';
		$html .= $module_table_html;
	}

	if (!empty($module_summary_html)) {
		$html .= '<h3>Résultat du module : Sommaire</h3>';
		$html .= $module_summary_html;
	}

	if (!empty($module_abstract_html)) {	
		$html .= '<h3>Résultat du module : Résumé</h3>';
		$html .= $module_abstract_html;
	}

	if (!empty($module_export_html)) {	
		$html .= '<h3>Résultat du module : Exports</h3>';
		$html .= $module_export_html;
	}
}


$html .= '<br /><br /><br />';

$html .= '</body></html>';

echo $html;

