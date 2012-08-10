1. Introduction
===============

_MyProfile REST API_ is a REST API, operating between the UI (e.g. MyProfile) and 
the SPARQL triple store (e.g. Virtuoso). It also handles external requests from 
other applications. _MyProfile REST API_ contains an access control mechanism, 
operating at the triple level. All requests made to this API require WebID 
authentication.

Further details of the WebID protocol can be obtained at <http://webid.info>

_MyProfile REST API_ supports standards and recommendations:

  *  Read/Write Linked Data
  *  Content negotiation
  *  SPARQL 1.1
  *  WebID


--------------------------------------------------------------------------------

2. Content negotiation
================================================================================

The most important part of _MyProfile REST API_ is content negotiation. This 
means that depending on the "Accept" header, the application will set the correct 
contet type for the response. 

For example, if a request has an HTTP header with the following option:
    Accept: text/n3, application/rdf+xml
    
then _MyProfile REST API_ will set the ContentType header to text/n3 (preffered): 
    Content-Type: text/n3

If for any reason n3 was not an acceptable format, the next one would have been 
chosen (i.e. application/rdf+xml).

Accepted request methods:

  *  Read: GET, HEAD, OPTIONS
  *  Write: PUT, DELETE
  *  Append: POST
  
Response content types:

  *  Turtle: */turtle, */rdf+n3, */n3
  *  RDF/XML: */rdf+xml
  *  NTriples: */rdf+nt, */nt
  *  JSON: application/json (soon)
  *  HTML: */html
  
If no matching format is found, the default format will be turtle.


--------------------------------------------------------------------------------

3. Quick example
================================================================================

The request:
```
HTTP/1.1 GET https://rest.example.com/profile/?webid=<urlencoded WebID uri>
```

With these extra header options:
```
Accept: text/rdf+n3
On-Behalf-Of: https://my-profile.eu/people/deiu/card#me
```

Will return*:
```
<https://my-profile.eu/people/deiu/card#me>
    a foaf:Person ;
    foaf:name "Andrei Vlad Sambra" ;
    foaf:givenName "Andrei Vlad" ;
        . . .
```    
*The returned profile matches the access control policies specific to the user 
on whose behalf the request is being made, and optionally the agent doing the request.

--------------------------------------------------------------------------------

4. Dependencies
================================================================================

_MyProfile REST API_ has only been installed and tested under Linux. 

  *  Slim Framework (PHP, REST) - <https://github.com/codeguy/Slim>
  *  ARC2 (PHP) - <https://github.com/semsol/arc2>
  *  EasyRdf (PHP) - bundled fork: <https://github.com/MyProfile/easyrdf>
  *  cURL (both php-curl and the curl application)
  *  OpenSSL (for authentication)


--------------------------------------------------------------------------------

5. Documentation
================================================================================

Documentation for the API classes can be found in public/docs/.


--------------------------------------------------------------------------------

6. Issues
================================================================================

A very common issue with this API is the fact that REST routes are not processed 
as they normally should. The main cause is either that ModRewrite has not been 
enabled in the web server, or that the "AllowOverride" directive in the web 
server configuration does not take into account the .htaccess file. A simple fix 
for the later case is to set AllowOverride to All:

    AllowOverride All 

