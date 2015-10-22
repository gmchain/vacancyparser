<?php
/**
 * Created by IntelliJ IDEA.
 * User: McHain
 * Date: 23.09.2015
 * Time: 21:23
 */

namespace mchain\vacancyparser\parsers;


use DateTime;
use mchain\vacancyparser\codes\CurrencyCodes;
use mchain\vacancyparser\codes\ExperienceCodes;
use mchain\vacancyparser\codes\JobTypeCodes;
use mchain\vacancyparser\codes\ScheduleCodes;
use mchain\vacancyparser\ParsedVacancy;
use Sunra\PhpSimple\HtmlDomParser;

class JobmaxRuParser extends AbstractVacancyParser
{
    /**
     * @var string
     */
    protected static $address = 'jobmax.ru';

    /**
     * @var string
     */
    protected $name = 'Site jobmax.ru';

    /**
     * @var string
     */
    protected $url = 'http://jobmax.ru';

    /**
     * @var string
     */
    protected $startUrl = 'http://novosibirsk.jobmax.ru/vacancy-search?employ=7&time=30&recruiter=1&employer=1&show=&sort_mode=time&sort_order=1';

    /**
     * @var int
     */
    protected $count = 0;

    /**
     * @return string
     */
    protected function getNextIndexUrl()
    {
        $this->count++;

        if ($this->count > 50) {
            return null;
        }

        return $this->startUrl . '&page=' . ($this->count * 25);
    }

    /**
     * @param String $url
     * @return mixed
     */
    protected function getIndexPage($url)
    {
        return HtmlDomParser::file_get_html($url);
    }

    /**
     * @param mixed $page
     * @return array
     */
    protected function getLinks($page)
    {
        $result = [];

        do {
            /** @var \simple_html_dom $page */
            $links = $page->find('.leftSearchVacancyResult a.titleSearchVacancyResult');

            /** @var \simple_html_dom_node $link */
            foreach ($links as $link) {
                if (strpos($link->attr['href'], self::$address) === false) {
                    continue;
                }
                /** @var \simple_html_dom_node $link */
                $result[] = $link->attr['href'];
            }
        } while (!$result && ($url = $this->getNextIndexUrl()) && ($page = $this->getIndexPage($url)));

        return $result;
    }

    /**
     * @param $string
     * @return \DateTime
     */
    private function parseDate($string)
    {
        $string = trim($string);

        $result = \DateTime::createFromFormat('d.mm.Y, H:i', $string);

        if (!$result) {
            $result = new \DateTime($string);
        }

        return $result;
    }

    /**
     * @param mixed $page
     * @return DateTime
     */
    protected function getLastVacancyDate($page)
    {
        /** @var \simple_html_dom $page */
        $dates = $page->find('.rightSearchVacancyResult i');

        /** @var \simple_html_dom_node $lastDate */
        $lastDate = array_pop($dates);

        if (!$lastDate) {
            return null;
        }

        $result = $this->parseDate($lastDate->text());

        return $result;
    }

    /**
     * @param string $url
     * @return ParsedVacancy
     */
    protected function parseVacancy($url)
    {
        /** @var \simple_html_dom $dom */
        $dom = HtmlDomParser::file_get_html($url);

        $result = new ParsedVacancy();

        $result->url  = $url;
        $result->site = $this->url;

        /** @var \simple_html_dom_node[] $names */
        $names = $dom->find('.showResumeBlockTitle .searchingtext');
        if ($names) {
            $result->name = trim($names[0]->text());
        }

        $description = [];
        /** @var \simple_html_dom_node[] $chapters */
        $chapters = $dom->find('.brend_vacancy .h3, .brend_vacancy .showVacancyBlockDescriptionText');
        foreach ($chapters as $chapter) {
            $description[] = trim($chapter->text());
        }

        $result->description = implode("\n", $description);
        $result->experience  = ExperienceCodes::EXPERIENCE_UNKNOWN;
        $result->jobType     = JobTypeCodes::JOB_TYPE_REMOTE;
        $result->schedule    = ScheduleCodes::SCHEDULE_FULL_TIME;
        $result->categories  = [];
        $result->categorized = false;

        /** @var \simple_html_dom_node[] $infoBlocks */
        $infoBlocks     = $dom->find('.showVacancyBlockDescription span');
        $nextExperience = false;
        foreach ($infoBlocks as $infoBlock) {
            if (trim($infoBlock->text()) == 'Опыт работы:') {
                $nextExperience = true;
                continue;
            }

            if ($nextExperience) {
                $result->experience = $this->getExperienceCode(trim($infoBlock->text()));
                $nextExperience     = false;
                continue;
            }
        }

        /** @var \simple_html_dom_node[] $dates */
        $dates        = $dom->find('.resumeShowInfoBlock .resNumberBlock i');
        $result->date = $this->parseDate(trim($dates[0]->text()));

        $result->salaryCurrency = CurrencyCodes::CURRENCY_RUR;

        $result->salaryFrom = $result->salaryTo = 0;
        /** @var \simple_html_dom_node[] $salaries */
        $salaries = $dom->find('.showResumeSallaryFrom span i');
        if ($salaries) {
            switch (count($salaries)) {
                case 2:
                    $result->salaryFrom = (int)str_replace(' ', '', $salaries[0]->text());
                    $result->salaryTo   = (int)str_replace(' ', '', $salaries[1]->text());
                    break;
                case 1:
                    $result->salaryFrom = $result->salaryTo = (int)str_replace(' ', '', $salaries[0]->text());
                    break;
            }
        }

        $url        = trim($url, '/');
        $result->id = substr($url, strrpos($url, '-') + 1);

        /** @var \simple_html_dom_node[] $employers */
        $employers = $dom->find('.showResumeInfo .searchingtext');
        if ($employers) {
            $employer         = $employers[0];
            $result->employer = trim($employer->text());
        }

        return $result;
    }

    /**
     * @param $experience
     * @return int
     */
    private function getExperienceCode($experience)
    {
        switch ($experience) {
            case 'Более 1 года':
                return ExperienceCodes::EXPERIENCE_ONE_YEAR;
            case 'Более 3 лет':
                return ExperienceCodes::EXPERIENCE_THREE_YEARS;
            case 'Более 5 лет':
                return ExperienceCodes::EXPERIENCE_FIVE_YEARS;
        }

        return ExperienceCodes::EXPERIENCE_UNKNOWN;
    }
}