<?php
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$replacementsCount = 0;
foreach ($files as $name => $file) {
    if (!$file->isDir() && $file->getExtension() === 'php' && strpos($name, 'scratch') === false) {
        $content = file_get_contents($name);
        
        // Skip auth.php as we don't want to replace the define
        if (basename($name) === 'auth.php') continue;

        $newContent = $content;
        
        // 1. Array replacements in has_permission: [..., ROLE_COMERCIAL, ...]
        $newContent = preg_replace('/(has_permission\s*\(\s*\[[^\]]*ROLE_COMERCIAL)(?!,\s*ROLE_JEFE_COMERCIAL)/', '$1, ROLE_JEFE_COMERCIAL', $newContent);
        
        // 2. Case replacements in switches
        $newContent = preg_replace('/(case\s+ROLE_COMERCIAL\s*:)/', "$1\n                        case ROLE_JEFE_COMERCIAL:", $newContent);
        
        // 3. Other edge cases (like in home.php where it's has_permission([ROLE_COMERCIAL]))
        
        if ($newContent !== $content) {
            file_put_contents($name, $newContent);
            echo "Updated: " . basename($name) . "\n";
            $replacementsCount++;
        }
    }
}
echo "Total files updated: $replacementsCount\n";
