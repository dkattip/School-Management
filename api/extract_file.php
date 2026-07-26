<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Smalot\PdfParser\Parser;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Upload error: ' . $file['error']]);
    exit;
}

if ($file['size'] > 20 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large. Max 20MB.']);
    exit;
}

$text = '';

try {
    if ($ext === 'docx') {
        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) === true) {
            $xmlContent = $zip->getFromName('word/document.xml');
            if ($xmlContent !== false) {
                $doc = new DOMDocument();
                $doc->loadXML($xmlContent);
                $nodes = $doc->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 't');
                $paragraphs = $doc->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'p');
                
                $allText = [];
                foreach ($paragraphs as $p) {
                    $pNodes = $p->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 't');
                    $line = '';
                    foreach ($pNodes as $n) {
                        $line .= $n->textContent;
                    }
                    $allText[] = $line;
                }
                $text = implode("\n", $allText);
            }
            $zip->close();
        } else {
            throw new Exception('Failed to open DOCX file');
        }
    } elseif ($ext === 'pdf') {
        $parser = new Parser();
        $pdf = $parser->parseFile($file['tmp_name']);
        $text = $pdf->getText();
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Unsupported file type. Please upload .docx or .pdf files.']);
        exit;
    }

    if (empty(trim($text))) {
        echo json_encode(['error' => 'Could not extract any text from the file. The file may be image-based or encrypted.', 'text' => '']);
        exit;
    }

    echo json_encode(['success' => true, 'text' => $text, 'filename' => $file['name']]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error processing file: ' . $e->getMessage()]);
}
