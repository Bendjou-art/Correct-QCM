<?php
require '../../vendor/autoload.php';
require '../../config/database.php';

$qcm_id = $_GET['id'] ?? null;
if (!$qcm_id) {
    die('ID du QCM manquant');
}

$pdo = getConnection();
$stmt = $pdo->prepare("SELECT * FROM qcms WHERE id = ?");
$stmt->execute([$qcm_id]);
$qcm = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$qcm) {
    die('QCM introuvable');
}

$nbQuestions = (int) $qcm['nb_questions'];
$typeEvaluation = $qcm['type_evaluation'] ?? '';
$estSN = (stripos($typeEvaluation, 'normale') !== false);
$estCC = (stripos($typeEvaluation, 'continu') !== false);
$estAnonymat = ($qcm['mode_identification'] === 'anonymat');

$cachetUrl = null;
$stmtCachet = $pdo->prepare("SELECT cachet_url FROM users WHERE id = ?");
$stmtCachet->execute([$qcm['id_enseignant']]);
$cachetUrl = $stmtCachet->fetchColumn();

$listeEtudiants = [];
if ($estCC) {
    $stmtEtu = $pdo->prepare("SELECT matricule, nom FROM etudiants_qcm WHERE id_qcm = ? ORDER BY nom");
    $stmtEtu->execute([$qcm_id]);
    $listeEtudiants = $stmtEtu->fetchAll(PDO::FETCH_ASSOC);
}

function calculerRepartitionColonnes(int $nbQuestions): array
{
    if ($nbQuestions == 10) {
        $nbColonnes = 2;
    } elseif ($nbQuestions <= 25) {
        $nbColonnes = 1;
    } elseif ($nbQuestions <= 50) {
        $nbColonnes = 2;
    } else {
        $nbColonnes = 3;
    }

    $base = intdiv($nbQuestions, $nbColonnes);
    $reste = $nbQuestions % $nbColonnes;

    $repartition = [];
    for ($i = 0; $i < $nbColonnes; $i++) {
        $repartition[] = $base + ($i < $reste ? 1 : 0);
    }

    return $repartition;
}

function genererGrilleReponses(TCPDF $pdf, int $nbQuestions, float $margeHaut): void
{
    // Marqueurs de calibration
    $pdf->SetFillColor(0, 0, 0);
    $pdf->Rect(6, 6, 5, 5, 'F');
    $pdf->Rect(205, 6, 5, 5, 'F');
    $pdf->Rect(6, 286, 5, 5, 'F');
    $pdf->Rect(205, 286, 5, 5, 'F');

    $repartition = calculerRepartitionColonnes($nbQuestions);
    $nbColonnes = count($repartition);
    $optionsLabels = ['A', 'B', 'C', 'D', 'E', 'Y', 'Z'];
    $nbOptions = count($optionsLabels);

    $currentY = $margeHaut;
    $largeurUtile = 191;
    $espaceEntreColonnes = 5;
    $largeurBloc = ($largeurUtile - ($espaceEntreColonnes * ($nbColonnes - 1))) / $nbColonnes;
    $largeurNum = 7;
    $largeurOption = ($largeurBloc - $largeurNum) / $nbOptions;

    $hauteurEnTeteCol = 5.5;
    $hauteurDisponible = 283 - $currentY;

    $maxQuestionsParColonne = max($repartition);
    $hauteurLigne = ($hauteurDisponible - $hauteurEnTeteCol) / $maxQuestionsParColonne;
    $hauteurLigne = min($hauteurLigne, 7);

    $pdf->SetLineWidth(0.15);
    $pdf->SetDrawColor(0, 0, 0);

    $xCol = 10;
    $numeroDepart = 1;

    foreach ($repartition as $questionsDansColonne) {
        $y = $currentY;

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Rect($xCol, $y, $largeurNum, $hauteurEnTeteCol);
        $pdf->SetXY($xCol, $y);
        $pdf->Cell($largeurNum, $hauteurEnTeteCol, 'N°', 0, 0, 'C');

        foreach ($optionsLabels as $idx => $label) {
            $x = $xCol + $largeurNum + ($idx * $largeurOption);
            $pdf->Rect($x, $y, $largeurOption, $hauteurEnTeteCol);
            $pdf->SetXY($x, $y);
            $pdf->Cell($largeurOption, $hauteurEnTeteCol, $label, 0, 0, 'C');
        }

        $y += $hauteurEnTeteCol;

        $pdf->SetFont('helvetica', '', 6.5);
        for ($i = 0; $i < $questionsDansColonne; $i++) {
            $numQuestion = $numeroDepart + $i;

            $pdf->Rect($xCol, $y, $largeurNum, $hauteurLigne);
            $pdf->SetXY($xCol, $y);
            $pdf->Cell($largeurNum, $hauteurLigne, $numQuestion, 0, 0, 'C');

            foreach ($optionsLabels as $idx => $label) {
                $x = $xCol + $largeurNum + ($idx * $largeurOption);
                $pdf->Rect($x, $y, $largeurOption, $hauteurLigne);
            }

            $y += $hauteurLigne;
        }

        $numeroDepart += $questionsDansColonne;
        $xCol += $largeurBloc + $espaceEntreColonnes;
    }

    $pdf->SetXY(10, 291);
    $pdf->SetFont('helvetica', 'I', 6);
    $pdf->Cell(190, 3, 'Formulaire généré automatiquement - Tailor Correct', 0, 1, 'C');
}

class FormulairePDF extends TCPDF
{
    public $qcmData = [];
    public $estSN = false;
    public $estAnonymat = false;
    public $cachetPath = null;
    public $margeHautGrille = 88;
    public $etudiantCourant = null;

    public function Header()
    {
        $logoPath = __DIR__ . '/../../assets/img/logo.png';

        if (file_exists($logoPath)) {
            $this->Image($logoPath, 10, 22, 16, 16);
        }
        $this->SetFont('helvetica', 'B', 11);
        $this->SetXY(10, 8);
        $this->Cell(85, 5, 'UNIVERSITÉ DE YAOUNDÉ I', 0, 1, 'L');
        $this->SetX(10);
        $this->Cell(85, 5, 'THE UNIVERSITY OF YAOUNDE I', 0, 1, 'L');

        // Bloc Matricule encadré
        $this->SetFont('helvetica', 'B', 9);
        $this->SetXY(30, 18);
        $this->Cell(35, 5, 'Matricule', 0, 1, 'L');
        $this->Rect(30, 23, 45, 9);
        if ($this->etudiantCourant) {
            $this->SetFont('helvetica', 'B', 11);
            $this->SetXY(33, 25);
            $this->Cell(40, 5, $this->etudiantCourant['matricule'], 0, 1, 'L');
        }

        // Niveau / Semestre
        $this->SetFont('helvetica', '', 8);
        $this->SetXY(10, 40);
        $this->Cell(80, 4, 'Niveau (1) : ' . str_repeat('.', 25), 0, 1, 'L');
        $this->SetX(10);
        $this->Cell(80, 4, 'Semestre (2) : ' . str_repeat('.', 22), 0, 1, 'L');
        $this->SetFont('helvetica', 'I', 6);
        $this->SetX(10);
        $this->Cell(80, 3, '(mois et année)', 0, 1, 'L');

        // Bloc identité (colonne droite)
        $xDroite = 88;
        $this->SetFont('helvetica', '', 8);
        $this->SetXY($xDroite, 8);
        if ($this->etudiantCourant) {
            $this->Cell(80, 4.5, 'Nom : ' . $this->etudiantCourant['nom'], 0, 1, 'L');
        } else {
            $this->Cell(80, 4.5, 'Nom : ' . str_repeat('.', 40), 0, 1, 'L');
        }
        $this->SetX($xDroite);
        $this->Cell(80, 4.5, 'Prénoms : ' . str_repeat('.', 38), 0, 1, 'L');
        $this->SetX($xDroite);
        $this->Cell(80, 4.5, 'Date et lieu de naissance : ' . str_repeat('.', 20), 0, 1, 'L');
        $this->SetX($xDroite);
        $this->Cell(80, 4.5, 'Unité de valeur (code et nom) : ' . str_repeat('.', 15), 0, 1, 'L');
        $this->SetX($xDroite);
        $this->Cell(80, 3, '', 0, 1, 'L');
        $this->SetX($xDroite);
        $this->Cell(80, 4.5, 'Filière : ' . str_repeat('.', 35), 0, 1, 'L');
        $this->SetX($xDroite);
        $this->Cell(80, 4.5, 'Journée du : ' . str_repeat('.', 33), 0, 1, 'L');

        // Cadre "Ne rien écrire dans ce cadre"
        $xCadre = 170;
        $largeurCadre = 30;
        $yCadreDebut = 40;
        $yCadreFin = 76;

        $this->Rect($xCadre, $yCadreDebut, $largeurCadre, $yCadreFin - $yCadreDebut);

        $this->SetFont('helvetica', '', 6.5);
        $this->SetXY($xCadre + 1, $yCadreDebut + 2);
        $this->Cell($largeurCadre - 2, 3, 'Ne rien écrire dans ce cadre', 0, 1, 'L');
        $this->SetX($xCadre + 1);
        $this->Cell($largeurCadre - 2, 3, 'N°' . str_repeat('.', 15), 0, 1, 'L');

        $this->SetY(52);
        $this->SetFont('helvetica', 'I', 6.5);
        $this->Cell(0, 3, '(Rabattre l\'en-tête ci-dessus sans le détacher. Il est interdit de signer sa composition.)', 0, 1, 'C');
        $this->SetLineStyle(['width' => 0.3, 'dash' => '2,2']);
        $this->Line(10, 56, 200, 56);
        $this->SetLineStyle(['width' => 0.2]);

        if ($this->cachetPath && file_exists($this->cachetPath)) {
            $this->Image($this->cachetPath, 145, 37, 20, 18);
        }

        $this->SetFont('helvetica', '', 6.5);
        $this->SetXY($xCadre + 1, 59);
        $this->Cell($largeurCadre - 2, 3, 'N°:' . str_repeat('.', 15), 0, 1, 'L');

        $this->SetFont('helvetica', '', 8);
        $this->SetXY(10, 60);
        $this->Cell(90, 4.5, 'Filière : ' . str_repeat('.', 30), 0, 0, 'L');
        $this->SetXY(110, 60);
        $this->Cell(55, 4.5, 'Niveau : ' . str_repeat('.', 20), 0, 1, 'L');

        $this->SetXY(10, 65);
        $this->Cell(90, 4.5, 'UE (code et nom) : ' . str_repeat('.', 30), 0, 1, 'L');

        $this->SetXY(10, 70);
        $this->Cell(90, 4.5, 'Journée du : ' . str_repeat('.', 30), 0, 1, 'L');

        $yApres = 78;
        $this->SetY($yApres);
        $this->SetFont('helvetica', 'B', 9);
        $this->Cell(0, 4.5, htmlspecialchars($this->qcmData['matiere']) . ' - ' . htmlspecialchars($this->qcmData['type_evaluation']), 0, 1, 'C');

        $this->SetFont('helvetica', 'B', 7.5);
        $this->Cell(0, 4, 'Instruction QCM : Y = Aucune réponse    Z = Je ne sais pas', 0, 1, 'L');
    }
}

$pdf = new FormulairePDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->qcmData = $qcm;
$pdf->estSN = $estSN;
$pdf->estAnonymat = $estAnonymat;
$pdf->cachetPath = $cachetUrl ? realpath('../../' . $cachetUrl) : null;

$margeHaut = $estAnonymat ? 97 : 88;
$pdf->margeHautGrille = $margeHaut;
$pdf->SetMargins(10, $margeHaut, 10);
$pdf->SetAutoPageBreak(false, 0);
$pdf->SetPrintHeader(true);
$pdf->SetPrintFooter(false);

if ($estCC && !empty($listeEtudiants)) {
    foreach ($listeEtudiants as $etu) {
        $pdf->etudiantCourant = $etu;
        $pdf->AddPage();
        genererGrilleReponses($pdf, $nbQuestions, $margeHaut);
    }
} else {
    $pdf->etudiantCourant = null;
    $pdf->AddPage();
    genererGrilleReponses($pdf, $nbQuestions, $margeHaut);
}

$nomFichier = $estCC && !empty($listeEtudiants)
    ? 'formulaires_qcm_' . $qcm_id . '.pdf'
    : 'formulaire_qcm_' . $qcm_id . '.pdf';

$pdf->Output($nomFichier, 'I');