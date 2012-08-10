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
 * The ContentType middleware class sets the HTTP Content-Type header based on
 * the received HTTP Accept header values.
 * 
 * It also includes the current API name and version into the X-Powered-By header
 */
class Middleware_ContentType extends Slim_Middleware {
    /**
     * Sets the HTTP Content-Type header based on the Accept values
     *
     * @return void
     */
    public function call() {
        $app = $this->app;
        $req = $app->request();
        $res = $app->response();
        // Set the correct Content-Type header for the response, based on the 
        // initial Accept value in the request

        // Default format is n3
        $format = 'text/rdf+n3';

        // Set format based on the request
        $headerType = $req->headers('Accept');
        if (strstr($headerType, 'application/rdf+xml')) {
            $format = 'application/rdf+xml'; 
        } else if (strstr($headerType, 'n3')) {
            $format = 'text/rdf+n3';
        } else if (strstr($headerType, 'turtle')) {
            $format = 'application/turtle';
        } else if ((strstr($headerType, 'nt')) ||
                (strstr($headerType, 'ntriples'))) {
            $format = 'application/rdf+nt';  
        } else if (strstr($headerType, 'html')) {
            $format = 'text/html';
        } 
        
        $res->header('X-Powered-By', 'MyProfile REST API v'.VERSION);
        $res->header('Content-Type', $format);
        $this->next->call();
    }
}
