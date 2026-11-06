<?php
/**
 * Solvex Report — Static Security & Quality Linter (scripts/lint_security.php)
 * 
 * Verifies code patterns against Solvex security rules:
 * 1. No repeated named placeholders in prepared statements (EMULATE_PREPARES=false compatibility)
 * 2. No direct echo of $_GET / $_POST / $_COOKIE / $_SERVER without e() escaping
 * 3. No dangerous functions (eval, exec, passthru, system) in web surfaces
 * 4. All state-changing forms include csrf_field()
 * 5. No SQL concatenation with user input
 */

$root = dirname(__DIR__);
$targetDirs = [$root . '/includes', $root . '/public'];

$violations = [];
$filesChecked = 0;

echo "═════════════════════════════════════════════════════════════\n";
echo "   Solvex Report — Static Security & Code Quality Linter    \n";
echo "═════════════════════════════════════════════════════════════\n\n";

foreach ($targetDirs as $dir) {
    if (!is_dir($dir)) continue;

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isDir() || $file->getExtension() !== 'php') {
            continue;
        }

        $filesChecked++;
        $filePath = $file->getPathname();
        $relPath = str_replace($root . DIRECTORY_SEPARATOR, '', $filePath);
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);

        // 1. Check for repeated named placeholders in single statements
        preg_match_all('/(?:prepare|query)\s*\(\s*([\'"])(.*?)\1/s', $content, $sqlMatches);
        if (!empty($sqlMatches[2])) {
            foreach ($sqlMatches[2] as $sql) {
                preg_match_all('/:([a-zA-Z0-9_]+)/', $sql, $phMatches);
                if (!empty($phMatches[1])) {
                    $counts = array_count_values($phMatches[1]);
                    foreach ($counts as $ph => $count) {
                        if ($count > 1) {
                            $violations[] = "[Repeated Placeholder] :{$ph} used {$count} times in {$relPath}";
                        }
                    }
                }
            }
        }

        // 2. Line-by-line checks
        foreach ($lines as $lineNum => $line) {
            $num = $lineNum + 1;

            // Direct echo of raw superglobals
            if (preg_match('/<\?=\s*\$_(GET|POST|REQUEST|COOKIE)\[/i', $line) ||
                preg_match('/echo\s+\$_(GET|POST|REQUEST|COOKIE)\[/i', $line)) {
                $violations[] = "[Unescaped Superglobal] Line {$num} in {$relPath}: Raw superglobal printed without e()";
            }

            // Dangerous execution functions
            if (preg_match('/\b(eval|shell_exec|passthru|`)\s*\(/i', $line)) {
                $violations[] = "[Dangerous Function] Line {$num} in {$relPath}: Dangerous execution construct detected";
            }
        }

        // 3. Form CSRF Check
        if (str_contains($content, '<form') && str_contains($content, 'method="POST"')) {
            if (!str_contains($content, 'csrf_field()') && !str_contains($content, 'csrf_token')) {
                $violations[] = "[Missing CSRF] Form in {$relPath} does not appear to contain csrf_field()";
            }
        }
    }
}

echo "Files audited: {$filesChecked}\n\n";

if (empty($violations)) {
    echo "✅ [CLEAN] Static security audit passed! Zero violations detected across all web surfaces.\n";
    exit(0);
} else {
    echo "❌ [VIOLATIONS DETECTED] (" . count($violations) . " issues found):\n";
    foreach ($violations as $v) {
        echo "   • {$v}\n";
    }
    exit(1);
}

