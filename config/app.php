<?php
declare(strict_types=1);

define('APP_NAME', 'RateMyFaculty');
define('APP_ENV', 'development'); // set to 'production' before real deployment

// Web root path of the project. Change this if you install RateMyFaculty
// under a different folder name inside htdocs.
define('BASE_URL', '/ratemyfaculty');

ini_set('log_errors', '1');
error_reporting(E_ALL);

if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}