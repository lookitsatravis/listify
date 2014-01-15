<?php

require_once __DIR__ . '/test_app/vendor/autoload.php';

function application_autoloader($class) {
    $class = strtolower($class);
    $class_filename = strtolower($class).'.php';
    $class_root = dirname(__FILE__) . "/../src/lookitsatravis";

    $directories = new RecursiveDirectoryIterator($class_root);
    foreach(new RecursiveIteratorIterator($directories) as $file) {
        
        if (strtolower("lookitsatravis\\listify\\".$file->getFilename()) == $class_filename) {
            $full_path = $file->getRealPath();
            require_once $full_path;
            break;
        }

        if (strtolower("lookitsatravis\\listify\\commands\\".$file->getFilename()) == $class_filename) {
            $full_path = $file->getRealPath();
            require_once $full_path;
            break;
        }

        if (strtolower("lookitsatravis\\listify\\exceptions\\".$file->getFilename()) == $class_filename) {
            $full_path = $file->getRealPath();
            require_once $full_path;
            break;
        }
    }
}
 
spl_autoload_register('application_autoloader');