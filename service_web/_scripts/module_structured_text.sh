#!/bin/bash

# Objectif : conversion et export PDF to MarkDown


# Variables
## Chemin complet du fichier PDF (autre que image)
PDF_FILE_PATH="$1"
## Nom du sous-dossier créé dans _data/output/
#HASH_ID=$(sha1sum "$PDF_FILE_PATH" | awk '{print $1}')
HASH_ID="$2"
# Ex. bash /path/to/EnrichPDF/_scripts/module_structured_text.sh PDF_FILE HASH_ID
# Ex. bash /path/to/EnrichPDF/_scripts/module_structured_text.sh /path/to//EnrichPDF/_data/source/Fichier\ PDF.pdf some_id_of_name
# LANGUAGE="${2:-fra}"  # Si aucune langue n'est fournie, utiliser 'fra' par défaut

# Current folder : FOLDER_PATH=$(dirname "$file_path")

# Vérification de l'entrée
if [[ ! -f "$PDF_FILE_PATH" ]]; then
  echo "Erreur : Le fichier PDF spécifié n'existe pas : $PDF_FILE_PATH"
  exit 1
fi

echo "TRAITEMENT du fichier $PDF_FILE_PATH"


SCRIPT_DIR=$(dirname "$0")
BASE_DIR=$(dirname "$SCRIPT_DIR")


# Extraction du nom de base du fichier sans extension
SOURCE_NAME=$(basename "$PDF_FILE_PATH")
SOURCE_BASE_NAME="${SOURCE_NAME%.pdf}"

# Calcul du dossier de sortie en fonction du hash du fichier
# si on veut calculer le chemin complet à partir d'un relatif : full_path=$(realpath "$relative_path")
# Compute the base path
BASE_PATH=$(dirname "$PDF_FILE_PATH")
echo " - Base path: $BASE_PATH"
# Get up 1 level
DATA_PATH=$(dirname "$BASE_PATH")
echo " - Data path : $DATA_PATH"
# Add "output/dir/" to the path
OUTPUT_PATH="$DATA_PATH/output/${HASH_ID:-$SOURCE_BASE_NAME}/"
#OUTPUT_PATH="$DATA_PATH/output/"
echo " - Output path : $OUTPUT_PATH"
OUTPUT_BASE_NAME="${SOURCE_BASE_NAME}"

MD_FILE_PATH="${OUTPUT_PATH}${SOURCE_BASE_NAME}.md"
echo " - chemin du fichier MarkDown : $MD_FILE_PATH"
echo ""


# Check if the folder exists, if not, create it
if [ ! -d "$OUTPUT_PATH" ]; then
  echo "Dossier de sortie inexistant, création :"
  mkdir -p "$OUTPUT_PATH"
  chmod -R 775 "$OUTPUT_PATH"
else
  echo "Le dossier de sortie existe."
fi
echo ""


echo ""
echo "# ETAPE 1 : Extraction des données et analyse pour générer un fichier MarkDown :"

# Vérification et Installation environnement (1 seule fois)
# Chargement environnement python
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
#/var/www/start.linagora.com/EnrichPDF/_scripts/service_web/_data/output/bash_struct_text
#/var/www/start.linagora.com/EnrichPDF/_scripts/_data/output/bash_struct_text

echo "BASE DIR : $BASE_DIR"
echo "Output param : ${BASE_DIR}/_data/output/${HASH_ID}"

# @TODO : la commande python devrait prendre uniquement des chemins absolus, 
# de sorte à permettre de gérer les emplacements côté stack web de manière homogène
echo "commande : python3 \"$SCRIPT_DIR/pyu4llm.py\" \"$PDF_FILE_PATH\" \"${BASE_DIR}/_data/output/${HASH_ID}\""
python_return=$(python3 "$SCRIPT_DIR/pyu4llm.py" "$PDF_FILE_PATH" "${BASE_DIR}/_data/output/${HASH_ID}")
echo "Retour de la commande python : "
echo "$python_return"



echo ""
echo "# ETAPE 2 : Conversion du fichier MD généré dans divers formats :"

echo "Conversion de $MD_FILE_PATH dans plusieurs formats :"
echo "Les fichiers générés seront dans : $OUTPUT_PATH"
echo ""

# 1. HTML
temp_html="${OUTPUT_PATH}${OUTPUT_BASE_NAME}.html"
pandoc "$MD_FILE_PATH" -o "$temp_html" && echo " - HTML généré : $temp_html.html"

# 2. JSON (représentation syntaxique Pandoc)
pandoc "$MD_FILE_PATH" -t json -o "${OUTPUT_PATH}${OUTPUT_BASE_NAME}.json" && echo " - JSON généré : ${OUTPUT_PATH}${OUTPUT_BASE_NAME}.json"

# 3. ODT (OpenDocument Text)
pandoc "$MD_FILE_PATH" -o "${OUTPUT_PATH}${OUTPUT_BASE_NAME}.odt" && echo " - ODT généré : ${OUTPUT_PATH}${OUTPUT_BASE_NAME}.odt"

# 4. DOCX (Microsoft Word)
pandoc "$MD_FILE_PATH" -o "${OUTPUT_PATH}${OUTPUT_BASE_NAME}.docx" && echo " - DOCX généré : ${OUTPUT_PATH}${OUTPUT_BASE_NAME}.docx"

# 5. ODS (OpenDocument Spreadsheet) : Conversion du fichier HTML en ODS via LibreOffice
libreoffice --calc --headless --convert-to ods "$temp_html" --outdir "$OUTPUT_PATH" && echo " - ODS généré : ${OUTPUT_PATH}${OUTPUT_BASE_NAME}.ods"

# 6. XSLX (Microsoft Excel) : Conversion du fichier HTML en XLSX via LibreOffice
libreoffice --calc --headless --convert-to xlsx "$temp_html" --outdir "$OUTPUT_PATH" && echo " - XLSX généré : ${OUTPUT_PATH}${OUTPUT_BASE_NAME}.xlsx"

# 7. CSV (extraction simple des tableaux)
# @TODO non opérationnel : Conversion du HTML en CSV via LibreOffice ?
#csv_file="${OUTPUT_PATH}${OUTPUT_BASE_NAME}.csv"
#grep -A 1000 "^ *\\|" "$MD_FILE_PATH" | sed '/^ *$/q' > "$csv_file" && echo " - CSV généré (extraction des tableaux) : $csv_file"

# 8. XML (utilisation du format JATS)
pandoc "$MD_FILE_PATH" -t jats -o "${OUTPUT_PATH}${OUTPUT_BASE_NAME}.xml" && echo " - XML (JATS) généré : ${OUTPUT_PATH}${OUTPUT_BASE_NAME}.xml"

# 9. RTF (Rich Text Format)
pandoc "$MD_FILE_PATH" -o "${OUTPUT_PATH}${OUTPUT_BASE_NAME}.rtf" && echo " - RTF généré : ${OUTPUT_PATH}${OUTPUT_BASE_NAME}.rtf"


echo ">>> Résultats : "
echo ""
echo "Conversion terminée. Les fichiers sont dans : $OUTPUT_PATH"
ls -alh "${OUTPUT_PATH}"
