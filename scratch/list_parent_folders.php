<?php
$parent = dirname(dirname(__DIR__));
echo "Parent path: " . $parent . "\n";
if (is_dir($parent)) {
    $files = scandir($parent);
    echo "Files in parent:\n";
    print_r($files);
} else {
    echo "Parent path is not a directory.\n";
}
