from docling.document_converter import DocumentConverter, PdfFormatOption
from docling.datamodel.pipeline_options import PdfPipelineOptions
from docling.datamodel.base_models import InputFormat
from docling_core.types.doc import ImageRefMode, PictureItem
from pathlib import Path
import re
import base64
import logging

_log = logging.getLogger(__name__)
IMAGE_RESOLUTION_SCALE = 2.0

class DocumentParser:
    def __init__(self, pdf_path):
        self.pdf_path = pdf_path
        
        # Configuration du pipeline pour la gestion des images
        pipeline_options = PdfPipelineOptions()
        pipeline_options.images_scale = IMAGE_RESOLUTION_SCALE
        pipeline_options.generate_page_images = True
        pipeline_options.generate_picture_images = True
        
        # Configuration du convertisseur
        self.converter = DocumentConverter(
            format_options={
                InputFormat.PDF: PdfFormatOption(pipeline_options=pipeline_options)
            }
        )
        
        self.result = None
        self.markdown_content = None
        self.pictures_info = []
    
    def parse(self):
        """Parse le PDF et extrait les informations"""
        _log.info(f"Début de la conversion du document: {self.pdf_path}")
        self.result = self.converter.convert(self.pdf_path)
        self.markdown_content = self.result.document.export_to_markdown()
        self._extract_pictures_info()
        return self._enrich_markdown()
    
    def _extract_ref_text(self, ref_item):
        """Extrait le texte d'un RefItem"""
        if hasattr(ref_item, 'text'):
            return ref_item.text
        elif hasattr(ref_item, 'cref'):
            return str(ref_item.cref)
        return str(ref_item)
    
    def _extract_pictures_info(self):
        """Extrait les informations de chaque image"""
        for idx, picture in enumerate(self.result.document.pictures):
            try:
                # Obtenir l'image en base64
                image_b64 = picture._image_to_base64(picture.image.pil_image)
                
                info = {
                    'index': idx,
                    'page': picture.prov[0].page_no if picture.prov else None,
                    'position': str(picture.prov[0].bbox) if picture.prov else None,
                    'captions': [self._extract_ref_text(cap) for cap in picture.captions] if picture.captions else [],
                    'references': [self._extract_ref_text(ref) for ref in picture.references] if picture.references else [],
                    'annotations': [self._extract_ref_text(ann) for ann in picture.annotations] if picture.annotations else [],
                    'image_b64': image_b64,
                    'llm_description': None
                }
                self.pictures_info.append(info)
                _log.debug(f"Image {idx} extraite avec succès")
                
            except Exception as e:
                _log.error(f"Erreur lors de l'extraction de l'image {idx}: {str(e)}")
    
    def _format_image_block(self, image_info):
        """Formate les informations d'une image en markdown"""
        block = [
            "<!-- BEGIN IMAGE -->",
            "### Image {index} (Page {page})".format(**image_info),
            "",  # Ligne vide pour meilleure lisibilité
        ]
        
        # Ajouter l'image en base64 directement dans le markdown
        if image_info['image_b64']:
            block.append(f"![Image {image_info['index']}](data:image/png;base64,{image_info['image_b64']})")
        else:
            block.append(f"![Image {image_info['index']}]()")
        
        block.append("")  # Ligne vide pour meilleure lisibilité
        
        # Informations détaillées
        block.append("#### Métadonnées")
        block.append(f"- **Position dans la page**: {image_info['position']}")
        
        if image_info['captions']:
            block.append("#### Légendes")
            for caption in image_info['captions']:
                block.append(f"- {caption}")
        
        if image_info['references']:
            block.append("#### Références")
            for ref in image_info['references']:
                block.append(f"- {ref}")
        
        if image_info['annotations']:
            block.append("#### Annotations")
            for ann in image_info['annotations']:
                block.append(f"- {ann}")
        
        if image_info['llm_description']:
            block.append("#### Description par l'IA")
            block.append(f"{image_info['llm_description']}")
        
        block.append("")  # Ligne vide pour meilleure lisibilité
        block.append("<!-- END IMAGE -->")
        block.append("")  # Ligne vide pour séparer les images
        
        return "\n".join(block)
    
    def add_llm_descriptions(self, llm_processor):
        """Ajoute les descriptions LLM aux images"""
        for info in self.pictures_info:
            if info['image_b64']:
                # Utiliser directement la chaîne base64
                description = llm_processor.describe_image(info['image_b64'])
                info['llm_description'] = description
    
    def _enrich_markdown(self):
        """Enrichit le markdown avec les informations des images"""
        enriched_content = self.markdown_content
        
        for idx, info in enumerate(self.pictures_info):
            image_pattern = r'<!-- image -->'
            image_block = self._format_image_block(info)
            enriched_content = enriched_content.replace('<!-- image -->', image_block, 1)
        
        return enriched_content

def parse_document(pdf_path, llm_processor=None):
    """Fonction utilitaire pour parser un document"""
    logging.basicConfig(level=logging.INFO)
    parser = DocumentParser(pdf_path)
    if llm_processor:
        parser.add_llm_descriptions(llm_processor)
    return parser.parse()

# Exemple d'utilisation
if __name__ == "__main__":
    pdf_path = "/Users/chrysostomebeltran/Downloads/dossier-de-presse-nah-2024_V1-1.pdf"
    
    class DummyLLMProcessor:
        def describe_image(self, image_b64):
            return "Description de test par le LLM"
    
    llm = DummyLLMProcessor()
    enriched_content = parse_document(pdf_path, llm)
    
    # Sauvegarder le résultat
    output_path = Path("output.md")
    output_path.write_text(enriched_content, encoding='utf-8')
    print(f"Document parsé et sauvegardé dans {output_path}") 