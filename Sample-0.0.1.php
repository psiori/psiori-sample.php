<?php

/**
 * Sample.php - Sends tracking events to 5d's analytics service. v0.0.1
 * http://www.5dlab.com
 *
 * Copyright (c) 2014-2015, Sascha Lange
 * Licensed under the MIT License.
 *
 */


/**
 * Singleton object to do server-side tracking calls. 
 */
class Sample
{
    static public $endPoint = "http://events.psiori.com/sample/v01/event";
    
    static protected $instance = null;

    /**
     * API Version
     *
     * @ignore
     * @var int
     */
    const API_VERSION     = 1;
    
    const SDK             = "Sample.php";
    const SDK_VERSION     = "0.0.1";

    const DEFAULT_CHARSET = 'utf-8';
    
    
    public $appToken      = null;
    public $serverSide    = true;


    protected function __construct() 
    {
        $this->httpReferer  = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : false;
        $this->charset      = self::DEFAULT_CHARSET;
        $this->pageUrl      = self::getCurrentUrl();
        $this->remoteIp     = !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : false;
        $this->userAgent    = !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : false;
    }
    
    public static function &instance($appToken=null) 
    {
        if (self::$instance == null) {
            self::$instance = new Sample();
        }
        if (!empty($appToken)) {
            self::$instance->appToken = $appToken;
        }
        return self::$instance;
    }

    /**
     * Set the time in ms that was needed to generate the page on the server.
     */
    public function setProcessingTime($timeInMs)
    {
        $this->processingTime = $timeInMs;
    }

    /**
     * Overrides auto-detected Remote IP address
     *
     */
    public function setRemoteIp($ip)
    {
        $this->remoteIp = $ip;
    }

    /**
    */
    public function setUserId($userId)
    {
        $this->userId = $userId;
    }
    
    
    // /////////////////////////////////////////////////////////////////////////
    //
    //   GENERIC TRACKING EVENT
    //
    // /////////////////////////////////////////////////////////////////////////

    /** generic tracking event that could be used to send pre-defined tracking
      * events as well as user-defined, custom events. Just pass the eventName,
      * the eventCategory (used for grouping in reports of the backend) and
      * an optional hash of parameters. 
      *
      * Please note that only known parameters will be passed to the server.
      * If you want to come up with your own parameters in you custom events,
      * use the six pre-defined fields "parameter1" to "parameter6" for this
      * purpose. 
      *
      * Examples:
      * $sample = Sample.instance();
      * $sample.track('session_start', 'session'); // send the session start event
      * $sample.track('found_item', 'custom', {    // send custom item event
      *   parameter1: 'Black Stab',                // custom item name
      *   parameter2: '21',                        // level of item
      * });
      */
    public function track($eventName, $eventCategory, $params=array()) 
    {
        $params = $this->mergeParams($params, $eventName, $eventCategory);
        $this->sendRequest(self::$endPoint, $params);
    }
    

    // /////////////////////////////////////////////////////////////////////////
    //
    //   ACCOUNT EVENTS
    //
    // /////////////////////////////////////////////////////////////////////////

    /** Should be send when a new user did register
      * Each registration takes, besides the user id, an optional list of key-value pairs.
      */
    public function registration($userId, $params=null)
    {
        $this->userId = empty($userId) ? $this->userId : $userId ;
        $this->track('registration', 'account', $params);
    }

    /** Should be send when an existing user signs in
      * Each sign intakes, besides the user id, an optional list of key-value pairs.
      */
    public function signIn($userId, $params=null)
    {
        $this->userId = empty($userId) ? $this->userId : $userId ;
        $this->track('sign_in', 'account', $params);
    }

    /** Should be send when the account of the current user needs an update.
      * For example when an field relating to the account changes. Like the target group
      * or country
      */
    public function profileUpdate($params)
    {
      $this->track('update', 'account', $params);
    }
     
     
    /**
     * Returns current timestamp, or forced timestamp/datetime if it was set
     * @return string|int
     */
    protected function getTimestamp()
    {
        return !empty($this->forcedDatetime)
            ? strtotime($this->forcedDatetime)
            : time();
    }
     

    /**
     */
    public function getRequestTimeout()
    {
        return $this->requestTimeout;
    }

    /**
     */
    public function setRequestTimeout($timeout)
    {
        if (!is_int($timeout) || $timeout < 0) {
            $timeout = 0;
        }
        $this->requestTimeout = $timeout;
    }
    


    // /////////////////////////////////////////////////////////////////////////
    //
    //   GENERATING AND SENDING REQUEST
    //
    // /////////////////////////////////////////////////////////////////////////
    
    /**
     */
    protected function sendRequest($url, $data = null)
    {
        $data_string = empty($data) ? "" :  json_encode([ "p" => $data]);  
        
        if (function_exists('curl_init') && function_exists('curl_exec')) {
            $options = array(
                CURLOPT_URL            => $url,
                CURLOPT_USERAGENT      => $this->userAgent,
                CURLOPT_HEADER         => true,
                CURLOPT_TIMEOUT        => $this->requestTimeout,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => array(
                    'Accept-Language: ' . $this->acceptLanguage
                ));
                
            switch ($method) {
                case 'POST':
                    $options[CURLOPT_POST] = TRUE;
                    break;
                default:
                    break;
            }
            
            //echo $url; exit;

            // only supports JSON data
            if (!empty($data)) {
                $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
                $options[CURLOPT_HTTPHEADER][] = 'Expect:';
                $options[CURLOPT_POSTFIELDS] = $data_string;
            }
            
            $ch = curl_init();
            curl_setopt_array($ch, $options);
            ob_start();
            $response = @curl_exec($ch);
            ob_end_clean();
            $content = '';
            if (!empty($response)) {
                list($header, $content) = explode("\r\n\r\n", $response, $limitCount = 2);
            }
        } else if (function_exists('stream_context_create')) {
            $stream_options = array(
                'http' => array(
                    'method'     => $method,
                    'user_agent' => $this->userAgent,
                    'header'     => "Accept-Language: " . $this->acceptLanguage . "\r\n",
                    'timeout'    => $this->requestTimeout, // PHP 5.2.1
                )
            );

            // only supports JSON data
            if (!empty($data)) {
                $stream_options['http']['header'] .= "Content-Type: application/json \r\n";
                $stream_options['http']['content'] = $data_string;
            }
            $ctx = stream_context_create($stream_options);
            $response = file_get_contents($url, 0, $ctx);
            $content = $response;
        }
        return $content;
    }
    
    
    protected function add(&$params, $key, $value, $default=null) 
    {
        // we'll add "0" but ignore values that equal null
        if (!empty($key))  
        {
            if (isset($value) && $value !== 0)
            {
                $params[$key] = $value;
            }
            else if (isset($default) && $default !== 0)
            {
                $params[$key] = $default;
            }
        }
    }
        
    protected function mergeParams($userParams, $eventName, $eventCategory)
    {
      $params = array();

      $this->add($params, "sdk",            self::SDK);
      $this->add($params, "sdk_version",    self::SDK_VERSION);

      $this->add($params, "server_side",    $this->serverSide);

      $this->add($params, "platform",       $userParams['platform'],         $this->platform);
      $this->add($params, "client",         $userParams['client'],           $this->client);
      $this->add($params, "client_version", $userParams['client_version'],   $this->clientVersion);

      $this->add($params, "event_name",     $eventName);
      $this->add($params, "app_token",      $this->appToken);
      
      // $this->add($params, "install_token",  installToken);
      // $this->add($params, "session_token",  sessionToken);
      $this->add($params, "debug",          $this->debugMode);
      $this->add($params, "timestamp",      $userParams["timestamp"], $this->getTimestamp());
      $this->add($params, "user_id",        $this->userId);

      $this->add($params, "event_category", $eventCategory, "custom");
      $this->add($params, "module",         $userParams["module"], $this->module);
      $this->add($params, "content_id",     $userParams["content_id"]);
      $this->add($params, "content_ids",    $userParams["content_ids"]);
      $this->add($params, "content_type",   $userParams["content_type"]);
      $this->add($params, "page_id",        $userParams["page_id"]); //, $this->getPageId());
      $this->add($params, "translation",    $userParams["translation"]);

      $this->add($params, "parameter1",     $userParams["parameter1"]);
      $this->add($params, "parameter2",     $userParams["parameter2"]);
      $this->add($params, "parameter3",     $userParams["parameter3"]);
      $this->add($params, "parameter4",     $userParams["parameter4"]);
      $this->add($params, "parameter5",     $userParams["parameter5"]);
      $this->add($params, "parameter6",     $userParams["parameter6"]);

      if ($eventName === "purchase" ||
          $eventName === "chargeback")
      {
        $this->add($params, "pur_provider",           $userParams["pur_provider"]);
        $this->add($params, "pur_gross",              $userParams["pur_gross"]);
        $this->add($params, "pur_currency",           $userParams["pur_currency"]);
        $this->add($params, "pur_country_code",       $userParams["pur_country_code"]);
        $this->add($params, "pur_earnings",           $userParams["pur_earnings"]);
        $this->add($params, "pur_product_sku",        $userParams["pur_product_sku"]);
        $this->add($params, "pur_product_category",   $userParams["pur_product_category"]);
        $this->add($params, "pur_receipt_identifier", $userParams["pur_receipt_identifier"]);
      }

      if ($eventName === "session_start"  ||
          $eventName === "session_update" ||
          $eventName === "session_resume" ||
          ($eventCategory && $eventCategory === "account")) 
      {
        $this->add($params, "email",         $userParams["email"], $this->email);
        $this->add($params, "locale",        $userParams["locale"], $this->locale);

        $this->add($params, "ad_referer",    $userParams["ad_referer"], $this->ad_referer);
        $this->add($params, "ad_campaign",   $userParams["ad_campaign"], $this->ad_campaign);
        $this->add($params, "ad_placement",  $userParams["ad_placement"], $this->ad_placement);

        $this->add($params, "longitute",     $userParams["longitude"], $this->longitude);
        $this->add($params, "latitude",      $userParams["latitude"], $this->latitude);

        $this->add($params, "country_code",  $userParams["country_code"], $this->country_code);
        $this->add($params, "facebook_id",   $userParams["facebook_id "], $this->facebook_id);

        $this->add($params, "target_group",  $userParams["target_group"]);

        $this->add($params, "host",          $userParams["host"], $this->host);
      }


      return $params;
    }
    
    
    /**
     */
    static protected function getCurrentScheme()
    {
        if (isset($_SERVER['HTTPS'])
            && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] === true)
        ) {
            return 'https';
        }
        return 'http';
    }

    /**
     */
    static protected function getCurrentHost()
    {
        if (isset($_SERVER['HTTP_HOST'])) {
            return $_SERVER['HTTP_HOST'];
        }
        return 'unknown';
    }

    /**
     */
    static protected function getCurrentQueryString()
    {
        $url = '';
        if (isset($_SERVER['QUERY_STRING'])
            && !empty($_SERVER['QUERY_STRING'])
        ) {
            $url .= '?' . $_SERVER['QUERY_STRING'];
        }
        return $url;
    }
    
    static protected function getCurrentScriptName()
    {
        $url = '';
        if (!empty($_SERVER['PATH_INFO'])) {
            $url = $_SERVER['PATH_INFO'];
        } else if (!empty($_SERVER['REQUEST_URI'])) {
            if (($pos = strpos($_SERVER['REQUEST_URI'], '?')) !== false) {
                $url = substr($_SERVER['REQUEST_URI'], 0, $pos);
            } else {
                $url = $_SERVER['REQUEST_URI'];
            }
        }
        if (empty($url)) {
            $url = $_SERVER['SCRIPT_NAME'];
        }

        if ($url[0] !== '/') {
            $url = '/' . $url;
        }
        return $url;
    }

    /**
     */
    static protected function getCurrentUrl()
    {
        return self::getCurrentScheme() . '://'
            . self::getCurrentHost()
            . self::getCurrentScriptName()
            . self::getCurrentQueryString();
    }
}
