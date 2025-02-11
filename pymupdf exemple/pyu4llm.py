import pymupdf4llm
import pymupdf
import pprint
import json

#handle Rect objects
class CustomJSONEncoder(json.JSONEncoder):
    def default(self, obj):
        if isinstance(obj, pymupdf.Rect):
            return [obj.x0, obj.y0, obj.x1, obj.y1]
        return super().default(obj)


md_text = pymupdf4llm.to_markdown("test_ocr.pdf", write_images=True, image_path="output", page_chunks=True)
for i, item in enumerate(md_text):
    json_str = json.dumps(item, cls=CustomJSONEncoder, indent=2)

    with open(f'output/output_page_{i+1}.json', 'w+') as f:
        f.write(json_str)
    print(f"Saved output_page_{i+1}.json")