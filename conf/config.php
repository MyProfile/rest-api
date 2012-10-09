<?php

/* Version */
define ('VERSION', '0.0.1');

/* Cache time to live - default 48h */
define ('CACHE_TTL', '172800');

/* SPARQL endpoint */
define ('SPARQL_ENDPOINT', 'http://localhost:8890/sparql');

/* SMTP config */
define ('SMTP_EMAIL', '');
define ('SMTP_AUTHENTICATION', '');
define ('SMTP_SERVER', '');
define ('SMTP_USERNAME', '');
define ('SMTP_PASSWORD', '');

/* Agent Identity */
define ('AGENT_IDENTITY', ''); // The WebID of the agent
define ('CERT_PATH', ''); // The WebID certificate path
define ('CERT_PASS', ''); // The WebID certificate pass

/* Private key used to sign the authentication response redirects */
define ('KEY_PATH', '');

// Get the current document URI
$page_uri = 'http';
if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == 'on') {
    $page_uri .= 's';
}
$page_uri .= '://' . $_SERVER['SERVER_NAME'];
// this is the base uri 
$base_uri = $page_uri;
define ('BASE_URI', $base_uri);
?>
