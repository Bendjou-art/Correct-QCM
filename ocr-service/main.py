from fastapi import FastAPI, UploadFile, File, Form
from fastapi.middleware.cors import CORSMiddleware
import cv2
import numpy as np
import json
import pytesseract
from PIL import Image

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

DPI = 200
PX_PER_MM = DPI / 25.4
A4_WIDTH_MM = 210
A4_HEIGHT_MM = 297

TARGET_WIDTH = int(A4_WIDTH_MM * PX_PER_MM)
TARGET_HEIGHT = int(A4_HEIGHT_MM * PX_PER_MM)


def mm_to_px(mm):
    return int(mm * PX_PER_MM)


def detecter_marqueurs_coins(image):
    """Détecte les 4 carrés noirs de calibration aux coins du formulaire.
    Retourne une liste de 4 points (x, y) dans l'ordre : haut-gauche, haut-droit, bas-gauche, bas-droit,
    ou None si moins de 4 marqueurs fiables sont trouvés."""

    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY) if len(image.shape) == 3 else image
    h, w = gray.shape[:2]

    _, binaire = cv2.threshold(gray, 100, 255, cv2.THRESH_BINARY_INV)
    contours, _ = cv2.findContours(binaire, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    candidats = []
    for c in contours:
        aire = cv2.contourArea(c)
        x, y, cw, ch = cv2.boundingRect(c)
        if ch == 0:
            continue
        ratio = cw / ch

        # Un marqueur de calibration : petit carré plein, presque carré, taille cohérente avec ~5mm à 200dpi
        aire_attendue = mm_to_px(5) * mm_to_px(5)
        if 0.4 * aire_attendue < aire < 3 * aire_attendue and 0.6 < ratio < 1.6:
            cx, cy = x + cw / 2, y + ch / 2
            candidats.append((cx, cy))

    if len(candidats) < 4:
        return None

    # Classer les candidats par quadrant (haut-gauche, haut-droit, bas-gauche, bas-droit)
    centre_x, centre_y = w / 2, h / 2

    hg = [p for p in candidats if p[0] < centre_x and p[1] < centre_y]
    hd = [p for p in candidats if p[0] >= centre_x and p[1] < centre_y]
    bg = [p for p in candidats if p[0] < centre_x and p[1] >= centre_y]
    bd = [p for p in candidats if p[0] >= centre_x and p[1] >= centre_y]

    if not (hg and hd and bg and bd):
        return None

    # Dans chaque quadrant, prendre le point le plus proche du coin réel de la page
    def plus_proche(points, coin):
        return min(points, key=lambda p: (p[0]-coin[0])**2 + (p[1]-coin[1])**2)

    pt_hg = plus_proche(hg, (0, 0))
    pt_hd = plus_proche(hd, (w, 0))
    pt_bg = plus_proche(bg, (0, h))
    pt_bd = plus_proche(bd, (w, h))

    return [pt_hg, pt_hd, pt_bg, pt_bd]


def redresser_image(image):
    """Redresse l'image par transformation de perspective en utilisant les 4 marqueurs de calibration.
    Si les marqueurs ne sont pas détectés de façon fiable, se rabat sur un simple resize."""

    marqueurs = detecter_marqueurs_coins(image)

    if marqueurs is None:
        # Repli : comportement précédent (resize simple si tailles très différentes)
        h, w = image.shape[:2]
        if abs(w - TARGET_WIDTH) < 30 and abs(h - TARGET_HEIGHT) < 30:
            return image
        return cv2.resize(image, (TARGET_WIDTH, TARGET_HEIGHT), interpolation=cv2.INTER_CUBIC)

    pt_hg, pt_hd, pt_bg, pt_bd = marqueurs

    points_source = np.float32([pt_hg, pt_hd, pt_bg, pt_bd])

    # Points de destination : positions exactes des marqueurs sur le PDF généré
    # Rappel formulaire-pdf.php : Rect(6,6,5,5), Rect(205,6,5,5), Rect(6,286,5,5), Rect(205,286,5,5)
    marge = mm_to_px(6) + mm_to_px(2.5)  # centre du marqueur ≈ coin + moitié de sa taille
    marge_droite = mm_to_px(205) + mm_to_px(2.5)
    marge_bas = mm_to_px(286) + mm_to_px(2.5)

    points_dest = np.float32([
        [marge, marge],
        [marge_droite, marge],
        [marge, marge_bas],
        [marge_droite, marge_bas]
    ])

    matrice = cv2.getPerspectiveTransform(points_source, points_dest)
    image_redressee = cv2.warpPerspective(image, matrice, (TARGET_WIDTH, TARGET_HEIGHT), flags=cv2.INTER_CUBIC, borderValue=(255, 255, 255))

    return image_redressee

def calculer_taux_noir(image_couleur, x_mm, y_mm, largeur_mm, hauteur_mm):
    """Mesure le pourcentage de pixels marqués (non-blancs) dans une cellule,
    peu importe la couleur du stylo (noir, bleu, rouge...)."""
    x1 = mm_to_px(x_mm) + 1
    y1 = mm_to_px(y_mm) + 1
    x2 = mm_to_px(x_mm + largeur_mm) - 1
    y2 = mm_to_px(y_mm + hauteur_mm) - 1

    if x2 <= x1 or y2 <= y1:
        return 0.0

    h, w = image_couleur.shape[:2]
    x1, x2 = max(0, x1), min(w, x2)
    y1, y2 = max(0, y1), min(h, y2)

    zone = image_couleur[y1:y2, x1:x2]
    if zone.size == 0:
        return 0.0

    # Distance par rapport au blanc pur (255,255,255) sur chaque pixel
    blanc = np.array([255, 255, 255])
    distance = np.linalg.norm(zone.astype(int) - blanc, axis=2)

    # Un pixel est considéré "marqué" si sa distance au blanc dépasse un seuil
    pixels_marques = np.count_nonzero(distance > 60)
    return pixels_marques / (zone.shape[0] * zone.shape[1])

def calculer_repartition_colonnes(nb_questions):
    """Reproduit exactement calculerRepartitionColonnes() du PHP."""
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


def extraire_reponses(image_couleur, nb_questions, options_labels, mode_identification):
    """Reproduit exactement la géométrie de formulaire-pdf.php (version multi-colonnes inégales)."""

    est_anonymat = (mode_identification == 'anonymat')
    marge_haut = 97 if est_anonymat else 88

    repartition = calculer_repartition_colonnes(nb_questions)
    nb_options = len(options_labels)

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

    resultats = []
    x_col = 10
    numero_depart = 1

    for questions_dans_colonne in repartition:
        y = marge_haut + hauteur_entete_col  # on saute l'en-tête de colonne

        for i in range(questions_dans_colonne):
            num_question = numero_depart + i
            if num_question > nb_questions:
                break

            reponses_cochees = []
            for idx, label in enumerate(options_labels):
                x_case = x_col + largeur_num + (idx * largeur_option)
                taux = calculer_taux_noir(image_couleur, x_case, y, largeur_option, hauteur_ligne)

                if taux > 0.06:
                    reponses_cochees.append({"label": label, "confiance": round(taux * 100, 1)})

            resultats.append({
                "numero_question": num_question,
                "reponses_cochees": [r["label"] for r in reponses_cochees],
                "confidence_score": max([r["confiance"] for r in reponses_cochees], default=0)
            })

            y += hauteur_ligne

        numero_depart += questions_dans_colonne
        x_col += largeur_bloc + espace_entre_colonnes

    return resultats


def extraire_identification(image_gray, mode_identification):
    """OCR ciblé sur les zones exactes où le texte est écrit (pas les libellés imprimés)."""
    h, w = image_gray.shape[:2]

    if mode_identification == 'anonymat':
        # Zone code d'anonymat dans le cadre "Ne rien écrire"
        y1, y2 = mm_to_px(44), mm_to_px(50)
        x1, x2 = mm_to_px(171), mm_to_px(198)
        y1, y2 = max(0, y1), min(h, y2)
        x1, x2 = max(0, x1), min(w, x2)

        zone = image_gray[y1:y2, x1:x2]
        pil_image = Image.fromarray(zone)
        texte = pytesseract.image_to_string(pil_image, lang='fra', config='--psm 11')
        return texte.strip()
    else:
        # Zone Matricule (dans le cadre encadré)
        ym1, ym2 = mm_to_px(24), mm_to_px(31)
        xm1, xm2 = mm_to_px(31), mm_to_px(74)
        ym1, ym2 = max(0, ym1), min(h, ym2)
        xm1, xm2 = max(0, xm1), min(w, xm2)

        zone_matricule = image_gray[ym1:ym2, xm1:xm2]
        pil_matricule = Image.fromarray(zone_matricule)
        texte_matricule = pytesseract.image_to_string(pil_matricule, lang='fra', config='--psm 11')

        # Zone Nom (ligne "Nom : ...")
        yn1, yn2 = mm_to_px(7), mm_to_px(12)
        xn1, xn2 = mm_to_px(95), mm_to_px(195)
        yn1, yn2 = max(0, yn1), min(h, yn2)
        xn1, xn2 = max(0, xn1), min(w, xn2)

        zone_nom = image_gray[yn1:yn2, xn1:xn2]
        zone_nom = cv2.GaussianBlur(zone_nom, (3, 3), 0)
        _, zone_nom = cv2.threshold(zone_nom, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
        pil_nom = Image.fromarray(zone_nom)
        texte_nom = pytesseract.image_to_string(pil_nom, lang='fra', config='--psm 11')

        # Zone Prénoms (ligne "Prénoms : ...")
        yp1, yp2 = mm_to_px(12), mm_to_px(17)
        xp1, xp2 = mm_to_px(95), mm_to_px(195)
        yp1, yp2 = max(0, yp1), min(h, yp2)
        xp1, xp2 = max(0, xp1), min(w, xp2)

        zone_prenom = image_gray[yp1:yp2, xp1:xp2]
        zone_prenom = cv2.GaussianBlur(zone_prenom, (3, 3), 0)
        _, zone_prenom = cv2.threshold(zone_prenom, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
        pil_prenom = Image.fromarray(zone_prenom)
        texte_prenom = pytesseract.image_to_string(pil_prenom, lang='fra', config='--psm 11')

        texte_nom = texte_nom.strip() + ' Prenoms : ' + texte_prenom.strip()

        return (texte_matricule.strip() + ' Nom : ' + texte_nom.strip())


@app.post("/process")
async def process_copie(
    file: UploadFile = File(...),
    nb_questions: int = Form(...),
    options_labels: str = Form(...),
    mode_identification: str = Form(...)
):
    contenu = await file.read()
    nparr = np.frombuffer(contenu, np.uint8)
    image = cv2.imdecode(nparr, cv2.IMREAD_COLOR)

    if image is None:
        return {"success": False, "message": "Image illisible"}

    image_redim = redresser_image(image)
    image_gray = cv2.cvtColor(image_redim, cv2.COLOR_BGR2GRAY)

    labels = json.loads(options_labels)

    reponses = extraire_reponses(image_redim, nb_questions, labels, mode_identification)
    identification_texte = extraire_identification(image_gray, mode_identification)

    return {
        "success": True,
        "identification_brute": identification_texte,
        "reponses": reponses
    }


@app.get("/health")
async def health():
    return {"status": "ok"}