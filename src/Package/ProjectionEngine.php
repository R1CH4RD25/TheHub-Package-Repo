<?php

namespace Hub\Package;

/**
 * Projection Engine
 * 
 * Applies field masking to query results based on data classification.
 * Redacts sensitive fields based on user permissions.
 * 
 * Version: v0 - Basic masking support (Sprint 0)
 * 
 * @see PACKAGE_ARCHITECTURE_SPEC.md §12 (Enforcement Pipelines)
 * @see PACKAGE_ARCHITECTURE_SPEC.md §15 (Data Classification & Encryption Rules)
 */
class ProjectionEngine
{
    /** @var string Redaction placeholder for masked fields */
    private const REDACTED = '[REDACTED]';

    /** @var string Placeholder for secret fields (never revealed directly) */
    private const SECRET = '[SECRET - Use reveal token]';

    /**
     * Apply field masking to query result
     * 
     * @param array $result Query result (single row or array of rows)
     * @param array $fieldMasks Field keys to mask from PolicyEngine::getFieldMasks()
     * @param array $dataClassifications Field key => classification map from PackageValidator
     * 
     * @return array Masked result
     */
    public function maskFields(array $result, array $fieldMasks, array $dataClassifications = []): array
    {
        // Check if this is a collection of rows or single row
        if (isset($result['data']) && is_array($result['data'])) {
            // Collection format: { data: [rows], meta: {...} }
            $result['data'] = array_map(function($row) use ($fieldMasks, $dataClassifications) {
                return $this->maskRow($row, $fieldMasks, $dataClassifications);
            }, $result['data']);
        } elseif ($this->isRow($result)) {
            // Single row
            $result = $this->maskRow($result, $fieldMasks, $dataClassifications);
        }

        return $result;
    }

    /**
     * Mask fields in a single row
     * 
     * @param array $row Single data row
     * @param array $fieldMasks Fields to mask
     * @param array $dataClassifications Field classifications
     * 
     * @return array Masked row
     */
    private function maskRow(array $row, array $fieldMasks, array $dataClassifications): array
    {
        foreach ($row as $key => $value) {
            // Check if field should be masked
            if (in_array($key, $fieldMasks)) {
                // Determine classification
                $classification = $dataClassifications[$key] ?? 'internal';

                // Apply appropriate redaction
                $row[$key] = $this->getRedactionValue($classification);
            } elseif (isset($dataClassifications[$key]) && $dataClassifications[$key] === 'secret') {
                // Always mask secret fields (regardless of fieldMasks)
                $row[$key] = self::SECRET;
            }
        }

        return $row;
    }

    /**
     * Get redaction value based on data classification
     * 
     * @param string $classification Data classification level
     * 
     * @return string Redaction placeholder
     */
    private function getRedactionValue(string $classification): string
    {
        return match($classification) {
            'secret' => self::SECRET,
            'regulated' => self::REDACTED,
            'confidential' => self::REDACTED,
            default => self::REDACTED
        };
    }

    /**
     * Check if array is a data row (not a collection)
     * 
     * @param array $array Array to check
     * 
     * @return bool True if this looks like a single row
     */
    private function isRow(array $array): bool
    {
        // If all keys are strings, likely a row
        foreach (array_keys($array) as $key) {
            if (is_int($key)) {
                return false; // Numeric keys suggest array of rows
            }
        }
        return true;
    }

    /**
     * Remove masked fields entirely (for APIs that shouldn't show [REDACTED])
     * 
     * Alternative to masking - removes fields entirely instead of showing [REDACTED].
     * 
     * @param array $result Query result
     * @param array $fieldMasks Fields to remove
     * 
     * @return array Result with fields removed
     */
    public function removeFields(array $result, array $fieldMasks): array
    {
        if (isset($result['data']) && is_array($result['data'])) {
            $result['data'] = array_map(function($row) use ($fieldMasks) {
                foreach ($fieldMasks as $field) {
                    unset($row[$field]);
                }
                return $row;
            }, $result['data']);
        } elseif ($this->isRow($result)) {
            foreach ($fieldMasks as $field) {
                unset($result[$field]);
            }
        }

        return $result;
    }

    /**
     * Get statistics about field masking
     * 
     * @param array $result Query result
     * @param array $fieldMasks Fields that were masked
     * 
     * @return array Statistics
     */
    public function getStats(array $result, array $fieldMasks): array
    {
        $stats = [
            'totalFields' => 0,
            'maskedFields' => count($fieldMasks),
            'rows' => 0,
            'redactions' => 0,
        ];

        if (isset($result['data']) && is_array($result['data'])) {
            $stats['rows'] = count($result['data']);
            if (!empty($result['data'])) {
                $stats['totalFields'] = count($result['data'][0]);
                $stats['redactions'] = $stats['rows'] * $stats['maskedFields'];
            }
        } elseif ($this->isRow($result)) {
            $stats['rows'] = 1;
            $stats['totalFields'] = count($result);
            $stats['redactions'] = $stats['maskedFields'];
        }

        return $stats;
    }
}
