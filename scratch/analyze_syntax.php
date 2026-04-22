<?php
$content = file_get_contents('includes/sidebar.php');
$tokens = token_get_all($content);
$stack = [];

foreach ($tokens as $index => $token) {
    if (is_array($token)) {
        $id = $token[0];
        $name = token_name($id);
        $line = $token[2];
        
        if ($id === T_IF || $id === T_FOREACH || $id === T_WHILE || $id === T_FOR || $id === T_SWITCH) {
            // Check if it uses colon
            $next_colon = false;
            for ($i = $index + 1; $i < count($tokens); $i++) {
                if ($tokens[$i] === ':') { $next_colon = true; break; }
                if ($tokens[$i] === '{' || $tokens[$i] === ';') { break; }
            }
            if ($next_colon) {
                $stack[] = ['type' => $name, 'line' => $line];
            }
        } elseif ($id === T_ENDIF || $id === T_ENDFOREACH || $id === T_ENDWHILE || $id === T_ENDFOR || $id === T_ENDSWITCH) {
            if (!empty($stack)) {
                array_pop($stack);
            } else {
                echo "Warning: Extra closing tag $name at line $line\n";
            }
        }
    }
}

if (empty($stack)) {
    echo "All blocks are closed.\n";
} else {
    echo "Unclosed blocks found:\n";
    print_r($stack);
}
