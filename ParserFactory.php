<?php
/**
 * Created by IntelliJ IDEA.
 * User: McHain
 * Date: 23.09.2015
 * Time: 21:20
 */

namespace mchain\vacancyparser;

use mchain\vacancyparser\parsers\AbstractVacancyParser;
use mchain\vacancyparser\parsers\JobmaxRuParser;
use mchain\vacancyparser\parsers\MskRjobParser;
use mchain\vacancyparser\parsers\NskErabotaParser;
use mchain\vacancyparser\parsers\NskJobParser;

class ParserFactory
{
    /**
     * @param null $siteNames
     * @param \DateTime $since
     * @param int $order
     * @return AbstractVacancyParser[]
     */
    public static function factory($siteNames = null, $since = null, $order = 0)
    {
        $siteNames = is_array($siteNames) ? $siteNames : [$siteNames];
        $result    = [];

        foreach ($siteNames as $siteName) {
            switch (true) {
                case (strpos($siteName, MskRjobParser::getAddress()) !== false) :
                    $result[] = new MskRjobParser($since, $order);
                    break;
                case (strpos($siteName, NskJobParser::getAddress()) !== false):
                    $result[] = new NskJobParser($since, $order);
                    break;
                case (strpos($siteName, NskErabotaParser::getAddress()) !== false):
                    $result[] = new NskErabotaParser($since, $order);
                    break;
                case (strpos($siteName, JobmaxRuParser::getAddress()) !== false):
                    $result[] = new JobmaxRuParser($since, $order);
                    break;
                case ($siteName == '*'):
                    $result = [
                        new MskRjobParser($since, $order),
                        new NskJobParser($since, $order),
                        new NskErabotaParser($since, $order),
                        new JobmaxRuParser($since, $order),
                    ];
                    break;
            }
        }

        return $result;
    }
}