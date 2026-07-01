<?php

/**
 * Geração e carimbo de PDF de contratos em PHP puro.
 * Usa FPDF (geração) + FPDI (importação de PDF existente).
 */

require_once __DIR__ . '/../lib/fpdf.php';
require_once __DIR__ . '/../lib/fpdi/autoload.php';

use setasign\Fpdi\Fpdi;

/**
 * Ponto de entrada principal.
 * Cenário A — sem PDF: gera PDF do texto + assinatura.
 * Cenário B — com PDF: importa PDF da empresa + carimba assinatura no campo CONTRATANTE.
 */
function contract_pdf_generate(array $row, string $signatureData, string $signerName, string $signedAt, string $outputPath): bool
{
    $outputDir = dirname($outputPath);
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    $prefix = 'data:image/png;base64,';
    if (!str_starts_with($signatureData, $prefix)) {
        return false;
    }
    $signatureBytes = base64_decode(substr($signatureData, strlen($prefix)), true);
    if ($signatureBytes === false || strlen($signatureBytes) < 8) {
        return false;
    }

    $sigTempPath = tempnam(sys_get_temp_dir(), 'arquidesk_sig_') . '.png';
    if (file_put_contents($sigTempPath, $signatureBytes) === false) {
        return false;
    }

    $hasPdf = !empty($row['source_file_stored'])
        && (($row['source_file_mime'] ?? '') === 'application/pdf'
            || strtolower(pathinfo($row['source_file_original'] ?? '', PATHINFO_EXTENSION)) === 'pdf');

    $sourcePdfPath = $hasPdf ? contract_original_file_path($row) : '';
    if ($hasPdf && !is_file($sourcePdfPath)) {
        $hasPdf = false;
    }

    try {
        $result = $hasPdf
            ? contract_pdf_stamp_existing($sourcePdfPath, $sigTempPath, $signerName, $signedAt, $outputPath)
            : contract_pdf_from_text($row['body'] ?? '', $row['title'] ?? 'Contrato', $sigTempPath, $signerName, $signedAt, $outputPath);
    } catch (Throwable $e) {
        @unlink($sigTempPath);
        return false;
    }

    @unlink($sigTempPath);
    return $result;
}

// ---------------------------------------------------------------------------
// CENÁRIO B — PDF da empresa: importa e carimba no campo CONTRATANTE
// ---------------------------------------------------------------------------

function contract_pdf_stamp_existing(string $sourcePath, string $sigImagePath, string $signerName, string $signedAt, string $outputPath): bool
{
    $pdf = new Fpdi();
    $pdf->SetAutoPageBreak(false);

    $pageCount = $pdf->setSourceFile($sourcePath);

    for ($i = 1; $i <= $pageCount; $i++) {
        $tpl   = $pdf->importPage($i);
        $size  = $pdf->getTemplateSize($tpl);
        $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
        $pdf->useTemplate($tpl);

        if ($i === $pageCount) {
            contract_pdf_stamp_contratante($pdf, $sigImagePath, $signerName, $signedAt, $size['width'], $size['height']);
        }
    }

    $pdf->Output('F', $outputPath);
    return is_file($outputPath) && filesize($outputPath) > 0;
}

/**
 * Posiciona a assinatura no bloco CONTRATANTE — lado esquerdo da última página,
 * na área de assinatura que contratos padrão reservam no rodapé.
 *
 * Layout típico:
 *   [ CONTRATANTE        ] [ CONTRATADO/EMPRESA ]
 *   [ _________________ ] [ __________________ ]
 *   [ Nome / Data        ] [ Nome empresa       ]
 *
 * A assinatura ocupa a metade esquerda, com margem de segurança.
 */
function contract_pdf_stamp_contratante(object $pdf, string $sigImagePath, string $signerName, string $signedAt, float $pageW, float $pageH): void
{
    // Bloco ocupa metade esquerda da página com margem
    $margin  = 14;
    $blockW  = ($pageW / 2) - $margin - 6; // metade esquerda com folga
    $blockH  = 46;
    $blockX  = $margin;
    $blockY  = $pageH - $margin - $blockH;

    // Fundo branco para cobrir qualquer linha pré-impressa do contrato
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect($blockX, $blockY, $blockW, $blockH, 'F');

    // Borda do bloco
    $pdf->SetDrawColor(30, 30, 30);
    $pdf->SetLineWidth(0.35);
    $pdf->Rect($blockX, $blockY, $blockW, $blockH);

    // Label
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetTextColor(30, 30, 30);
    $pdf->SetXY($blockX + 2, $blockY + 2);
    $pdf->Cell($blockW - 4, 4, 'CONTRATANTE - Assinado eletronicamente', 0, 0, 'L');

    // Linha separadora
    $pdf->SetDrawColor(210, 210, 210);
    $pdf->SetLineWidth(0.2);
    $pdf->Line($blockX + 2, $blockY + 7.5, $blockX + $blockW - 2, $blockY + 7.5);

    // Imagem da assinatura centralizada no bloco
    $sigW = $blockW - 10;
    $sigH = 22;
    $sigX = $blockX + 5;
    $sigY = $blockY + 9;

    if (is_file($sigImagePath) && filesize($sigImagePath) > 0) {
        try {
            $pdf->Image($sigImagePath, $sigX, $sigY, $sigW, $sigH, 'PNG');
        } catch (Throwable) {
            $pdf->SetDrawColor(180, 180, 180);
            $pdf->SetLineWidth(0.3);
            $pdf->Line($sigX + 4, $sigY + $sigH / 2, $sigX + $sigW - 4, $sigY + $sigH / 2);
        }
    }

    // Linha de assinatura
    $lineY = $blockY + 9 + $sigH + 1;
    $pdf->SetDrawColor(30, 30, 30);
    $pdf->SetLineWidth(0.3);
    $pdf->Line($blockX + 5, $lineY, $blockX + $blockW - 5, $lineY);

    // Nome
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->SetXY($blockX + 2, $lineY + 1.5);
    $pdf->Cell($blockW - 4, 3.5, contract_pdf_latin1(mb_substr($signerName, 0, 48)), 0, 1, 'C');

    // Data + plataforma
    $pdf->SetFont('Arial', '', 6.5);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetXY($blockX + 2, $lineY + 5.5);
    $pdf->Cell($blockW - 4, 3, 'Arquidesk · ' . $signedAt, 0, 0, 'C');
}

// ---------------------------------------------------------------------------
// CENÁRIO A — Sem PDF: gera contrato do texto com visual profissional
// ---------------------------------------------------------------------------

function contract_pdf_from_text(string $body, string $title, string $sigImagePath, string $signerName, string $signedAt, string $outputPath): bool
{
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(true, 30);
    $pdf->SetMargins(25, 25, 25);
    $pdf->AddPage();

    $pageW     = $pdf->GetPageWidth();
    $innerW    = $pageW - 50; // 25mm margem de cada lado
    $stampH    = 52;          // altura reservada para o bloco de assinatura

    // ── Cabeçalho ──────────────────────────────────────────────────────────
    $pdf->SetFont('Arial', 'B', 15);
    $pdf->SetTextColor(21, 32, 29);
    $pdf->MultiCell($innerW, 9, contract_pdf_latin1($title), 0, 'C');
    $pdf->Ln(2);

    // Linha decorativa abaixo do título
    $pdf->SetDrawColor(21, 32, 29);
    $pdf->SetLineWidth(0.6);
    $pdf->Line(25, $pdf->GetY(), $pageW - 25, $pdf->GetY());
    $pdf->Ln(6);

    // ── Corpo ───────────────────────────────────────────────────────────────
    $pdf->SetFont('Arial', '', 10.5);
    $pdf->SetTextColor(35, 35, 35);

    $paragraphs = explode("\n", str_replace("\r\n", "\n", $body));
    foreach ($paragraphs as $para) {
        $para = rtrim($para);

        // Linha em branco = espaço entre parágrafos
        if ($para === '') {
            $pdf->Ln(3);
            continue;
        }

        // Detecta título de seção (linha toda em maiúsculas ou começa com número + ponto)
        $isSection = (mb_strtoupper($para) === $para && mb_strlen($para) > 4)
                  || preg_match('/^\d+[\.\)]\s/', $para);

        if ($isSection) {
            // Garante espaço antes da seção
            if ($pdf->GetY() > 35) {
                $pdf->Ln(3);
            }
            $pdf->SetFont('Arial', 'B', 10.5);
            $pdf->SetTextColor(21, 32, 29);
            $pdf->MultiCell($innerW, 6.5, contract_pdf_latin1($para), 0, 'L');
            $pdf->SetFont('Arial', '', 10.5);
            $pdf->SetTextColor(35, 35, 35);
        } else {
            $pdf->MultiCell($innerW, 6.5, contract_pdf_latin1($para), 0, 'J');
        }
    }

    // ── Bloco de assinatura ─────────────────────────────────────────────────
    // Garante que o bloco cabe na página atual; senão, nova página
    $pdf->Ln(8);
    if ($pdf->GetY() + $stampH > $pdf->GetPageHeight() - 25) {
        $pdf->AddPage();
    }

    contract_pdf_draw_signature_block($pdf, $sigImagePath, $signerName, $signedAt);

    $pdf->Output('F', $outputPath);
    return is_file($outputPath) && filesize($outputPath) > 0;
}

/**
 * Bloco de assinatura para o Cenário A (contrato gerado do texto).
 * Ocupa a largura útil inteira, dividida em dois campos lado a lado:
 *   [ CONTRATANTE (assinatura desenhada) ] [ CONTRATADA (espaço em branco) ]
 */
function contract_pdf_draw_signature_block(object $pdf, string $sigImagePath, string $signerName, string $signedAt): void
{
    $pageW  = $pdf->GetPageWidth();
    $margin = 25;
    $innerW = $pageW - ($margin * 2);
    $colW   = ($innerW / 2) - 4; // cada coluna
    $blockH = 46;
    $startY = $pdf->GetY();

    // ── Linha de data/local ─────────────────────────────────────────────────
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetX($margin);
    $pdf->Cell($innerW, 6, contract_pdf_latin1('Local e data: ______________________________, _____ de ________________ de _______.'), 0, 1, 'L');
    $pdf->Ln(5);

    $startY = $pdf->GetY();

    // ── COLUNA ESQUERDA: CONTRATANTE ────────────────────────────────────────
    $colX = $margin;
    $colY = $startY;

    $pdf->SetFillColor(250, 249, 247);
    $pdf->SetDrawColor(30, 30, 30);
    $pdf->SetLineWidth(0.35);
    $pdf->Rect($colX, $colY, $colW, $blockH, 'FD');

    // Label
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->SetTextColor(21, 32, 29);
    $pdf->SetXY($colX + 3, $colY + 2.5);
    $pdf->Cell($colW - 6, 4, 'CONTRATANTE', 0, 0, 'L');

    // Badge "Assinado eletronicamente"
    $pdf->SetFont('Arial', '', 6);
    $pdf->SetTextColor(60, 120, 60);
    $pdf->SetXY($colX + 3, $colY + 7);
    $pdf->Cell($colW - 6, 3, contract_pdf_latin1('✓ Assinado eletronicamente via Arquidesk'), 0, 0, 'L');

    // Linha separadora
    $pdf->SetDrawColor(210, 210, 210);
    $pdf->SetLineWidth(0.2);
    $pdf->Line($colX + 3, $colY + 11, $colX + $colW - 3, $colY + 11);

    // Imagem da assinatura
    $sigW = $colW - 10;
    $sigH = 20;
    $sigX = $colX + 5;
    $sigY = $colY + 12.5;
    if (is_file($sigImagePath) && filesize($sigImagePath) > 0) {
        try {
            $pdf->Image($sigImagePath, $sigX, $sigY, $sigW, $sigH, 'PNG');
        } catch (Throwable) {
            $pdf->SetDrawColor(180, 180, 180);
            $pdf->SetLineWidth(0.3);
            $pdf->Line($sigX + 4, $sigY + $sigH / 2, $sigX + $sigW - 4, $sigY + $sigH / 2);
        }
    }

    // Linha de assinatura
    $lineY = $sigY + $sigH + 1;
    $pdf->SetDrawColor(30, 30, 30);
    $pdf->SetLineWidth(0.3);
    $pdf->Line($colX + 5, $lineY, $colX + $colW - 5, $lineY);

    // Nome e data
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->SetXY($colX + 3, $lineY + 1.5);
    $pdf->Cell($colW - 6, 3.5, contract_pdf_latin1(mb_substr($signerName, 0, 45)), 0, 1, 'C');

    $pdf->SetFont('Arial', '', 6.5);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->SetXY($colX + 3, $lineY + 5.5);
    $pdf->Cell($colW - 6, 3, 'Arquidesk · ' . $signedAt, 0, 0, 'C');

    // ── COLUNA DIREITA: CONTRATADA (espaço em branco) ───────────────────────
    $col2X = $margin + $colW + 8;
    $col2Y = $startY;

    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor(30, 30, 30);
    $pdf->SetLineWidth(0.35);
    $pdf->Rect($col2X, $col2Y, $colW, $blockH, 'FD');

    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->SetTextColor(21, 32, 29);
    $pdf->SetXY($col2X + 3, $col2Y + 2.5);
    $pdf->Cell($colW - 6, 4, 'CONTRATADA', 0, 0, 'L');

    // Linha de assinatura em branco
    $lineY2 = $col2Y + $blockH - 14;
    $pdf->SetDrawColor(30, 30, 30);
    $pdf->SetLineWidth(0.3);
    $pdf->Line($col2X + 5, $lineY2, $col2X + $colW - 5, $lineY2);

    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->SetXY($col2X + 3, $lineY2 + 1.5);
    $pdf->Cell($colW - 6, 3.5, contract_pdf_latin1('Assinatura do contratado'), 0, 0, 'C');
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function contract_pdf_latin1(string $text): string
{
    return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
}
