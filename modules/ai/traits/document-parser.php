<?php
namespace Aiutoma\Modules\Ai\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait DocumentParser {

    /**
     * Parse a document and extract its text content using PHP libraries.
     * Supported formats: PDF, DOCX, XLSX, CSV, TXT
     *
     * @param string $file_path Absolute path to the file
     * @param string $mime_type Optional mime type if known
     * @return string Extracted text content or error message string.
     */
    public function parse_document($file_path, $mime_type = '') {
        if (!file_exists($file_path)) {
            return "Error: File not found.";
        }

        if (empty($mime_type) && function_exists('wp_check_filetype')) {
            $wp_filetype = wp_check_filetype($file_path);
            $mime_type = $wp_filetype['type'];
        }
        
        if (empty($mime_type)) {
            $mime_type = mime_content_type($file_path);
        }

        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }

        $text = "";

        try {
            switch ($mime_type) {
                case 'application/pdf':
                    if (class_exists('\\Smalot\\PdfParser\\Parser')) {
                        $parser = new \Smalot\PdfParser\Parser();
                        $pdf = $parser->parseFile($file_path);
                        $text = $pdf->getText();
                    } else {
                        return "Error: smalot/pdfparser library is not installed.";
                    }
                    break;

                case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                case 'application/msword': // Note: MSWord .doc binary files are not fully supported via ZipArchive.
                    if (class_exists('ZipArchive')) {
                        $zip = new \ZipArchive();
                        if ($zip->open($file_path) === true) {
                            if (($index = $zip->locateName('word/document.xml')) !== false) {
                                $xml_data = $zip->getFromIndex($index);
                                // Add newlines after paragraphs for basic formatting
                                $xml_data = str_replace("</w:p>", "</w:p>\n", $xml_data);
                                $text = trim(wp_strip_all_tags($xml_data));
                            } else {
                                return "Error: Could not locate word/document.xml in DOCX archive.";
                            }
                            $zip->close();
                        } else {
                            return "Error: Could not open DOCX file as ZIP archive.";
                        }
                    } else {
                        return "Error: PHP ZipArchive extension is not installed on this server.";
                    }
                    break;
                    
                case 'application/vnd.oasis.opendocument.presentation':
                case 'application/vnd.oasis.opendocument.text':
                    if (class_exists('ZipArchive')) {
                        $zip = new \ZipArchive();
                        if ($zip->open($file_path) === true) {
                            if (($index = $zip->locateName('content.xml')) !== false) {
                                $xml_data = $zip->getFromIndex($index);
                                // Add newlines after paragraphs for basic formatting
                                $xml_data = str_replace(["</text:p>", "</text:h>"], ["</text:p>\n", "</text:h>\n"], $xml_data);
                                $text = trim(wp_strip_all_tags($xml_data));
                            } else {
                                return "Error: Could not locate content.xml in ODT/ODP archive.";
                            }
                            $zip->close();
                        } else {
                            return "Error: Could not open ODT/ODP file as ZIP archive.";
                        }
                    } else {
                        return "Error: PHP ZipArchive extension is not installed on this server.";
                    }
                    break;

                case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
                    if (class_exists('ZipArchive')) {
                        $zip = new \ZipArchive();
                        if ($zip->open($file_path) === true) {
                            $slide_texts = [];
                            for ($i = 0; $i < $zip->numFiles; $i++) {
                                $filename = $zip->getNameIndex($i);
                                if (preg_match('/^ppt\/slides\/slide\d+\.xml$/', $filename)) {
                                    $xml_data = $zip->getFromIndex($i);
                                    // Add newlines after paragraphs for basic formatting
                                    $xml_data = str_replace("</a:p>", "</a:p>\n", $xml_data);
                                    $slide_texts[$filename] = trim(wp_strip_all_tags($xml_data));
                                }
                            }
                            
                            // Sort slides by number so they appear in correct order
                            uksort($slide_texts, function($a, $b) {
                                preg_match('/slide(\d+)\.xml/', $a, $ma);
                                preg_match('/slide(\d+)\.xml/', $b, $mb);
                                return (int)$ma[1] - (int)$mb[1];
                            });
                            
                            foreach ($slide_texts as $filename => $slide_text) {
                                preg_match('/slide(\d+)\.xml/', $filename, $m);
                                $text .= "## Slide " . $m[1] . "\n\n" . $slide_text . "\n\n";
                            }
                            
                            $zip->close();
                        } else {
                            return "Error: Could not open PPTX file as ZIP archive.";
                        }
                    } else {
                        return "Error: PHP ZipArchive extension is not installed on this server.";
                    }
                    break;

                case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                    if (class_exists('\\Shuchkin\\SimpleXLSX')) {
                        if ($xlsx = \Shuchkin\SimpleXLSX::parse($file_path)) {
                            foreach ($xlsx->sheetNames() as $sheetIndex => $sheetName) {
                                $text .= "## Sheet: " . $sheetName . "\n\n";
                                $text .= "|";
                                $firstRow = true;
                                foreach ($xlsx->rows($sheetIndex) as $row) {
                                    $rowText = [];
                                    foreach ($row as $cell) {
                                        $val = str_replace(["\n", "\r"], " ", (string)$cell);
                                        $rowText[] = trim($val);
                                    }
                                    
                                    $text .= implode(" | ", $rowText) . " |\n";
                                    
                                    if ($firstRow) {
                                        $text .= "|" . implode("|", array_fill(0, count($rowText), "---")) . "|\n";
                                        $firstRow = false;
                                    }
                                }
                                $text .= "\n\n";
                            }
                        } else {
                            return "Error parsing Excel (XLSX): " . \Shuchkin\SimpleXLSX::parseError();
                        }
                    } else {
                        return "Error: shuchkin/simplexlsx library is not installed.";
                    }
                    break;

                case 'application/vnd.ms-excel':
                    if (class_exists('\\Shuchkin\\SimpleXLS')) {
                        if ($xls = \Shuchkin\SimpleXLS::parse($file_path)) {
                            foreach ($xls->sheetNames() as $sheetIndex => $sheetName) {
                                $text .= "## Sheet: " . $sheetName . "\n\n";
                                $text .= "|";
                                $firstRow = true;
                                foreach ($xls->rows($sheetIndex) as $row) {
                                    $rowText = [];
                                    foreach ($row as $cell) {
                                        $val = str_replace(["\n", "\r"], " ", (string)$cell);
                                        $rowText[] = trim($val);
                                    }
                                    
                                    $text .= implode(" | ", $rowText) . " |\n";
                                    
                                    if ($firstRow) {
                                        $text .= "|" . implode("|", array_fill(0, count($rowText), "---")) . "|\n";
                                        $firstRow = false;
                                    }
                                }
                                $text .= "\n\n";
                            }
                        } else {
                            return "Error parsing Excel (XLS): " . \Shuchkin\SimpleXLS::parseError();
                        }
                    } else {
                        return "Error: shuchkin/simplexls library is not installed.";
                    }
                    break;

                case 'text/csv':
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
                    if (($handle = fopen($file_path, "r")) !== FALSE) {
                        $firstRow = true;
                        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
                            $rowText = [];
                            foreach ($data as $cell) {
                                $val = str_replace(["\n", "\r"], " ", (string)$cell);
                                $rowText[] = trim($val);
                            }
                            $text .= "| " . implode(" | ", $rowText) . " |\n";
                            if ($firstRow) {
                                $text .= "|" . implode("|", array_fill(0, count($rowText), "---")) . "|\n";
                                $firstRow = false;
                            }
                        }
                        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
                        fclose($handle);
                    } else {
                        return "Error: Could not read CSV file.";
                    }
                    break;

                case 'text/plain':
                    $text = file_get_contents($file_path);
                    break;

                default:
                    return "Error: Unsupported mime type ({$mime_type}).";
            }
        } catch (\Exception $e) {
            return "Error parsing document: " . $e->getMessage();
        }

        return trim($text);
    }
}
