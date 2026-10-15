import cv2
import numpy as np

# Dimensions cibles identiques à main.py (A4 à 200 DPI)
DPI = 200
PX_PER_MM = DPI / 25.4
LARGEUR = int(210 * PX_PER_MM)
HAUTEUR = int(297 * PX_PER_MM)

def mm_to_px(mm):
    return int(mm * PX_PER_MM)

# Créer une image blanche A4
image = np.ones((HAUTEUR, LARGEUR), dtype=np.uint8) * 255

# Dessiner les 4 marqueurs de calibration (comme dans le PDF)
taille_marqueur = mm_to_px(6)
cv2.rectangle(image, (mm_to_px(6), mm_to_px(6)), (mm_to_px(6)+taille_marqueur, mm_to_px(6)+taille_marqueur), 0, -1)
cv2.rectangle(image, (mm_to_px(204), mm_to_px(6)), (mm_to_px(204)+taille_marqueur, mm_to_px(6)+taille_marqueur), 0, -1)
cv2.rectangle(image, (mm_to_px(6), mm_to_px(285)), (mm_to_px(6)+taille_marqueur, mm_to_px(285)+taille_marqueur), 0, -1)
cv2.rectangle(image, (mm_to_px(204), mm_to_px(285)), (mm_to_px(204)+taille_marqueur, mm_to_px(285)+taille_marqueur), 0, -1)

# Simuler la case "A" noircie pour la question 1
# (mêmes calculs de position que dans formulaire-pdf.php / main.py)
nb_questions = 10
options_labels = ['A', 'B', 'C', 'D', 'E', 'JNSP', 'ARJ']
nb_colonnes = 1
largeur_colonne = 190 / nb_colonnes
hauteur_ligne = 6.5
start_y = 42 + 6 + 10 + 7

# Question 1, réponse A cochée
num_question = 1
i = num_question - 1
y = start_y + (i * hauteur_ligne)
x_col = 10
x_case = x_col + 7
espacement = (largeur_colonne - 7) / len(options_labels)

idx_A = 0  # "A" est le premier label
x_groupe = x_case + (idx_A * espacement)
x_case_rect = x_groupe + 3  # largeur_label pour une lettre simple

x_px = mm_to_px(x_case_rect)
y_px = mm_to_px(y + (hauteur_ligne / 2) - 1.6)
taille_px = mm_to_px(3.2)

cv2.rectangle(image, (x_px, y_px), (x_px + taille_px, y_px + taille_px), 0, -1)

# Simuler aussi la question 3, réponse C cochée
num_question = 3
i = num_question - 1
y = start_y + (i * hauteur_ligne)
idx_C = 2
x_groupe = x_case + (idx_C * espacement)
x_case_rect = x_groupe + 3
x_px = mm_to_px(x_case_rect)
y_px = mm_to_px(y + (hauteur_ligne / 2) - 1.6)
cv2.rectangle(image, (x_px, y_px), (x_px + taille_px, y_px + taille_px), 0, -1)

cv2.imwrite('copie_simulee.jpg', image)
print("Image simulée créée : copie_simulee.jpg")
print(f"Réponses simulées : Q1=A, Q3=C")
