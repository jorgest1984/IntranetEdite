<?php
error_reporting(E_ALL);
require 'includes/fpdf/fpdf.php';
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 15);
$pdf->Image('img/logo_efp.png', 10, 8, 30);
$pdf->Cell(40,10,'Hello World!');
$pdf->Output('F', 'test.pdf');
echo 'SUCCESS';
