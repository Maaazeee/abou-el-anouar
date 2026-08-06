<?php
// =====================================================
//  EXPORT PDF — généré avec FPDF (en-tête école)
// =====================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/fpdf/fpdf.php';

// Convertit UTF-8 → ISO-8859-1 pour les polices FPDF
function pdf_str($s) {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s ?? '');
}

class PDF_Ecole extends FPDF {
    private $doc_title = '';

    public function GetMarginLeft() { return $this->lMargin; }
    public function GetMarginRight() { return $this->rMargin; }

    public function set_doc_title($t) {
        $this->doc_title = $t;
    }

    function Header() {
        $logo = __DIR__ . '/../../images/logo_ecole.jpg';
        if (file_exists($logo)) {
            $this->Image($logo, 10, 8, 20);
        }
        $this->SetFont('Helvetica', 'B', 13);
        $this->SetTextColor(27, 58, 92);
        $this->Cell(0, 7, pdf_str('École Privée Abou el Anouar'), 0, 1, 'C');
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(120, 130, 145);
        $this->Cell(0, 5, pdf_str('294, Aïn Benian, Alger  |  0561 78 69 66  |  ecole.abou.anouar@gmail.com'), 0, 1, 'C');
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(201, 168, 76);
        $this->Cell(0, 6, pdf_str($this->doc_title), 0, 1, 'C');
        $this->SetDrawColor(201, 168, 76);
        $this->SetLineWidth(0.4);
        $this->Line(10, $this->GetY() + 1, 200, $this->GetY() + 1);
        $this->SetLineWidth(0.2);
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(120, 130, 145);
        $this->Cell(0, 10, pdf_str('École Abou el Anouar — Espace Surveillant — Page ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Génère et télécharge un PDF avec un tableau
function pdf_export($title, $headers, $rows, $filename) {
    $pdf = new PDF_Ecole();
    $pdf->AliasNbPages();
    $pdf->set_doc_title($title);
    $pdf->AddPage();

    $page_w = $pdf->GetPageWidth() - $pdf->GetMarginLeft() - $pdf->GetMarginRight();
    $n = max(1, count($headers));
    $w = $page_w / $n;
    $lh = 7;

    $draw_header = function () use ($pdf, $headers, $w, $lh) {
        $pdf->SetFillColor(27, 58, 92);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 8);
        foreach ($headers as $h) {
            $pdf->Cell($w, $lh, pdf_str($h), 1, 0, 'C', true);
        }
        $pdf->Ln();
        $pdf->SetTextColor(38, 50, 56);
        $pdf->SetFont('Helvetica', '', 8);
    };

    $draw_header();
    $fill = false;
    foreach ($rows as $r) {
        $vals = array_values($r);

        // Nouvelle page + ré-entête si nécessaire
        if ($pdf->GetY() + $lh > $pdf->GetPageHeight() - 20) {
            $pdf->AddPage();
            $draw_header();
            $fill = false;
        }

        $pdf->SetFillColor(244, 247, 251);
        for ($i = 0; $i < $n; $i++) {
            $v = $vals[$i] ?? '';
            $pdf->Cell($w, $lh, pdf_str((string)$v), 1, 0, 'L', $fill);
        }
        $pdf->Ln();
        $fill = !$fill;
    }

    $pdf->Output('D', $filename . '.pdf');
    exit;
}
