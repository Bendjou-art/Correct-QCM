import cv2

DPI = 200
PX_PER_MM = DPI / 25.4

def mm_to_px(mm):
    return int(mm * PX_PER_MM)

image = cv2.imread('copie_remplie.jpg', cv2.IMREAD_GRAYSCALE)

# Zone Nom à tester
y1, y2 = mm_to_px(7), mm_to_px(14)
x1, x2 = mm_to_px(95), mm_to_px(185)

zone = image[y1:y2, x1:x2]
cv2.imwrite('debug_zone_nom.jpg', zone)
print("Zone sauvegardée : debug_zone_nom.jpg")
