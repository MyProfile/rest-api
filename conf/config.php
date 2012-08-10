<?php

/* Version */
define ('VERSION', '0.0.1');

/* IDP address */
define ('IDP', 'https://auth.my-profile.eu/auth/?authreqissuer=');

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
define ('AGENT_IDENTITY', 'https://my-profile.eu/agent/myp/card#me');
define ('CERT_PATH', '../conf/myp.pass.pem');
define ('CERT_PASS', 'password');

/* Private key used to sign the authentication response redirects */
define ('KEY_PATH', '../conf/private.key');

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
