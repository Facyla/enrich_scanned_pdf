#!/bin/bash

# Objectif : série de commande correspondant à un module


# Variables
## input file path
PDF_FILE_PATH="$1"
HASH_ID="$2"
# LANGUAGE="${2:-fra}"  # Si aucune langue n'est fournie, utiliser 'fra' par défaut

# Current folder : FOLDER_PATH=$(dirname "$file_path")

# Vérification de l'entrée
if [[ ! -f "$PDF_FILE_PATH" ]]; then
  echo "Erreur : Le fichier PDF spécifié n'existe pas : $PDF_FILE_PATH"
  exit 1
fi


python3 FILE_PATH.pdf => MD
// Génère 1 fichier MD


echo "TRAITEMENT du fichier $PDF_FILE_PATH"
meta_output=$(pdfinfo -meta "$PDF_FILE_PATH" 2>&1)
meta_status=$?
echo "$meta_output"

if [ $meta_status -eq 0 ] && [ -n "$meta_output" ]; then
    echo "✅ Sortie OK."
else
    echo "❌ Aucune sortie."
fi
