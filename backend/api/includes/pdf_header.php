<?php
/**
 * Shared PDF Header with Institution Logos
 * 
 * This file contains the common header design for all PDF reports
 * featuring the IIT logo and NSTU logo with institution names.
 */

/**
 * Add institution header to PDF
 * 
 * @param TCPDF $pdf The TCPDF instance
 * @param string $reportTitle The title of the report
 * @param string $subtitle Optional subtitle text
 */
function addPdfHeader($pdf, $reportTitle, $subtitle = '') {
    $assetsPath = dirname(dirname(__DIR__)) . '/assets/';
    $iitLogo = $assetsPath . 'iit-logo.jpeg';
    // Use RGB PNG (no alpha channel) for NSTU logo
    $nstuLogo = $assetsPath . 'nstu_logo_rgb.png';
    
    // Get page width for calculations (A4 landscape = 297mm, portrait = 210mm)
    $pageWidth = $pdf->getPageWidth() - 20; // Subtract margins
    $logoWidth = 20;
    $logoHeight = 20;
    
    // Starting Y position
    $startY = 10;
    
    // ===== LEFT SIDE: IIT Logo and Text =====
    $leftX = 10;
    if (file_exists($iitLogo)) {
        try {
            $pdf->Image($iitLogo, $leftX, $startY, $logoWidth, $logoHeight, 'JPEG', '', '', true, 300);
        } catch (Exception $e) {
            // Skip logo if it fails to load
        }
    }
    
    // Left institution text (IIT)
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->SetXY($leftX + $logoWidth + 3, $startY + 6);
    $pdf->Cell(90, 6, 'Institute of Information Technology', 0, 1, 'L');
    
    // ===== RIGHT SIDE: NSTU Logo and Text =====
    $rightLogoX = $pageWidth - 10;
    $rightTextX = $pageWidth - 110;
    
    if (file_exists($nstuLogo)) {
        try {
            $pdf->Image($nstuLogo, $rightLogoX, $startY, $logoWidth, $logoHeight, 'PNG', '', '', true, 300);
        } catch (Exception $e) {
            // Skip logo if it fails to load
        }
    }
    
    // Right institution text (NSTU)
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->SetXY($rightTextX, $startY + 6);
    $pdf->Cell(90, 6, 'Noakhali Science & Technology University', 0, 1, 'R');
    
    // Horizontal line below header
    $pdf->SetY($startY + $logoHeight + 4);
    $pdf->SetDrawColor(37, 99, 235);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(10, $pdf->GetY(), $pageWidth + 10, $pdf->GetY());
    
    // Report Title
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(37, 99, 235);
    $pdf->Cell(0, 8, $reportTitle, 0, 1, 'C');
    
    // Subtitle if provided
    if (!empty($subtitle)) {
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 5, $subtitle, 0, 1, 'C');
    }
    
    // Generated date
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(0, 5, 'Generated: ' . date('F j, Y \a\t h:i A'), 0, 1, 'C');
    
    // Reset text color
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(3);
}

/**
 * Add PDF footer with page numbers
 * 
 * @param TCPDF $pdf The TCPDF instance
 */
function addPdfFooter($pdf) {
    $pdf->SetY(-15);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(0, 10, 'IITShelf Digital Library System - Page ' . $pdf->getAliasNumPage() . '/' . $pdf->getAliasNbPages(), 0, 0, 'C');
}
