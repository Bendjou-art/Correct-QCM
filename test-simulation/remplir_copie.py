
import cv2
import numpy as np
import random
import json

DPI = 200
PX_PER_MM = DPI / 25.4

def mm_to_px(mm):
    return int(mm * PX_PER_MM)

# ==================== CONFIGURATION ====================
# À adapter selon votre QCM réel
NB_QUESTIONS = 10
MODE_IDENTIFICATION = 'matricule'  # ou 'anonymat'
OPTIONS_LABELS = ['A', 'B', 'C', 'D', 'E', 'Y', 'Z']

MATRICULE = "21V2207"
NOM = "HAPSIBISIE KOUAYEP"
PRENOM = "NEHEMIE"
CODE_ANONYMAT = "A007"
# =========================================================

def calculer_repartition_colonnes(nb_questions):
    if nb_questions == 10:
        nb_colonnes = 2
    elif nb_questions <= 25:
        nb_colonnes = 1
    elif nb_questions <= 50:
        nb_colonnes = 2
    else:
        nb_colonnes = 3

    base = nb_questions // nb_colonnes
    reste = nb_questions % nb_colonnes

    repartition = []
    for i in range(nb_colonnes):
        repartition.append(base + (1 if i < reste else 0))

    return repartition


# Charger l'image du formulaire vierge
image = cv2.imread('formulaire-1.jpg')
if image is None:
    print("Erreur : impossible de charger formulaire-1.jpg")
    exit(1)

# ==================== REMPLIR L'IDENTIFICATION ====================
font = cv2.FONT_HERSHEY_SIMPLEX

if MODE_IDENTIFICATION == 'matricule':
    # Zone Matricule (dans le cadre encadré, environ x=30-75mm, y=23-32mm)
    cv2.putText(image, MATRICULE, (mm_to_px(33), mm_to_px(29)), font, 0.6, (0, 0, 0), 2)

    # Zone Nom (colonne droite, environ x=88mm, y=8-12mm — juste après "Nom : ")
    cv2.putText(image, NOM, (mm_to_px(100), mm_to_px(11)), font, 0.6, (0, 0, 0), 2)

    # Zone Prénoms (juste après "Prénoms : ")
    cv2.putText(image, PRENOM, (mm_to_px(105), mm_to_px(15.5)), font, 0.6, (0, 0, 0), 2)

else:
    # Zone Code d'anonymat (dans le cadre "Ne rien écrire", ou cadre dédié)
    cv2.putText(image, CODE_ANONYMAT, (mm_to_px(172), mm_to_px(47)), font, 0.6, (0, 0, 0), 2)

# ==================== NOIRCIR DES CASES ALÉATOIRES ====================
image_gray_ref = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)

est_anonymat = (MODE_IDENTIFICATION == 'anonymat')
marge_haut = 97 if est_anonymat else 88

repartition = calculer_repartition_colonnes(NB_QUESTIONS)
nb_options = len(OPTIONS_LABELS)

largeur_utile = 191
espace_entre_colonnes = 5
nb_colonnes = len(repartition)
largeur_bloc = (largeur_utile - (espace_entre_colonnes * (nb_colonnes - 1))) / nb_colonnes
largeur_num = 7
largeur_option = (largeur_bloc - largeur_num) / nb_options

hauteur_entete_col = 5.5
hauteur_disponible = 283 - marge_haut
max_questions_par_colonne = max(repartition)
hauteur_ligne = (hauteur_disponible - hauteur_entete_col) / max_questions_par_colonne
hauteur_ligne = min(hauteur_ligne, 7)

reponses_choisies = {}
x_col = 10
numero_depart = 1

for questions_dans_colonne in repartition:
    y = marge_haut + hauteur_entete_col

    for i in range(questions_dans_colonne):
        num_question = numero_depart + i
        if num_question > NB_QUESTIONS:
            break

        # Choisir aléatoirement UNE option à noircir pour cette question
        idx_choisi = random.randint(0, len(OPTIONS_LABELS) - 1)
        label_choisi = OPTIONS_LABELS[idx_choisi]
        reponses_choisies[num_question] = label_choisi

        x_case = x_col + largeur_num + (idx_choisi * largeur_option)

        x1 = mm_to_px(x_case) + 2
        y1 = mm_to_px(y) + 1
        x2 = mm_to_px(x_case + largeur_option) - 2
        y2 = mm_to_px(y + hauteur_ligne) - 1

        cv2.rectangle(image, (x1, y1), (x2, y2), (0, 0, 0), -1)

        y += hauteur_ligne

    numero_depart += questions_dans_colonne
    x_col += largeur_bloc + espace_entre_colonnes

cv2.imwrite('copie_remplie.jpg', image)

print("Image créée : copie_remplie.jpg")
print(f"Identification : {MATRICULE if MODE_IDENTIFICATION == 'matricule' else CODE_ANONYMAT} - {NOM} {PRENOM}")
print("Réponses simulées (à comparer avec le résultat OCR) :")
print(json.dumps(reponses_choisies, indent=2, ensure_ascii=False))
