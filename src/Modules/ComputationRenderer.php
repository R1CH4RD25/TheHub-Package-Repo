<?php

namespace Hub\Modules;

use Hub\Database;
use Hub\Auth;
use Hub\AuditLogger;
use Exception;

require_once __DIR__ . '/../bootstrap.php';

/**
 * ComputationRenderer - Server-side calculations and data transformations
 * 
 * Implements Computation module type from MODULE_CATALOG_V2.md [CMP-R01 through CMP-R08]
 * 
 * Use cases:
 * - GPA calculation from grades
 * - Budget totals and summaries
 * - Performance score aggregation
 * - Custom business logic
 * 
 * Features:
 * - Safe expression evaluation
 * - Field references
 * - Math functions (SUM, AVG, MIN, MAX, COUNT)
 * - Conditional logic
 * - Result caching
 * - Audit logging
 * 
 * @author The Hub Team
 * @version 1.0.0
 */
class ComputationRenderer implements ModuleInterface
{
    private array $config;
    private Database $db;
    private AuditLogger $auditLogger;
    
    // Allowed functions for safe evaluation
    private const ALLOWED_FUNCTIONS = [
        'abs', 'ceil', 'floor', 'round', 'max', 'min',
        'sqrt', 'pow', 'log', 'exp',
        'sin', 'cos', 'tan', 'asin', 'acos', 'atan'
    ];
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->db = Database::getInstance();
        $this->auditLogger = new AuditLogger();
    }
    
    /**
     * Render computation result or form
     * 
     * @return string HTML output
     */
    public function render(): string
    {
        if (!$this->validate()) {
            return '<div class="alert alert-danger">Invalid computation configuration</div>';
        }
        
        $displayName = $this->config['displayName'] ?? 'Computation';
        $description = $this->config['description'] ?? '';
        $recordId = $_GET['record_id'] ?? null;
        
        $html = '<div class="computation-container">';
        $html .= '<div class="card">';
        $html .= '<div class="card-header">';
        $html .= '<h5 class="mb-0">' . htmlspecialchars($displayName) . '</h5>';
        $html .= '</div>';
        $html .= '<div class="card-body">';
        
        if ($description) {
            $html .= '<p class="text-muted">' . htmlspecialchars($description) . '</p>';
        }
        
        try {
            if ($recordId) {
                // Compute for specific record
                $result = $this->computeForRecord($recordId);
                $html .= $this->renderResult($result);
            } else {
                // Aggregate computation
                $result = $this->computeAggregate();
                $html .= $this->renderResult($result);
            }
        } catch (Exception $e) {
            $html .= '<div class="alert alert-danger">Computation error: ' 
                   . htmlspecialchars($e->getMessage()) . '</div>';
        }
        
        $html .= '</div>'; // card-body
        $html .= '</div>'; // card
        $html .= '</div>'; // computation-container
        
        return $html;
    }
    
    /**
     * Render computation result
     */
    private function renderResult($result): string
    {
        $format = $this->config['resultFormat'] ?? null;
        $label = $this->config['resultLabel'] ?? 'Result';
        
        $formattedResult = $this->formatValue($result, $format);
        
        $html = '<div class="alert alert-success">';
        $html .= '<h4 class="alert-heading">' . htmlspecialchars($label) . '</h4>';
        $html .= '<h2 class="mb-0">' . htmlspecialchars($formattedResult) . '</h2>';
        $html .= '</div>';
        
        // Show computation details if configured
        if ($this->config['showDetails'] ?? false) {
            $html .= '<div class="mt-3">';
            $html .= '<h6>Computation Details</h6>';
            $html .= '<pre class="bg-light p-3">' . htmlspecialchars($this->config['expression'] ?? '') . '</pre>';
            $html .= '</div>';
        }
        
        return $html;
    }
    
    /**
     * Compute for specific record
     */
    private function computeForRecord(string $recordId)
    {
        $entity = $this->config['entity'] ?? '';
        $expression = $this->config['expression'] ?? '';
        $inputs = $this->config['inputs'] ?? [];
        
        // Load record data
        $sql = "SELECT * FROM $entity WHERE id = :id AND tenant_id = :tenant_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $recordId,
            'tenant_id' => $_SESSION['tenant_id'] ?? 'default'
        ]);
        
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$record) {
            throw new Exception('Record not found');
        }
        
        // Prepare variables for expression
        $variables = [];
        foreach ($inputs as $input) {
            $name = $input['name'] ?? '';
            $field = $input['field'] ?? $name;
            $variables[$name] = $record[$field] ?? 0;
        }
        
        // Evaluate expression
        $result = $this->evaluateExpression($expression, $variables);
        
        // Save result if configured
        if (!empty($this->config['saveToField'])) {
            $this->saveResult($entity, $recordId, $result);
        }
        
        return $result;
    }
    
    /**
     * Compute aggregate across records
     */
    private function computeAggregate()
    {
        $entity = $this->config['entity'] ?? '';
        $expression = $this->config['expression'] ?? '';
        
        // For aggregate computations, use SQL queries
        if (stripos($expression, 'SUM(') !== false || 
            stripos($expression, 'AVG(') !== false ||
            stripos($expression, 'COUNT(') !== false ||
            stripos($expression, 'MIN(') !== false ||
            stripos($expression, 'MAX(') !== false) {
            
            return $this->evaluateAggregateSQL($expression, $entity);
        }
        
        throw new Exception('Aggregate computation requires SUM, AVG, COUNT, MIN, or MAX functions');
    }
    
    /**
     * Evaluate SQL aggregate expression
     */
    private function evaluateAggregateSQL(string $expression, string $entity)
    {
        // Parse expression to extract SQL aggregate
        $sql = "SELECT $expression as result FROM $entity WHERE tenant_id = :tenant_id";
        
        // Apply filters if configured
        if (!empty($this->config['filters'])) {
            foreach ($this->config['filters'] as $filter) {
                $field = $filter['field'] ?? '';
                $operator = $filter['operator'] ?? '=';
                $value = $filter['value'] ?? '';
                
                if ($field && $value) {
                    $sql .= " AND $field $operator :filter_$field";
                }
            }
        }
        
        $stmt = $this->db->prepare($sql);
        $params = ['tenant_id' => $_SESSION['tenant_id'] ?? 'default'];
        
        // Add filter params
        if (!empty($this->config['filters'])) {
            foreach ($this->config['filters'] as $filter) {
                $field = $filter['field'] ?? '';
                $value = $filter['value'] ?? '';
                if ($field && $value) {
                    $params["filter_$field"] = $value;
                }
            }
        }
        
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $row['result'] ?? 0;
    }
    
    /**
     * Evaluate expression safely
     * 
     * [CMP-R02] No eval(), system(), exec() - safe evaluation only
     */
    private function evaluateExpression(string $expression, array $variables)
    {
        // Replace variable names with values
        foreach ($variables as $name => $value) {
            $expression = str_replace('$' . $name, $value, $expression);
        }
        
        // [CMP-R02] Validate expression is safe
        if (!$this->isSafeExpression($expression)) {
            throw new Exception('Unsafe expression detected');
        }
        
        // Evaluate using custom parser (safe alternative to eval)
        return $this->safeEvaluate($expression);
    }
    
    /**
     * Check if expression is safe
     */
    private function isSafeExpression(string $expression): bool
    {
        // Disallow dangerous functions
        $dangerous = ['eval', 'exec', 'system', 'shell_exec', 'passthru', 'file', 'fopen', 'include', 'require'];
        
        foreach ($dangerous as $func) {
            if (stripos($expression, $func) !== false) {
                return false;
            }
        }
        
        // Only allow numbers, operators, and approved functions
        $pattern = '/^[0-9+\-*\/().,' . implode('|', self::ALLOWED_FUNCTIONS) . '\s]+$/';
        
        return preg_match($pattern, $expression) === 1;
    }
    
    /**
     * Safe expression evaluator (without eval)
     */
    private function safeEvaluate(string $expression)
    {
        // Remove whitespace
        $expression = preg_replace('/\s+/', '', $expression);
        
        // Handle parentheses recursively
        while (preg_match('/\(([^()]+)\)/', $expression, $matches)) {
            $inner = $matches[1];
            $result = $this->evaluateSimple($inner);
            $expression = str_replace($matches[0], $result, $expression);
        }
        
        // Evaluate remaining expression
        return $this->evaluateSimple($expression);
    }
    
    /**
     * Evaluate simple expression (no parentheses)
     */
    private function evaluateSimple(string $expression): float
    {
        // Handle functions
        foreach (self::ALLOWED_FUNCTIONS as $func) {
            if (stripos($expression, $func) !== false) {
                $pattern = "/$func\(([0-9.]+)\)/i";
                $expression = preg_replace_callback($pattern, function($m) use ($func) {
                    return call_user_func($func, (float)$m[1]);
                }, $expression);
            }
        }
        
        // Evaluate arithmetic
        // Order of operations: *, /, +, -
        
        // Multiplication and division
        while (preg_match('/(-?[0-9.]+)\s*([*\/])\s*(-?[0-9.]+)/', $expression, $m)) {
            $result = $m[2] === '*' ? ((float)$m[1] * (float)$m[3]) : ((float)$m[1] / (float)$m[3]);
            $expression = str_replace($m[0], $result, $expression);
        }
        
        // Addition and subtraction
        while (preg_match('/(-?[0-9.]+)\s*([+\-])\s*(-?[0-9.]+)/', $expression, $m)) {
            $result = $m[2] === '+' ? ((float)$m[1] + (float)$m[3]) : ((float)$m[1] - (float)$m[3]);
            $expression = str_replace($m[0], $result, $expression);
        }
        
        return (float)$expression;
    }
    
    /**
     * Save computation result to record
     */
    private function saveResult(string $entity, string $recordId, $result): void
    {
        $field = $this->config['saveToField'];
        
        $sql = "UPDATE $entity SET $field = :result, updated_at = NOW() 
                WHERE id = :id AND tenant_id = :tenant_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'result' => $result,
            'id' => $recordId,
            'tenant_id' => $_SESSION['tenant_id'] ?? 'default'
        ]);
        
        // [CMP-R07] Log to audit
        $this->auditLogger->log(
            'computation_saved',
            $entity,
            $recordId,
            null,
            [$field => $result],
            Auth::getCurrentUserId()
        );
    }
    
    /**
     * Format value based on format type
     */
    private function formatValue($value, ?string $format): string
    {
        if ($format === 'currency') {
            return '$' . number_format((float)$value, 2);
        } elseif ($format === 'number') {
            return number_format((float)$value);
        } elseif ($format === 'percent') {
            return number_format((float)$value, 1) . '%';
        } elseif ($format === 'decimal') {
            return number_format((float)$value, 2);
        }
        
        return (string)$value;
    }
    
    /**
     * Handle computation request
     * 
     * @param array $data Input data
     * @return array Result
     */
    public function handle(array $data): array
    {
        try {
            $recordId = $data['record_id'] ?? null;
            
            if ($recordId) {
                $result = $this->computeForRecord($recordId);
            } else {
                $result = $this->computeAggregate();
            }
            
            return [
                'success' => true,
                'result' => $result,
                'formatted' => $this->formatValue($result, $this->config['resultFormat'] ?? null)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
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
        // Must have expression
        if (empty($this->config['expression'])) {
            return false;
        }
        
        // Must have entity
        if (empty($this->config['entity'])) {
            return false;
        }
        
        // Expression must be safe
        if (!$this->isSafeExpression($this->config['expression'])) {
            return false;
        }
        
        return true;
    }
}
