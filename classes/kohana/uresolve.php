<?php

Class Kohana_Uresolve
{
    protected $scheme = NULL;
    protected $host = NULL;
    protected $port = NULL;
    protected $user = NULL;
    protected $password = NULL;
    protected $path = NULL;
    protected $query = array ();
    protected $fragment = NULL;

    protected $hostname = NULL;
    protected $subdomain = NULL;

    public static function factory ($url)
    {
        return new Kohana_Uresolve ($url);
    }

    public function __construct ($url = NULL)
    {
        if ( ! is_null ($url))
        {
            $this->parse ($url);
        }
    }

    public function parse ($url)
    {
        if (is_string ($url))
        {
		if ( ! preg_match ('/^[^:]+:\/\//i', $url))
		{
		    $url = "scheme://" . $url;
		}

            $url = self::parseString ($url);
        }
        else
        {
            throw new Kohana_Exception_Uresolve ('unsupported type for argument.');
        }

	$this->fillFromArray ($url);

        $this->hostname ();
	$this->subdomain ();

	return $this;
    }

    protected function fillFromArray ($url)
    {
        foreach (array ('scheme', 'host', 'port', 'user', 'password', 'path', 'query', 'fragment') as $var)
        {
            if (isset ($url[$var]))
            {
                $this->$var = $url[$var];
            }
        }
    }

    static protected function parseString ($url)
    {
	$res = array ();

	// Character sets from RFC3986.
	$xunressub     = 'a-zA-Z\d\-._~\!$&\'()*+,;=';
	$xpchar        = $xunressub . ':@%';

	// Scheme from RFC3986.
	$xscheme        = '([a-zA-Z][a-zA-Z\d+-.]*)';

	// User info (user + password) from RFC3986.
	$xuserinfo     = '((['  . $xunressub . '%]*)' .
	                 '(:([' . $xunressub . ':%]*))?)';

	// IPv4 from RFC3986 (without digit constraints).
	$xipv4         = '(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})';

	// IPv6 from RFC2732 (without digit and grouping constraints).
	$xipv6         = '(\[([a-fA-F\d.:]+)\])';

	// Host name from RFC1035.  Technically, must start with a letter.
	// Relax that restriction to better parse URL structure, then
	// leave host name validation to application.
	$xhost_name    = '([a-zA-Z\d-.%]+)';

	// Authority from RFC3986.  Skip IP future.
	$xhost         = '(' . $xhost_name . '|' . $xipv4 . '|' . $xipv6 . ')';
	$xport         = '(\d*)';
	$xauthority    = '((' . $xuserinfo . '@)?' . $xhost .
		         '?(:' . $xport . ')?)';

	// Path from RFC3986.  Blend absolute & relative for efficiency.
	$xslash_seg    = '(/[' . $xpchar . ']*)';
	$xpath_authabs = '((//' . $xauthority . ')((/[' . $xpchar . ']*)*))';
	$xpath_rel     = '([' . $xpchar . ']+' . $xslash_seg . '*)';
	$xpath_abs     = '(/(' . $xpath_rel . ')?)';
	$xapath        = '(' . $xpath_authabs . '|' . $xpath_abs .
			 '|' . $xpath_rel . ')';

	// Query and fragment from RFC3986.
	$xqueryfrag    = '([' . $xpchar . '/?' . ']*)';

	// URL.
	$xurl          = '^(' . $xscheme . ':)?' .  $xapath . '?' .
	                 '(\?' . $xqueryfrag . ')?(#' . $xqueryfrag . ')?$';


	// Split the URL into components.
	if ( !preg_match( '!' . $xurl . '!', $url, $m ) )
        {
            throw new Kohana_Exception_Uresolve ('unable to split');
        }

	if ( !empty($m[2]) )		$res['scheme']  = strtolower($m[2]);

	if ( !empty($m[7]) ) {
		if ( isset( $m[9] ) )	$res['user']    = $m[9];
		else			$res['user']    = '';
	}
	if ( !empty($m[10]) )		$res['password']    = $m[11];

	if ( !empty($m[13]) )		$res['host'] = $m[13];
	else if ( !empty($m[14]) )	$res['host']    = $m[14];
	else if ( !empty($m[16]) )	$res['host']    = $m[16];
	else if ( !empty( $m[5] ) )	$res['host']    = '';
	if ( !empty($m[17]) )		$res['port']    = $m[18];

	if ( !empty($m[19]) )		$res['path']    = $m[19];
	else if ( !empty($m[21]) )	$res['path']    = $m[21];
	else if ( !empty($m[25]) )	$res['path']    = $m[25];

	if ( !empty($m[27]) )
        {
            $_qs   = $m[28];
            foreach (explode ("&", $_qs) as $q)
            {
                $buf = explode ("=", $q);
                $res['query'][$buf[0]] = $buf[1];
            }
        }

	if ( !empty($m[29]) )		$res['fragment'] = $m[30];

	return $res;
    }

    public function subdomain ($sub = NULL)
    {
	// @xxx: this method considers anything but hostname () as subdomain

	if ( ! is_null ($sub))
	{
	    if ($sub == '')
	    {
		// no subdomain
	    	$this->host = $this->hostname ();
	        $this->subdomain = '';
	    }
	    else
	    {
		// set subdomain
		$this->host = implode ('.', array ($sub, $this->hostname ()));
	        $this->subdomain = NULL;
	    }

	    return $this;
	}

	if ( ! is_null ($this->subdomain))
	{
	    return $this->subdomain;
	}

	$parts = explode ('.', $this->host);
	$count = count ($parts);
	if ($count > 2)
	{
	    $this->subdomain = implode ('.', array_slice ($parts, 0, $count - 2));
	}
	else
	{
	    $this->subdomain = '';
	}

        return $this->subdomain;
    }

    public function hostname ()
    {
	if ( ! is_null ($this->hostname))
	{
	    return $this->hostname;
	}

        $parts = explode ('.', $this->host);
	$count = count ($parts);
	if ($count < 2)
	{
	    $this->hostname = '';
	}
        else
	{
	    $this->hostname = implode ('.', array_slice ($parts, $count - 2, $count));
	}

	return $this->hostname;
    }

    public function tld ()
    {
    	$parts = explode ('.', $this->host);
	$count = count ($parts);

	if ($count < 2)
	{
		return NULL;
	}

    	return $parts[$count - 1];
    }

    public function __call ($name, $arguments)
    {
        if ( ! count ($arguments))
        {
	    if ($name == 'query')
	    {
                return http_build_query ($this->query);
	    }

            return $this->$name;
        }

        switch ($name)
        {
            case 'scheme':
            case 'host':
            case 'port':
            case 'user':
            case 'password':
            case 'fragment':
                $this->$name = $arguments[0];
                break;
            case 'path':
                $this->path = self::removeDotSegments ($arguments[0]);
                break;
            case 'query':
                if (is_string ($arguments[0]))
                {
                    $_qs = array ();
                    foreach (explode ("&", $arguments[0]) as $_q)
                    {
                        $buf = explode ("=", $_q);
                        $_qs[$buf[0]] = $buf[1];
                    }
                }
                else if (is_array ($arguments[0]))
                {
                    $_qs = $arguments[0];
                }
		else if (is_null ($arguments[0]))
		{
		    $_qs = array ();
		}
                else
                {
		    throw new Kohana_Exception_Uresolve ('query called with argument of invalid type');
                }

                // arguments[1]: replace (true/false)
                if (isset ($arguments[1]) && true === $arguments[1])
                {
                    $this->query = array_merge ($this->query, $_qs);
                }
                else
                {
                    $this->query = $_qs;
                }
                break;
            default:
                throw new Kohana_Exception_Uresolve ($name . ' is not a supported variable.');
        }


        return $this;
    }

    public function __get ($name)
    {
        return $this->$name;
    }

    public function __set ($name, $value)
    {
        $this->__call ($name, $value);
    }

    public function baseUrl ()
    {
	$url = '';
	if ( !empty( $this->scheme ) )
		$url .= $this->scheme . ':';
	if ( !empty( $this->host ) )
	{
		$url .= '//';
		if ( !empty( $this->user ) )
		{
			$url .= $this->user;
			if ( !empty( $this->password ) )
				$url .= ':' . $this->password;
			$url .= '@';
		}
		if ( preg_match( '!^[\da-f]*:[\da-f.:]+$!ui', $this->host ) )
			$url .= '[' . $this->host . ']';	// IPv6
		else
			$url .= $this->host;			// IPv4 or name
		if ( !empty( $this->port ) )
			$url .= ':' . $this->port;
		if ( !empty( $this->path ) && $this->path[0] != '/' )
			$url .= '/';
	}

        return $url;
    }

    public function __toString ()
    {
	$url = '';
	if ( !empty( $this->scheme ) )
		$url .= $this->scheme . ':';
	if ( !empty( $this->host ) )
	{
		$url .= '//';
		if ( !empty( $this->user ) )
		{
			$url .= $this->user;
			if ( !empty( $this->password ) )
				$url .= ':' . $this->password;
			$url .= '@';
		}
		if ( preg_match( '!^[\da-f]*:[\da-f.:]+$!ui', $this->host ) )
			$url .= '[' . $this->host . ']';	// IPv6
		else
			$url .= $this->host;			// IPv4 or name
		if ( !empty( $this->port ) )
			$url .= ':' . $this->port;
		if ( !empty( $this->path ) && $this->path[0] != '/' )
			$url .= '/';
	}
	if ( !empty( $this->path ) )
		$url .= $this->path;
	else
		$url .= '/';
	if ( count( $this->query ) )
        {
		$url .= '?' . http_build_query ($this->query);
        }
	if ( ! is_null ( $this->fragment ) )
        {
		$url .= '#' . $this->fragment;
        }
	return $url;
    }

    public function resolve ($url)
    {
    	// TODO: make this useful
/*        if ($url instanceof get_class ($this))
	{
		foreach (array ('scheme', 'host', 'port', 'user', 'password', 'path', 'query', 'fragment', ) as $name)
		{
			if ( ! empty ($url->$name))
			{
				$this->$name = $url->$name;
			}
		}
	} */

	$target = self::parseString ($url);
	if ( ! empty ($target['scheme']))
	{
		/* target is absolute url */
		$this->fillFromArray ($target);
		return;
	}

	if ($target['path'][0] != '/')
	{
		$base = mb_strrchr( $this->path, '/', TRUE, 'UTF-8' );
		if ( $base === FALSE ) $base = '';
		$target['path'] = $base . '/' . $target['path'];
	}

	$this->path = $target['path'];

	return $this;
    }

    static protected function removeDotSegments ($path)
    {
	$inSegs  = preg_split( '!/!u', $path );
	$outSegs = array( );
	foreach ( $inSegs as $seg )
	{
		if ( $seg == '' || $seg == '.')
			continue;
		if ( $seg == '..' )
			array_pop( $outSegs );
		else
			array_push( $outSegs, $seg );
	}
	$outPath = implode( '/', $outSegs );
	if ( $path[0] == '/' )
		$outPath = '/' . $outPath;
	// compare last multi-byte character against '/'
	if ( $outPath != '/' &&
		(mb_strlen($path)-1) == mb_strrpos( $path, '/', 'UTF-8' ) )
		$outPath .= '/';
	return $outPath;
    }
}
