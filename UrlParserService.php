<?php

namespace Invesp\Parser;

use Pdp\Parser;
use Pdp\PublicSuffixListManager;

/**
 * Class UrlParserService
 *
 * IMPORTANT:
 * PHP's intl extension is required for the IDN functions.
 * PHP's mb_string extension is required for mb_strtolower.
 *
 * https://publicsuffix.org/list/public_suffix_list.dat need to be accessible
 *
 * Usage:
 *
 * $url = 'http://user:pass@www.pref.okinawa.jp:8080/path/to/page.html?query=string#fragment';
 *
 * $obj = new UrlParserService();
 * $parsed = $obj->parseUrl($url);
 * array[
 *      "host" => "www.pref.okinawa.jp"
 *      "scheme" => "http"
 *      "user" => "user"
 *      "pass" => "pass"
 *      "subdomain" => "www"
 *      "domain" => "pref.okinawa.jp"
 *      "port" => "8080"
 *      "path" => "/path/to/page.html"
 *      "query" => "query=string"
 *      "fragment" => "fragment"
 * ]
 * $domain = $obj->joinUrl($parsed, UrlParserService::$TEMPLATE_DOMAIN_EXCLUDES);
 * $clean_url = $obj->joinUrl($parsed, UrlParserService::$TEMPLATE_SIMPLE_EXCLUDES);
 *
 * $obj = new UrlParserService();
 * $domain = $obj->getDomain($url); //pref.okinawa.jp
 * $domain = $obj->getHost($url); //www.pref.okinawa.jp
 *
 * @link https://github.com/jeremykendall/php-domain-parser
 * @package Invesp\Parser
 * @author Kadir Erce Erözbek <erce.erozbek@gmail.com>
 */
class UrlParserService
{
    const URL_SCHEME = 'scheme';
    const URL_HOST = 'host';
    const URL_SUBDOMAIN = 'subdomain';
    const URL_DOMAIN = 'domain';
    const URL_PORT = 'port';
    const URL_USER = 'user';
    const URL_PASS = 'pass';
    const URL_PATH = 'path';
    const URL_QUERY = 'query';
    const URL_FRAGMENT = 'fragment';

    public static $TEMPLATE_EXCLUDES_REGEXP = [
        self::URL_SCHEME,
        self::URL_HOST,
        self::URL_SUBDOMAIN,
        self::URL_DOMAIN,
        self::URL_USER,
        self::URL_PASS,
        self::URL_PORT,
        self::URL_QUERY,
        self::URL_FRAGMENT,
    ];

    public static $TEMPLATE_EXCLUDES_EXACT = [
        self::URL_SCHEME,
        self::URL_HOST,
        self::URL_USER,
        self::URL_PASS,
        self::URL_PORT,
        self::URL_FRAGMENT,
    ];

    public static $TEMPLATE_EXCLUDES_SIMPLE = [
        self::URL_SCHEME,
        self::URL_HOST,
        self::URL_USER,
        self::URL_PASS,
        self::URL_PORT,
        self::URL_QUERY,
        self::URL_FRAGMENT,
    ];

    public static $TEMPLATE_EXCLUDES_DOMAIN = [
        self::URL_SCHEME,
        self::URL_HOST,
        self::URL_USER,
        self::URL_PASS,
        self::URL_PORT,
        self::URL_PATH,
        self::URL_QUERY,
        self::URL_FRAGMENT,
    ];

    private $object = [];

    /**
     * @param $url
     * @param bool $key
     * @param bool $query_as_array
     * @return array|bool|string
     */
    public function parseUrl($url, $key = false, $query_as_array = false)
    {
        if (!$url) {
            return false;
        }

        $pslManager = new PublicSuffixListManager();
        $parser = new Parser($pslManager->getList());

        /* @var $return \Pdp\Uri\Url */
        $return = $parser->parseUrl($url);

        //TODO use http_build_query instead of the code below
        $params = array();
        if ($query_as_array) {
            $_tmp = explode('&', $return->query);
            foreach ($_tmp as $p) {
                $_p = explode('=', $p);
                if (count($_p) === 2) {
                    $params[$_p[0]] = urldecode($_p[1]);
                }
            }
        }
        $this->object = array(
            self::URL_HOST => $return->host->host,
            self::URL_SCHEME => $return->scheme,
            self::URL_USER => $return->user,
            self::URL_PASS => $return->pass,
            self::URL_SUBDOMAIN => $return->host->subdomain,
            self::URL_DOMAIN => $return->host->registerableDomain,
            self::URL_PORT => $return->port,
            self::URL_PATH => !$return->path ? '/' : $return->path,
            self::URL_QUERY => !$query_as_array ? $return->query : $params,
            self::URL_FRAGMENT => $return->fragment,
        );

        return !$key ? $this->object : (isset($this->object[$key]) ? $this->object[$key] : false);
    }

    /**
     * @param array $url_array
     * @param array|null $exclude_keys
     * @param bool $remove_www_subdomain
     * @return string
     * @throws \InvalidArgumentException
     */
    public function joinUrl($url_array, $exclude_keys = null, $remove_www_subdomain = true)
    {
        if ($exclude_keys === null) {
            $exclude_keys = self::$TEMPLATE_EXCLUDES_SIMPLE;
        }

        if($remove_www_subdomain && $this->object[self::URL_SUBDOMAIN] === 'www'){
            $exclude_keys[] = self::URL_SUBDOMAIN;
        }

        if(!is_array($url_array)){
            throw new \InvalidArgumentException('Url array is not valid');
        }

        $url = '';
        foreach ($url_array as $key => $value) {
            if (in_array($key, $exclude_keys, false)) {
                continue;
            }

            if (!$value) {
                continue;
            }

            switch ($key) {
                case self::URL_HOST:
                    break;
                case self::URL_USER:
                    break;
                case self::URL_PASS:
                    break;
                case self::URL_SCHEME:
                    $url .= $value.'://';
                    break;
                case self::URL_SUBDOMAIN:
                    $url .= $value.'.';
                    break;
                case self::URL_PORT:
                    $url .= ':'.$value;
                    break;
                case self::URL_QUERY:
                    $url .= '?'.$value;
                    break;
                case self::URL_FRAGMENT:
                    $url .= '#'.$value;
                    break;
                default:
                    $url .= $value;
                    break;
            }
        }

        return $url;
    }

    public function getDomain($url = null)
    {
        if ($url) {
            $this->parseUrl($url);
        }
        $this->checkData();
        return $this->joinUrl($this->object, self::$TEMPLATE_EXCLUDES_DOMAIN);
    }

    public function getHost($url = null)
    {
        if ($url) {
            $this->parseUrl($url);
        }
        $this->checkData();
        return $this->object['host'];
    }

    public function checkData(){
        if(!is_array($this->object)){
            throw new \InvalidArgumentException("Url is not setted");
        }
    }
}
