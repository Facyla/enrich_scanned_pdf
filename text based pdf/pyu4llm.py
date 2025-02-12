
"""
Script permettant de convertir un PDF textuel en Markdown et de sauvegarder les médias associés.
Ce script ne nécessite pas d'OCR car il fonctionne uniquement avec les PDF contenant du texte.

Usage:
    python pyu4llm.py <chemin_du_fichier_pdf>

Le script va :
- Convertir le contenu du PDF en Markdown
- Extraire et sauvegarder les images dans le dossier 'output'
- Générer un fichier JSON par page dans le dossier 'output'
"""

import pymupdf4llm
import pymupdf
import pprint
import json
import sys

class CustomJSONEncoder(json.JSONEncoder):
    def default(self, obj):
        if isinstance(obj, pymupdf.Rect):
            return [obj.x0, obj.y0, obj.x1, obj.y1]
        return super().default(obj)

def process_pdf(input_file):
    md_text = pymupdf4llm.to_markdown(input_file, write_images=True, image_path="text based pdf/output", page_chunks=True)
    for i, item in enumerate(md_text):
        json_str = json.dumps(item, cls=CustomJSONEncoder, indent=2)

        with open(f'text based pdf/output/output_page_{i+1}.json', 'w+') as f:
            f.write(json_str)
        print(f"Saved output_page_{i+1}.json")

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python script.py <pdf_file_path>")
        sys.exit(1)
    
    input_file = sys.argv[1]
    process_pdf(input_file)