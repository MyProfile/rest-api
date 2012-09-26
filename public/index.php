<?php
/*
 *  Copyright (C) 2012 MyProfile Project - http://myprofile-project.org
 *  
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal 
 *  in the Software without restriction, including without limitation the rights 
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell 
 *  copies of the Software, and to permit persons to whom the Software is furnished 
 *  to do so, subject to the following conditions:

 *  The above copyright notice and this permission notice shall be included in all 
 *  copies or substantial portions of the Software.

 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, 
 *  INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A 
 *  PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT 
 *  HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION 
 *  OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE 
 *  SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
 
set_include_path(get_include_path() . PATH_SEPARATOR . '../');
set_include_path(get_include_path() . PATH_SEPARATOR . '../lib/');

/* ---- LIBRARIES ---- */

// Load the Slim framework and related stuff
require 'lib/Slim/Slim.php';
require 'middleware/ContentType.php';

// Load libs
require 'lib/logger.php';

// RDF libs
require 'lib/arc/ARC2.php';
require 'lib/EasyRdf.php';
require 'lib/sparqllib.php';

// Load local classes
require 'classes/Profile.php';

// load controller
require 'controllers/Profile.php';

// Load WebIDauth class
require 'classes/WebidAuth.php';

/* ---- CONFIGURATION ---- */

// Load configuration variables
require 'conf/config.php';

//phpinfo(INFO_VARIABLES);
$log = new KLogger ('../logs/log.txt', 1);

// Load Slim framework
$app = new Slim();

// Register ContentType middleware
$app->add(new Middleware_ContentType());
// Configure the REST API 
$app->config(array(
    'debug' => true
));

/* ---- ROUTES ---- */

// Redirect all requests made to /, to /user/
$app->get('/', function () use ($app) {
    $base = BASE_URI;
    $version = VERSION;
    include 'views/welcome/index.php';
 //   $app->redirect('/welcome');
});


// Authenticate users with WebIDs
$app->map('/auth/', function () use ($app) {

    // Prepare the authentication object
    $auth = new Classes_WebidAuth(CERT_PATH, CERT_PASS);
    
    $issuer = trim(urldecode($app->request()->get('authreqissuer')));
    // Authenticate user
    $auth = new Auth_Webid(CERT_PATH, CERT_PASS, $issuer);  
    $isAuth = $auth->processReq();

    if ($isAuth === true) {
        $log->LogInfo('Authenticated '.$auth->getIdentity().
                        ' for '.$issuer.'.');
        // redirect
        $auth->redirect(KEY_PATH);
    } else {
        $log->LogInfo('Could not authenticate '.$auth->getIdentity().
                        ' for '.$issuer.'. Reason: '.$auth->getReason());
        $auth->getReason();
    }
})->via('GET', 'POST');

// GET a user's profile
$app->get('/profile/', function () use ($app, $log) {
    // Prepare the authentication object
    $auth = new Classes_WebidAuth(CERT_PATH, CERT_PASS);

    // prepare the response object
    $response = $app->response();
    // get the parameters from the URI   
    $webid = trim(urldecode($app->request()->get('webid')));
    // 
    $debug = (trim(urldecode($app->request()->get('debug')))) ? true : false;
    // prepare the controller for users
    $ctrl = new Controllers_Profile($app);  
    
    $log_text = '';
    
    // display form, if no webid is requested
    if ( ! $webid) {
        $response->body($ctrl->form(BASE_URI));
    } else {
        // check if user is authenticated
        $isAuthenticated = $auth->processReq();
        
        // Log some useful debug stuff
        $log_text .= 'Received request for WebID: ' . $webid . "\n";
        $log_text .= ' * AcceptType: ' . $app->request()->headers('Accept') . "\n";
        
        if ($isAuthenticated === true) {
            // Log some useful debug stuff
            if (strlen($app->request()->headers('On-Behalf-Of')) > 0)
                $onBehalf = urldecode($app->request()->headers('On-Behalf-Of'));
            else
                $onBehalf = $auth->getIdentity();
            $log_text .= ' * Authenticated user: ' . $auth->getIdentity() . "\n";
            $log_text .= ' * On behalf of: ' . $onBehalf . "\n";
            
            $ctrl->view($auth->getIdentity());
            $body = $ctrl->get_body();
            $status = $ctrl->get_status();
    
            $body = ($debug) ? '<pre>'.$log_text.'</pre>'.$body : $body;
            
            $app->response()->body($body);
            $app->response()->status($status);

        } else {
            $log_text .= " * Authentication Error: " . $auth->getReason() . "\n";
            $app->response()->body($isAuthenticated);
            $app->response()->status(403);
        }
    }
    $headers = $app->response()->headers();
    $log_text .= ' * ContentType: ' . $headers['Content-Type'] . "\n";
    $log->LogInfo($log_text);
});

// DELETE a user's profile from the public cache 
$app->delete('/profile/cache/', function () use ($app) {
    // Prepare the authentication object
    $auth = new Classes_WebidAuth(CERT_PATH, CERT_PASS);
    
    // Authenticate user
    $isAuthenticated = $auth->processReq();
    
    if ($isAuthenticated === true) {
        // Get the user on whose behalf the action is made
        if (strlen($app->request()->headers('On-Behalf-Of')) > 0)
            $onBehalf = urldecode($app->request()->headers('On-Behalf-Of'));
        else
            $onBehalf = $auth->getIdentity();
            
        $ctrl = new Controllers_Profile($app);
        $ctrl->deleteCache($onBehalf);
        $body = $ctrl->get_body();
        $status = $ctrl->get_status();

        // Set the appropriate response
        $app->response()->body($body);
        $app->response()->status($status);
    } else {
        $app->halt(403, 'Not authenticated.');
    }
});

/* ---- TESTS ---- */

// Secretary test
$app->get('/test', function () use ($app, $log) {
    // Prepare the authentication object
    $auth = new Classes_WebidAuth(CERT_PATH, CERT_PASS);
    
    // Authenticate user
    $isAuth = $auth->processReq();
    
    $uri = 'https://auth.my-profile.eu/profile/?webid=https%3A%2F%2Fmy-profile.eu%2Fpeople%2Fdeiu%2Fcard%23me';

    echo 'Creating the graph object..<br/>';
    $g = new EasyRdf_Graph();
    $g->setCertParams(CERT_PATH, CERT_PASS);
    echo ' * Setting the On-Behalf-Of header for ' . $auth->getIdentity() . ' ..<br/>';
    $g->setHeaders('On-Behalf-Of', $auth->getIdentity());
    echo ' * Fetching graph..<br/>';
    $g->load($uri);
    echo ' * Dumping graph..<br/>';
    echo $g->dump();
    
});


/**
 * Run the Slim application
 */
$app->run();

