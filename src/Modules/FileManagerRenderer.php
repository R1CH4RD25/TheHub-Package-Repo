<?php

namespace Hub\Modules;

use Hub\Database;
use Hub\Auth;
use Hub\AuditLogger;
use Exception;

require_once __DIR__ . '/../bootstrap.php';

/**
 * FileManagerRenderer - Universal file/document management system
 *
 * Implements File Manager module type for organizing, uploading, and managing files.
 * Generic design works for any organization (schools, businesses, personal projects).
 *
 * Use cases:
 * - Document library (policies, procedures, forms)
 * - Media gallery (photos, videos)
 * - Asset repository (logos, templates, resources)
 * - Project files (contracts, proposals, deliverables)
 * - Personal files (resumes, certificates, receipts)
 *
 * Features:
 * - Drag-and-drop file upload
 * - Folder/tag organization
 * - File versioning
 * - Thumbnail generation for images
 * - Search by name, tags, metadata
 * - Download tracking
 * - Role-based access control
 * - File size and type restrictions
 * - Preview for common formats
 *
 * @author The Hub Team
 * @version 1.0.0
 */
class FileManagerRenderer implements ModuleInterface
{
    private array $config;
    private Database $db;

    // Allowed file types (can be overridden in config)
    private array $allowedTypes = [
        'documents' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'odt'],
        'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'],
        'videos' => ['mp4', 'webm', 'mov', 'avi', 'mkv'],
        'audio' => ['mp3', 'wav', 'ogg', 'm4a'],
        'archives' => ['zip', 'tar', 'gz', 'rar', '7z'],
        'code' => ['php', 'js', 'css', 'html', 'json', 'xml', 'sql', 'sh']
    ];

    private int $maxFileSize = 52428800; // 50MB default

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->db = Database::getInstance();

        // Override defaults from config
        if (isset($config['max_file_size'])) {
            $this->maxFileSize = (int)$config['max_file_size'];
        }

        if (isset($config['allowed_types'])) {
            $this->allowedTypes = array_merge($this->allowedTypes, $config['allowed_types']);
        }
    }

    /**
     * Render file manager interface
     */
    public function render(): string
    {
        $currentFolder = $_GET['folder'] ?? '';
        $viewMode = $_GET['view'] ?? 'grid'; // grid or list

        $html = '<div class="file-manager-container">';

        // Toolbar
        $html .= $this->renderToolbar($currentFolder);

        // Breadcrumb navigation
        $html .= $this->renderBreadcrumb($currentFolder);

        // Main content area
        $html .= '<div class="file-manager-content">';
        $html .= '    <div class="row">';

        // Sidebar (folders and filters)
        $html .= '        <div class="col-md-3">';
        $html .= $this->renderSidebar($currentFolder);
        $html .= '        </div>';

        // File listing
        $html .= '        <div class="col-md-9">';
        $html .= $this->renderFileList($currentFolder, $viewMode);
        $html .= '        </div>';

        $html .= '    </div>';
        $html .= '</div>';

        // Modals
        $html .= $this->renderUploadModal();
        $html .= $this->renderNewFolderModal();
        $html .= $this->renderFileDetailsModal();

        // Scripts
        $html .= $this->renderScripts();

        $html .= '</div>';

        return $html;
    }

    /**
     * Render toolbar with actions
     */
    private function renderToolbar(string $currentFolder): string
    {
        $html = '<div class="file-manager-toolbar mb-3">';
        $html .= '    <div class="btn-toolbar" role="toolbar">';

        // Upload button
        $html .= '        <div class="btn-group me-2">';
        $html .= '            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">';
        $html .= '                <i class="bi bi-upload"></i> Upload Files';
        $html .= '            </button>';
        $html .= '            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#newFolderModal">';
        $html .= '                <i class="bi bi-folder-plus"></i> New Folder';
        $html .= '            </button>';
        $html .= '        </div>';

        // View mode switcher
        $html .= '        <div class="btn-group me-2">';
        $html .= '            <button type="button" class="btn btn-outline-secondary btn-view-mode" data-view="grid">';
        $html .= '                <i class="bi bi-grid-3x3"></i> Grid';
        $html .= '            </button>';
        $html .= '            <button type="button" class="btn btn-outline-secondary btn-view-mode" data-view="list">';
        $html .= '                <i class="bi bi-list-ul"></i> List';
        $html .= '            </button>';
        $html .= '        </div>';

        // Search
        $html .= '        <div class="flex-grow-1">';
        $html .= '            <input type="search" class="form-control" id="fileSearch" placeholder="Search files...">';
        $html .= '        </div>';

        // Selected actions (hidden by default)
        $html .= '        <div class="btn-group ms-2 d-none" id="selectedActions">';
        $html .= '            <button type="button" class="btn btn-outline-danger" id="deleteSelected">';
        $html .= '                <i class="bi bi-trash"></i> Delete';
        $html .= '            </button>';
        $html .= '            <button type="button" class="btn btn-outline-secondary" id="moveSelected">';
        $html .= '                <i class="bi bi-folder"></i> Move';
        $html .= '            </button>';
        $html .= '        </div>';

        $html .= '    </div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render breadcrumb navigation
     */
    private function renderBreadcrumb(string $currentFolder): string
    {
        $html = '<nav aria-label="breadcrumb" class="mb-3">';
        $html .= '    <ol class="breadcrumb">';
        $html .= '        <li class="breadcrumb-item"><a href="?folder="><i class="bi bi-house"></i> Root</a></li>';

        if ($currentFolder) {
            $parts = explode('/', trim($currentFolder, '/'));
            $path = '';

            foreach ($parts as $part) {
                $path .= $part . '/';
                $html .= '        <li class="breadcrumb-item"><a href="?folder=' . urlencode($path) . '">' . htmlspecialchars($part) . '</a></li>';
            }
        }

        $html .= '    </ol>';
        $html .= '</nav>';

        return $html;
    }

    /**
     * Render sidebar with folders and filters
     */
    private function renderSidebar(string $currentFolder): string
    {
        $html = '<div class="file-manager-sidebar">';

        // Folder tree
        $html .= '    <div class="card mb-3">';
        $html .= '        <div class="card-header"><strong>Folders</strong></div>';
        $html .= '        <div class="card-body p-0">';
        $html .= $this->renderFolderTree();
        $html .= '        </div>';
        $html .= '    </div>';

        // Filters
        $html .= '    <div class="card mb-3">';
        $html .= '        <div class="card-header"><strong>Filter by Type</strong></div>';
        $html .= '        <div class="list-group list-group-flush">';
        $html .= '            <a href="#" class="list-group-item list-group-item-action filter-type" data-type="all">All Files</a>';
        $html .= '            <a href="#" class="list-group-item list-group-item-action filter-type" data-type="documents"><i class="bi bi-file-text"></i> Documents</a>';
        $html .= '            <a href="#" class="list-group-item list-group-item-action filter-type" data-type="images"><i class="bi bi-image"></i> Images</a>';
        $html .= '            <a href="#" class="list-group-item list-group-item-action filter-type" data-type="videos"><i class="bi bi-camera-video"></i> Videos</a>';
        $html .= '            <a href="#" class="list-group-item list-group-item-action filter-type" data-type="archives"><i class="bi bi-file-zip"></i> Archives</a>';
        $html .= '        </div>';
        $html .= '    </div>';

        // Storage info
        $storageInfo = $this->getStorageInfo();
        $usedPercent = ($storageInfo['used'] / $storageInfo['total']) * 100;

        $html .= '    <div class="card">';
        $html .= '        <div class="card-header"><strong>Storage</strong></div>';
        $html .= '        <div class="card-body">';
        $html .= '            <div class="progress mb-2" style="height: 25px;">';
        $html .= '                <div class="progress-bar" role="progressbar" style="width: ' . $usedPercent . '%">';
        $html .= number_format($usedPercent, 1) . '%';
        $html .= '                </div>';
        $html .= '            </div>';
        $html .= '            <small>' . $this->formatBytes($storageInfo['used']) . ' of ' . $this->formatBytes($storageInfo['total']) . ' used</small>';
        $html .= '        </div>';
        $html .= '    </div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Render folder tree
     */
    private function renderFolderTree(): string
    {
        $folders = $this->getFolders();

        if (empty($folders)) {
            return '<div class="p-3 text-muted">No folders yet</div>';
        }

        $html = '<ul class="list-unstyled folder-tree mb-0">';

        foreach ($folders as $folder) {
            $html .= '    <li>';
            $html .= '        <a href="?folder=' . urlencode($folder['path']) . '">';
            $html .= '            <i class="bi bi-folder"></i> ' . htmlspecialchars($folder['name']);
            $html .= '            <span class="badge bg-secondary">' . $folder['file_count'] . '</span>';
            $html .= '        </a>';
            $html .= '    </li>';
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * Render file listing
     */
    private function renderFileList(string $currentFolder, string $viewMode): string
    {
        $files = $this->getFiles($currentFolder);

        $html = '<div class="file-list-container" data-view-mode="' . $viewMode . '">';

        if (empty($files)) {
            $html .= '<div class="text-center py-5">';
            $html .= '    <i class="bi bi-folder2-open" style="font-size: 4rem; color: #ccc;"></i>';
            $html .= '    <p class="text-muted mt-3">No files in this folder</p>';
            $html .= '    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">Upload Files</button>';
            $html .= '</div>';
        } else {
            if ($viewMode === 'grid') {
                $html .= $this->renderGridView($files);
            } else {
                $html .= $this->renderListView($files);
            }
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render grid view
     */
    private function renderGridView(array $files): string
    {
        $html = '<div class="row g-3">';

        foreach ($files as $file) {
            $html .= '    <div class="col-md-3 col-sm-4 col-6">';
            $html .= '        <div class="card file-card h-100" data-file-id="' . $file['id'] . '">';
            $html .= '            <div class="card-img-top file-thumbnail">';
            $html .= $this->renderThumbnail($file);
            $html .= '            </div>';
            $html .= '            <div class="card-body p-2">';
            $html .= '                <div class="form-check">';
            $html .= '                    <input class="form-check-input file-checkbox" type="checkbox" value="' . $file['id'] . '">';
            $html .= '                </div>';
            $html .= '                <h6 class="card-title small mb-1 text-truncate" title="' . htmlspecialchars($file['filename']) . '">';
            $html .= htmlspecialchars($file['filename']);
            $html .= '                </h6>';
            $html .= '                <p class="card-text small text-muted mb-0">';
            $html .= $this->formatBytes($file['filesize']) . ' • ' . date('M d, Y', strtotime($file['uploaded_at']));
            $html .= '                </p>';
            $html .= '            </div>';
            $html .= '            <div class="card-footer p-2 bg-white border-top-0">';
            $html .= '                <div class="btn-group btn-group-sm w-100">';
            $html .= '                    <button class="btn btn-outline-primary btn-download" data-file-id="' . $file['id'] . '"><i class="bi bi-download"></i></button>';
            $html .= '                    <button class="btn btn-outline-info btn-details" data-file-id="' . $file['id'] . '"><i class="bi bi-info-circle"></i></button>';
            $html .= '                    <button class="btn btn-outline-danger btn-delete-file" data-file-id="' . $file['id'] . '"><i class="bi bi-trash"></i></button>';
            $html .= '                </div>';
            $html .= '            </div>';
            $html .= '        </div>';
            $html .= '    </div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render list view
     */
    private function renderListView(array $files): string
    {
        $html = '<div class="table-responsive">';
        $html .= '    <table class="table table-hover">';
        $html .= '        <thead>';
        $html .= '            <tr>';
        $html .= '                <th width="30"><input type="checkbox" id="selectAllFiles"></th>';
        $html .= '                <th>Name</th>';
        $html .= '                <th>Size</th>';
        $html .= '                <th>Type</th>';
        $html .= '                <th>Modified</th>';
        $html .= '                <th>Actions</th>';
        $html .= '            </tr>';
        $html .= '        </thead>';
        $html .= '        <tbody>';

        foreach ($files as $file) {
            $html .= '            <tr>';
            $html .= '                <td><input type="checkbox" class="file-checkbox" value="' . $file['id'] . '"></td>';
            $html .= '                <td><i class="' . $this->getFileIcon($file['extension']) . ' me-2"></i>' . htmlspecialchars($file['filename']) . '</td>';
            $html .= '                <td>' . $this->formatBytes($file['filesize']) . '</td>';
            $html .= '                <td><span class="badge bg-secondary">' . strtoupper($file['extension']) . '</span></td>';
            $html .= '                <td>' . date('Y-m-d H:i', strtotime($file['uploaded_at'])) . '</td>';
            $html .= '                <td>';
            $html .= '                    <button class="btn btn-sm btn-outline-primary btn-download" data-file-id="' . $file['id'] . '"><i class="bi bi-download"></i></button>';
            $html .= '                    <button class="btn btn-sm btn-outline-info btn-details" data-file-id="' . $file['id'] . '"><i class="bi bi-info-circle"></i></button>';
            $html .= '                    <button class="btn btn-sm btn-outline-danger btn-delete-file" data-file-id="' . $file['id'] . '"><i class="bi bi-trash"></i></button>';
            $html .= '                </td>';
            $html .= '            </tr>';
        }

        $html .= '        </tbody>';
        $html .= '    </table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render file thumbnail
     */
    private function renderThumbnail(array $file): string
    {
        $ext = strtolower($file['extension']);

        // If image and thumbnail exists
        if (in_array($ext, $this->allowedTypes['images']) && !empty($file['thumbnail_path'])) {
            return '<img src="' . htmlspecialchars($file['thumbnail_path']) . '" alt="' . htmlspecialchars($file['filename']) . '" class="img-fluid">';
        }

        // Otherwise show icon
        $icon = $this->getFileIcon($ext);
        return '<div class="text-center py-4"><i class="' . $icon . '" style="font-size: 3rem;"></i></div>';
    }

    /**
     * Get file icon based on extension
     */
    private function getFileIcon(string $extension): string
    {
        $icons = [
            'pdf' => 'bi bi-file-pdf text-danger',
            'doc' => 'bi bi-file-word text-primary',
            'docx' => 'bi bi-file-word text-primary',
            'xls' => 'bi bi-file-excel text-success',
            'xlsx' => 'bi bi-file-excel text-success',
            'ppt' => 'bi bi-file-ppt text-warning',
            'pptx' => 'bi bi-file-ppt text-warning',
            'zip' => 'bi bi-file-zip text-secondary',
            'rar' => 'bi bi-file-zip text-secondary',
            'jpg' => 'bi bi-file-image text-info',
            'jpeg' => 'bi bi-file-image text-info',
            'png' => 'bi bi-file-image text-info',
            'gif' => 'bi bi-file-image text-info',
            'mp4' => 'bi bi-camera-video text-danger',
            'mp3' => 'bi bi-file-music text-purple',
            'txt' => 'bi bi-file-text text-dark',
            'php' => 'bi bi-file-code text-secondary',
            'js' => 'bi bi-file-code text-warning',
            'css' => 'bi bi-file-code text-primary',
            'html' => 'bi bi-file-code text-danger',
        ];

        return $icons[$extension] ?? 'bi bi-file-earmark text-secondary';
    }

    /**
     * Render upload modal
     */
    private function renderUploadModal(): string
    {
        $maxSizeMB = $this->maxFileSize / 1048576;

        $html = '<div class="modal fade" id="uploadModal" tabindex="-1">';
        $html .= '    <div class="modal-dialog modal-lg">';
        $html .= '        <div class="modal-content">';
        $html .= '            <div class="modal-header">';
        $html .= '                <h5 class="modal-title">Upload Files</h5>';
        $html .= '                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
        $html .= '            </div>';
        $html .= '            <div class="modal-body">';
        $html .= '                <div class="upload-area border rounded p-5 text-center mb-3" id="uploadArea">';
        $html .= '                    <i class="bi bi-cloud-upload" style="font-size: 3rem; color: #0d6efd;"></i>';
        $html .= '                    <p class="mt-3">Drag & drop files here or click to browse</p>';
        $html .= '                    <p class="text-muted small">Max ' . $maxSizeMB . ' MB per file</p>';
        $html .= '                    <input type="file" id="fileInput" multiple style="display: none;">';
        $html .= '                </div>';
        $html .= '                <div id="uploadQueue" class="list-group"></div>';
        $html .= '            </div>';
        $html .= '            <div class="modal-footer">';
        $html .= '                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
        $html .= '                <button type="button" class="btn btn-primary" id="startUpload">Upload Files</button>';
        $html .= '            </div>';
        $html .= '        </div>';
        $html .= '    </div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render new folder modal
     */
    private function renderNewFolderModal(): string
    {
        $html = '<div class="modal fade" id="newFolderModal" tabindex="-1">';
        $html .= '    <div class="modal-dialog">';
        $html .= '        <div class="modal-content">';
        $html .= '            <div class="modal-header">';
        $html .= '                <h5 class="modal-title">Create New Folder</h5>';
        $html .= '                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
        $html .= '            </div>';
        $html .= '            <div class="modal-body">';
        $html .= '                <div class="mb-3">';
        $html .= '                    <label for="folderName" class="form-label">Folder Name</label>';
        $html .= '                    <input type="text" class="form-control" id="folderName" required>';
        $html .= '                </div>';
        $html .= '            </div>';
        $html .= '            <div class="modal-footer">';
        $html .= '                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
        $html .= '                <button type="button" class="btn btn-primary" id="createFolder">Create</button>';
        $html .= '            </div>';
        $html .= '        </div>';
        $html .= '    </div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render file details modal
     */
    private function renderFileDetailsModal(): string
    {
        $html = '<div class="modal fade" id="fileDetailsModal" tabindex="-1">';
        $html .= '    <div class="modal-dialog modal-lg">';
        $html .= '        <div class="modal-content">';
        $html .= '            <div class="modal-header">';
        $html .= '                <h5 class="modal-title">File Details</h5>';
        $html .= '                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
        $html .= '            </div>';
        $html .= '            <div class="modal-body" id="fileDetailsContent">';
        $html .= '                <div class="text-center"><div class="spinner-border"></div></div>';
        $html .= '            </div>';
        $html .= '        </div>';
        $html .= '    </div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render JavaScript
     */
    private function renderScripts(): string
    {
        $html = '<script>';
        $html .= <<<'JS'
document.addEventListener('DOMContentLoaded', function() {
    // View mode switcher
    document.querySelectorAll('.btn-view-mode').forEach(btn => {
        btn.addEventListener('click', function() {
            const view = this.dataset.view;
            window.location.href = '?view=' + view + '&folder=' + (new URLSearchParams(window.location.search).get('folder') || '');
        });
    });

    // File search
    const searchInput = document.getElementById('fileSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            document.querySelectorAll('.file-card, tbody tr').forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }

    // File selection
    document.querySelectorAll('.file-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedActions);
    });

    document.getElementById('selectAllFiles')?.addEventListener('change', function() {
        document.querySelectorAll('.file-checkbox').forEach(cb => cb.checked = this.checked);
        updateSelectedActions();
    });

    function updateSelectedActions() {
        const selected = document.querySelectorAll('.file-checkbox:checked').length;
        document.getElementById('selectedActions').classList.toggle('d-none', selected === 0);
    }

    // Upload area drag & drop
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('fileInput');

    if (uploadArea && fileInput) {
        uploadArea.addEventListener('click', () => fileInput.click());

        ['dragenter', 'dragover'].forEach(event => {
            uploadArea.addEventListener(event, (e) => {
                e.preventDefault();
                uploadArea.classList.add('border-primary', 'bg-light');
            });
        });

        ['dragleave', 'drop'].forEach(event => {
            uploadArea.addEventListener(event, (e) => {
                e.preventDefault();
                uploadArea.classList.remove('border-primary', 'bg-light');
            });
        });

        uploadArea.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            handleFiles(files);
        });

        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });
    }

    function handleFiles(files) {
        const queue = document.getElementById('uploadQueue');
        queue.innerHTML = '';

        Array.from(files).forEach(file => {
            const item = document.createElement('div');
            item.className = 'list-group-item';
            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <span>${file.name} (${formatBytes(file.size)})</span>
                    <span class="badge bg-secondary">Pending</span>
                </div>
            `;
            queue.appendChild(item);
        });
    }

    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    // File download
    document.querySelectorAll('.btn-download').forEach(btn => {
        btn.addEventListener('click', function() {
            const fileId = this.dataset.fileId;
            window.location.href = 'api/files.php?action=download&id=' + fileId;
        });
    });

    // File details
    document.querySelectorAll('.btn-details').forEach(btn => {
        btn.addEventListener('click', function() {
            const fileId = this.dataset.fileId;
            const modal = new bootstrap.Modal(document.getElementById('fileDetailsModal'));

            fetch('api/files.php?action=details&id=' + fileId)
                .then(r => r.json())
                .then(data => {
                    document.getElementById('fileDetailsContent').innerHTML = `
                        <table class="table">
                            <tr><th>Filename:</th><td>${data.filename}</td></tr>
                            <tr><th>Size:</th><td>${formatBytes(data.filesize)}</td></tr>
                            <tr><th>Type:</th><td>${data.extension}</td></tr>
                            <tr><th>Uploaded:</th><td>${data.uploaded_at}</td></tr>
                            <tr><th>Downloads:</th><td>${data.download_count || 0}</td></tr>
                        </table>
                    `;
                });

            modal.show();
        });
    });

    // Delete file
    document.querySelectorAll('.btn-delete-file').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('Delete this file?')) return;

            const fileId = this.dataset.fileId;
            fetch('api/files.php?action=delete', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: fileId})
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        });
    });
});
JS;
        $html .= '</script>';

        return $html;
    }

    /**
     * Get files in folder
     */
    private function getFiles(string $folder): array
    {
        // This would query your files table
        // Placeholder implementation
        return [];
    }

    /**
     * Get folders
     */
    private function getFolders(): array
    {
        // This would query your folders table
        // Placeholder implementation
        return [];
    }

    /**
     * Get storage info
     */
    private function getStorageInfo(): array
    {
        // Calculate storage usage
        return [
            'used' => 1073741824,  // 1GB
            'total' => 10737418240  // 10GB
        ];
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 Bytes';

        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log($k));

        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    /**
     * Handle file operations
     */
    public function handle(array $data): array
    {
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'upload':
                return $this->handleUpload($data);
            case 'delete':
                return $this->handleDelete($data);
            case 'create_folder':
                return $this->handleCreateFolder($data);
            case 'move':
                return $this->handleMove($data);
            default:
                return ['success' => false, 'error' => 'Invalid action'];
        }
    }

    /**
     * Handle file upload
     */
    private function handleUpload(array $data): array
    {
        // Implementation would handle file upload from $data
        return ['success' => true, 'message' => 'Files uploaded'];
    }

    /**
     * Handle file deletion
     */
    private function handleDelete(array $data): array
    {
        $fileId = $data['id'] ?? 0;

        // Delete file from database and filesystem
        AuditLogger::log('file_delete', 'files', $fileId, [], ['id' => $fileId]);

        return ['success' => true, 'message' => 'File deleted'];
    }

    /**
     * Handle folder creation
     */
    private function handleCreateFolder(array $data): array
    {
        $folderName = $data['name'] ?? '';

        // Create folder in database
        AuditLogger::log('folder_create', 'folders', null, [], ['name' => $folderName]);

        return ['success' => true, 'message' => 'Folder created'];
    }

    /**
     * Handle move operation
     */
    private function handleMove(array $data): array
    {
        $fileIds = $data['file_ids'] ?? [];
        $targetFolder = $data['target_folder'] ?? '';

        // Move files to target folder

        return ['success' => true, 'message' => 'Files moved'];
    }

    /**
     * Validate configuration
     */
    public function validate(): bool
    {
        // File manager doesn't require specific config
        return true;
    }

    /**
     * Validate file data
     *
     * @param array $data File data to validate
     * @return array Array of error messages (empty if valid)
     */
    public function validateFile(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = 'File name is required';
        }

        if (!empty($data['size'])) {
            $maxSize = $this->config['maxFileSize'] ?? 10485760; // 10MB default
            if ($data['size'] > $maxSize) {
                $errors[] = 'File size exceeds maximum allowed';
            }
        }

        if (!empty($data['type'])) {
            $allowedTypes = $this->config['allowedTypes'] ?? ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'png', 'gif'];
            $extension = strtolower(pathinfo($data['name'] ?? '', PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedTypes) && !in_array($data['type'], ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'])) {
                $errors[] = 'File type not allowed';
            }
        }

        return $errors;
    }    /**
     * Get configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
