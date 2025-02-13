# enrich_scanned_pdf
Prototypes d'enrichissement de fichiers PDF avec des données océrisées

## Documents exemples

## Base de code "web"
Interface web PHP, Bash, Python et bibliothèques tierces


## Scripts python

### _scripts/pyu4llm.py : génération de MD pour chaque page d'un PDF (local)
Ex. utilisation (debian/ubuntu) : 
python3 /path/to/EnrichPDF/_scripts/pyu4llm.py /path/to/EnrichPDF/_data/source/Fichier\ PDF\ non\ image.pdf /path/to/EnrichPDF/_data/output/HASH_OF_FILE
ou : python3 "/path/to/EnrichPDF/_scripts/pyu4llm.py" "/path/to/EnrichPDF/_data/source/Fichier PDF non image.pdf" "/path/to/EnrichPDF/_data/output/HASH_OF_FILE"
=> va générer : 
 * les images intermédiaires générées (une par page PDF), de la forme : /path/to/EnrichPDF/_data/output/HASH_OF_FILE/Fichier\ PDF\ non\ image.pdf-0-0.png
 * le fichier /path/to/EnrichPDF/_data/output/HASH_OF_FILE/Fichier\ PDF\ non\ image.md

Si nécessaire : 
	source env/bin/activate
	pip install pymupdf4llm

Script bash : idem, mais permet d'appeler le script depuis PHP avec un contrôle des entrées
bash /path/to/EnrichPDF/_scripts/module_export.sh '/path/to/EnrichPDF/_data/source/Fichier PDF Image.pdf' /path/to/EnrichPDF/_data/output/bash_parser


### _scripts/parser.py : analyse d'image (utilise l'API Mistral)
Ex. utilisation (debian/ubuntu) : 
python3 "/path/to/EnrichPDF/_scripts/parser.py" "/path/to/EnrichPDF/_data/source/Fichier PDF Image.pdf" "/path/to/EnrichPDF/_data/output/HASH_OF_FILE"

=> va générer le fichier /path/to/EnrichPDF/_data/output/HASH_OF_FILE/Fichier\ PDF\ Image.md

Si nécessaire : 
	source env/bin/activate

Script bash : idem, mais permet d'appeler le script depuis PHP avec un contrôle des entrées
bash /path/to/EnrichPDF/_scripts/module_export.sh '/path/to/EnrichPDF/_data/source/Fichier PDF Image.pdf' /path/to/EnrichPDF/_data/output/bash_parser

