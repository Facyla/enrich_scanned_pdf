<?php
/**
 * Fonctions utilisées
 */



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
	$return = '';

	if ($debug) { $return .= "DEBUG : $command<br />"; }
	
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
		if ($debug) { $return .= "DEBUG : ressource OK<br />"; }
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
		return false;
	}

	// Définir les en-têtes pour forcer le téléchargement
	$fileName = basename($outputFile);
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=\"" . addslashes($fileName) . "\"");
	header("Content-Length: " . filesize($outputFile));

	// Lire et envoyer le fichier
	if (readfile($outputFile)) { return true; }
	return false;
}


// Envoi du fichier généré si demandé (seulement depuis le dossier défini)
function enrichpdf_serve_file_if_request($baseDir = false) {
	if (empty($baseDir)) { return false; }
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
				return true;
			} else {
				// Gestion des erreurs en cas de tentative d'accès non autorisé ou fichier inexistant
				header("HTTP/1.1 404 Not Found");
				echo "Fichier introuvable ou accès interdit.";
			}
		}
	}
	return false;
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
	//if ($debug) echo "Handle File upload<br />";
	// Vérifiez si le dossier d'upload existe, sinon créez-le
	if (!is_dir($basedir)) {
		mkdir($basedir, 0750, true); // Création récursive avec permissions 755
	}
	//if ($debug) echo " - dir OK<br />";

	// Vérifiez si un fichier a été envoyé
	if (!isset($_FILES['uploaded_file']) || empty($_FILES['uploaded_file']['tmp_name'])) {
		return false;
	}
	//if ($debug) echo " - file OK<br />";

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
	//if ($debug) echo " - content OK<br />";

	// Utilisez la fonction `write_file` pour sauvegarder le fichier
	if (write_file($target_path, $file_content)) {
		//if ($debug) echo " - write OK : $target_path<br />";
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

// Execute a shell command, and return its outputs as array
function accessible_documents_proc_open($command = '') {
	$return = [
		'command' => $command,
		'return_code' => 1,
		'stdin' => null,
		'stdout' => null,
		'stderr' => null,
	];

	// Descripteurs pour les flux
	$descriptors = [
		0 => ["pipe", "r"], // stdin
		1 => ["pipe", "w"], // stdout
		2 => ["pipe", "w"], // stderr
	];

	$process = proc_open($command, $descriptors, $pipes);
	if (is_resource($process)) {
		// Lecture de la sortie et des erreurs
		$stdin = stream_get_contents($pipes[0]); // Capture stdin
		fclose($pipes[0]); // Close stdin
		$stdout = stream_get_contents($pipes[1]); // Capture stdout
		fclose($pipes[1]);
		$stderr = stream_get_contents($pipes[2]); // Capture stderr
		fclose($pipes[2]);

		// Fermeture du processus
		$return_code = proc_close($process);

		$return['return_code'] = $return_code;
		$return['stdin'] = $stdin;
		$return['stdout'] = $stdout;
		$return['stdin'] = $stderr;
	}

	return $return;
}

// Wrapper for easier use of accessible_documents_proc_open
function accessible_documents_proc_open_return($command = '') {
	$return = accessible_documents_proc_open($command);
	echo print_r($return, true);
	if ($return['return_code'] === 0) {
		return $return['stdout'];
	}
	return false;
}


