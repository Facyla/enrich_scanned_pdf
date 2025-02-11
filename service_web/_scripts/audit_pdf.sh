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

# Vérification de l'entrée
if [[ ! -f "$PDF_FILE_PATH" ]]; then
  echo "Erreur : Le fichier PDF spécifié n'existe pas : $PDF_FILE_PATH"
  exit 1
fi

echo "METADONNEES : "
pdftk -meta $PDF_FILE_PATH

echo ""
echo "STRUCTURE (seule) : "
pdftk -struct $PDF_FILE_PATH

echo ""
echo "Structure et texte : "
pdftk -struct-text $PDF_FILE_PATH
