<?php
// Run: C:\php\php.exe remove_comments.php
// Removes all PHP and HTML comments from every .php file in the project.

$projectRoot = __DIR__;
$skipDirs    = ['vendor', '.git', 'docs', 'database'];   // leave schema/seed files alone
$dryRun      = in_array('--dry-run', $argv);

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS)
);

$total   = 0;
$changed = 0;

foreach ($files as $file) {
    if ($file->getExtension() !== 'php') continue;

    // Skip excluded directories
    $rel = ltrim(str_replace($projectRoot, '', $file->getPathname()), DIRECTORY_SEPARATOR . '/');
    foreach ($skipDirs as $skip) {
        if (str_starts_with($rel, $skip . DIRECTORY_SEPARATOR) || str_starts_with($rel, $skip . '/')) {
            continue 2;
        }
    }
    // Skip this script itself
    if ($file->getFilename() === 'remove_comments.php') continue;

    $total++;
    $original = file_get_contents($file->getPathname());
    $cleaned  = stripPhpComments($original);

    if ($cleaned !== $original) {
        $changed++;
        echo ($dryRun ? '[DRY] ' : '') . $rel . "\n";
        if (!$dryRun) {
            file_put_contents($file->getPathname(), $cleaned);
        }
    }
}

echo "\nDone. Scanned {$total} files, modified {$changed}.\n";
if ($dryRun) echo "(Dry run — no files written)\n";

// ---------------------------------------------------------------------------
function stripPhpComments(string $source): string
{
    $tokens = token_get_all($source);
    $out    = '';

    foreach ($tokens as $tok) {
        if (is_array($tok)) {
            [$type, $text] = $tok;

            // Drop PHP single-line and block comments
            if ($type === T_COMMENT || $type === T_DOC_COMMENT) {
                // Preserve newlines so line numbers stay roughly intact
                $out .= str_repeat("\n", substr_count($text, "\n"));
                continue;
            }

            // For inline HTML, strip <!-- ... --> HTML comments
            if ($type === T_INLINE_HTML) {
                $text = preg_replace('/<!--.*?-->/s', '', $text);
            }

            $out .= $text;
        } else {
            // Single-character token (punctuation, operators, etc.)
            $out .= $tok;
        }
    }

    // Collapse 3+ consecutive blank lines down to 1
    $out = preg_replace("/(\r?\n){3,}/", "\n\n", $out);

    return $out;
}
