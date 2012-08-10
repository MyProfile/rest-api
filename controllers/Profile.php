<?php
/**
 *  Copyright (C) 2012 MyProfile Project
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
 *
 *  @author Andrei Sambra 
 */
 
/**
 * The Profile controller class handles actions related to user profiles
 */
class Controllers_Profile
{
    /** Slim app object */
    private $app;
    /** HTTP reponse body */
    private $body;
    /** HTTP reponse code */
    private $status;
    /** requested WebID */
    private $webid;
    /** On behalf of whom is the action made (i.e. a user's WebID) */
    private $on_behalf; 
    /** Serialization format (e.g. rdfxml, n3, etc.) */
    private $_format;
        
    // hack until proper AC is used
    //private $allowed_users = array("https://my-profile.eu/people/deiu/card#me", 
    //                "https://my-profile.eu/people/myprofile/card#me");

    /**
     * Constructor for the class
     * (sets the HTTP response body contents and status code)
     *
     * @param Slim $app   the Slim REST API object (used for the HTTP response)
     *
     * @return void 
     */
    function __construct ($app)
    {
        if ( ! $app) {
            echo 'Controller_User -> Constructor error: missing app object.';
            exit(1);
        } else {
            // setting the app object
            $this->app = $app;
            // get the WebID 
            $this->webid = trim(urldecode($app->request()->get('webid')));
            // get the Acting-On-Behalf-Of header value
            $this->on_behalf = urldecode($app->request()->headers('Acting-On-Behalf-Of'));
            
            // Use RDF+XML by default 
            $format = 'rdfxml';
            // Set format based on the request
            $headerType = $app->request()->headers('Accept');
            if (strstr($headerType, 'application/rdf+xml')) {
                $format = 'rdfxml'; 
            } else if (strstr($headerType, 'n3')) {
                $format = 'n3';
            } else if (strstr($headerType, 'text/turtle')) {
                $format = 'turtle';              
            } else if (strstr($headerType, 'ntriples')) {
                $format = 'ntriples';  
            } else if (strstr($headerType, 'text/html')) {
                $format = 'html';
            } 
            $this->_format = $format;
        }
    }

    /**
     * Display a user's profile based on the requested format
     * (sets the HTTP response body contents and status code)
     *
     * @param string $on_behalf the WebID on behalf of whom the request is made
     *
     * @return void
     */
    function view($on_behalf=null)
    {
        $profile = new Classes_Profile($this->webid, BASE_URI, SPARQL_ENDPOINT);
        if ($on_behalf)
            $profile->setOnBehalf($on_behalf);
        if ((CERT_PATH) && (CERT_PASS))
            $profile->setCertParams(CERT_PATH, CERT_PASS);
        $profile->load();

        if ( ! $profile->get_graph()->isEmpty())  {   
            // return RDF or text
            if ($this->_format !== 'html') {
                $this->body = $profile->serialise($this->_format);
            } else {
                // Dump the html view of a profile graph
                $html_view = '<br/>'.$profile->get_graph()->dump(true);

                $this->body = $html_view;
            }
            $this->status = 200;
        } else {   
            $this->body = 'Resource '.$this->webid.' not found.';
            $this->status = 404;
        }
    }

    /**
     * Delete a cached graph from the SPARQL store
     *
     * @param string $requestOwner the user requesting the delete action
     * @return boolean
     */
    function deleteCache($requestOwner)
    {
        $allowed = false; // for now
        
        // Delete only if the requested graph is the user's own graph
        if ($this->webid === $requestOwner)
            $allowed = true;

        if ($allowed === true) {
            // delete
            $db = sparql_connect(SPARQL_ENDPOINT);
            $sql = "CLEAR GRAPH <" . $this->webid . ">";
            $res = $db->query($sql);

            // Set up the response
            $this->body = 'Successfully deleted profile '.$this->webid.".\n";
            $this->status = 200;
            
            return true;
        } else {
            // not allowed
            // Set up the response
            $this->body = 'You are not allowed to delete profile '.$this->webid.".\n";
            $this->status = 403;

            return false;
        }
    }

    /**
     * Display form for manually requesting to view a user's profile
     *
     * @param string $base_uri  the server's FQDN (i.e. http://example.com)
     *
     * @return string
     */
    function form($base_uri = '')
    {
        $form = ''; 
        $form .= "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML+RDFa 1.0//EN\" \"http://www.w3.org/MarkUp/DTD/xhtml-rdfa-1.dtd\">\n";
        $form .= "<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" xmlns:foaf=\"http://xmlns.com/foaf/0.1/\">\n";
        $form .= "<head>\n";
        $form .= "<title>View a user's profile</title>\n";
        $form .= "<meta http-equiv=\"Content-Type\" content=\"text/html;charset=utf-8\" />\n";
        $form .= "</head>\n";
        $form .= "<body typeof=\"\">\n";
        $form .= "<form method=\"GET\" action=\"" . $base_uri . "/profile/\">\n";
        $form .= "WebID for requested profile: <input type=\"text\" property=\"foaf:webid\" name=\"webid\" />\n";
        $form .= "<input type=\"submit\" name=\"submit\" value=\"Get\" />\n";
        $form .= "</form>\n";
        $form .= "</body>\n";
        $form .= "</html>\n";
    
        return $form;
    }

    /**
     * Get the contents of the body for HTTP reponse 
     *
     * @return string
     */
    function get_body()
    {
        return $this->body;
    }

    /**
     * Get the status code for the HTTP response (e.g. 200, 404, etc.)
     *
     * @return integer
     */
    function get_status()
    {
        return $this->status;
    }
}

