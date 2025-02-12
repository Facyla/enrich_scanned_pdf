import base64
import sys
import json
import re
from mistralai import Mistral

def encode_image(image_path):
    try:
        with open(image_path, "rb") as image_file:
            return base64.b64encode(image_file.read()).decode('utf-8')
    except FileNotFoundError:
        print(f"Error: The file {image_path} was not found.")
        return None
    except Exception as e:
        print(f"Error: {e}")
        return None

def sanitize_json_string(text):
    """Clean the text to ensure valid JSON and extract lists/tables"""
    # Remove markdown code blocks
    text = re.sub(r'```json\s*|\s*```', '', text)
    
    # Parse the text to extract components
    try:
        data = json.loads(text)
        
        # Extract and remove lists and tables
        lists = data.pop('listes', '')
        tables = data.pop('tableaux', '')
        
        # Convert back to clean JSON string
        clean_json = json.dumps(data)
        
        return clean_json, lists.strip(), tables.strip()
    except json.JSONDecodeError:
        print("Error in initial JSON parsing")
        return None, None, None

def extract_markdown_content(text):
    """Extract lists and tables from the response text"""
    # Find markdown lists (lines starting with - or *)
    lists = '\n'.join(re.findall(r'^[\s]*[-*].*$', text, re.MULTILINE)).strip()
    
    # Find markdown tables (lines containing |)
    tables = '\n'.join(re.findall(r'^.*\|.*$', text, re.MULTILINE)).strip()
    
    return lists, tables

def get_image_description(image_path, api_key="P7xBuh2o09ir5b7tTcOY1xKN8ameLul8", model="pixtral-12b-2409"):
    """
    Get a structured description of an image using Mistral AI.
    
    Args:
        image_path (str): Path to the image file
        api_key (str): Mistral API key
        model (str): Model to use for image analysis
    
    Returns:
        tuple: (json_data: dict, lists: str, tables: str)
    """
    base64_image = encode_image(image_path)
    if base64_image is None:
        return None, None, None

    client = Mistral(api_key=api_key)
    
    messages = [
        {
            "role": "user",
            "content": [
                {
                    "type": "text",
                    "text": """
                    Décris cette image en français pour une personne aveugle ou malvoyante, en suivant les standards RGAA et WCAG 2.1 AA.
                    Structure ta réponse avec :
                    1. Un JSON contenant :
                    {
                        "type_image": "Type d'image (photo, graphique, tableau, schéma, etc.) et objectif principal.",
                        "elements_visuels": {
                            "sujets_principaux": "Sujets/objets principaux (position, taille, relations spatiales).",
                            "texte_integral": "Texte intégral présent dans l'image (transcrit mot à mot, si pertinent), si applicable. ",
                            "couleurs_significatives": "Couleurs significatives (ex: 'ligne rouge représentant les ventes'), si applicable.",
                            "elements_contextuels": "Éléments contextuels (lieu, ambiance, source si pertinente), si applicable."
                        },
                        "interpretation_donnees": "Tendances, comparaisons, chiffres clés (si graphique/tableau). Éviter les métaphores visuelles."
                    }

                    2. Si applicable, une liste en Markdown (avec - ou *) des éléments importants.
                    3. Si applicable, un tableau en Markdown pour les données tabulaires.

                    Exemple :
                    {
                        "type_image": "Graphique en barres intitulé 'Ventes trimestrielles 2024'.",
                        "elements_visuels": {
                            "sujets_principaux": "Axe X : trimestres (Q1 à Q4). Axe Y : chiffre d'affaires en millions d'euros.",
                            "texte_integral": "",
                            "couleurs_significatives": "",
                            "elements_contextuels": "Source : Rapport financier DGAC."
                        },
                        "interpretation_donnees": "Barre Q1 : 2M€, Q2 : 4M€, Q3 : 3.5M€, Q4 : 5M€."
                    }

                    - Q1 2024 : 2M€
                    - Q2 2024 : 4M€
                    - Q3 2024 : 3.5M€
                    - Q4 2024 : 5M€

                    | Trimestre | Ventes (M€) |
                    |-----------|-------------|
                    | Q1 2024   | 2           |
                    | Q2 2024   | 4           |
                    | Q3 2024   | 3.5         |
                    | Q4 2024   | 5           |
                    """
                },
                {
                    "type": "image_url",
                    "image_url": f"data:image/jpeg;base64,{base64_image}"
                }
            ]
        }
    ]

    chat_response = client.chat.complete(
        model=model,
        messages=messages
    )
    
    try:
        response_text = chat_response.choices[0].message.content
        # Extract JSON part (first { to last })
        json_text = re.search(r'({.*})', response_text, re.DOTALL).group(1)
        json_data = json.loads(json_text)
        
        # Extract markdown content
        lists, tables = extract_markdown_content(response_text)
        
        return json_data, lists, tables
    except Exception as e:
        print(f"Error processing response: {e}")
        print("Raw response:", response_text)
        return None, None, None

def get_image_description_from_base64(base64_image, api_key="P7xBuh2o09ir5b7tTcOY1xKN8ameLul8", model="pixtral-12b-2409"):
    """
    Get a structured description of an image using Mistral AI, given a base64-encoded image.

    Args:
        base64_image (str): Base64-encoded image string
        api_key (str): Mistral API key
        model (str): Model to use for image analysis

    Returns:
        tuple: (json_data: dict, lists: str, tables: str)
    """
    if not base64_image:
        return None, None, None

    client = Mistral(api_key=api_key)
    
    messages = [
        {
            "role": "user",
            "content": [
                {
                    "type": "text",
                    "text": """
                    Décris cette image en français pour une personne aveugle ou malvoyante, en suivant les standards RGAA et WCAG 2.1 AA.
                    Structure ta réponse avec :
                    1. Un JSON contenant :
                    {
                        "type_image": "Type d'image (photo, graphique, tableau, schéma, etc.) et objectif principal.",
                        "elements_visuels": {
                            "sujets_principaux": "Sujets/objets principaux (position, taille, relations spatiales).",
                            "texte_integral": "Texte intégral présent dans l'image (transcrit mot à mot, si pertinent), si applicable. ",
                            "couleurs_significatives": "Couleurs significatives (ex: 'ligne rouge représentant les ventes'), si applicable.",
                            "elements_contextuels": "Éléments contextuels (lieu, ambiance, source si pertinente), si applicable."
                        },
                        "interpretation_donnees": "Tendances, comparaisons, chiffres clés (si graphique/tableau). Éviter les métaphores visuelles."
                    }

                    2. Si applicable, une liste en Markdown (avec - ou *) des éléments importants.
                    3. Si applicable, un tableau en Markdown pour les données tabulaires.

                    Exemple :
                    {
                        "type_image": "Graphique en barres intitulé 'Ventes trimestrielles 2024'.",
                        "elements_visuels": {
                            "sujets_principaux": "Axe X : trimestres (Q1 à Q4). Axe Y : chiffre d'affaires en millions d'euros.",
                            "texte_integral": "",
                            "couleurs_significatives": "",
                            "elements_contextuels": "Source : Rapport financier DGAC."
                        },
                        "interpretation_donnees": "Barre Q1 : 2M€, Q2 : 4M€, Q3 : 3.5M€, Q4 : 5M€."
                    }

                    - Q1 2024 : 2M€
                    - Q2 2024 : 4M€
                    - Q3 2024 : 3.5M€
                    - Q4 2024 : 5M€

                    | Trimestre | Ventes (M€) |
                    |-----------|-------------|
                    | Q1 2024   | 2           |
                    | Q2 2024   | 4           |
                    | Q3 2024   | 3.5         |
                    | Q4 2024   | 5           |
                    """
                },
                {
                    "type": "image_url",
                    "image_url": f"data:image/jpeg;base64,{base64_image}"
                }
            ]
        }
    ]

    chat_response = client.chat.complete(
        model=model,
        messages=messages
    )
    
    try:
        response_text = chat_response.choices[0].message.content
        # Extract JSON part (first { to last })
        json_text = re.search(r'({.*})', response_text, re.DOTALL).group(1)
        json_data = json.loads(json_text)
        
        # Extract markdown content
        lists, tables = extract_markdown_content(response_text)
        
        return json_data, lists, tables
    except Exception as e:
        print(f"Error processing response: {e}")
        print("Raw response:", response_text)
        return None, None, None
def main():
    if len(sys.argv) != 2:
        print("Usage: python image_description.py <path_to_image>")
        sys.exit(1)

    image_path = sys.argv[1]
    json_data, lists, tables = get_image_description(image_path)
    
    if json_data:
        print("\n=== JSON DATA ===")
        print(json.dumps(json_data, ensure_ascii=False, indent=2))
        if lists:
            print("\n=== LISTS ===")
            print(lists)
        if tables:
            print("\n=== TABLES ===")
            print(tables)

if __name__ == "__main__":
    main()