<?php
/**
 * Created by IntelliJ IDEA.
 * User: McHain
 * Date: 23.09.2015
 * Time: 21:29
 */

namespace mchain\vacancyparser\parsers;

use DateTime;
use mchain\vacancyparser\ParsedVacancy;

abstract class AbstractVacancyParser
{
    /**
     * @var string
     */
    protected static $address = '';

    /**
     * @var string
     */
    protected $url = '';

    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var array
     */
    protected $currentLinks = [];

    /**
     * @var DateTime
     */
    protected $startDate;

    /**
     * @var string
     */
    protected $startUrl = '';

    /**
     * @var string
     */
    protected $currentUrl = '';

    /**
     * @var mixed
     */
    protected $currentPage = null;

    /**
     * @var int
     */
    protected $order = 0;

    /**
     * @param DateTime $startDate
     * @param int $order
     */
    public function __construct($startDate = null, $order = 0)
    {
        $startDate       = $startDate ?: new DateTime();
        $this->startDate = $startDate;
        $this->order     = $order;
    }

    /**
     * @return ParsedVacancy
     */
    public function find()
    {
        $currentLink = null;

        if (!$this->currentLinks) {
            $this->currentLinks = $this->loadLinks();
        }

        $currentLink = $this->order ? array_shift($this->currentLinks) : array_pop($this->currentLinks);

        if ($currentLink) {
            return $this->parseVacancy($currentLink);
        }

        return null;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public static function getAddress()
    {
        return static::$address;
    }

    /**
     * @return array
     */
    protected function loadLinks()
    {
        $timeStamp = $this->startDate->getTimestamp();
        $result    = [];

        do {
            $indexUrl = $this->getIndexUrl();
            if (!$indexUrl) {
                break;
            }
            $page     = $this->getIndexPage($indexUrl);

            $lastDate  = $this->getLastVacancyDate($page);
            $pageLinks = $this->getLinks($page);

            $result = array_merge($result, $pageLinks);
        } while ($pageLinks && $lastDate && ($lastDate->getTimestamp() > $timeStamp));

        return $result;
    }

    /**
     * @return string
     */
    protected function getIndexUrl()
    {
        if (!$this->currentUrl) {
            $this->currentUrl = $this->getStartIndexUrl();
        } else {
            $this->currentUrl = $this->getNextIndexUrl();
        }

        return $this->currentUrl;
    }

    /**
     * @return string
     */
    protected function getStartIndexUrl()
    {
        return $this->startUrl;
    }

    /**
     * @return string
     */
    abstract protected function getNextIndexUrl();

    /**
     * @param String $url
     * @return mixed
     */
    abstract protected function getIndexPage($url);

    /**
     * @param mixed $page
     * @return array
     */
    abstract protected function getLinks($page);

    /**
     * @param mixed $page
     * @return DateTime
     */
    abstract protected function getLastVacancyDate($page);

    /**
     * @param string $url
     * @return ParsedVacancy
     */
    abstract protected function parseVacancy($url);

    public function testParse($url)
    {
        $vacancy = $this->parseVacancy($url);
        var_dump($vacancy);
    }
}