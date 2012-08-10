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
 * Profile class 
 *
 * @todo use boolean return methods for current functions which return html
 */ 
class Classes_Profile
{
    /** SPRAQL endpoint address */
    private $endpoint;
    /** Time to live value for cached data */
    private $ttl;
    /** The user's WebID URI */
    private $webid;
    /** The server's FQDN (i.e. http://example.com/ */
    private $base_uri;
    /** Cache dir (not used for now) */
    private $cache_dir;
    /** The primary topic of a profile document */
    private $primarytopic;
    /** EasyRdf graph object containing the profile RDF data */
    private $graph;
    /** EasyRdf resource object containing the user's FOAF profile */
    private $profile;
    /** The user's full name */
    private $fullName;
    /** The user's profile picture (avatar) */
    private $picture;
    /** The user's RSS feed hash (feed id) */
    private $feed_hash;
    /** The user's hash (local id) */
    private $user_hash;
    /** The user's email address */
    private $email;
    /** The number of profile triples returned by SPARQL */
    private $count;
    /** On behalf of whom is the action made (i.e. a user's WebID) */
    private $on_behalf; 
    /** Path to secretary certificate */
    private $cert_path;
    /** Secretary certificate password */
    private $cert_pass;

    /**
     * Build the selectors for adding more form content (default ttl is 24h)
     *
     * @param string    $webid      the WebID of the user
     * @param string    $base_uri   the server's FQDN 
     * @param string    $endpoint   the SPARQL endpoint
     * @param integer   $ttl        a time to live value for cache expiration
     * @return void
     */
    function __construct($webid, $base_uri, $endpoint, $ttl = 86400) {
        $this->webid = $webid;
        
        if (isset($base_uri))
            $this->base_uri = $base_uri;
        // set cache dir
        $this->cache_dir = 'cache/';
        
        // set the SPARQL endpoint address
        $this->endpoint = $endpoint; 
        
        // set cache time to live (default is 24h)
        $this->ttl = $ttl;
    }
    
    /**
     * Cache user data into a SPARQL triplestore
     *
     * @return errorString|true
     */
    function sparql_cache() {
        $db = sparql_connect($this->endpoint);
        // first delete previous data for the graph
        $sql = "CLEAR GRAPH <".$this->webid.">";
        $res = $db->query($sql);
            
        // Load URI into the triple store
        $sql = "LOAD <".$this->webid.">";
        $res = $db->query($sql);

        if( !$res ) { return sparql_errno().": ".sparql_error(). "\n"; exit; }

        if ($res->num_rows($res) > 0) { 
            // check if we hanve an empty graph
            $sql = 'SELECT * FROM <'.$this->webid.'> WHERE {?s ?p ?o} LIMIT 1';
            $res = $db->query($sql);
            if( !$res ) { return sparql_errno().": ".sparql_error(). "\n"; exit; }

            // Add timestamp only if the graph is not empty
            if ($res->num_rows($res) > 0) {
                // Add the timestamp for the date at which it was inserted
                $time = time();
                $date = date("Y", $time).'-'.date("m", $time).'-'.date("d", $time).
                        'T'.date("H", $time).':'.date("i", $time).':'.date("s", $time);
                $sql = 'INSERT DATA INTO GRAPH IDENTIFIED BY <'.$this->webid.'> ' .
                        '{<'.$this->webid.'> dc:date "'.$date.'"^^xsd:dateTime.}';
                $res = $db->query($sql);
                
                return ( ! $res)? sparql_errno().': '.sparql_error(). "\n" : true;
            } else {
                // We have an empty graph, might as well delete it
                $sql = "CLEAR GRAPH <".$this->webid.">";
                $res = $db->query($sql); 
                
                return ( ! $res)? sparql_errno().': '.sparql_error(). "\n" : true;
            }
        } else {
            return false;
        }
    }   
    
    /**
     * Load chached profile data using SPARQL (or cache if needed) 
     * - fallback to EasyRdf if there is a problem with the SPARQL endpoint
     *
     * @return true
     */
    function sparql_graph() {
        // cache data is refreshed if it's older than the given TTL
        $time = time() - $this->ttl;
        $date = date("Y", $time).'-'.date("m", $time).'-'.date("d", $time).'T'.
                date("H", $time).':'.date("i", $time).':'.date("s", $time);
        
        $db = sparql_connect($this->endpoint);
        $query = 'SELECT * FROM <'.$this->webid.'> WHERE { '.
                '?person dc:date ?date.'.
                'FILTER (?date > "'.$date.'"^^xsd:dateTime)}';
        $result = $db->query($query);

        // fallback to EasyRdf if there's a problem with the SPARQL endpoint
        if (!$result) {
            $this->direct_graph();
        } else {
            // cache data into the triple store if it's the first time we see it
            $count = $result->num_rows($result);

            // force refresh of data if cache expired
            if ($count == 0)
                $this->sparql_cache();

            $query = "PREFIX xsd: <http://www.w3.org/2001/XMLSchema#> ";
            $query .= "PREFIX cert: <http://www.w3.org/ns/auth/cert#> ";
            $query .= "CONSTRUCT { ?s ?p ?o } WHERE { GRAPH <".$this->webid."> { ?s ?p ?o } }";
     
            $sparql = new EasyRdf_Sparql_Client($this->endpoint); 
            $graph = $sparql->query($query);            

            $this->graph = $graph;
        }
        return true;
    }
    
    /** 
     * Load profile data using EasyRdf
     *
     * @return true
     */
    function direct_graph() {
        // Load the RDF graph data
        $graph = new EasyRdf_Graph();
        if ((isset($this->cert_path)) && (isset($this->cert_pass)))
            $graph->setCertParams($this->cert_path, $this->cert_pass);
        // Set on behalf, if we need to
        if ($this->on_behalf)
            $graph->setHeaders('On-Behalf-Of', $this->on_behalf);
        $graph->load($this->webid);

        $this->graph = $graph;

        return true;
    }
    
    /** 
     * Load the user's data (priority is for SPARQL, otherwise do it with EasyRdf)
     *
     * @param boolean $refresh  force a cache refresh or not
     * @return true
     */
    function load($refresh = false) {
        // check if we have a SPARQL endpoint configured
        if (strlen($this->endpoint) > 0) {
            // force a cache refresh
            if ($refresh === true)
                $this->sparql_cache();
            // use the SPARQL endpoint 
            $this->sparql_graph();
        } else {
            // use the direct method (EasyRdf)
            $this->direct_graph();
        }
        
        // try to get primary topic, else go with default uri (some people don't use #)
        $pt = $this->graph->resource('foaf:PersonalProfileDocument');
        $this->primarytopic = $pt->get('foaf:primaryTopic');
        if ($this->primarytopic != null) 
            $profile = $this->graph->resource($this->primarytopic);
        else
            $profile = $this->graph->resource($this->webid);
        
        $this->profile = $profile;
        
        // get user's name and picture info for display purposes    
        $this->fullName = $profile->get('foaf:name');

        if ($this->fullName == null) {
            $first = $profile->get('foaf:givenName');
            $last = $profile->get('foaf:familyName');

            $name = ''; 
            if ($first != null)
                $name .= $first.' ';
            if ($last != null)
                $name .= $last;
            if (strlen($name) > 0)
                $this->fullName = $name;
            else
                $this->fullName = 'Anonymous';
        }

        // get the user's picture
        if ($profile->get('foaf:img') != null)
            $this->picture = $profile->get('foaf:img'); 
        else if ($profile->get('foaf:depiction') != null)
            $this->picture = $profile->get('foaf:depiction');
        else
            $this->picture = 'img/nouser.png'; // default image
        
        // get the user's first email address
        if ($profile->get('foaf:mbox') != null)
            $this->email = $profile->get('foaf:mbox');
            
        return true;
    }
    
    /**
     * Get the number of triples returned by SPARQL
     *
     * @return integer
     */
    function get_count() {
        return $this->count;
    }
    
    /**
     * Get the user's raw graph object
     *
     * @return EasyRdf_graph
     */
    function get_graph() {
        return $this->graph;
    }
    
    /** 
     * Get the user's raw profile object
     *
     * @return EasyRdf_resource
     */
    function get_profile() {
        return $this->profile;
    }

    /** 
     * Get the primary topic of a profile object
     *
     * @return EasyRdf_resource
     */
    function get_primarytopic() {
        return $this->primarytopic;
    }
    
    /**
     * Get the user's RSS feed hash (feed id)
     *
     * @return string
     */
    function get_feed() {
        return $this->feed_hash;
    }
    
    /**
     * Get the user's hash (local id)
     *
     * @return string
     */
    function get_hash() {
        return $this->user_hash;
    }
    
    /**
     * Get the user's full name
     *
     * @return string
     */
    function get_name() {
        return $this->fullName;
    }
    
    /** 
     * Get the user's nickname
     *
     * @return string
     */
    function get_nick() {
        return $this->profile->get("foaf:nick");
    }
    
    /**
     * Get the user's profile picture
     *
     * @return string (URI)
     */
    function get_picture() {
        return $this->picture;
    }
    
    /**
     * Get the user's email address
     *
     * @return string 
     */ 
    function get_email() {
        return $this->email;
    } 
    
    /** 
     * Get the user's pingback endpoint
     *
     * @return string (URI)
     */
    function get_pingback() {
        return $this->profile->get("http://purl.org/net/pingback/to");
    }
     
    /** 
     * Get the whole graph, serialized in the specified format
     *
     * @param string $format    serialization format (e.g. rdfxml, n3, etc.)
     * @return string
     */
    function serialise($format) {
        return $this->graph->serialise($format);
    }

    /**
     * Set the certificate parameters for secretary
     *
     * @param string $path  path to the certificate file
     * @param string $pass  password for the certificate
     *
     * @return true
     */
    function setCertParams($path, $pass)
    {
        $this->cert_path = $path;
        $this->cert_pass = $pass;
        return true;
    }
  
    /** 
     * Set the WebID URI of the person on behalf of whom the secretary is working on
     *
     * @param string $webid the WebID of a user on behalf of whom the action is done
     *
     * @return true
     */
    function setOnBehalf($webid)
    {
        $this->on_behalf = urldecode($webid);
        return true;
    }
    
    /** 
     * Check if the given webid is in the user's list of foaf:knows
     *
     * @param string $webid the WebID of the user we're checking against
     *
     * @return boolean
     */
    function is_friend($webid) {
        if (!isset($this->profile)) {
            $this->load();
        }
        $profile = $this->profile;        
        $friends = $profile->all('foaf:knows');
        if (in_array($webid, $friends))
            return true;
        else
            return false;
    }
    
    /**
     * Check if the webid is a local and return the corresponding account name
     *
     * @param string $webid the WebID of the user
     *
     * @return boolean
     */ 
    function is_local($webid) {
        $webid = (isset($webid)) ? $webid : $this->webid;
        if (strstr($webid, $_SERVER['SERVER_NAME']))
            return true;
        else
            return false;
    }
    
    /**
     * Get local path for user (only if it's a local user)
     *
     * @param string $webid the WebID of the user
     *
     * @return string|false
     */
    function get_local_path($webid) {
        // verify if it's a local user or not
        if ($this->is_local($webid)) {
            $location = strstr($webid, $_SERVER['SERVER_NAME']);
            $path = explode('/', $location);
            $path = $path[1]."/".$path[2];
            return $path;
        } else {
            return false;
        }
    }
  
    /**
     * Add a foaf:knows relation to the user's profile
     *
     * @param string $uri       the WebID of the user we're adding
     * @param string $format    serialization format (e.g. rdfxml, n3, etc.)
     * 
     * @return html
     */
    function add_friend($uri, $format='rdfxml') {
        $uri = urldecode($uri);
        $path = $this->get_local_path($this->webid);
        
        // Create the new graph object in which we store data
        $graph = new EasyRdf_Graph($this->webid);
        $graph->load();
        $me = $graph->resource($this->webid);
        $graph->addResource($me, 'foaf:knows', $uri);
        
        // reserialize graph
        $data = $graph->serialise($format);
        if (!is_scalar($data))
            $data = var_export($data, true);
        else
            $data = print_r($data, true);
        // write profile to file
        $pf = fopen($path.'/foaf.rdf', 'w') or error('Cannot open profile RDF file!');
        fwrite($pf, $data);
        fclose($pf);    
        
        $pf = fopen($path.'/foaf.txt', 'w') or error('Cannot open profile PHP file!');
        fwrite($pf, $data);
        fclose($pf);
        
        // cache the user's data if possible
        $friend = new User_Profile($uri, $this->base_uri, SPARQL_ENDPOINT);
        $friend->load();

        // everything is fine
        return success("You have just added ".$friend->get_name()." to your list of friends.");
    }
    
    /**
     * Remove a foaf:knows relation from the user's profile
     *
     * @param string $uri       the WebID of the user we're removing
     * @param string $format    serialization format (e.g. rdfxml, n3, etc.)
     *
     * @return html
     */
    function del_friend($uri, $format='rdfxml') {
        $uri = urldecode($uri);
        $path = $this->get_local_path($this->webid);

        // Create the new graph object in which we store data
        $graph = new EasyRdf_Graph($this->webid);
        $graph->load();
        $person = $graph->resource($this->webid);
        $graph->deleteResource($person, 'foaf:knows', $uri);
        
        // write profile to file
        $data = $graph->serialise($format);
        if (!is_scalar($data))
            $data = var_export($data, true);
        else
            $data = print_r($data, true);

        $pf = fopen($path.'/foaf.rdf', 'w') or die('Cannot open profile RDF file!');
        fwrite($pf, $data);
        fclose($pf);    

        // get the user's name
        $friend = new User_Profile($uri, $this->base_uri, SPARQL_ENDPOINT);
        $friend->load();
        
        // everything is fine
        return success("You have just removed ".$friend->get_name().
                        " from your list of friends.");
    }
    
    /**
     * Delete a user account by removing the following tables from MySQL:
     * pingback, pingback_messages, votes
     *
     * @return boolean
     */
    function delete_account() {
        $webid = mysql_real_escape_string($this->webid);
        
        // delete the WebID from subscriptions
        $result = mysql_query("DELETE FROM pingback WHERE webid='".$webid."'");
        if (!$result) {
            return false;       
        } else {
            mysql_free_result($result);
        }
        
        // delete all messages sent by the user
        $result = mysql_query("DELETE FROM pingback_messages WHERE from_uri='".
                                $webid."'");
        if (!$result) {
            return false;       
        } else {
            mysql_free_result($result);
        }
        return true;
        
        // delete all votes cast by the user
        $result = mysql_query("DELETE FROM votes WHERE webid='".$webid."'");
        if (!$result) {
            return false;       
        } else {
            mysql_free_result($result);
        }
        return true;
    }
    
    /**
     * Subscribe the user to local services (pingbacks, messages, notifications)
     *
     * @return html
     */
    function subscribe() {
        $webid      = $this->webid;
        $feed_hash  = substr(md5(uniqid(microtime(true),true)),0,8);
        $user_hash  = substr(md5($webid), 0, 8);

        $this->feed_hash = $feed_hash;
        $this->user_hash = $user_hash;        
                
        // write webid uri to database
        $query = "INSERT INTO pingback SET " .
                "webid='".mysql_real_escape_string($webid)."', " .
                "feed_hash='".mysql_real_escape_string($feed_hash)."', ".
                "user_hash='".mysql_real_escape_string($user_hash)."'";
        $result = mysql_query($query);

        if (!$result) {
            return error('Unable to connect to the database!');
        } else {
            if ($result !== true) {
                mysql_free_result($result);
            }
            return success('You have successfully subscribed to local services.');
        }
    }
    
    /**
     * Unsubscribe the user from local services (removes entries from MySQL)
     *
     * @return html
     */
    function unsubscribe() {
        $query = "DELETE FROM pingback WHERE webid='".
                    mysql_real_escape_string($this->webid)."'";
        $result = mysql_query($query);
        if (!$result) {
            return error('Unable to connect to the database!');
        } else { 
            // delete any pingbacks addressed to me
            $query = "DELETE FROM pingback_messages WHERE to_uri='".
                        mysql_real_escape_string($this->webid)."'";
            $result = mysql_query($query);
            if (!$result) {
                return error('Unable to connect to the database!');
            } else {
                mysql_free_result($result);
                return success('Your WebID has been successfully unregistered.');
            }
        }
    }
    
    /**
     * Subscribe the user to receive email notifications
     *
     * @return html
     */
    function subscribe_email() {
        // subscribe only if we are not subscribed already
        if (is_subscribed_email($this->webid) == false) {
            $query = "UPDATE pingback SET email='1' WHERE webid='".
                        mysql_real_escape_string($this->webid)."'";
            $result = mysql_query($query);
            if (!$result) {
                return error('Unable to connect to the database!');
            } else {
                return success('You have successfully subscribed to receiving email notifications.');
            }
        }
    }
    
    /**
     * Unsubscribe the user from receiving email notifications
     *
     * @return html
     */
    function unsubscribe_email() {
        // unsubscribe only if we are already subscribed
        if (is_subscribed_email($this->webid) == true) {
            $query = "UPDATE pingback SET email='0' WHERE webid='".
                        mysql_real_escape_string($this->webid)."'";
            $result = mysql_query($query);
            if (!$result) {
                return error('Unable to connect to the database!');
            } else {
                mysql_free_result($result);
                return success('You have unsubscribed from receiving email notifications.');
            }
        }
    }
}
 
