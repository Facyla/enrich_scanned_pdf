#!/bin/bash

# List of required commands and their corresponding package names
declare -A commands_and_packages=(
  ["cd"]="coreutils" # cd is a shell built-in, but included here for consistency
  ["cat"]="coreutils"
  ["awk"]="awk"
  ["dirname"]="coreutils"
  ["basename"]="coreutils"
  ["tesseract"]="tesseract-ocr"
  ["pdftk"]="pdftk"
  ["pdftoppm"]="poppler-utils"
  ["pdftotext"]="poppler-utils"
  ["ocrmypdf"]="ocrmypdf"
)

# Variables to store the missing commands and install commands
missing_commands=()
declare -A install_commands

# Check if each command is installed and build lists of missing commands
for cmd in "${!commands_and_packages[@]}"; do
  if ! command -v "$cmd" &> /dev/null; then
    missing_commands+=("$cmd")
    package="${commands_and_packages[$cmd]}"
    install_commands["$package"]=1  # Mark the package as needing installation
  fi
done

# Check that TESSDATA_PREFIX is defined, and that it points to an existing directory
if [ -z "$TESSDATA_PREFIX" ]; then
  if [ -d "/usr/share/tesseract-ocr/5/tessdata" ]; then
	  echo ""
    echo "TESSDATA_PREFIX n'est pas défini ou est vide"
	  echo "Notez que le chemin qui doit être défini dépend de votre OS et de la version de Tesseract installée, par ex. dans /usr/share/tesseract-ocr/5/tessdata (Debian 12), ou /usr/share/tesseract-ocr/4.00/tessdata (Ubuntu 22.04)"
    echo "export TESSDATA_PREFIX=/usr/share/tesseract-ocr/5/tessdata"
  else
	  echo ""
    echo "Le répertoire /usr/share/tesseract-ocr/5/tessdata n'existe pas : veuillez vérifier l'installation de Tesseract"
	  echo "Notez que le chemin dépend de votre OS et de la version de Tesseract installée, par ex. dans /usr/share/tesseract-ocr/5/tessdata (Debian 12), ou /usr/share/tesseract-ocr/4.00/tessdata (Ubuntu 22.04)"
    exit 1
  fi
fi

# Check that /usr/share/tesseract-ocr/5/tessdata/fra.traineddata exists ; if not, install missing package
# Install required Tesseract languages files
# Attention : le chemin va dépendre de la version installée, de l'OS, etc.
if [ ! -f "/usr/share/tesseract-ocr/5/tessdata/fra.traineddata" ]; then
  echo "/usr/share/tesseract-ocr/5/tessdata/fra.traineddata n'existe pas, veuiller installer le fichier de langue"
  echo ""
  echo "Notez que le chemin dépend de votre OS et de la version de Tesseract installée, par ex. dans /usr/share/tesseract-ocr/5/tessdata (Debian 12), ou /usr/share/tesseract-ocr/4.00/tessdata (Ubuntu 22.04)"
  echo "sudo apt-get install tesseract-ocr-fra"
fi
# eng, rus, ara, tha, deu, spa...
if [ ! -f "/usr/share/tesseract-ocr/5/tessdata/eng.traineddata" ]; then
  echo ""
  echo "/usr/share/tesseract-ocr/5/tessdata/eng.traineddata n'existe pas"
	echo "sudo apt-get install tesseract-ocr-eng"
fi

# Vérification de la police utilisée par OCRMyPDF
if [ ! -f "/usr/share/tesseract-ocr/5/tessdata/tessconfigs/pdf.ttf" ]; then
  echo ""
  echo "/usr/share/tesseract-ocr/5/tessdata/tessconfigs/pdf.ttf n'existe pas"
  echo ""
  echo "Ce fichier est requi par OCRMyPDF, installez la police requise : "
  echo "wget https://github.com/tesseract-ocr/tesseract/blob/master/tessdata/pdf.ttf"
  echo "Puis copiez ou déplacez le fichier dans le dossier \"tessconfigs/\""
  echo "Notez que le chemin dépend de votre OS et de la version de Tesseract installée, par ex. dans /usr/share/tesseract-ocr/5/tessdata (Debian 12), ou /usr/share/tesseract-ocr/4.00/tessdata (Ubuntu 22.04)"
  echo "sudo cp pdf.ttf /usr/share/tesseract-ocr/5/tessdata/tessconfigs/"
  echo "sudo cp pdf.ttf ${TESSDATA_PREFIX}/tessconfigs/"
  echo ""
fi

# If there are missing commands, display the results
if [ ${#missing_commands[@]} -gt 0 ]; then
  echo "Erreurs : Les commandes suivantes ne sont pas installées :"
  for cmd in "${missing_commands[@]}"; do
    echo "- $cmd"
  done

  # Display the unique installation commands
  echo -e "\nPour les installer, exécutez la commande suivante :"
  for package in "${!install_commands[@]}"; do
    echo "sudo apt install -y $package"
  done
  echo ""
  echo "En cas de soucis pour installer l'un des paquets, essayez ceci : "
  echo "1) Ajoutez  --fix-missing à la commande d'installation : apt install <paquet> --fix-missing"
  echo "2) Mettez à jour les dépôts avant l'installation"
  echo "sudo apt update && sudo apt upgrade"
  echo "sudo apt --fix-broken install"
  echo ""
  echo "En cas de soucis avec Tesseract, installez les langues requises : (au moins fra) "
  echo "sudo apt-get install tesseract-ocr-<CODE_LANG>"
  echo ""
else
  echo "Toutes les commandes nécessaires sont installées."
fi

