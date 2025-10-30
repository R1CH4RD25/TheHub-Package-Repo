<?php

namespace Hub\Modules;

use Hub\Database;
use Hub\Auth;
use Hub\AuditLogger;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Exception;

require_once __DIR__ . '/../bootstrap.php';

/**
 * PDFGeneratorRenderer - Generate PDF documents from templates
 * 
 * Implements PDFGenerator module type from MODULE_CATALOG_V2.md
 * 
 * Use cases:
 * - Employee evaluation reports
 * - Purchase order documents
 * - Certificates and awards
 * - Invoices and receipts
 * - Custom reports with data
 * 
 * Features:
 * - HTML template rendering with mPDF
 * - Variable substitution {firstName}, {date}, etc.
 * - Custom headers and footers
 * - Page numbers and watermarks
 * - Portrait/landscape orientation
 * - Custom paper sizes
 * - File storage with signed URLs
 * - Direct download or save to disk
 * - Multi-tenant file isolation
 * 
 * @author The Hub Team
 * @version 1.0.0
 */
class PDFGeneratorRenderer implements ModuleInterface
{
    private array $config;
    private Database $db;
    private ?array $data = null;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->db = Database::getInstance();
    }
    
    /**
     * Render PDF configuration interface
     * 
     * PDFGenerator modules don't have a visual UI,
     * but we can show configuration and generate button.
     * 
     * @return string HTML output
     */
    public function render(): string
    {
        if (!$this->validate()) {
            return '<div class="alert alert-danger">Invalid PDF generator configuration</div>';
        }
        
        $html = '<div class="pdf-generator-info">';
        $html .= '<div class="alert alert-info">';
        $html .= '<h5><i class="bi bi-file-pdf me-2"></i>PDF Generator Module</h5>';
        $html .= '<p class="mb-0">This module generates PDF documents programmatically. ';
        $html .= 'It is typically triggered by other modules or actions.</p>';
        $html .= '</div>';
        
        // Show configuration (for admins)
        if (Auth::hasRole('admin') || Auth::hasRole('super_admin')) {
            $html .= '<div class="card">';
            $html .= '<div class="card-header"><h6 class="mb-0">Configuration</h6></div>';
            $html .= '<div class="card-body">';
            $html .= '<dl class="row mb-0">';
            
            $html .= '<dt class="col-sm-3">Filename</dt>';
            $html .= '<dd class="col-sm-9">' . htmlspecialchars($this->config['filename'] ?? 'document.pdf') . '</dd>';
            
            $html .= '<dt class="col-sm-3">Orientation</dt>';
            $html .= '<dd class="col-sm-9">' . htmlspecialchars($this->config['orientation'] ?? 'portrait') . '</dd>';
            
            $html .= '<dt class="col-sm-3">Template</dt>';
            $html .= '<dd class="col-sm-9">' . htmlspecialchars($this->config['template'] ?? 'inline') . '</dd>';
            
            $html .= '</dl>';
            $html .= '</div>';
            $html .= '</div>';
            
            // Test generation button
            $html .= '<div class="mt-3">';
            $html .= '<form method="POST">';
            $html .= '<input type="hidden" name="action" value="generate_test">';
            $html .= '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'] ?? '') . '">';
            $html .= '<button type="submit" class="btn btn-primary">';
            $html .= '<i class="bi bi-file-pdf me-2"></i>Generate Test PDF';
            $html .= '</button>';
            $html .= '</form>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Handle PDF generation
     * 
     * @param array $data Data for template substitution
     * @return array Result
     */
    public function handle(array $data): array
    {
        if (!$this->validate()) {
            return [
                'success' => false,
                'error' => 'Invalid PDF configuration'
            ];
        }
        
        $this->data = $data;
        
        try {
            // Generate PDF
            $result = $this->generatePDF($data);
            
            // Log generation
            AuditLogger::log('pdf_generated', 'pdf_generator', $this->config['id'] ?? 'unknown', [
                'filename' => $result['filename'] ?? 'unknown',
                'size' => $result['size'] ?? 0
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("PDF generation error: " . $e->getMessage());
            
            // Log failure
            AuditLogger::log('pdf_failed', 'pdf_generator', $this->config['id'] ?? 'unknown', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to generate PDF'
            ];
        }
    }
    
    /**
     * Generate PDF document
     * 
     * @param array $data Template data
     * @return array Result with file path
     */
    private function generatePDF(array $data): array
    {
        // Render HTML content
        $html = $this->renderTemplate($data);
        
        // Configure mPDF
        $config = $this->getMpdfConfig();
        $mpdf = new Mpdf($config);
        
        // Set metadata
        $this->setMetadata($mpdf, $data);
        
        // Add header/footer
        $this->addHeaderFooter($mpdf, $data);
        
        // Add watermark if configured
        $this->addWatermark($mpdf, $data);
        
        // Write HTML
        $mpdf->WriteHTML($html);
        
        // Determine output mode
        $outputMode = $this->config['output'] ?? 'save';
        
        switch ($outputMode) {
            case 'download':
                return $this->outputDownload($mpdf, $data);
            
            case 'inline':
                return $this->outputInline($mpdf, $data);
            
            case 'save':
            default:
                return $this->outputSave($mpdf, $data);
        }
    }
    
    /**
     * Get mPDF configuration
     * 
     * @return array mPDF config
     */
    private function getMpdfConfig(): array
    {
        $orientation = strtoupper(substr($this->config['orientation'] ?? 'P', 0, 1));
        if (!in_array($orientation, ['P', 'L'])) {
            $orientation = 'P';
        }
        
        return [
            'mode' => 'utf-8',
            'format' => $this->config['format'] ?? 'Letter',
            'orientation' => $orientation,
            'margin_left' => $this->config['marginLeft'] ?? 15,
            'margin_right' => $this->config['marginRight'] ?? 15,
            'margin_top' => $this->config['marginTop'] ?? 16,
            'margin_bottom' => $this->config['marginBottom'] ?? 16,
            'margin_header' => $this->config['marginHeader'] ?? 9,
            'margin_footer' => $this->config['marginFooter'] ?? 9,
            'tempDir' => sys_get_temp_dir()
        ];
    }
    
    /**
     * Set PDF metadata
     * 
     * @param Mpdf $mpdf mPDF instance
     * @param array $data Template data
     */
    private function setMetadata(Mpdf $mpdf, array $data): void
    {
        $title = $this->substituteVariables($this->config['title'] ?? 'Document', $data);
        $author = $this->config['author'] ?? $_ENV['APP_NAME'] ?? 'The Hub';
        $subject = $this->substituteVariables($this->config['subject'] ?? '', $data);
        
        $mpdf->SetTitle($title);
        $mpdf->SetAuthor($author);
        $mpdf->SetSubject($subject);
        $mpdf->SetCreator($author);
    }
    
    /**
     * Add header and footer
     * 
     * @param Mpdf $mpdf mPDF instance
     * @param array $data Template data
     */
    private function addHeaderFooter(Mpdf $mpdf, array $data): void
    {
        // Header
        if (!empty($this->config['header'])) {
            $header = $this->substituteVariables($this->config['header'], $data);
            $mpdf->SetHTMLHeader($header);
        }
        
        // Footer
        if (!empty($this->config['footer'])) {
            $footer = $this->substituteVariables($this->config['footer'], $data);
            $mpdf->SetHTMLFooter($footer);
        }
        
        // Default footer with page numbers
        if (empty($this->config['footer']) && ($this->config['showPageNumbers'] ?? true)) {
            $footerHtml = '<div style="text-align: center; font-size: 10px; color: #666;">';
            $footerHtml .= 'Page {PAGENO} of {nbpg}';
            $footerHtml .= '</div>';
            $mpdf->SetHTMLFooter($footerHtml);
        }
    }
    
    /**
     * Add watermark
     * 
     * @param Mpdf $mpdf mPDF instance
     * @param array $data Template data
     */
    private function addWatermark(Mpdf $mpdf, array $data): void
    {
        if (empty($this->config['watermark'])) {
            return;
        }
        
        $watermark = $this->config['watermark'];
        
        if (is_string($watermark)) {
            // Text watermark
            $text = $this->substituteVariables($watermark, $data);
            $mpdf->SetWatermarkText($text);
            $mpdf->showWatermarkText = true;
        } elseif (is_array($watermark) && !empty($watermark['image'])) {
            // Image watermark
            $imagePath = $this->substituteVariables($watermark['image'], $data);
            if (file_exists($imagePath)) {
                $mpdf->SetWatermarkImage(
                    $imagePath,
                    $watermark['alpha'] ?? 0.2,
                    $watermark['size'] ?? 'D',
                    $watermark['position'] ?? 'P'
                );
                $mpdf->showWatermarkImage = true;
            }
        }
    }
    
    /**
     * Output as download
     * 
     * @param Mpdf $mpdf mPDF instance
     * @param array $data Template data
     * @return array Result
     */
    private function outputDownload(Mpdf $mpdf, array $data): array
    {
        $filename = $this->getFilename($data);
        
        // This will trigger browser download and exit
        $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
        
        return [
            'success' => true,
            'filename' => $filename,
            'mode' => 'download'
        ];
    }
    
    /**
     * Output inline (display in browser)
     * 
     * @param Mpdf $mpdf mPDF instance
     * @param array $data Template data
     * @return array Result
     */
    private function outputInline(Mpdf $mpdf, array $data): array
    {
        $filename = $this->getFilename($data);
        
        // This will display in browser and exit
        $mpdf->Output($filename, \Mpdf\Output\Destination::INLINE);
        
        return [
            'success' => true,
            'filename' => $filename,
            'mode' => 'inline'
        ];
    }
    
    /**
     * Save to file system
     * 
     * @param Mpdf $mpdf mPDF instance
     * @param array $data Template data
     * @return array Result with file path
     */
    private function outputSave(Mpdf $mpdf, array $data): array
    {
        $filename = $this->getFilename($data);
        
        // Determine storage path
        $storagePath = $this->getStoragePath($data);
        
        // Ensure directory exists
        $dir = dirname($storagePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Save file
        $mpdf->Output($storagePath, \Mpdf\Output\Destination::FILE);
        
        // Get file size
        $size = filesize($storagePath);
        
        // Generate signed URL if configured
        $url = null;
        if ($this->config['signedUrl'] ?? false) {
            $url = $this->generateSignedUrl($storagePath, $data);
        } else {
            // Direct URL (if in public directory)
            $publicPath = str_replace($_SERVER['DOCUMENT_ROOT'] ?? '', '', $storagePath);
            $url = $publicPath;
        }
        
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $storagePath,
            'url' => $url,
            'size' => $size,
            'mode' => 'save'
        ];
    }
    
    /**
     * Get filename for PDF
     * 
     * @param array $data Template data
     * @return string Filename
     */
    private function getFilename(array $data): string
    {
        $filename = $this->substituteVariables(
            $this->config['filename'] ?? 'document.pdf',
            $data
        );
        
        // Ensure .pdf extension
        if (!str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }
        
        // Sanitize filename
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
        
        return $filename;
    }
    
    /**
     * Get storage path for PDF
     * 
     * @param array $data Template data
     * @return string File path
     */
    private function getStoragePath(array $data): string
    {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/../uploads/pdfs';
        $tenantId = $_SESSION['tenant_id'] ?? 'default';
        $yearMonth = date('Y/m');
        
        // Custom storage path from config
        if (!empty($this->config['storagePath'])) {
            $customPath = $this->substituteVariables($this->config['storagePath'], $data);
            return $customPath;
        }
        
        // Default: uploads/pdfs/{tenant}/{year}/{month}/{filename}
        $filename = $this->getFilename($data);
        return "{$uploadDir}/{$tenantId}/{$yearMonth}/{$filename}";
    }
    
    /**
     * Generate signed URL for file access
     * 
     * @param string $filePath File path
     * @param array $data Template data
     * @return string Signed URL
     */
    private function generateSignedUrl(string $filePath, array $data): string
    {
        // Generate token
        $token = bin2hex(random_bytes(32));
        $expiry = time() + ($this->config['urlExpiry'] ?? 3600); // Default 1 hour
        
        // Store token in database
        $stmt = $this->db->prepare("
            INSERT INTO file_access_tokens (token, file_path, expires_at, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        
        $stmt->execute([$token, $filePath, date('Y-m-d H:i:s', $expiry)]);
        
        // Build URL
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        return "{$baseUrl}/download.php?token={$token}";
    }
    
    /**
     * Render HTML template with data
     * 
     * @param array $data Template data
     * @return string HTML content
     */
    private function renderTemplate(array $data): string
    {
        $template = $this->config['template'] ?? null;
        
        // Inline HTML template
        if (!empty($this->config['html'])) {
            return $this->substituteVariables($this->config['html'], $data);
        }
        
        // External template file
        if ($template && file_exists($template)) {
            $templateContent = file_get_contents($template);
            return $this->substituteVariables($templateContent, $data);
        }
        
        // Default template
        return $this->renderDefaultTemplate($data);
    }
    
    /**
     * Render default PDF template
     * 
     * @param array $data Template data
     * @return string HTML
     */
    private function renderDefaultTemplate(array $data): string
    {
        $title = $this->substituteVariables($this->config['title'] ?? 'Document', $data);
        $content = $data['content'] ?? 'No content provided.';
        
        $html = '<!DOCTYPE html>';
        $html .= '<html>';
        $html .= '<head>';
        $html .= '<style>';
        $html .= 'body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.6; }';
        $html .= 'h1 { color: #0d6efd; font-size: 24px; margin-bottom: 20px; }';
        $html .= 'table { width: 100%; border-collapse: collapse; margin: 20px 0; }';
        $html .= 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
        $html .= 'th { background-color: #f2f2f2; font-weight: bold; }';
        $html .= '</style>';
        $html .= '</head>';
        $html .= '<body>';
        $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
        $html .= $content;
        $html .= '</body>';
        $html .= '</html>';
        
        return $html;
    }
    
    /**
     * Substitute variables in template
     * 
     * @param string $template Template string
     * @param array $data Data for substitution
     * @return string Processed template
     */
    private function substituteVariables(string $template, array $data): string
    {
        // Add common variables
        $data['appName'] = $_ENV['APP_NAME'] ?? 'The Hub';
        $data['currentDate'] = date('F j, Y');
        $data['currentTime'] = date('g:i A');
        $data['pageNumber'] = '{PAGENO}';
        $data['pageCount'] = '{nbpg}';
        
        // Replace {variable} patterns
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $template = str_replace('{' . $key . '}', (string)$value, $template);
            }
        }
        
        return $template;
    }
    
    /**
     * Static helper: Generate PDF from other modules
     * 
     * @param array $config PDF configuration
     * @param array $data Template data
     * @return array Result
     */
    public static function generate(array $config, array $data = []): array
    {
        $renderer = new self($config);
        return $renderer->handle($data);
    }
    
    /**
     * Get module configuration
     * 
     * @return array Configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }
    
    /**
     * Validate module configuration
     * 
     * @return bool True if valid
     */
    public function validate(): bool
    {
        // Must have content (html, template, or default will be used)
        // Filename is optional (will use 'document.pdf' as default)
        
        return true;
    }
}
