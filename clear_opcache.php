<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPCache cleared.\n";
} else {
    echo "OPCache not enabled.\n";
}
?>
