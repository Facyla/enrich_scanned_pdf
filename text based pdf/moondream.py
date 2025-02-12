import moondream as md
from PIL import Image
import sys

def process_image(image_path):
    # Initialize with API key
    API_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJrZXlfaWQiOiIxMDQ4MDZjMS0yNTE1LTQzNDEtOTJiNi1mNzI2Y2JjOTc0NjUiLCJpYXQiOjE3MzkzMDA3NTh9.YeDsJWGj1o8rMn5mmbDiFDEgnbILJ49XIdSHnoUCUt4"
    model = md.vl(api_key=API_KEY)

    # Load the image
    try:
        image = Image.open(image_path)
        encoded_image = model.encode_image(image)

        # Generate caption
        caption = model.caption(encoded_image)["caption"]
        print("Caption:", caption)

        # Ask for alt title
        answer = model.query(encoded_image, "Write a short alt title for this image")["answer"]
        print("Alt title:", answer)

    except FileNotFoundError:
        print(f"Error: Image file '{image_path}' not found")
    except Exception as e:
        print(f"Error processing image: {str(e)}")

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python moondream.py <image_path>")
        sys.exit(1)
    
    image_path = sys.argv[1]
    process_image(image_path)
