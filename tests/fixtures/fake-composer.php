<?php
declare(strict_types=1);

/**
 * A Composer for the tests: its version, or an audit that writes much to the error output first, as many
 * deprecation notices would
 */
if (in_array('--version', $argv, true)) {
    echo "Composer version 2.10.3 2026-08-27 13:34:23\n";

    return;
}
fwrite(STDERR, str_repeat("Deprecated: a notice of a plugin\n", 40000));
echo json_encode(['advisories' => [], 'abandoned' => []]);
exit(0);
