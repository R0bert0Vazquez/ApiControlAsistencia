<?php
// Evitar que se envíe cualquier salida antes del PDF
ob_start();

require('./vistas/fpdf186/fpdf.php');

// Verificar si la variable $titulo está definida en el ámbito global
$tituloReporte = isset($titulo) ? $titulo : 'Reporte';

class PDF extends FPDF
{
    // Propiedad para almacenar el título
    protected $reportTitle;

    // Método para establecer el título
    function setReportTitle($title)
    {
        $this->reportTitle = $title;
    }

    // Cabecera de página
    function Header()
    {
        // Logo (opcional)
        // $this->Image('logo.png',10,8,33);

        // Arial bold 15
        $this->SetFont('Arial', 'B', 15);

        // Título
        $this->Cell(0, 10, $this->reportTitle, 0, 1, 'C');

        // Salto de línea
        $this->Ln(10);
    }

    // Pie de página
    function Footer()
    {
        // Posición: a 1.5 cm del final
        $this->SetY(-15);
        // Arial italic 8
        $this->SetFont('Arial', 'I', 8);
        // Número de página
        $this->Cell(0, 10, 'Página ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Crear nuevo documento PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->setReportTitle(isset($titulo) ? $titulo : 'Reporte Generico Predeterminado');
$pdf->AddPage();

// Configuración inicial
$pdf->SetAutoPageBreak(true, 20);
$pdf->SetFont('Arial', '', 10);

// Función para obtener el ancho máximo de cada columna
function getColumnWidths($data, $headers)
{
    $widths = array();
    foreach ($headers as $header) {
        $maxWidth = strlen($header);
        foreach ($data as $row) {
            if (isset($row->$header)) {
                $maxWidth = max($maxWidth, strlen($row->$header));
            }
        }
        // Convertir caracteres a unidades de ancho (aproximadamente)
        $widths[$header] = $maxWidth * 2.5;
    }
    return $widths;
}

// Función para dibujar la tabla
function drawTable($pdf, $data, $headers, $widths)
{
    // Colores, línea y fuente
    $pdf->SetFillColor(232, 232, 232);
    $pdf->SetTextColor(0);
    $pdf->SetDrawColor(128, 128, 128);
    $pdf->SetLineWidth(.3);
    $pdf->SetFont('Arial', 'B', 10);

    // Cabecera
    foreach ($headers as $header) {
        // Convertir encabezado a Latin-1 para FPDF
        $header_latin1 = utf8_decode($header);
        $pdf->Cell($widths[$header], 7, $header_latin1, 1, 0, 'C', true);
    }
    $pdf->Ln();

    // Restaurar fuente para los datos
    $pdf->SetFont('Arial', '', 10);

    // Datos
    $fill = false;
    foreach ($data as $row) {
        $maxHeight = 0;
        $startX = $pdf->GetX();
        $startY = $pdf->GetY();

        // Calcular la altura máxima de la fila
        foreach ($headers as $header) {
            $value = isset($row->$header) ? (string) $row->$header : '';
            $value_latin1 = utf8_decode($value);
            $cellWidth = $widths[$header];

            // Usar un método de FPDF para estimar la altura requerida por MultiCell
            // Clonar FPDF o usar un enfoque similar no es práctico aquí.
            // Estimaremos usando GetStringWidth y una altura de línea fija.
            $textWidth = $pdf->GetStringWidth($value_latin1);
            $lines = $cellWidth > 0 ? ceil($textWidth / $cellWidth) : 1; // Evitar división por cero
            $cellHeight = $lines * 5; // Altura de línea estimada (5 unidades)
            $maxHeight = max($maxHeight, $cellHeight);
        }

        // Asegurar una altura mínima para la fila
        $rowHeight = $maxHeight > 5 ? $maxHeight : 5;

        // Dibujar celdas usando MultiCell
        $pdf->SetXY($startX, $startY); // Restablecer posición al inicio de la fila
        foreach ($headers as $header) {
            $value = isset($row->$header) ? (string) $row->$header : '';
            $value_latin1 = utf8_decode($value);
            $cellWidth = $widths[$header];

            // Dibujar la celda con MultiCell
            $pdf->MultiCell($cellWidth, 5, $value_latin1, 1, 'L', $fill, 0, '', '', true, 0, false, true, $rowHeight, 'M');

            // Mover a la posición X para la siguiente celda (GetX() después de MultiCell está al final del texto)
            // Necesitamos calcular la posición X de la siguiente celda manualmente
            $startX += $cellWidth;
            $pdf->SetXY($startX, $startY);
        }

        // Mover a la siguiente línea después de dibujar todas las celdas de la fila
        $pdf->SetY($startY + $rowHeight);

        $fill = !$fill;
    }
}

// Obtener datos JSON
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData);

if ($data && is_array($data) && count($data) > 0) {
    // Obtener los encabezados del primer objeto
    $headers = array_keys(get_object_vars($data[0]));

    // Calcular anchos de columna
    $widths = getColumnWidths($data, $headers);

    // Dibujar la tabla
    drawTable($pdf, $data, $headers, $widths);
} else {
    $pdf->Cell(0, 10, 'No hay datos para mostrar', 0, 1, 'C');
}

// Enviar el PDF
$pdf->Output();
?>