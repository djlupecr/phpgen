<?php
require 'vendor/autoload.php';
\$db = new PhpGen\Database\Introspector();
\$gen = new PhpGen\Generator\Generator(\$db);
\$gen->generate('usuarios');
echo 'OK';
