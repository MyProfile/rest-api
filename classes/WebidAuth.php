<?
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
 * WebID (delegated) Authentication class
 *
 */
class Classes_WebidAuth {
    /** Holds our diagnostic errors */
    public  $err        = array(); 
    /** Array of WebID URIs */
    private $webid      = array();
    /** Timestamp in W3C XML format */
    private $ts         = null;
    /** php array with certificate contents */
    private $cert       = null;
    /** Certificate in pem format */
    private $cert_pem   = null;
    /** Value of the modulus inside the public key */
    private $modulus    = null;
    /** Value of the exponent inside the public key */
    private $exponent   = null;
    /** Webid URI for which we have a match */
    private $claim_id   = null;
    /** textual representation of the client */
    private $cert_txt   = null;
    /** Path for storing temporary files needed by Openssl */
    private $tmp        = null;
    /** Error code for the application (if any) */
    private $code       = null;
    /** TLS client private key verification outcome */
    private $verified   = null;
    /** Service where the delegated authentication request originates from */
    private $referrer   = null;
    /** Path to the secretary's certificate */
    private $_cert_path = null;
    /** Password for the secretary's certificate */
    private $_cert_pass = null;
    /** URI of the service used to generate WebIDs */
    private $genURI     = 'https://my-profile.eu/certgen.php';
    
    // Returned error messages
    /** */
    const parseError        = 'Cannot parse WebID';
    const nocert            = 'No certificates installed in the client\'s browser'; 
    const certNoOwnership   = 'No ownership! Could not verify the the private key';
    const certExpired       = 'The certificate has expired';
    const noVerifiedWebId   = 'WebId does not match the certificate';
    const noURI             = 'No WebID URIs found in the provided certificate';
    const noWebId           = 'No identity found for existing WebID';
    const IdPError          = 'Other error(s) in the IdP setup.';

    /**
     * Initialize the variables and perfom sanity checks
     * 
     * @param string $cert_path the path to secretary certificate
     * @param string $cert_pass the password for the secretary certificate
     * @param string $referrer  the URI of the site/service the request comes from
     * @param string $tmp       temporary directory
     *
     * @return boolean
     */
    public function __construct($cert_path, $cert_pass, $referrer=null, $tmp=null)
    {
        // set the certificate path and password for the secretary agent
        $this->_cert_path = $cert_path;
        $this->_cert_pass = $cert_pass;

        // SSL protocol
        $protocol = $_SERVER["SSL_PROTOCOL"];

        // client's browser certificate (in PEM format)
        $certificate = $_SERVER['SSL_CLIENT_CERT'];

        // if the client certificate's public key matches his private key
        $verified = $_SERVER['SSL_CLIENT_VERIFY'];
        $this->verified = $verified;
        
        // check for desired protocol (TLSv1 at least)
        if (strpos($protocol, 'SSL'))
            $this->err[] = "[WebIDauth Error] TLSv1 required - found ".
                            $protocol."\n";

        // set timestamp in XML format
        $this->ts = date("Y-m-dTH:i:sP", time());
    
        // set the referrer
        if ($referrer)
            $this->referrer = $referrer;
        
        // where to store temporary files
        // check first if we can write in the temp dir
        if ($tmp) {
            $this->tmp = $tmp;
            // test if we can write to this dir
            $tmpfile = $this->tmp."/CRT".md5(time().rand());
            $handle = fopen($tmpfile, "w") or die('[Runtime Error] Cannot '.
                            'write file to temporary dir ('.$tmpfile.')!');
      	    fclose($handle);
      	    unlink($tmpfile);
        } else {
            $this->tmp = sys_get_temp_dir();
        }        
        
        // check if we have openssl installed 
        $command = "openssl version";
        $output = shell_exec($command);
        if (preg_match("/command not found/", $output) == 1)
        {
            $this->err[] = '[Runtime Error] OpenSSL may not be installed on '.
                            'your host!';
        }
        
        // process certificate contents 
        if ($certificate)
        {
            // set the certificate in pem format
            $this->cert_pem = $certificate;

            // get the modulus from the browser certificate (ugly hack)
            $tmpCRTname = $this->tmp."/CRT".md5(time().rand());
            // write the certificate into the temporary file
            $handle = fopen($tmpCRTname, "w") or die('[Runtime Error] Cannot '.
                    'open temporary file to store the client\'s certificate!');
            fwrite($handle, $this->cert_pem);
            fclose($handle);

            // get the hexa representation of the modulus
          	$command = "openssl x509 -in ".$tmpCRTname." -modulus -noout";
          	$output = explode('=', shell_exec($command));
            $this->modulus = preg_replace('/\s+/', '', strtolower($output[1]));

            // get the full contents of the certificate
            $command = "openssl x509 -in ".$tmpCRTname." -noout -text";
            $this->cert_txt = shell_exec($command);
            
            // create a php array with the contents of the certificate
            $this->cert = openssl_x509_parse(openssl_x509_read($this->cert_pem));

            if ( ! $this->cert)
            {
                $this->err[] = WebIDauth::nocert;
                $this->code = "nocert";
                $this->data = $this->retErr($this->code);
            }
            
            // get the subjectAltName from certificate
            $alt = explode(', ', $this->cert['extensions']['subjectAltName']);
            // find the webid URI
            foreach ($alt as $val) {
                if (strstr($val, 'URI:'))
                {
                    $webid = explode('URI:', $val);
                    $this->webid[] = $webid[1];
                }
            }
                                
            // delete the temporary certificate file
            unlink($tmpCRTname);
        }
        else
        {
            $this->err[] = "[Client Error] You have to provide a certificate! ".
                            "You can create one <a href=\"".$this->genURI.
                            "\">here</a>.";
        }
		
        // check if everything is good
        if (sizeof($this->err))
        {
            $this->getErr();
            exit;
        }
        else
        {
            return true;
        }
    }
    
    /** 
     * Return the error message based on a given code
     *
     * @param string $code (e.g nocert, noVerifiedWebId, noWebId, IdPError)
     *
     * @return string
     */
    function retErr($code)
    {
        return ($this->referrer) ? $this->referrer."?error=".$code :
                                "WebID authentication error: ".$code;
    }
    
    /**
     * Return all errors generated by the authentication process
     *
     * @return array
     */
    function getErr()
    {
        $ret = "";
        foreach ($this->err as $error) {
            echo "FATAL: ".$error."<br/>";
        }
        return $ret;
    }
    
    /**
     * Returns the successful matching WebID
     *
     * @return string
     */
    function getIdentity()
    {
        return $this->claim_id;
    }
    
    /**
     * Process the authentication request according to the WebID protocol 
     *
     * @return boolean 
     */
    function processReq()
    {
        // verify client certificate using TLS
        if (($this->verified == 'SUCCESS') || ($this->verified == 'GENEROUS')) {
        } else {
            $this->err[] = WebidAuth::certNoOwnership;
            $this->code = "certNoOwnership";
            $this->data = $this->retErr($this->code);
            return false;
        }

        // check if we have URIs
        if ( ! sizeof($this->webid))
        {
            $this->err[] = WebidAuth::noURI;
            $this->code = "noURI";
            $this->data = $this->retErr($this->code);
            return false;
        }
        
        // default = no match
        $match = false;
        $match_id = array();
        // try to find a match for each webid URI in the certificate
        // maximum of 3 URIs per certificate - to prevent DoS
        $i = 0;
        if (sizeof($this->webid) >= 3)
            $max = 3;
        else
            $max = sizeof($this->webid);
            
        while ($i < $max)
        {
            $webid = $this->webid[$i];

            $curr = $i + 1;
            
            // fetch identity for webid profile 
            $graph = new EasyRdf_Graph();
            // add secretary info (we're just being polite)
            $graph->setCertParams($this->_cert_path, $this->_cert_pass);
            $graph->setHeaders('Acting-On-Behalf-Of', $webid);
            $graph->load($webid);
            $person = $graph->resource($webid);
            $type = $person->type();

            // parse all certificates contained in the webid document
            foreach ($graph->allOfType('cert:RSAPublicKey') as $certs) { 
                $hex = $certs->get('cert:modulus');

                // clean up string
                $hex = strtolower(preg_replace('/\s+/', '', $hex));

                // check if the two modulus values match
                if ($hex == $this->modulus)
                {
                    $match = true;
                    // prepare the URI string with the required parameters
                    $this->data = $this->referrer.
                            (strpos($this->referrer,'?')===false?"?":"&").
                            'webid='.urlencode($webid).
                            "&ts=".urlencode($this->ts);
                            
                    $this->claim_id = $webid;
                    // we got a match -> exit loop
                    break;
                }
                else
                {
                    continue;
                }
                // do not check further identities
                if ($this->claim_id)
                    break;
            } // end foreach($cert)
            
            // exit while loop if we have a match
            if ($match)
            {
                break;           
            }
           
            $i++;
        } // end while()

        // we had no match, return false          
        if ( ! $match)
        {
            $this->err[] = WebidAuth::noVerifiedWebId;
            $this->code = "noVerifiedWebId";
            $this->data = $this->retErr($this->code);

            return $this->data;
        }
            
        return true;
    } // end function
    
    /**
     * Get the reason in case the authentication failed
     *
     * @return string
     */
    public function getReason()
    {
        return $this->data;
    }
    
    /**
     * Redirrect user to the referring Service Provider's page, then exit.
     * The header location is signed with the private key of the IdP 
     *
     * @param string $privKey  path to the key used for signing the response
     *
     * @return void
     */
    public function redirect($privKey)
    {
        // load private key
        if (! $privKey) {
            $this->err[] = '[Runtime Error] You have to provide the location '.
                            'of the server SSL certificate\'s private key!';
        } else {
            // check if we can open location and then read key
            $fp = fopen($privKey, "r") or die('[Runtime Error] Cannot open '.
                        'private key file for the server\'s SSL certificate!');
            $privKey = fread($fp, 8192);
            fclose($fp);

            $pkey = openssl_get_privatekey($privKey);

            // $signature contains the signature for the data
            if (openssl_sign($this->data, $signature, $pkey)) {
                // redirect user back to referring page
                openssl_free_key($pkey);
                header("Location: ".$this->data."&sig=".
                    rtrim(strtr(base64_encode($signature), '+/', '-_'), '=').
                    "&referer=https://".$_SERVER["SERVER_NAME"]);
                exit;
            } else {
                openssl_free_key($pkey);
                die(openssl_error_string());           
            }
        }
    }
}

