#!/bin/bash

# Objectif : donner des informations sur le fichier PDF
# Metadonnées
# Structure
# Nombre et typologies d'éléments
# MuPDF : métadonnées, images avec références (titre, position, référence ID), texte en MD
#   => 

# Variables
## input file path
PDF_FILE_PATH="$1"
# LANGUAGE="${2:-fra}"  # Si aucune langue n'est fournie, utiliser 'fra' par défaut

# Current folder : FOLDER_PATH=$(dirname "$file_path")

# Vérification de l'entrée
if [[ ! -f "$PDF_FILE_PATH" ]]; then
  echo "Erreur : Le fichier PDF spécifié n'existe pas : $PDF_FILE_PATH"
  exit 1
fi

#!/bin/bash

echo "Informations de base : "
meta_output=$(pdfinfo "$PDF_FILE_PATH" 2>&1)
meta_status=$?
echo "$meta_output"
if [ $meta_status -eq 0 ] && [ -n "$meta_output" ]; then
    echo "✅ Métadonnées trouvées."
else
    echo "❌ Aucune métadonnée trouvée."
fi

echo "METADONNEES : XML (de type XMP) "
meta_output=$(pdfinfo -meta "$PDF_FILE_PATH" 2>&1)
meta_status=$?
echo "$meta_output"
if [ $meta_status -eq 0 ] && [ -n "$meta_output" ]; then
    echo "✅ Métadonnées trouvées."
else
    echo "❌ Aucune métadonnée trouvée."
fi

echo ""
echo "STRUCTURE (seule) : "
struct_output=$(pdfinfo -struct "$PDF_FILE_PATH" 2>&1)
struct_status=$?
echo "$struct_output"
if [ $struct_status -eq 0 ] && [ -n "$struct_output" ]; then
    echo "✅ Structure trouvée."
else
    echo "❌ Aucune structure trouvée."
fi

echo ""
echo "Structure et texte : "
struct_text_output=$(pdfinfo -struct-text "$PDF_FILE_PATH" 2>&1)
struct_text_status=$?
echo "$struct_text_output"
if [ $struct_text_status -eq 0 ] && [ -n "$struct_text_output" ]; then
    echo "✅ Structure et texte trouvés."
else
    echo "❌ Aucune structure ou texte trouvé."
fi
