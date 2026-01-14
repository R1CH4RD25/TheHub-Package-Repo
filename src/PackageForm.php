<?php

namespace Hub;

use PDO;

/**
 * Package Form Management
 * 
 * Handles dynamic form definitions, submissions, and alerts
 */
class PackageForm
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Create a new form for a package
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO package_forms 
            (package_id, name, description, context, icon, success_message, display_order, created_by)
            VALUES (:package_id, :name, :description, :context, :icon, :success_message, :display_order, :created_by)
        ");

        $stmt->execute([
            'package_id' => $data['package_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'context' => $data['context'] ?? 'hub',
            'icon' => $data['icon'] ?? 'bi-file-text',
            'success_message' => $data['success_message'] ?? null,
            'display_order' => $data['display_order'] ?? 0,
            'created_by' => $data['created_by'] ?? null
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Get all forms for a package
     */
    public function getByPackage(int $packageId, string $context = null): array
    {
        $sql = "SELECT * FROM package_forms WHERE package_id = :package_id AND is_active = 1";
        if ($context) {
            $sql .= " AND context = :context";
        }
        $sql .= " ORDER BY display_order, name";

        $stmt = $this->db->prepare($sql);
        $params = ['package_id' => $packageId];
        if ($context) {
            $params['context'] = $context;
        }
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a single form by ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM package_forms WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Update a form
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE package_forms 
            SET name = :name,
                description = :description,
                context = :context,
                icon = :icon,
                success_message = :success_message,
                display_order = :display_order
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'context' => $data['context'] ?? 'hub',
            'icon' => $data['icon'] ?? 'bi-file-text',
            'success_message' => $data['success_message'] ?? null,
            'display_order' => $data['display_order'] ?? 0
        ]);
    }

    /**
     * Delete a form (and all its fields, submissions, alerts via CASCADE)
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM package_forms WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Add a field to a form
     */
    public function addField(int $formId, array $fieldData): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO package_form_fields
            (form_id, field_key, label, field_type, placeholder, help_text, 
             options_json, validation_rules, default_value, display_order, 
             is_required, is_visible, conditional_logic)
            VALUES (:form_id, :field_key, :label, :field_type, :placeholder, :help_text,
                    :options_json, :validation_rules, :default_value, :display_order,
                    :is_required, :is_visible, :conditional_logic)
        ");

        $stmt->execute([
            'form_id' => $formId,
            'field_key' => $fieldData['field_key'],
            'label' => $fieldData['label'],
            'field_type' => $fieldData['field_type'],
            'placeholder' => $fieldData['placeholder'] ?? null,
            'help_text' => $fieldData['help_text'] ?? null,
            'options_json' => isset($fieldData['options']) ? json_encode($fieldData['options']) : null,
            'validation_rules' => isset($fieldData['validation_rules']) ? json_encode($fieldData['validation_rules']) : null,
            'default_value' => $fieldData['default_value'] ?? null,
            'display_order' => $fieldData['display_order'] ?? 0,
            'is_required' => $fieldData['is_required'] ?? 0,
            'is_visible' => $fieldData['is_visible'] ?? 1,
            'conditional_logic' => isset($fieldData['conditional_logic']) ? json_encode($fieldData['conditional_logic']) : null
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Get all fields for a form
     */
    public function getFields(int $formId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM package_form_fields 
            WHERE form_id = :form_id 
            ORDER BY display_order, id
        ");
        $stmt->execute(['form_id' => $formId]);
        $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON fields
        foreach ($fields as &$field) {
            if ($field['options_json']) {
                $field['options'] = json_decode($field['options_json'], true);
            }
            if ($field['validation_rules']) {
                $field['validation'] = json_decode($field['validation_rules'], true);
            }
            if ($field['conditional_logic']) {
                $field['conditional'] = json_decode($field['conditional_logic'], true);
            }
        }

        return $fields;
    }

    /**
     * Update a field
     */
    public function updateField(int $fieldId, array $fieldData): bool
    {
        $stmt = $this->db->prepare("
            UPDATE package_form_fields
            SET label = :label,
                field_type = :field_type,
                placeholder = :placeholder,
                help_text = :help_text,
                options_json = :options_json,
                validation_rules = :validation_rules,
                default_value = :default_value,
                display_order = :display_order,
                is_required = :is_required,
                is_visible = :is_visible,
                conditional_logic = :conditional_logic
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $fieldId,
            'label' => $fieldData['label'],
            'field_type' => $fieldData['field_type'],
            'placeholder' => $fieldData['placeholder'] ?? null,
            'help_text' => $fieldData['help_text'] ?? null,
            'options_json' => isset($fieldData['options']) ? json_encode($fieldData['options']) : null,
            'validation_rules' => isset($fieldData['validation_rules']) ? json_encode($fieldData['validation_rules']) : null,
            'default_value' => $fieldData['default_value'] ?? null,
            'display_order' => $fieldData['display_order'] ?? 0,
            'is_required' => $fieldData['is_required'] ?? 0,
            'is_visible' => $fieldData['is_visible'] ?? 1,
            'conditional_logic' => isset($fieldData['conditional_logic']) ? json_encode($fieldData['conditional_logic']) : null
        ]);
    }

    /**
     * Delete a field
     */
    public function deleteField(int $fieldId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM package_form_fields WHERE id = :id");
        return $stmt->execute(['id' => $fieldId]);
    }

    /**
     * Submit a form (create submission)
     */
    public function submit(int $formId, int $userId, array $data, string $ipAddress = null, string $userAgent = null): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO package_form_submissions
            (form_id, submitted_by, data_json, ip_address, user_agent)
            VALUES (:form_id, :submitted_by, :data_json, :ip_address, :user_agent)
        ");

        $stmt->execute([
            'form_id' => $formId,
            'submitted_by' => $userId,
            'data_json' => json_encode($data),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);

        $submissionId = (int) $this->db->lastInsertId();

        // Trigger alert evaluation
        $this->evaluateAlerts($submissionId, $formId, $data);

        return $submissionId;
    }

    /**
     * Get submissions for a form
     */
    public function getSubmissions(int $formId, array $filters = []): array
    {
        $sql = "
            SELECT s.*, u.name as submitted_by_name, u.email as submitted_by_email
            FROM package_form_submissions s
            LEFT JOIN users u ON s.submitted_by = u.id
            WHERE s.form_id = :form_id
        ";

        $params = ['form_id' => $formId];

        if (!empty($filters['status'])) {
            $sql .= " AND s.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND s.submitted_at >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND s.submitted_at <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY s.submitted_at DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT :limit";
            $params['limit'] = (int) $filters['limit'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON data
        foreach ($submissions as &$submission) {
            $submission['data'] = json_decode($submission['data_json'], true);
        }

        return $submissions;
    }

    /**
     * Evaluate alert rules for a submission
     */
    private function evaluateAlerts(int $submissionId, int $formId, array $data): void
    {
        // Get all active alerts for this form
        $stmt = $this->db->prepare("
            SELECT * FROM package_form_alerts 
            WHERE form_id = :form_id AND is_active = 1
        ");
        $stmt->execute(['form_id' => $formId]);
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($alerts as $alert) {
            $shouldTrigger = false;

            if ($alert['trigger_type'] === 'always') {
                $shouldTrigger = true;
            } else {
                // Check conditions
                $shouldTrigger = $this->checkAlertConditions($alert['id'], $data);
            }

            if ($shouldTrigger) {
                $this->queueAlert($submissionId, $alert, $data);
            }
        }
    }

    /**
     * Check if alert conditions are met
     */
    private function checkAlertConditions(int $alertId, array $data): bool
    {
        $stmt = $this->db->prepare("
            SELECT * FROM package_form_alert_conditions 
            WHERE alert_id = :alert_id 
            ORDER BY id
        ");
        $stmt->execute(['alert_id' => $alertId]);
        $conditions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($conditions)) {
            return true; // No conditions = always trigger
        }

        $result = true;
        $currentLogic = 'AND';

        foreach ($conditions as $condition) {
            $fieldValue = $data[$condition['field_key']] ?? null;
            $conditionMet = $this->evaluateCondition($fieldValue, $condition['operator'], $condition['value']);

            if ($currentLogic === 'AND') {
                $result = $result && $conditionMet;
            } else {
                $result = $result || $conditionMet;
            }

            $currentLogic = $condition['logic_type'];
        }

        return $result;
    }

    /**
     * Evaluate a single condition
     */
    private function evaluateCondition($fieldValue, string $operator, $expectedValue): bool
    {
        switch ($operator) {
            case 'equals':
                return $fieldValue == $expectedValue;
            case 'not_equals':
                return $fieldValue != $expectedValue;
            case 'contains':
                return stripos($fieldValue, $expectedValue) !== false;
            case 'not_contains':
                return stripos($fieldValue, $expectedValue) === false;
            case 'greater_than':
                return $fieldValue > $expectedValue;
            case 'less_than':
                return $fieldValue < $expectedValue;
            case 'is_empty':
                return empty($fieldValue);
            case 'is_not_empty':
                return !empty($fieldValue);
            default:
                return false;
        }
    }

    /**
     * Queue an alert for sending
     */
    private function queueAlert(int $submissionId, array $alert, array $data): void
    {
        // Determine recipient email
        $recipientEmail = $alert['recipient_email'];
        
        if ($alert['recipient_type'] === 'user' && $alert['recipient_id']) {
            $stmt = $this->db->prepare("SELECT email FROM users WHERE id = :id");
            $stmt->execute(['id' => $alert['recipient_id']]);
            $recipientEmail = $stmt->fetchColumn();
        } elseif ($alert['recipient_type'] === 'org_role' && $alert['recipient_id']) {
            // Get all users with this org role
            $stmt = $this->db->prepare("
                SELECT u.email 
                FROM users u
                JOIN user_org_roles uor ON u.id = uor.user_id
                WHERE uor.org_role_id = :role_id
            ");
            $stmt->execute(['role_id' => $alert['recipient_id']]);
            $recipientEmail = implode(',', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        // Log the alert
        $stmt = $this->db->prepare("
            INSERT INTO package_form_alert_log
            (submission_id, alert_id, recipient_type, recipient_id, recipient_email, notification_method, status)
            VALUES (:submission_id, :alert_id, :recipient_type, :recipient_id, :recipient_email, :notification_method, 'pending')
        ");

        $stmt->execute([
            'submission_id' => $submissionId,
            'alert_id' => $alert['id'],
            'recipient_type' => $alert['recipient_type'],
            'recipient_id' => $alert['recipient_id'],
            'recipient_email' => $recipientEmail,
            'notification_method' => $alert['notification_method']
        ]);

        // TODO: Actually send the email/SMS (integrate with existing mail system)
    }
}
