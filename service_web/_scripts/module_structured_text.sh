#!/bin/bash

# Objectif : conversion et export PDF to MarkDown


# Variables
## input file path
PDF_FILE_PATH="$1"
HASH_ID="$2"
#HASH_ID=$(sha1sum "$PDF_FILE_PATH" | awk '{print $1}')
# LANGUAGE="${2:-fra}"  # Si aucune langue n'est fournie, utiliser 'fra' par défaut

# Current folder : FOLDER_PATH=$(dirname "$file_path")

# Vérification de l'entrée
if [[ ! -f "$PDF_FILE_PATH" ]]; then
  echo "Erreur : Le fichier PDF spécifié n'existe pas : $PDF_FILE_PATH"
  exit 1
fi

echo "TRAITEMENT du fichier $PDF_FILE_PATH"


# Vérification et Installation environnement (1 seule fois)
# Chargement environnement python
SCRIPT_DIR=$(dirname "$0")
echo "- script dir : $SCRIPT_DIR"
cd "$SCRIPT_DIR"
if [ -d "./env" ]; then
  echo "Le dossier 'env' existe."
else
  echo "Le dossier 'env' n'existe pas : création."
  python3 -m venv env
fi
echo ""
#ls -alh
#source /env/bin/activate

#python3 "$PDF_FILE_PATH" "$HASH_ID"
echo "commande : python3 \"$SCRIPT_DIR/pyu4llm.py\" \"$PDF_FILE_PATH\" \"$HASH_ID\""
python_return=$(python3 "$SCRIPT_DIR/pyu4llm.py" "$PDF_FILE_PATH" "$HASH_ID")
echo "Retour de la commande python : "
echo "$python_return"
exit

# Extraction du nom de base du fichier sans extension
SOURCE_NAME=$(basename "$PDF_FILE_PATH")
SOURCE_BASE_NAME="${SOURCE_NAME%.md}"

# Calcul du dossier de sortie en fonction du hash du fichier
# si on veut calculer le chemin complet à partir d'un relatif : full_path=$(realpath "$relative_path")
# Compute the base path
BASE_PATH=$(dirname "$PDF_FILE_PATH")
echo " - Base path: $BASE_PATH"
# Get up 1 level
DATA_PATH=$(dirname "$BASE_PATH")
echo " - Data path : $DATA_PATH"
# Add "output/dir/" to the path
OUTPUT_PATH="$OUTPUT_PATH/output/${HASH_ID:-$SOURCE_BASE_NAME}/"
#OUTPUT_PATH="$DATA_PATH/output/"
echo " - Output path : $OUTPUT_PATH"
echo ""
OUTPUT_BASE_NAME="${SOURCE_BASE_NAME}"

MD_FILE_PATH="${OUTPUT_PATH}${SOURCE_NAME}.md"
echo " - MD file path : $MD_FILE_PATH"


# Check if the folder exists, if not, create it
if [ ! -d "$OUTPUT_PATH" ]; then
  echo "Dossier de sortie inexistant, création :"
  mkdir -p "$OUTPUT_PATH"
  chmod -R 775 "$OUTPUT_PATH"
else
  echo "Le dossier de sortie existe."
fi

echo "Conversion de $MD_FILE_PATH dans plusieurs formats :"
echo "Les fichiers générés seront dans : $OUTPUT_PATH"

# 1. HTML
pandoc "$MD_FILE_PATH" -o "${OUTPUT_PATH}${OUTPUT_BASE_NAME}.html"
echo "HTML généré : ${OUTPUT_PATH}${OUTPUT_BASE_NAME}.html"

# 2. JSON (représentation syntaxique Pandoc)
pandoc "$MD_FILE_PATH" -t json -o "${OUTPUT_PATH}${OUTPUT_BASE_NAME}.json"
echo "JSON généré : ${OUTPUT_PATH}${OUTPUT_BASE_NAME}.json"

# 3. XML (utilisation du format JATS)
pandoc "$MD_FILE_PATH" -t jats -o "${OUTPUT_PATH}${OUTPUT_BASE_NAME}.xml"
echo "XML (JATS) généré : ${OUTPUT_PATH}${OUTPUT_BASE_NAME}.xml"

# 4. CSV (extraction simple des tableaux)
csv_file="${OUTPUT_PATH}${OUTPUT_BASE_NAME}.csv"
grep -A 1000 "^ *\\|" "$MD_FILE_PATH" | sed '/^ *$/q' > "$csv_file"
echo "CSV généré (simple extraction des tableaux) : $csv_file"

# 5. ODT (OpenDocument Text)
pandoc "$MD_FILE_PATH" -o "${OUTPUT_PATH}${OUTPUT_BASE_NAME}.odt"
echo "ODT généré : ${OUTPUT_PATH}${OUTPUT_BASE_NAME}.odt"

# 6. DOCX (Microsoft Word)
pandoc "$MD_FILE_PATH" -o "${OUTPUT_PATH}${OUTPUT_BASE_NAME}.docx"
echo "DOCX généré : ${OUTPUT_PATH}${OUTPUT_BASE_NAME}.docx"

# 7. ODS (OpenDocument Spreadsheet) via LibreOffice
libreoffice --headless --convert-to ods "${OUTPUT_PATH}${OUTPUT_BASE_NAME}.odt" --outdir "$OUTPUT_PATH"
echo "ODS généré : ${OUTPUT_PATH}${OUTPUT_BASE_NAME}.ods"

# 8. XLSX (Microsoft Excel) via LibreOffice
libreoffice --headless --convert-to xlsx "${OUTPUT_PATH}${OUTPUT_BASE_NAME}.odt" --outdir "$OUTPUT_PATH"
echo "XLSX généré : ${OUTPUT_PATH}${OUTPUT_BASE_NAME}.xlsx"

# 9. RTF (Rich Text Format)
pandoc "$MD_FILE_PATH" -o "${OUTPUT_PATH}${OUTPUT_BASE_NAME}.rtf"
echo "RTF généré : ${OUTPUT_PATH}${OUTPUT_BASE_NAME}.rtf"

echo "Conversion terminée. Les fichiers sont dans : $OUTPUT_PATH"
