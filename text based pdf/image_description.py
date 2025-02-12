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
    """Clean the text to ensure valid JSON"""
    # Remove any control characters
    text = ''.join(char for char in text if ord(char) >= 32 or char in '\n\r\t')
    # Remove any markdown code block markers
    text = re.sub(r'```json\s*|\s*```', '', text)
    
    # Fix multiline strings by escaping newlines and quotes
    def fix_multiline(match):
        content = match.group(1)
        # Escape newlines and quotes, preserve intended newlines
        content = content.replace('\n', '\\n').replace('"', '\\"')
        return f'"{content}"'
    
    # Find and fix multiline strings between triple quotes
    text = re.sub(r'"""(.*?)"""', fix_multiline, text, flags=re.DOTALL)
    
    # Clean up any extra whitespace
    text = text.strip()
    return text

def get_image_description(image_path, api_key="P7xBuh2o09ir5b7tTcOY1xKN8ameLul8", model="pixtral-12b-2409"):
    """
    Get a structured description of an image using Mistral AI.
    
    Args:
        image_path (str): Path to the image file
        api_key (str): Mistral API key
        model (str): Model to use for image analysis
    
    Returns:
        str: JSON-formatted description of the image
    """
    base64_image = encode_image(image_path)
    if base64_image is None:
        return None

    client = Mistral(api_key=api_key)
    
    # messages = [
    #     {
    #         "role": "user",
    #         "content": [
    #             {
    #                 "type": "text",
    #                 "text": """
    #                 Décris cette image en français pour une personne aveugle ou malvoyante, en suivant les standards RGAA et WCAG 2.1 AA.
    #                 Structure ta réponse en JSON ainsi :
    #                 {
    #                     "type_image": "Type d'image (photo, graphique, tableau, schéma, etc.) et objectif principal.",
    #                     "elements_visuels": {
    #                         "sujets_principaux": "Sujets/objets principaux (position, taille, relations spatiales).",
    #                         "texte_integral": "Texte intégral présent dans l'image (transcrit mot à mot, si pertinent), si applicable. ",
    #                         "couleurs_significatives": "Couleurs significatives (ex: 'ligne rouge représentant les ventes'), si applicable.",
    #                         "elements_contextuels": "Éléments contextuels (lieu, ambiance, source si pertinente), si applicable."
    #                     },
    #                     "interpretation_donnees": "Tendances, comparaisons, chiffres clés (si graphique/tableau). Éviter les métaphores visuelles.",
    #                     "listes": "Liste retranscrite en Markdown, si applicable.",
    #                     "tableaux": "Tableau retranscrit en Markdown, si applicable."
    #                 }

    #                 Exemple pour un graphique :
    #                 {
    #                     "type_image": "Graphique en barres intitulé 'Ventes trimestrielles 2024'.",
    #                     "elements_visuels": {
    #                         "sujets_principaux": "Axe X : trimestres (Q1 à Q4). Axe Y : chiffre d'affaires en millions d'euros.",
    #                         "texte_integral": "",
    #                         "couleurs_significatives": "",
    #                         "elements_contextuels": "Source : Rapport financier DGAC."
    #                     },
    #                     "interpretation_donnees": "Barre Q1 : 2M€, Q2 : 4M€, Q3 : 3.5M€, Q4 : 5M€.",
    #                     "listes": "",
    #                     "tableaux": "| Trimestre | Ventes (M€) |\n|-----------|-------------|\n| Q1        | 2           |\n| Q2        | 4           |\n| Q3        | 3.5         |\n| Q4        | 5           |"
    #                 }
    #                 """
    #             },
    #             {
    #                 "type": "image_url",
    #                 "image_url": f"data:image/jpeg;base64,{base64_image}"
    #             }
    #         ]
    #     }
    # ]

    messages = [
        {
            "role": "user",
            "content": [
                {
                    "type": "text",
                    "text": """
                    Décris cette image en français pour une personne aveugle ou malvoyante, en suivant les standards RGAA et WCAG 2.1 AA.
                    Structure ta réponse en JSON ainsi :
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

                    Exemple pour un graphique :
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
        clean_text = sanitize_json_string(response_text)
        return json.loads(clean_text)
    except json.JSONDecodeError as e:
        print(f"Error parsing JSON response: {e}")
        print("Raw response:", response_text)  # For debugging
        return None

def main():
    if len(sys.argv) != 2:
        print("Usage: python image_description.py <path_to_image>")
        sys.exit(1)

    image_path = sys.argv[1]
    description = get_image_description(image_path)
    if description:
        # Pretty print the JSON for console output
        print(json.dumps(description, ensure_ascii=False, indent=2))

if __name__ == "__main__":
    main()