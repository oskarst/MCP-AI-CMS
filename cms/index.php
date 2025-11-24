<?php
/**
 * CMS Directory - Direct access not allowed
 */

http_response_code(404);
header('Content-Type: text/plain');
echo '404 Not Found';
exit;
