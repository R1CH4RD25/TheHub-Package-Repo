<?php

namespace Hub\Modules;

use Hub\Database;
use Hub\Auth;
use Hub\AuditLogger;
use Exception;

require_once __DIR__ . '/../bootstrap.php';

/**
 * WorkflowRenderer - Generic state machine for approval processes
 *
 * Implements Workflow module type from MODULE_CATALOG_V2.md [WFL-R01 through WFL-R10]
 *
 * Use cases:
 * - Employee evaluations (draft → submit → approve)
 * - Purchase requests (request → review → approve/deny)
 * - Time-off requests (submit → manager review → HR approval)
 * - Document approvals (draft → review → publish)
 * - Any multi-step approval process
 *
 * Features:
 * - State machine with configurable states and transitions
 * - Role-based transition permissions
 * - Conditional transitions (based on data)
 * - Email notifications on state change
 * - Audit trail for all transitions
 * - Multi-tenant isolation
 * - Comment/notes on transitions
 * - History timeline view
 *
 * @author The Hub Team
 * @version 1.0.0
 */
class WorkflowRenderer implements ModuleInterface
{
    private array $config;
    private Database $db;
    private ?array $record = null;
    private ?string $recordId = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->db = Database::getInstance();

        // Get record ID from query string
        $this->recordId = $_GET['record_id'] ?? null;

        // Load record if ID provided
        if ($this->recordId) {
            $this->loadRecord();
        }
    }

    /**
     * Render workflow interface
     *
     * @return string HTML output
     */
    public function render(): string
    {
        if (!$this->validate()) {
            return '<div class="alert alert-danger">Invalid workflow configuration</div>';
        }

        // If no record, show error
        if (!$this->recordId) {
            return '<div class="alert alert-info">No workflow record specified</div>';
        }

        if (!$this->record) {
            return '<div class="alert alert-danger">Workflow record not found</div>';
        }

        $html = '<div class="workflow-container">';

        // Current state badge
        $html .= $this->renderStateBadge();

        // Record details
        $html .= $this->renderRecordDetails();

        // Available transitions
        $html .= $this->renderTransitions();

        // History timeline
        $html .= $this->renderHistory();

        $html .= '</div>';

        return $html;
    }

    /**
     * Load workflow record from database
     */
    private function loadRecord(): void
    {
        $table = $this->config['table'] ?? '';
        $stateField = $this->config['stateField'] ?? 'status';

        if (!$table) {
            return;
        }

        $sql = "SELECT * FROM {$table} WHERE id = ? AND tenant_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->recordId, $_SESSION['tenant_id'] ?? 'default']);

        $this->record = $stmt->fetch() ?: null;
    }

    /**
     * Get current state of record
     *
     * @return string|null Current state
     */
    private function getCurrentState(): ?string
    {
        if (!$this->record) {
            return null;
        }

        $stateField = $this->config['stateField'] ?? 'status';
        return $this->record[$stateField] ?? null;
    }

    /**
     * Render current state badge
     *
     * @return string HTML
     */
    private function renderStateBadge(): string
    {
        $currentState = $this->getCurrentState();
        if (!$currentState) {
            return '';
        }

        $states = $this->config['states'] ?? [];
        $stateConfig = null;

        foreach ($states as $state) {
            if ($state['name'] === $currentState) {
                $stateConfig = $state;
                break;
            }
        }

        $label = $stateConfig['label'] ?? ucfirst($currentState);
        $color = $stateConfig['color'] ?? 'secondary';
        $icon = $stateConfig['icon'] ?? '';

        $html = '<div class="mb-4">';
        $html .= '<h5>Current Status</h5>';
        $html .= '<h3 class="mb-0">';

        if ($icon) {
            $html .= '<i class="' . htmlspecialchars($icon) . ' me-2"></i>';
        }

        $html .= '<span class="badge bg-' . htmlspecialchars($color) . ' fs-5">';
        $html .= htmlspecialchars($label);
        $html .= '</span>';
        $html .= '</h3>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render record details
     *
     * @return string HTML
     */
    private function renderRecordDetails(): string
    {
        if (empty($this->config['displayFields'])) {
            return '';
        }

        $html = '<div class="card mb-4">';
        $html .= '<div class="card-header"><h5 class="mb-0">Details</h5></div>';
        $html .= '<div class="card-body">';
        $html .= '<dl class="row mb-0">';

        foreach ($this->config['displayFields'] as $field) {
            $fieldName = $field['field'];
            $label = $field['label'] ?? ucfirst($fieldName);
            $value = $this->record[$fieldName] ?? '';

            // Format value
            $value = $this->formatValue($value, $field);

            $html .= '<dt class="col-sm-3">' . htmlspecialchars($label) . '</dt>';
            $html .= '<dd class="col-sm-9">' . $value . '</dd>';
        }

        $html .= '</dl>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Format field value
     *
     * @param mixed $value Raw value
     * @param array $field Field config
     * @return string Formatted value
     */
    private function formatValue($value, array $field): string
    {
        if ($value === null || $value === '') {
            return '<span class="text-muted">—</span>';
        }

        $format = $field['format'] ?? 'text';

        switch ($format) {
            case 'date':
                return htmlspecialchars(date('m/d/Y', strtotime($value)));

            case 'datetime':
                return htmlspecialchars(date('m/d/Y g:i A', strtotime($value)));

            case 'currency':
                return '$' . number_format((float)$value, 2);

            case 'email':
                return '<a href="mailto:' . htmlspecialchars($value) . '">' . htmlspecialchars($value) . '</a>';

            case 'user':
                // TODO: Look up user name from users table
                return htmlspecialchars($value);

            default:
                return htmlspecialchars($value);
        }
    }

    /**
     * Render available transitions
     *
     * @return string HTML
     */
    private function renderTransitions(): string
    {
        $currentState = $this->getCurrentState();
        $availableTransitions = $this->getAvailableTransitions($currentState);

        if (empty($availableTransitions)) {
            return '<div class="alert alert-info">No actions available for this workflow</div>';
        }

        $html = '<div class="card mb-4">';
        $html .= '<div class="card-header"><h5 class="mb-0">Available Actions</h5></div>';
        $html .= '<div class="card-body">';

        foreach ($availableTransitions as $transition) {
            $html .= $this->renderTransitionButton($transition);
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Get available transitions for current state and user
     *
     * @param string|null $currentState Current state
     * @return array Available transitions
     */
    private function getAvailableTransitions(?string $currentState): array
    {
        if (!$currentState) {
            return [];
        }

        $transitions = $this->config['transitions'] ?? [];
        $available = [];

        foreach ($transitions as $transition) {
            // Check if transition applies to current state
            if ($transition['from'] !== $currentState) {
                continue;
            }

            // Check if user has required role
            if (!$this->canPerformTransition($transition)) {
                continue;
            }

            // Check conditional requirements
            if (!$this->meetsConditions($transition)) {
                continue;
            }

            $available[] = $transition;
        }

        return $available;
    }

    /**
     * Check if user can perform transition
     *
     * @param array $transition Transition config
     * @return bool True if allowed
     */
    private function canPerformTransition(array $transition): bool
    {
        // Check required role
        if (!empty($transition['requiredRole'])) {
            $requiredRole = $transition['requiredRole'];

            // Handle array of roles (any role matches)
            if (is_array($requiredRole)) {
                $hasRole = false;
                foreach ($requiredRole as $role) {
                    if (Auth::hasRole($role)) {
                        $hasRole = true;
                        break;
                    }
                }
                if (!$hasRole) {
                    return false;
                }
            } else {
                if (!Auth::hasRole($requiredRole)) {
                    return false;
                }
            }
        }

        // Check if user is record owner
        if (!empty($transition['requireOwner'])) {
            $ownerField = $this->config['ownerField'] ?? 'created_by';
            $recordOwner = $this->record[$ownerField] ?? null;

            if ($recordOwner !== Auth::getUserId()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if record meets transition conditions
     *
     * @param array $transition Transition config
     * @return bool True if conditions met
     */
    private function meetsConditions(array $transition): bool
    {
        if (empty($transition['conditions'])) {
            return true;
        }

        foreach ($transition['conditions'] as $condition) {
            $field = $condition['field'];
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'];
            $recordValue = $this->record[$field] ?? null;

            $result = $this->evaluateCondition($recordValue, $operator, $value);

            if (!$result) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate condition
     *
     * @param mixed $recordValue Value from record
     * @param string $operator Comparison operator
     * @param mixed $compareValue Value to compare against
     * @return bool Result
     */
    private function evaluateCondition($recordValue, string $operator, $compareValue): bool
    {
        switch ($operator) {
            case '=':
            case '==':
                return $recordValue == $compareValue;

            case '!=':
                return $recordValue != $compareValue;

            case '>':
                return $recordValue > $compareValue;

            case '>=':
                return $recordValue >= $compareValue;

            case '<':
                return $recordValue < $compareValue;

            case '<=':
                return $recordValue <= $compareValue;

            case 'in':
                return is_array($compareValue) && in_array($recordValue, $compareValue);

            case 'not_in':
                return is_array($compareValue) && !in_array($recordValue, $compareValue);

            case 'contains':
                return stripos($recordValue, $compareValue) !== false;

            default:
                return false;
        }
    }

    /**
     * Render transition button
     *
     * @param array $transition Transition config
     * @return string HTML
     */
    private function renderTransitionButton(array $transition): string
    {
        $label = $transition['label'] ?? ucfirst($transition['to']);
        $color = $transition['color'] ?? 'primary';
        $icon = $transition['icon'] ?? '';
        $requireComment = $transition['requireComment'] ?? false;

        $transitionId = 'transition_' . md5($transition['from'] . '_' . $transition['to']);

        $html = '<button type="button" ';
        $html .= 'class="btn btn-' . htmlspecialchars($color) . ' me-2 mb-2" ';
        $html .= 'onclick="showTransitionModal(\'' . htmlspecialchars($transitionId) . '\')">';

        if ($icon) {
            $html .= '<i class="' . htmlspecialchars($icon) . ' me-2"></i>';
        }

        $html .= htmlspecialchars($label);
        $html .= '</button>';

        // Modal for comment (if required or optional)
        if ($requireComment || !empty($transition['allowComment'])) {
            $html .= $this->renderTransitionModal($transition, $transitionId, $requireComment);
        }

        return $html;
    }

    /**
     * Render modal for transition comment
     *
     * @param array $transition Transition config
     * @param string $modalId Modal ID
     * @param bool $required Whether comment is required
     * @return string HTML
     */
    private function renderTransitionModal(array $transition, string $modalId, bool $required): string
    {
        $label = $transition['label'] ?? ucfirst($transition['to']);
        $commentLabel = $transition['commentLabel'] ?? 'Comment';
        $commentPlaceholder = $transition['commentPlaceholder'] ?? 'Enter a comment...';

        $html = '<div class="modal fade" id="' . htmlspecialchars($modalId) . '" tabindex="-1">';
        $html .= '<div class="modal-dialog">';
        $html .= '<div class="modal-content">';

        // Header
        $html .= '<div class="modal-header">';
        $html .= '<h5 class="modal-title">' . htmlspecialchars($label) . '</h5>';
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
        $html .= '</div>';

        // Body
        $html .= '<div class="modal-body">';
        $html .= '<form id="form_' . htmlspecialchars($modalId) . '" method="POST">';

        // Hidden fields
        $html .= '<input type="hidden" name="action" value="transition">';
        $html .= '<input type="hidden" name="transition_from" value="' . htmlspecialchars($transition['from']) . '">';
        $html .= '<input type="hidden" name="transition_to" value="' . htmlspecialchars($transition['to']) . '">';
        $html .= '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'] ?? '') . '">';

        // Comment field
        $html .= '<div class="mb-3">';
        $html .= '<label class="form-label">' . htmlspecialchars($commentLabel);
        if ($required) {
            $html .= ' <span class="text-danger">*</span>';
        }
        $html .= '</label>';
        $html .= '<textarea name="comment" class="form-control" rows="4" ';
        $html .= 'placeholder="' . htmlspecialchars($commentPlaceholder) . '" ';
        if ($required) {
            $html .= 'required ';
        }
        $html .= '></textarea>';
        $html .= '</div>';

        $html .= '</form>';
        $html .= '</div>';

        // Footer
        $html .= '<div class="modal-footer">';
        $html .= '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
        $html .= '<button type="submit" form="form_' . htmlspecialchars($modalId) . '" class="btn btn-primary">Confirm</button>';
        $html .= '</div>';

        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render workflow history
     *
     * @return string HTML
     */
    private function renderHistory(): string
    {
        $history = $this->getHistory();

        if (empty($history)) {
            return '';
        }

        $html = '<div class="card">';
        $html .= '<div class="card-header"><h5 class="mb-0">Workflow History</h5></div>';
        $html .= '<div class="card-body">';

        $html .= '<div class="timeline">';

        foreach ($history as $entry) {
            $html .= $this->renderHistoryEntry($entry);
        }

        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Get workflow history from audit log
     *
     * @return array History entries
     */
    private function getHistory(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                action,
                details,
                user_id,
                created_at
            FROM audit_logs
            WHERE action = 'workflow_transition'
            AND target_type = ?
            AND target_id = ?
            ORDER BY created_at DESC
        ");

        $table = $this->config['table'] ?? '';
        $stmt->execute([$table, $this->recordId]);

        return $stmt->fetchAll();
    }

    /**
     * Render single history entry
     *
     * @param array $entry History entry
     * @return string HTML
     */
    private function renderHistoryEntry(array $entry): string
    {
        $details = json_decode($entry['details'], true) ?? [];
        $fromState = $details['from'] ?? '';
        $toState = $details['to'] ?? '';
        $comment = $details['comment'] ?? '';
        $timestamp = date('m/d/Y g:i A', strtotime($entry['created_at']));

        // Get user name
        // TODO: Look up from users table
        $userName = $entry['user_id'] ?? 'Unknown';

        $html = '<div class="timeline-entry mb-3 pb-3 border-bottom">';
        $html .= '<div class="d-flex justify-content-between align-items-start">';
        $html .= '<div>';

        // Transition description
        $html .= '<strong>' . htmlspecialchars($userName) . '</strong> ';
        $html .= 'changed status from ';
        $html .= '<span class="badge bg-secondary">' . htmlspecialchars($fromState) . '</span> ';
        $html .= 'to ';
        $html .= '<span class="badge bg-primary">' . htmlspecialchars($toState) . '</span>';

        // Comment
        if ($comment) {
            $html .= '<div class="mt-2 text-muted">';
            $html .= '<i class="bi bi-chat-quote me-1"></i>';
            $html .= htmlspecialchars($comment);
            $html .= '</div>';
        }

        $html .= '</div>';

        // Timestamp
        $html .= '<small class="text-muted">' . htmlspecialchars($timestamp) . '</small>';

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Handle workflow action
     *
     * @param array $data Posted data
     * @return array Result
     */
    public function handle(array $data): array
    {
        // Verify CSRF token
        if (empty($data['csrf_token']) || $data['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            return [
                'success' => false,
                'error' => 'Invalid security token'
            ];
        }

        $action = $data['action'] ?? '';

        if ($action === 'transition') {
            return $this->handleTransition($data);
        }

        return [
            'success' => false,
            'error' => 'Invalid action'
        ];
    }

    /**
     * Handle state transition
     *
     * @param array $data Posted data
     * @return array Result
     */
    private function handleTransition(array $data): array
    {
        $fromState = $data['transition_from'] ?? '';
        $toState = $data['transition_to'] ?? '';
        $comment = trim($data['comment'] ?? '');

        // Verify current state matches
        $currentState = $this->getCurrentState();
        if ($currentState !== $fromState) {
            return [
                'success' => false,
                'error' => 'Record state has changed. Please refresh and try again.'
            ];
        }

        // Find transition config
        $transition = $this->findTransition($fromState, $toState);
        if (!$transition) {
            return [
                'success' => false,
                'error' => 'Invalid transition'
            ];
        }

        // Check permissions
        if (!$this->canPerformTransition($transition)) {
            return [
                'success' => false,
                'error' => 'You do not have permission to perform this action'
            ];
        }

        // Check conditions
        if (!$this->meetsConditions($transition)) {
            return [
                'success' => false,
                'error' => 'Transition conditions not met'
            ];
        }

        // Validate comment requirement
        if (!empty($transition['requireComment']) && empty($comment)) {
            return [
                'success' => false,
                'error' => 'Comment is required for this action'
            ];
        }

        try {
            // Update record state
            $this->updateState($toState);

            // Log transition
            AuditLogger::log('workflow_transition', $this->config['table'] ?? 'unknown', $this->recordId, [
                'from' => $fromState,
                'to' => $toState,
                'comment' => $comment,
                'transition_label' => $transition['label'] ?? null
            ]);

            // Send notifications
            if (!empty($transition['notify'])) {
                $this->sendNotifications($transition, $fromState, $toState, $comment);
            }

            // Execute onTransition action
            if (!empty($transition['onTransition'])) {
                $this->executeTransitionAction($transition['onTransition']);
            }

            return [
                'success' => true,
                'message' => 'Status updated successfully',
                'redirect' => $_SERVER['REQUEST_URI'] ?? null
            ];

        } catch (Exception $e) {
            error_log("Workflow transition error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'An error occurred while processing the transition'
            ];
        }
    }

    /**
     * Find transition config
     *
     * @param string $from From state
     * @param string $to To state
     * @return array|null Transition config
     */
    private function findTransition(string $from, string $to): ?array
    {
        foreach ($this->config['transitions'] ?? [] as $transition) {
            if ($transition['from'] === $from && $transition['to'] === $to) {
                return $transition;
            }
        }

        return null;
    }

    /**
     * Update record state
     *
     * @param string $newState New state
     */
    private function updateState(string $newState): void
    {
        $table = $this->config['table'] ?? '';
        $stateField = $this->config['stateField'] ?? 'status';

        $sql = "UPDATE {$table} SET {$stateField} = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$newState, $this->recordId, $_SESSION['tenant_id'] ?? 'default']);
    }

    /**
     * Send transition notifications
     *
     * @param array $transition Transition config
     * @param string $fromState From state
     * @param string $toState To state
     * @param string $comment Comment
     */
    private function sendNotifications(array $transition, string $fromState, string $toState, string $comment): void
    {
        // TODO: Integrate with EmailNotificationRenderer when implemented
        // For now, this is a placeholder

        $recipients = $transition['notify'] ?? [];

        // Could be: ['owner', 'admin', 'specific@email.com']
        // or more complex: [{'role': 'admin'}, {'user': 'user_id'}]
    }

    /**
     * Execute transition action
     *
     * @param array $action Action config
     */
    private function executeTransitionAction(array $action): void
    {
        $type = $action['type'] ?? '';

        switch ($type) {
            case 'updateField':
                $field = $action['field'];
                $value = $action['value'];
                $this->updateField($field, $value);
                break;

            case 'webhook':
                $url = $action['url'];
                $this->triggerWebhook($url);
                break;

            // Add more action types as needed
        }
    }

    /**
     * Update a field on the record
     *
     * @param string $field Field name
     * @param mixed $value New value
     */
    private function updateField(string $field, $value): void
    {
        $table = $this->config['table'] ?? '';

        $sql = "UPDATE {$table} SET {$field} = ? WHERE id = ? AND tenant_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$value, $this->recordId, $_SESSION['tenant_id'] ?? 'default']);
    }

    /**
     * Trigger webhook
     *
     * @param string $url Webhook URL
     */
    private function triggerWebhook(string $url): void
    {
        // TODO: Implement async webhook trigger
        // For now, placeholder
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
        // Must have table
        if (empty($this->config['table'])) {
            return false;
        }

        // Must have states
        if (empty($this->config['states']) || !is_array($this->config['states'])) {
            return false;
        }

        // Must have transitions
        if (empty($this->config['transitions']) || !is_array($this->config['transitions'])) {
            return false;
        }

        // Validate each state
        foreach ($this->config['states'] as $state) {
            if (empty($state['name'])) {
                return false;
            }
        }

        // Validate each transition
        foreach ($this->config['transitions'] as $transition) {
            if (empty($transition['from']) || empty($transition['to'])) {
                return false;
            }
        }

        return true;
    }
}
