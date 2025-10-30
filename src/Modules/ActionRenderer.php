<?php

namespace Hub\Modules;

use Hub\Database;
use Hub\Auth;
use Hub\AuditLogger;
use Exception;

require_once __DIR__ . '/../bootstrap.php';

/**
 * ActionRenderer - Execute single or bulk operations on records
 * 
 * Implements Action module type from MODULE_CATALOG_V2.md [ACT-R01 through ACT-R07]
 * 
 * Use cases:
 * - Mark as Closed/Approved/Completed
 * - Assign Owner
 * - Bulk Archive/Delete
 * - Update Status
 * - Trigger workflows
 * 
 * Features:
 * - Single and bulk operations
 * - Confirmation dialogs for destructive actions
 * - Permission checks
 * - Rate limiting (100 actions/minute)
 * - Audit logging
 * - CSRF protection
 * 
 * @author The Hub Team
 * @version 1.0.0
 */
class ActionRenderer implements ModuleInterface
{
    private array $config;
    private Database $db;
    private AuditLogger $auditLogger;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->db = Database::getInstance();
        $this->auditLogger = new AuditLogger();
    }
    
    /**
     * Render action button or form
     * 
     * @return string HTML output
     */
    public function render(): string
    {
        if (!$this->validate()) {
            return '<div class="alert alert-danger">Invalid action configuration</div>';
        }
        
        // [ACT-R01] Check permission
        $permission = $this->config['permission'] ?? null;
        if ($permission && !Auth::hasPermission($permission)) {
            return '<div class="alert alert-warning">You do not have permission to perform this action</div>';
        }
        
        $displayName = $this->config['displayName'] ?? 'Execute Action';
        $description = $this->config['description'] ?? '';
        $buttonClass = $this->config['buttonClass'] ?? 'btn-primary';
        $icon = $this->config['icon'] ?? 'bi-play-circle';
        
        // Get record IDs from query string (single or bulk)
        $recordIds = $_GET['ids'] ?? $_GET['id'] ?? '';
        $recordIds = is_array($recordIds) ? $recordIds : explode(',', $recordIds);
        $recordIds = array_filter($recordIds);
        
        if (empty($recordIds)) {
            return '<div class="alert alert-info">No records selected for this action</div>';
        }
        
        $isBulk = count($recordIds) > 1;
        $confirmationEnabled = $this->config['confirmation']['enabled'] ?? false;
        
        $html = '<div class="action-container">';
        $html .= '<div class="card">';
        $html .= '<div class="card-body">';
        
        $html .= '<h5>' . htmlspecialchars($displayName) . '</h5>';
        if ($description) {
            $html .= '<p class="text-muted">' . htmlspecialchars($description) . '</p>';
        }
        
        $html .= '<p><strong>' . count($recordIds) . '</strong> record(s) selected</p>';
        
        // Show form
        $html .= '<form method="POST" id="action-form">';
        $html .= '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'] ?? '') . '">';
        $html .= '<input type="hidden" name="action" value="execute">';
        
        foreach ($recordIds as $id) {
            $html .= '<input type="hidden" name="record_ids[]" value="' . htmlspecialchars($id) . '">';
        }
        
        // Additional fields if configured
        if (!empty($this->config['inputFields'])) {
            $html .= '<div class="mb-3">';
            foreach ($this->config['inputFields'] as $field) {
                $html .= $this->renderInputField($field);
            }
            $html .= '</div>';
        }
        
        // Buttons
        $html .= '<div class="d-flex gap-2">';
        
        if ($confirmationEnabled) {
            $confirmMessage = $this->config['confirmation']['message'] 
                            ?? 'Are you sure you want to perform this action?';
            $html .= '<button type="button" class="btn ' . htmlspecialchars($buttonClass) . '" 
                     onclick="confirmAction(\'' . htmlspecialchars($confirmMessage, ENT_QUOTES) . '\')">';
        } else {
            $html .= '<button type="submit" class="btn ' . htmlspecialchars($buttonClass) . '">';
        }
        
        $html .= '<i class="bi ' . htmlspecialchars($icon) . '"></i> ' 
               . htmlspecialchars($displayName) . '</button>';
        $html .= '<a href="javascript:history.back()" class="btn btn-secondary">Cancel</a>';
        $html .= '</div>';
        
        $html .= '</form>';
        $html .= '</div>'; // card-body
        $html .= '</div>'; // card
        $html .= '</div>'; // action-container
        
        // JavaScript for confirmation
        if ($confirmationEnabled) {
            $html .= '<script>';
            $html .= <<<'JS'
function confirmAction(message) {
    if (confirm(message)) {
        document.getElementById('action-form').submit();
    }
}
JS;
            $html .= '</script>';
        }
        
        return $html;
    }
    
    /**
     * Render input field
     */
    private function renderInputField(array $field): string
    {
        $name = $field['name'] ?? '';
        $label = $field['label'] ?? $name;
        $type = $field['type'] ?? 'text';
        $required = $field['required'] ?? false;
        
        $html = '<div class="mb-3">';
        $html .= '<label class="form-label">' . htmlspecialchars($label);
        if ($required) {
            $html .= ' <span class="text-danger">*</span>';
        }
        $html .= '</label>';
        
        if ($type === 'textarea') {
            $html .= '<textarea name="' . htmlspecialchars($name) . '" class="form-control" rows="3"';
            if ($required) $html .= ' required';
            $html .= '></textarea>';
        } elseif ($type === 'select' && !empty($field['options'])) {
            $html .= '<select name="' . htmlspecialchars($name) . '" class="form-select"';
            if ($required) $html .= ' required';
            $html .= '>';
            $html .= '<option value="">Select...</option>';
            foreach ($field['options'] as $opt) {
                $value = $opt['value'] ?? $opt;
                $label = $opt['label'] ?? $opt;
                $html .= '<option value="' . htmlspecialchars($value) . '">' 
                       . htmlspecialchars($label) . '</option>';
            }
            $html .= '</select>';
        } else {
            $html .= '<input type="' . htmlspecialchars($type) . '" name="' . htmlspecialchars($name) 
                   . '" class="form-control"';
            if ($required) $html .= ' required';
            $html .= '>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Handle action execution
     * 
     * [ACT-R06] CSRF token required
     * [ACT-R07] Rate limit: max 100 actions/minute
     * 
     * @param array $data Input data
     * @return array Result
     */
    public function handle(array $data): array
    {
        // [ACT-R06] Verify CSRF token
        if (!isset($data['csrf_token']) || $data['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            return [
                'success' => false,
                'message' => 'Invalid CSRF token'
            ];
        }
        
        // [ACT-R01] Check permission
        $permission = $this->config['permission'] ?? null;
        if ($permission && !Auth::hasPermission($permission)) {
            return [
                'success' => false,
                'message' => 'Permission denied'
            ];
        }
        
        // [ACT-R07] Rate limiting
        if (!$this->checkRateLimit()) {
            return [
                'success' => false,
                'message' => 'Rate limit exceeded. Please wait a minute and try again.'
            ];
        }
        
        $recordIds = $data['record_ids'] ?? [];
        if (empty($recordIds)) {
            return [
                'success' => false,
                'message' => 'No records specified'
            ];
        }
        
        // Validate record count (max 1000 for bulk operations)
        if (count($recordIds) > 1000) {
            return [
                'success' => false,
                'message' => 'Maximum 1000 records can be processed at once'
            ];
        }
        
        $entity = $this->config['entity'] ?? '';
        $operation = $this->config['operation'] ?? 'update';
        $fields = $this->config['fields'] ?? [];
        
        // Process field values (replace placeholders)
        $processedFields = [];
        foreach ($fields as $key => $value) {
            $processedFields[$key] = $this->processFieldValue($value, $data);
        }
        
        // Add any input fields from form
        if (!empty($this->config['inputFields'])) {
            foreach ($this->config['inputFields'] as $field) {
                $name = $field['name'] ?? '';
                if (isset($data[$name])) {
                    $processedFields[$name] = $data[$name];
                }
            }
        }
        
        $successCount = 0;
        $failCount = 0;
        $errors = [];
        
        // Execute action on each record
        foreach ($recordIds as $recordId) {
            try {
                if ($operation === 'update') {
                    $this->updateRecord($entity, $recordId, $processedFields);
                } elseif ($operation === 'delete') {
                    $this->deleteRecord($entity, $recordId);
                } else {
                    throw new Exception("Unknown operation: $operation");
                }
                
                // [ACT-R02] Log to audit trail
                $auditAction = $this->config['audit']['action'] ?? 'action_executed';
                $this->auditLogger->log(
                    $auditAction,
                    $entity,
                    $recordId,
                    null,
                    $processedFields,
                    Auth::getCurrentUserId()
                );
                
                $successCount++;
            } catch (Exception $e) {
                $failCount++;
                $errors[] = "Record $recordId: " . $e->getMessage();
            }
        }
        
        $message = "$successCount record(s) updated successfully";
        if ($failCount > 0) {
            $message .= ", $failCount failed";
        }
        
        // [ACT-R05] Consistent JSON response
        return [
            'success' => $successCount > 0,
            'message' => $message,
            'details' => [
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'errors' => $errors
            ]
        ];
    }
    
    /**
     * Process field value (replace placeholders)
     */
    private function processFieldValue($value, array $data): string
    {
        if (!is_string($value)) {
            return $value;
        }
        
        // Replace placeholders
        $replacements = [
            '{now}' => date('Y-m-d H:i:s'),
            '{current_user_id}' => Auth::getCurrentUserId(),
            '{current_user_name}' => Auth::getCurrentUserName(),
            '{today}' => date('Y-m-d'),
            '{tenant_id}' => $_SESSION['tenant_id'] ?? 'default'
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $value);
    }
    
    /**
     * Update record in database
     */
    private function updateRecord(string $entity, string $recordId, array $fields): void
    {
        if (empty($fields)) {
            return;
        }
        
        $sql = "UPDATE $entity SET ";
        $setParts = [];
        $params = [];
        
        foreach ($fields as $key => $value) {
            $setParts[] = "$key = :$key";
            $params[$key] = $value;
        }
        
        $sql .= implode(', ', $setParts);
        $sql .= " WHERE id = :record_id AND tenant_id = :tenant_id";
        
        $params['record_id'] = $recordId;
        $params['tenant_id'] = $_SESSION['tenant_id'] ?? 'default';
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }
    
    /**
     * Delete record from database (soft delete if is_deleted column exists)
     */
    private function deleteRecord(string $entity, string $recordId): void
    {
        // Try soft delete first
        $sql = "UPDATE $entity SET is_deleted = 1, deleted_at = NOW(), deleted_by = :user_id 
                WHERE id = :record_id AND tenant_id = :tenant_id";
        
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute([
                'user_id' => Auth::getCurrentUserId(),
                'record_id' => $recordId,
                'tenant_id' => $_SESSION['tenant_id'] ?? 'default'
            ]);
        } catch (Exception $e) {
            // If soft delete fails (no is_deleted column), do hard delete
            $sql = "DELETE FROM $entity WHERE id = :record_id AND tenant_id = :tenant_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'record_id' => $recordId,
                'tenant_id' => $_SESSION['tenant_id'] ?? 'default'
            ]);
        }
    }
    
    /**
     * Check rate limit (100 actions per minute)
     */
    private function checkRateLimit(): bool
    {
        $key = 'action_rate_' . Auth::getCurrentUserId();
        $window = 60; // 1 minute
        $maxActions = 100;
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'start' => time()];
        }
        
        $elapsed = time() - $_SESSION[$key]['start'];
        
        if ($elapsed > $window) {
            // Reset window
            $_SESSION[$key] = ['count' => 1, 'start' => time()];
            return true;
        }
        
        if ($_SESSION[$key]['count'] >= $maxActions) {
            return false; // Rate limit exceeded
        }
        
        $_SESSION[$key]['count']++;
        return true;
    }
    
    /**
     * Get module configuration
     * 
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }
    
    /**
     * Validate module configuration
     * 
     * @return bool
     */
    public function validate(): bool
    {
        // Must have entity
        if (empty($this->config['entity'])) {
            return false;
        }
        
        // Must have operation
        if (empty($this->config['operation'])) {
            return false;
        }
        
        // Valid operations
        $validOps = ['update', 'delete'];
        if (!in_array($this->config['operation'], $validOps)) {
            return false;
        }
        
        // Update operation must have fields
        if ($this->config['operation'] === 'update' && empty($this->config['fields'])) {
            return false;
        }
        
        return true;
    }
}
