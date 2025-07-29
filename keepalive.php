<?php
// Keep session alive for AJAX ping
ini_set('session.gc_maxlifetime', 5400); // 90 minutes
session_set_cookie_params(5400);
session_start();
http_response_code(204);
