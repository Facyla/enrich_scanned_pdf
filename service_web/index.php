<?php
$debug = false; // @debug enable only for debug

// Définir le chemin absolu du script Bash à exécuter
$baseDir = dirname(__FILE__) . '/'; // Répertoire parent de celui où se trouve ce script PHP
$bashScript = $baseDir . '_scripts/Enrich_PDF.sh'; // Chemin absolu du script Bash


// Envoi du fichier généré si demandé (seulement depuis le dossier défini)
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



// Récupération des paramètres
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

// Add a PDF summary
$addSummary = 'yes';
if (isset($_REQUEST['add_summary']) && !empty($_REQUEST['add_summary'])) {
	$addSummary = strip_tags($_REQUEST['add_summary']);
	if (!empty($addSummary) && $addSummary != 'no') { $addSummary = 'yes'; } else { $addSummary = 'no'; }
}

// Use LLMs to improve text recognition
$useLLM = 'yes';
if (isset($_REQUEST['use_llm']) && !empty($_REQUEST['use_llm'])) {
	$useLLM = strip_tags($_REQUEST['use_llm']);
	if (!empty($useLLM) && $useLLM != 'no') { $useLLM = 'yes'; } else { $useLLM = 'no'; }
}



// Formulaire pour utilisation en ligne (GET car on veut une web-API)
$html = '';
$html .= '<html lang="fr">
<head>
	<title>' . "Service d'enrichissement de fichiers PDF scannés" . '</title>
	<meta name="description" value="Ajoute des données textuelles d\'accessibilité et d\'indexation à des fichiers PDF scannés" />
	<meta name="tags" value="PDF, PDF/UA, accessible, OCR, indexation, reconnaissance de caractères" />
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <!--
  <link rel="stylesheet" href="style.css">
  //-->
  <style>
  body { font-family: Arial; }
	pre { padding: .25rem .5rem; color: white; background: black; }
	code { padding: 0 .25rem; color: white; background: black; }
	form#enrich-pdf { border: 3px solid #AFA; background: #DFD; padding: .5rem 1rem; display: flex; flex-wrap: wrap; grid-gap: 1rem; }
	#enrich-pdf p { margin: .5rem 0 .5rem 0; }
	#enrich-pdf > div { display: inline-block; flex: 0 1 calc(100% / 3 - 2rem - 3 * 3px); margin: 0; padding: .5rem; border: 3px dotted #0F0; font-size: 1.2rem; }
	#enrich-pdf input, #enrich-pdf select { min-width: 20rem; }
	#enrich-pdf input[type="checkbox"], #enrich-pdf input[type="radio"] { min-width: initial; }
	#enrich-pdf input[type="file"] { display: inline-block; border: 3px dashed #333; margin: 1rem; padding: 1rem; background: #EAEAEA; width: calc(100% - 2rem); min-height: 5rem; }
	#enrich-pdf .clear-flex { border: 0; margin: 0; padding: 0; display: block; flex: 0 0 100%; }
	#enrich-pdf button { background: green; border: 3px outset green; border-radius: .5rem; color: white; font-weight: bold; padding: .5rem 1rem; font-size: 1.5rem; }
	#enrich-pdf button:hover { border-style: inset; }
	.link-download { border: 1px solid darkred; background: #C42D43; color: white; font-weight: bold; text-decoration: none; border-radius: .25rem; padding: .5rem 1rem; }
	</style>
</head>
<body>';
$html .= '<h2>Reconnaissance de caractères et enrichissement de documents PDF scannés pour les rendre accessibles et indexables</h2>';

$html .= '<h3>' . "Reconnaissance de caractères et enrichissement d'un PDF scanné" . '</h3>';

//$html .= '<form id="enrich-pdf" method="GET">';
$html .= '<form id="enrich-pdf" method="POST" enctype="multipart/form-data">';

$html .= '<input type="hidden" name="action" value="process" />
  <div><label>Choississez un fichier PDF <input type="file" name="uploaded_file" id="file" /></label><br /><em>Faites glisser le PDF à enrichir, ou cliquez pour le choisir parmi vos fichiers.</em></div>';

//$html .= '<div><label>Nom du fichier source <input type="text" name="input" placeholder="Nom_du_scan_PDF.pdf" value="' . $inputFile . '" /></label><br /><em>Si le fichier existe déjà dans le répertoire, indiquer son  (démo, envoi précédent)</em></div>

$html .= '<div><label>Langue pour l\'OCR <select type="text" name="ocr_lang" id="ocr_lang">
		<option value="fra" selected="selected">French</option>
		<option value="eng">English</option>
		<option value="rus">Russian</option>
		<option value="ara">Arabic</option>
		<option value="tha">Thai</option>
		<option value="deu">German</option>
		<option value="spa">Spanish; Castilian</option>
		<option value="ita">Italian</option>';
/*
$html .= '
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
$html .= '
  </select></label></div>';

/*
$html .= '<div><label>Nom du fichier enrichi <input type="text" name="output" placeholder="Page_de_garde_PDFUA.pdf" value="' . $outputFile . '"></label></div>';

//$html .= '<div><label>Chemin du dossier temporaire<input type="text" name="temp_path" value="" />' . $temp_path . '</label></div>';

if ($addSummary != 'no') {
	$html .= '<div><label>Générer un sommaire <input type="checkbox" name="add_summary" value="yes" checked="checked" /></label></div>';
} else {
	$html .= '<div><label>Générer un sommaire <input type="checkbox" name="add_summary" value="yes" /></label></div>';
}

if ($useLLM != 'no') {
	$html .= '<div><label>Utiliser un LLM pour corriger le texte océrisé <input type="checkbox" name="use_llm" id="use_llm" value="yes" checked="checked" /></label></div>';
} else {
	$html .= '<div><label>Utiliser un LLM pour corriger le texte océrisé <input type="checkbox" name="use_llm" id="use_llm" value="yes" /></label></div>';
}
*/

$html .= '<div class="clear-flex"></div>';
$html .= '<p><button type="submit">Envoyer</button></p>';
$html .= '</form>';




// Exécution de la commande - traitement du PDF
if ($action) {
	
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
	if (empty($inputFile)) { $can_process = false; if ($debug) echo "DEBUG : empty inputFile"; }
	
	// Vérification que le fichier existe, sinon on ne peut pas continuer
	if (!file_exists($inputFile_path)) { $can_process = false; if ($debug) echo "DEBUG : inputFile don't exist at $inputFile_path"; }
	
	if ($debug) echo "DEBUG : file sent and uploaded to $inputFile_path<br />";
	
	// Construction de la commande à exécuter - les paramètres doivent être dans l'ordre
	$command = sprintf(
		'bash %s %s %s %s %s',
		$bashScript,
		escapeshellarg($inputFile_path),
		escapeshellarg($OCRLanguage),
		escapeshellarg($outputFile_path),
		escapeshellarg($addSummary),
		escapeshellarg($useLLM)
	);


	// Debug only - sensitive information
	if ($debug) {
		$html .= '<h3>DEBUG</h3>';
		$html .= '<ul>';
			$html .= '<li>baseDir : <code>' . $baseDir . '</code></li>';
			$html .= '<li>bashScript : <code>' . $bashScript . '</code></li>';
			$html .= '<li>inputFile : <code>' . $inputFile . '</code></li>';
			$html .= '<li>inputFile_path : <code>' . $inputFile_path . '</code></li>';
			$html .= '<li>outputFile : <code>' . $outputFile . '</code></li>';
			$html .= '<li>outputFile_path : <code>' . $outputFile_path . '</code></li>';
			$html .= '<li>addSummary : <code>' . $addSummary . '</code></li>';
			$html .= '<li>useLLM : <code>' . $useLLM . '</code></li>';
			$html .= '<li>can_process : <code>' . $can_process . '</code></li>';
		$html .= '</ul>';
		$html .= '<p>Commande : <code>' . $command . '</code></p>';
	}

	if ($debug) echo "DEBUG : file sent and uploaded to $inputFile_path<br />";
  if ($can_process) {
  	if ($debug) echo "DEBUG : processing file $inputFile_path<br />";
		$result = enrichpdf_process($command, $inputFile_path, $outputFile_path, $baseDir, $OCRLanguage, $addSummary, $useLLM, $debug);
		if ($debug) { $html .= '<pre>' . print_r($result, true) . '</pre>'; }
		if ($result['status']) {
			$generated_file_name = basename($result['enriched_pdf_path']);
			$encodedFileName = urlencode(base64_encode($generated_file_name));
			
			$html .= '<h3>Résultat : fichier PDF accessible et indexable généré</h3>';
			$html .= '<a class="link-download" href="?serve_file=' . $encodedFileName . '" target="_blank">Télécharger le fichier ' . $generated_file_name . '</a>';
		} else {
			$html .= '<h3>Résultat : ECHEC</p>';
		}
		if (!empty(trim($result['message']))) {
			$html .= '<p>Message : ' . $result['message'] . '</p>';
		}
  }
}



$html .= '</body></html>';

echo $html;



/* 
 * string $command : system command to execute
 * string $inputFile : input file path
 * string $outputFile : output file path
 * string $baseDir : valid base directory from which serving files is allowed
 * string $OCRLanguage : language code used by Tesseract for OCR
 * string $addSummary : add a summary
 * string $useLLM : use a LLM
 */
function enrichpdf_process($command = '', $inputFile = '', $outputFile = '', $baseDir = '', $OCRLanguage = 'fra', $addSummary = true, $useLLM = true, $debug = false) {
	if (empty($command)) { return false; }
	if (empty($inputFile)) { return false; }
	
	if ($debug) { echo "DEBUG : $command<br />"; }
	
	$status = false; // Return status (true|false)
	$return = ''; // Return message
	$enriched_pdf_path = '';
	
	// Descripteurs pour les flux
	$descriptors = [
		0 => ["pipe", "r"], // stdin
		1 => ["pipe", "w"], // stdout
		2 => ["pipe", "w"], // stderr
	];


	$process = proc_open($command, $descriptors, $pipes);
	if (is_resource($process)) {
		if ($debug) { echo "DEBUG : ressource OK<br />"; }
		// Lire la sortie et les erreurs
		fclose($pipes[0]); // Close stdin
		$output = stream_get_contents($pipes[1]); // Capture stdout
		fclose($pipes[1]);
		$errorOutput = stream_get_contents($pipes[2]); // Capture stderr
		fclose($pipes[2]);
		// Fermeture du processus
		$returnCode = proc_close($process);
		
		if ($debug) {
			$return .= "Valeur de retour : <code>$returnCode</code><br />";
			if ($returnCode === 0) {
				$return .= "Sortie (stdout) : <pre>$output</pre>";
				$return .= "Fichier enrichi généré : <pre>$outputFile</pre>";
				if (file_exists($outputFile)) {
					// Encoder uniquement le nom du fichier pour la sécurité
					$encodedFileName = urlencode(base64_encode(basename($outputFile))); // Encode le nom du fichier pour plus de sécurité
					$return .= '<a href="?serve_file=' . $encodedFileName . '">Télécharger le fichier</a>';
				} else {
					$return .= '<p style="color:red;">Le fichier n\'existe pas ou n\'est pas accessible.</p>';
				}
			} else {
				$return .= "Erreur (stderr) : <code>$errorOutput</code><br />";
				$return .= "Erreur (stdout) : <pre>$output</pre>";
			}
		}
		// Lien de téléchargement
		if ($returnCode === 0) {
			if (file_exists($outputFile)) {
				$status = true;
			} else {
				$return .= '<p style="color:red;">Le fichier n\'existe pas ou n\'est pas accessible.</p>';
			}
		} else {
			$return .= '<p style="color:red;">La commande a échoué.</p>';
		}
	} else {
		$return .= "Impossible d'ouvrir le processus.";
	}
	
	// Return status as array
	return [
		'status' => $status, // true|false
		'enriched_pdf_path' => $outputFile, // string
		'message' => $return, // string
	];
}



/**
 * Sert un fichier généré de manière sécurisée.
 *
 * @param string $outputFile Chemin complet du fichier à servir.
 */
function enrichpdf_serve_generated_file($outputFile) {
	// Vérification supplémentaire avant d'envoyer le fichier
	if (!file_exists($outputFile)) {
		header("HTTP/1.1 404 Not Found");
		echo "Fichier introuvable.";
		return;
	}

	// Définir les en-têtes pour forcer le téléchargement
	$fileName = basename($outputFile);
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=\"" . addslashes($fileName) . "\"");
	header("Content-Length: " . filesize($outputFile));

	// Lire et envoyer le fichier
	readfile($outputFile);
	return;
}


// Get and clean the requests
function get_input($variable, $default = '', $filter = true) {
	if (!isset($_REQUEST[$variable])) return $default;
	if (is_array($_REQUEST[$variable])) {
		$result = $_REQUEST[$variable];
	} else {
		$result = trim($_REQUEST[$variable]);
	}
	if ($filter) {
		$result = get_input_filter($result);
	}
	return $result;
}

// Write file to disk
function write_file($path = "", $content = '') {
	if ($fp = fopen($path, 'w')) {
		fwrite($fp, $content);
		fclose($fp);
		return true;
	}
	return false;
}

function get_input_filter($input) {
	if (is_array($input)) {
		$input = array_map('get_input_filter', $input);
	} else {
		$input = strip_tags($input);
	}
	return $input;
}

/**
 * Gère le fichier téléchargé par l'utilisateur.
 *
 * @param string $basedir Chemin de base où les fichiers doivent être enregistrés.
 * @return string Target path (ie. corresponds to $inputFile_path)
 */
function enrichpdf_handle_uploaded_file($basedir = '', $debug = false) {
	if ($debug) echo "Handle File upload<br />";
	// Vérifiez si le dossier d'upload existe, sinon créez-le
	if (!is_dir($basedir)) {
		mkdir($basedir, 0750, true); // Création récursive avec permissions 755
	}
	if ($debug) echo " - dir OK<br />";

	// Vérifiez si un fichier a été envoyé
	if (!isset($_FILES['uploaded_file']) || empty($_FILES['uploaded_file']['tmp_name'])) {
		return false;
	}
	if ($debug) echo " - file OK<br />";

	$file = $_FILES['uploaded_file'];
	$file_name = basename($file['name']); // Récupère le nom du fichier
	$file_name = enrichpdf_sanitize_filename($file_name); // Nettoyer le nom du fichier
	$target_path = $basedir . $file_name; // Chemin complet pour l'enregistrement

	// Vérifications de base sur le fichier
	if ($file['error'] !== UPLOAD_ERR_OK) {
		error_log("DEBUG - file upload failed : Erreur lors de l envoi du fichier");
		return false;
	}

	// Vérifiez le type MIME (optionnel, pour plus de sécurité)
	//$allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
	$allowed_types = ['application/pdf'];
	if (!in_array($file['type'], $allowed_types)) {
		error_log("DEBUG - file upload : Type de fichier non autorisé. Types autorisés : PDF");
		return false;
	}

	// Vérification de la taille du fichier
	$max_size = 64 * 1024 * 1024; // 64 MB
	if ($file['size'] > $max_size) {
		error_log("DEBUG - file upload : Le fichier est trop volumineux. Taille maximale autorisée : 64MB");
		return false;
	}

	// Déplacez temporairement le fichier pour lire son contenu
	$temp_path = $file['tmp_name'];
	$file_content = file_get_contents($temp_path); // Lire le contenu du fichier
	if ($debug) echo " - content OK<br />";

	// Utilisez la fonction `write_file` pour sauvegarder le fichier
	if (write_file($target_path, $file_content)) {
		if ($debug) echo " - write OK : $target_path<br />";
		return $target_path;
	}
	return false;
}


/**
 * Nettoie un nom de fichier pour le rendre sûr et propre.
 *
 * @param string $filename Nom original du fichier.
 * @return string Nom nettoyé et sécurisé.
 */
function enrichpdf_sanitize_filename($filename) {
	// Remplacer les espaces par des underscores
	$filename = str_replace(' ', '_', $filename);

	// Remplacer les caractères accentués par leur équivalent non accentué
	$filename = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);

	// Remplacer les caractères non autorisés par des underscores
	$filename = preg_replace('/[^a-zA-Z0-9.\-_\[\]\(\)]/', '_', $filename);

	// S'assurer que le fichier ne commence pas ou ne se termine pas par un "."
	$filename = trim($filename, '.');
	
	// Ajoute un préfixe de date pour l'unicité des fichiers
	$date_prefix = date("Y-m-d-H-i-s") . '_';

	return $date_prefix . '_' . $filename;
}


/*
// Load a file content from an URL
function get_file_from_url($url) {
	// File retrieval can fail on timeout or redirects, so make it more failsafe
	$context = stream_context_create(array('http' => array('max_redirects' => 5, 'timeout' => 60)));
	// using timestamp and URL hash for quick retrieval based on time and URL source unicity
	return file_get_contents($url, false, $context);
}
*/



