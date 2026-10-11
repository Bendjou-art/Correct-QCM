import pytesseract
from PIL import Image
import sys

if len(sys.argv) < 2:
    print("Usage: python test_ocr.py /home/nehemie-hapsibisie/Téléchargements/test.jpeg")
    sys.exit(1)

chemin_image = sys.argv[1]
image = Image.open(chemin_image)
texte = pytesseract.image_to_string(image, lang='fra')
print("=== Texte extrait de l'image : ===")
print(texte)
print("=== Fin du texte extrait ===")