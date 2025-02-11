#!/bin/bash

# Liste les langues disponibles pour Tesseract

# Dossier contenant les fichiers traineddata
tessdata_dir="/usr/share/tesseract-ocr/5/tessdata/"

# Vérification si le dossier existe
if [ ! -d "$tessdata_dir" ]; then
  echo "{\"error\": \"Le dossier $tessdata_dir n'existe pas.\"}"
  exit 1
fi

# Lister les fichiers se terminant par .traineddata, exclure osd.traineddata, et extraire les préfixes
available_languages=$(ls "$tessdata_dir" | grep -E '\.traineddata$' | grep -v '^osd\.traineddata$' | sed 's/\.traineddata$//')

# Vérifier s'il y a des fichiers disponibles
if [ -z "$available_languages" ]; then
  echo "{\"error\": \"Aucun fichier .traineddata trouvé dans $tessdata_dir.\"}"
else
  # Construire la liste au format JSON manuellement
  json="["
  first=1
  while IFS= read -r lang; do
    if [ "$first" -eq 0 ]; then
      json+=", "
    else
      first=0
    fi
    json+="\"$lang\""
  done <<< "$available_languages"
  json+="]"

  # Afficher le résultat en JSON
  echo "{\"languages\": $json}"
fi

