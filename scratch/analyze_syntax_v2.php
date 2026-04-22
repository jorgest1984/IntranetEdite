<?php
$content = file_get_contents('includes/sidebar.php');
$tokens = token_get_all($content);
$brace_stack = 0;
$colon_stack = [];

foreach ($tokens as $index => $token) {
    if (is_array($token)) {
        $id = $token[0];
        $line = $token[2];
        
        if ($id === T_IF || $id === T_FOREACH || $id === T_WHILE || $id === T_FOR || $id === T_SWITCH) {
            // Find next ':' or '{'
            for ($i = $index + 1; $i < count($tokens); $i++) {
                if ($tokens[$i] === ':') {
                    $colon_stack[] = ['type' => token_name($id), 'line' => $line];
                    break;
                }
                if ($tokens[$i] === '{') {
                    // Braces are handled by the '{' token itself
                    break;
                }
                if ($tokens[$i] === ';') break; // Single line if
            }
        } elseif ($id === T_ENDIF || $id === T_ENDFOREACH || $id === T_ENDWHILE || $id === T_ENDFOR || $id === T_ENDSWITCH) {
            array_pop($colon_stack);
        }
    } else {
        if ($token === '{') $brace_stack++;
        if ($token === '}') $brace_stack--;
    }
}

echo "Brace balance: $brace_stack\n";
if (!empty($colon_stack)) {
    echo "Unclosed colon blocks:\n";
    print_r($colon_stack);
} else {
    echo "Colon blocks are balanced.\n";
}
