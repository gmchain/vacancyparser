<?php
/**
 * Created by IntelliJ IDEA.
 * User: McHain
 * Date: 23.09.2015
 * Time: 21:22
 */

namespace mchain\vacancyparser\parsers;


use DateTime;
use mchain\vacancyparser\codes\CurrencyCodes;
use mchain\vacancyparser\codes\ExperienceCodes;
use mchain\vacancyparser\codes\JobTypeCodes;
use mchain\vacancyparser\codes\ScheduleCodes;
use mchain\vacancyparser\ParsedVacancy;
use Sunra\PhpSimple\HtmlDomParser;

class NskJobParser extends AbstractVacancyParser
{
    /**
     * @var string
     */
    protected static $address = 'nsk.job.ru';

    /**
     * @var string
     */
    protected $name = 'Site nsk.job.ru';

    /**
     * @var string
     */
    protected $url = 'http://nsk.job.ru';

    /**
     * @var string
     */
    protected $startUrl = 'http://nsk.job.ru/seeker/job/?period=0&ep=2&rstm=1&srch=1';

    /**
     * @var int
     */
    protected $count = 1;

    /**
     * @return string
     */
    protected function getNextIndexUrl()
    {
        $this->count++;
        return $this->startUrl . '&p=' . $this->count;
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

        /** @var \simple_html_dom $page */
        $links = $page->find('a.srName');

        foreach ($links as $link) {
            /** @var \simple_html_dom_node $link */
            $result[] = $this->url . $link->attr['href'];
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
        $dates = $page->find('div.b-vacancies__date');

        /** @var \simple_html_dom_node $lastDate */
        $lastDate = array_pop($dates);

        if (!$lastDate) {
            return null;
        }

        $result = $this->parseDate($lastDate->text());

        return $result;
    }

    /**
     * @param $string
     * @return \DateTime
     */
    private function parseDate($string)
    {
        $string = str_replace(
            ['вчера', 'сегодня'],
            ['Yesterday', 'Today'],
            $string
        );

        $result = \DateTime::createFromFormat('d.F.Y', $string);

        if (!$result) {
            $result = new \DateTime($string);
        }

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
        $names = $dom->find('.inner-content h1');
        if ($names) {
            $result->name = trim($names[0]->text());
        }

        $description = [];
        /** @var \simple_html_dom_node[] $chapters */
        $chapters  = $dom->find('.vacancy-description h2, .vacancy-description p');
        $parseNext = false;
        $jobType   = '';
        $schedule  = '';
        foreach ($chapters as $chapter) {
            if ($parseNext) {
                $data = (string)$chapter;
                $data = explode('<br/>', $data);
                $data = trim(strip_tags($data[0]), ' ▪');
                if (substr_count($data, ',') > 1) {
                    list(, $schedule, $jobType) = explode(',', $data);
                } else {
                    list(, $schedule) = explode(',', $data);
                }
                $parseNext = false;
            }
            if ($chapter->text() == 'Условия') {
                $parseNext = true;
            }
            $description[] = html_entity_decode(ucfirst(trim($chapter->text())));
        }

        $result->description    = implode("\n", $description);
        $result->schedule       = $this->getScheduleCode(trim($schedule));
        $result->jobType        = $this->getJobTypeCode(trim($jobType));
        $result->salaryCurrency = CurrencyCodes::CURRENCY_RUR;

        /** @var \simple_html_dom_node[] $infoBlocks */
        $infoBlocks         = $dom->find('.vacancy-info .info-block .info');
        $result->experience = ExperienceCodes::EXPERIENCE_UNKNOWN;
        foreach ($infoBlocks as $infoBlock) {
            switch (true) {
                case (strpos($infoBlock->text(), 'Опыт работы') !== false) :
                    $result->experience = $this->getExperienceCode($infoBlock->text());
                    break;
                case (strpos($infoBlock->text(), 'Размещено') !== false) :
                    $result->date = $this->parseDate(str_replace('Размещено ', '', $infoBlock->text()));
                    break;
            }
        }

        /** @var \simple_html_dom_node[] $salaries */
        $salaries = $dom->find('.vacancy-info .money-block');
        if ($salaries) {
            $salary = $salaries[0];
            $salary = $salary->text();
            $salary = str_replace('\'', '', $salary);
            $salary = trim(substr($salary, 0, strpos($salary, '<') - 1));

            $strict = strpos($salary, 'от') === false;
            $salary = str_replace('от ', '', $salary);

            if (strpos($salary, ' до ') !== false) {
                list($salaryFrom, $salaryTo) = explode(' до ', $salary);
            } elseif ($strict) {
                $salaryFrom = $salaryTo = (int)$salary;
            } else {
                $salaryFrom = $salary;
                $salaryTo   = 0;
            }

            $result->salaryFrom = (int)$salaryFrom;
            $result->salaryTo   = (int)$salaryTo;
        }

        $url        = trim($url, '/');
        $result->id = substr($url, strrpos($url, '/') + 1);

        /** @var \simple_html_dom_node[] $employers */
        $employers = $dom->find('.employer-description .company-link');
        if ($employers) {
            $employer         = $employers[0];
            $result->employer = trim($employer->text());
        }

        /** @var \simple_html_dom_node[] $categories */
        $categories = $dom->find('.job-sphere');
        $text       = trim($categories[0]->text());
        $text       = str_replace('Сферы деятельности', '', $text);
        $text       = substr($text, 0, strpos($text, 'еще'));
        $spheres    = explode(';', $text);
        $categories = [];

        foreach ($spheres as $sphere) {
            if (trim($sphere) == '') {
                continue;
            }
            list($main, $subs) = explode('→', $sphere);
            $main = trim($main);
            $subs = preg_split('/,\s*(?=\p{Lu}{Ll})/', $subs);
            foreach ($subs as $sub) {
                $categories[] = $main . ': ' . trim($sub);
            }
        }

        foreach ($categories as $category) {
            if (strpos($category, 'Удаленная работа') !== 0) {
                $result->categories[] = $category;
            }
        }

        //Categories off
        $result->categorized = false;

        return $result;
    }

    /**
     * @param $schedule
     * @return int
     */
    private function getScheduleCode($schedule)
    {
        switch (mb_strtolower($schedule, 'utf-8')) {
            case 'полный день':
                return ScheduleCodes::SCHEDULE_FULL_TIME;
            case 'сменный график':
            case 'свободный / гибкий график':
                return ScheduleCodes::SCHEDULE_PART_TIME;
        }

        return ScheduleCodes::SCHEDULE_UNKNOWN;
    }

    /**
     * @param $jobType
     * @return int
     */
    private function getJobTypeCode($jobType)
    {
        switch (mb_strtolower($jobType, 'utf-8')) {
            case 'работа на территории работодателя':
                return JobTypeCodes::JOB_TYPE_OFFLINE;
            case 'на дому / удаленная работа':
                return JobTypeCodes::JOB_TYPE_REMOTE;
        }

        return JobTypeCodes::JOB_TYPE_UNKNOWN;
    }

    /**
     * @param $experience
     * @return int
     */
    private function getExperienceCode($experience)
    {
        switch ($experience) {
            case 'Опыт работы: от 1 года':
                return ExperienceCodes::EXPERIENCE_ONE_YEAR;
            case 'Опыт работы: от 2 лет':
            case 'Опыт работы: от 3 лет':
                return ExperienceCodes::EXPERIENCE_THREE_YEARS;
            case 'Опыт работы: от 5 лет':
                return ExperienceCodes::EXPERIENCE_FIVE_YEARS;
        }

        return ExperienceCodes::EXPERIENCE_UNKNOWN;
    }
}