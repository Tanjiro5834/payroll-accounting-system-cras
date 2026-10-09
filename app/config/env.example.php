<?php
// Copy to app/config/env.php on the server and fill in. env.php is git-ignored and
// blocked from the web by .htaccess. Values become environment variables (getenv).
return [
    'APP_ENV'   => 'production',   // 'local' on XAMPP
    'APP_DEBUG' => '0',            // '1' shows PHP errors in the browser; never on production

    // Hostinger: hPanel → Databases → MySQL Databases (create a NEW database just for payroll)
    'DB_HOST'   => 'localhost',
    'DB_NAME'   => 'u000000000_payroll',
    'DB_USER'   => 'u000000000_payroll',
    'DB_PASS'   => 'change-me',
];
