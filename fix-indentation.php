<?php
/**
 * Fix indentation in index.php
 * This script normalizes indentation to 4 spaces per level
 */

$file = __DIR__ . '/public/admin/index.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);

$output = [];
$indentLevel = 0;
$inPhpBlock = false;

foreach ($lines as $lineNum => $line) {
    $trimmed = trim($line);
    
    // Skip empty lines - preserve them as-is
    if ($trimmed === '') {
        $output[] = '';
        continue;
    }
    
    // Track PHP blocks
    if (strpos($trimmed, '<?php') !== false) {
        $inPhpBlock = true;
    }
    if (strpos($trimmed, '?>') !== false) {
        $inPhpBlock = false;
    }
    
    // Calculate indent change BEFORE this line
    $dedentBefore = 0;
    if (preg_match('/^<\/(div|button|select|table|thead|tbody|tr|ul|ol|form|section|nav|header|footer|main|aside)/', $trimmed)) {
        $dedentBefore = 1;
    }
    if (preg_match('/^<\?php\s+(endif|endforeach|endwhile|endfor|end)/', $trimmed)) {
        $dedentBefore = 1;
    }
    
    // Apply dedent
    $indentLevel = max(0, $indentLevel - $dedentBefore);
    
    // Output line with current indent
    $indent = str_repeat('    ', $indentLevel);
    $output[] = $indent . $trimmed;
    
    // Calculate indent change AFTER this line
    $indentAfter = 0;
    
    // Opening tags that don't close on same line
    if (preg_match('/<(div|button|select|table|thead|tbody|tr|ul|ol|form|section|nav|header|footer|main|aside)(?:\s|>)/', $trimmed) && 
        !preg_match('/<\/\1>/', $trimmed) && 
        !preg_match('/\/>/', $trimmed)) {
        $indentAfter = 1;
    }
    
    // PHP control structures
    if (preg_match('/<\?php\s+if\s*\(/', $trimmed) && !preg_match('/endif/', $trimmed)) {
        $indentAfter = 1;
    }
    if (preg_match('/<\?php\s+(foreach|while|for)\s*\(/', $trimmed)) {
        $indentAfter = 1;
    }
    
    $indentLevel += $indentAfter;
}

// Write back
file_put_contents($file, implode("\n", $output));

echo "Indentation fixed in index.php\n";
echo "Total lines processed: " . count($lines) . "\n";
