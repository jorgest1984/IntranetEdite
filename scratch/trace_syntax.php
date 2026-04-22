<?php
$content = file_get_contents('includes/sidebar.php');
$tokens = token_get_all($content);
$stack = [];

foreach ($tokens as $index => $token) {
    if (is_array($token)) {
        $id = $token[0];
        $line = $token[2];
        
        if ($id === T_IF || $id === T_FOREACH) {
            // Find next ':'
            $is_colon = false;
            for ($i = $index + 1; $i < count($tokens); $i++) {
                if ($tokens[$i] === ':') { $is_colon = true; break; }
                if ($tokens[$i] === '{' || $tokens[$i] === ';') break;
            }
            if ($is_colon) {
                $stack[] = ['type' => token_name($id), 'line' => $line];
                echo "Open " . token_name($id) . " at line $line\n";
            }
        } elseif ($id === T_ENDIF || $id === T_ENDFOREACH) {
            $last = array_pop($stack);
            echo "Close " . token_name($id) . " at line $line (matching line " . ($last['line'] ?? 'unknown') . ")\n";
        }
    }
}

if (!empty($stack)) {
    echo "Unclosed blocks:\n";
    print_r($stack);
} else {
    echo "All blocks closed.\n";
}
