<?php
return [
    'report' => ['language' => 'ja', 'dir' => 'tmp/security', 'project' => 'Acme'],
    'sca' => ['failOn' => 'medium', 'noDev' => true, 'composer' => 'composer2'],
    'sast' => ['engine' => 'phpstan'],
];
