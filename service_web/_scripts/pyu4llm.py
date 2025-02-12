# -*- coding: utf-8 -*-

"""
Script permettant de convertir un PDF textuel en Markdown et de sauvegarder les médias associés.
Ce script ne nécessite pas d'OCR car il fonctionne uniquement avec les PDF contenant du texte.

Usage:
    python pyu4llm.py <chemin_du_fichier_pdf> <hash_value>

Le script va :
- Convertir le contenu du PDF en Markdown
- Extraire et sauvegarder les images dans le dossier 'output'
- Générer un fichier JSON par page dans le dossier 'output'
"""

import pymupdf4llm
import pymupdf
import os
import sys
import re
import hashlib
from image_description import get_image_description

def ensure_directory(path):
    """Create directory if it doesn't exist"""
    os.makedirs(path, exist_ok=True)
    return path

def process_image_references(text, hash_value, temp_dir):
    """Replace image references with their descriptions and update image paths"""
    img_pattern = fr'!\[(.*?)\]\(({re.escape(temp_dir)}/[^)]+)\)'
    
    def replace_with_description(match):
        description = match.group(1)
        old_path = match.group(2)
        
        # Get image filename and create new path
        img_filename = os.path.basename(old_path)
        new_path = f'service_web/_data/{hash_value}/{img_filename}'
        
        # Move the image to the new location
        if os.path.exists(old_path):
            os.rename(old_path, new_path)
        
        if not description:
            json_data, lists, tables = get_image_description(new_path)
            if json_data and 'type_image' in json_data:
                description = json_data['type_image']
        
        return f'![{description}]({new_path})'

    return re.sub(img_pattern, replace_with_description, text)

def process_pdf(input_file, hash_value):
    print(f"\n[DEBUG] Starting PDF processing: {input_file}")
    
    # Create directories
    hash_dir = ensure_directory(f'service_web/_data/{hash_value}')
    temp_dir = ensure_directory('service_web/_data/temp')
    print(f"[DEBUG] Using hash directory: {hash_dir}")
    print(f"[DEBUG] Using temp directory: {temp_dir}")
    
    # Get the PDF filename without extension
    base_name = os.path.splitext(os.path.basename(input_file))[0]
    output_file = f'{hash_dir}/{base_name}.md'
    
    # Convert PDF to markdown
    md_text = pymupdf4llm.to_markdown(
        input_file, 
        write_images=True, 
        image_path=temp_dir,  # Use temp directory for initial image storage
        page_chunks=True
    )
    
    # Combine all pages into a single markdown string
    full_markdown = ""
    for item in md_text:
        if isinstance(item, dict) and 'text' in item:
            # Process and move images to hash directory
            page_text = process_image_references(item['text'], hash_value, temp_dir)
            full_markdown += page_text + "\n\n"
    
    # Write the markdown file
    print(f"[DEBUG] Saving markdown to: {output_file}")
    with open(output_file, 'w+', encoding='utf-8') as f:
        f.write(full_markdown.strip())
    print(f"[DEBUG] Successfully saved markdown file")
    
    # Clean up temp directory
    if os.path.exists(temp_dir):
        import shutil
        shutil.rmtree(temp_dir)
    
    return hash_value

if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("Usage: python script.py <pdf_file_path> <hash_value>")
        sys.exit(1)
    
    input_file = sys.argv[1]
    hash_value = sys.argv[2]
    process_pdf(input_file, hash_value)
    print(f"Content stored in: service_web/_data/{hash_value}")