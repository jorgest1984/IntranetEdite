<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__));
$count = 0;
foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && 
        $file->getFilename() !== 'refactor_sessions.php' && 
        $file->getFilename() !== 'SessionHandlerDB.php') 
    {
        $path = $file->getRealPath();
        if (strpos($path, 'vendor') !== false || strpos($path, '.gemini') !== false) { continue; }
        
        $content = file_get_contents($path);
        $original = $content;
        
        // Match specific common session start lines
        $content = preg_replace('/^[ \t]*if\s*\(\s*session_status\(\)\s*===\s*PHP_SESSION_NONE\s*\)\s*\{\s*session_start\(\);\s*\}[ \t]*\r?\n/mi', '', $content);
        $content = preg_replace('/^[ \t]*session_start\(\);[ \t]*\r?\n/mi', '', $content);
        
        if ($content !== $original) {
            file_put_contents($path, $content);
            $count++;
            echo "Refactored: " . basename($path) . "\n";
        }
    }
}
echo "Total files updated: $count\n";
