#!/bin/bash

# Variables
## input file path
PDF_FILE_PATH="$1"
## tesseract OCR language
LANGUAGE="${2:-fra}"  # Si aucune langue n'est fournie, utiliser 'fra' par défaut
## output file path
UNUSED_OUTPUT="$3"
## add a summary
UNUSED_ADD_SUMMARY="$4"
## use LLM to improve OCR-ed text
UNUSED_USE_LLM="$5"

# Extraire le répertoire et le nom de base du fichier
DIR_NAME=$(dirname "$PDF_FILE_PATH")
EXTENSION="${PDF_FILE_PATH##*.}"
BASE_NAME=$(basename "$PDF_FILE_PATH" ."$EXTENSION")  # Le nom de base sans l'extension
PDF_NAME="$BASE_NAME.$EXTENSION"

# Mettre à jour les noms de fichiers basés sur le nom de base
output_pdf="${DIR_NAME}/${BASE_NAME}_PDFUA.pdf"
ocr_text="${DIR_NAME}/tmp_${BASE_NAME}_OCR_text.txt"
temp_pdf="${DIR_NAME}/tmp_${BASE_NAME}_temp.pdf"

# Vérification de l'entrée
if [[ ! -f "$PDF_FILE_PATH" ]]; then
  echo "Erreur : Le fichier PDF spécifié n'existe pas : $PDF_FILE_PATH"
  exit 1
fi

# Étape 1 : Convertir le PDF en images (une image par page)
IMAGE_BASE_NAME="tmp_${BASE_NAME}_image"
# Changer de répertoire pour éviter de passer des chemins complets
cd "$DIR_NAME" || { echo "Erreur : Impossible de se déplacer dans le répertoire $DIR_NAME"; exit 1; }

# Convertir le PDF en images avec un chemin relatif (en utilisant uniquement le nom de fichier sans le chemin)
pdftoppm -png "${PDF_NAME}" "${IMAGE_BASE_NAME}"
if [[ $? -ne 0 ]]; then
  echo "Erreur : La conversion du PDF en images a échoué."
  echo "La cause probable est soit un problème de chemin, soit un problème de droits d'écriture pour l'utilisateur du serveur web."
	echo "# Conversion du PDF en images :"
	echo "DIR_NAME ${DIR_NAME}"
	echo "BASENAME ${BASE_NAME}"
	echo "EXTENSION ${EXTENSION}"
	echo "PDF_NAME ${PDF_NAME}"
	echo "pdftoppm -png ${PDF_NAME} IMAGE_BASE_NAME"
	echo "Current path :" `pwd`
  exit 1
fi

# Étape 2 : Effectuer l'OCR sur les pages (en utilisant le français)
echo "# Exécution de l'OCR sur chaque page (en français) :"
page_number=1  # Initialisation du numéro de page
for img in "${IMAGE_BASE_NAME}"*.png; do
  # Nom du fichier texte OCR
  output_text="tmp_${BASE_NAME}_text_${page_number}.txt"

  # Exécution de Tesseract
  tesseract "$img" "${output_text%.txt}" -l "$LANGUAGE" txt
  if [[ $? -ne 0 ]]; then
    echo "Erreur : L'OCR pour l'image $img a échoué."
    echo tesseract "$img" "${output_text%.txt}" -l "$LANGUAGE" txt
    exit 1
  fi

  # Incrémenter le numéro de page
  page_number=$((page_number + 1))
done

# Étape 3 : Générer un fichier OCR final
echo "# Génération du fichier texte OCR :"
cat "tmp_${BASE_NAME}_text_"*.txt > "$ocr_text"
if [[ $? -ne 0 ]]; then
  echo "Erreur : La génération du fichier texte OCR a échoué."
  exit 1
fi

# Étape 4 : Créer un PDF avec le texte OCR intégré (overlay) tout en conservant les images
echo "# Création du PDF OCR :"
# Note : si la page contient déjà du texte, la comande plante : PriorOcrFoundError: page already has text! - aborting (use --force-ocr to force OCR;  see also help for the arguments --skip-text and --redo-ocr
ocrmypdf "$(basename "$PDF_FILE_PATH")" "$temp_pdf" --force-ocr
if [[ $? -ne 0 ]]; then
  echo "Erreur : La création du PDF avec OCR a échoué."
  exit 1
fi

# Vérification que le fichier PDF a bien été généré
if [[ ! -f "$temp_pdf" ]]; then
  echo "Erreur : Le fichier PDF de sortie n'a pas été généré."
  exit 1
fi

# Étape 5 : Ajouter un sommaire (si le PDF a bien été généré)
echo "# Ajout du sommaire :"
# Compter le nombre de pages dans le PDF temporaire
page_count=$(pdftk "$temp_pdf" dump_data | grep NumberOfPages | awk '{print $2}')
if [[ -z "$page_count" ]]; then
  echo "Erreur : Impossible de compter les pages dans $temp_pdf."
  exit 1
fi
# Créer une métadonnée pour chaque page
metadata="tmp_${BASE_NAME}_metadata.txt"
echo "InfoBegin" > "$metadata"
echo "InfoKey: Title" >> "$metadata"
echo "InfoValue: Sommaire" >> "$metadata"
for ((i = 1; i <= page_count; i++)); do
  echo "BookmarkBegin" >> "$metadata"
  echo "BookmarkTitle: Page $i" >> "$metadata"
  echo "BookmarkLevel: 1" >> "$metadata"
  echo "BookmarkPageNumber: $i" >> "$metadata"
done

# Appliquer les métadonnées au fichier temporaire
pdftk "$temp_pdf" update_info "$metadata" output "$output_pdf"
if [[ $? -ne 0 ]]; then
  echo "Erreur : L'ajout du sommaire avec pdftk a échoué."
  rm -f "$metadata"
  exit 1
fi

# Nettoyer le fichier temporaire des métadonnées
rm -f "$metadata"

# Étape 6 : Validation du PDF/UA (si nécessaire, utiliser PAC pour la validation manuelle)
# Vérification du contenu du PDF généré (extraction et affichage du texte OCR)
echo "# Vérification de la conformité PDF/UA :"
echo "Vérification du texte dans le fichier PDF généré (page par page) :"
for ((i=1; i<=page_count; i++)); do
  # Extraire le texte de chaque page du PDF
  page_text=$(pdftotext -f $i -l $i "$temp_pdf" -)
  if [[ $? -ne 0 ]]; then
    echo "Erreur : Impossible d'extraire le texte de la page $i."
    exit 1
  fi
  # Afficher le texte extrait pour cette page
  echo -e "\n--- Page $i ---"
  echo "$page_text"
done

echo " "
echo "Opération terminée avec succès : "
echo " - Fichier PDF/UA  accessible et indexable : $output_pdf"

