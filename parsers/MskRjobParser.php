<?php
/**
 * Created by IntelliJ IDEA.
 * User: McHain
 * Date: 23.09.2015
 * Time: 21:22
 */

namespace mchain\vacancyparser\parsers;

use mchain\vacancyparser\codes\CurrencyCodes;
use mchain\vacancyparser\codes\ExperienceCodes;
use mchain\vacancyparser\codes\JobTypeCodes;
use mchain\vacancyparser\codes\ScheduleCodes;
use mchain\vacancyparser\ParsedVacancy;
use Sunra\PhpSimple\HtmlDomParser;

class MskRjobParser extends AbstractVacancyParser
{
    /**
     * @var string
     */
    protected static $address = 'msk.rjob.ru';

    /**
     * @var string
     */
    protected $name = 'Site msk.rjob.ru';

    /**
     * @var string
     */
    protected $url = 'http://msk.rjob.ru';

    /**
     * @var string
     */
    protected $startUrl = 'http://msk.rjob.ru/vacancies/?sort[active_from]=DESC&cty_path[0]=001094.&nPageSize=20&emp[]=4';

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
        return $this->startUrl . '&nPage=' . $this->count;
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
        $links = $page->find('a.js-vacancy-title');

        foreach ($links as $link) {
            /** @var \simple_html_dom_node $link */
            $result[] = $this->url . $link->attr['href'];
        }

        return $result;
    }

    /**
     * @param mixed $page
     * @return \DateTime
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
            ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря', 'Вчера', 'Сегодня'],
            ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'Yesterday', 'Today'],
            $string
        );

        $string = trim(str_replace(' в', '', $string));

        $result = \DateTime::createFromFormat('d F H:i', $string);

        if (!$result) {
            $result = \DateTime::createFromFormat('d F Y H:i', $string);
        }

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
        $names = $dom->find('#js_vacancy-name');
        if ($names) {
            $result->name = trim($names[0]->text());
        }

        $description = [];
        /** @var \simple_html_dom_node[] $chapters */
        $chapters = $dom->find('.employer__content-list p');
        foreach ($chapters as $chapter) {
            if (empty($chapter->attr['id'])) {
                $description[] = html_entity_decode(ucfirst(trim($chapter->text())));
            }
        }
        $result->description = implode("\n", $description);

        /** @var \simple_html_dom_node[] $schedules */
        $schedules = $dom->find('[itemprop="employmentType"]');
        if ($schedules) {
            $schedule         = $schedules[0];
            $result->schedule = $this->getScheduleCode(trim($schedule->text()));
            $result->jobType  = $this->getJobTypeCode(trim($schedule->text()));
        }

        /** @var \simple_html_dom_node[] $experiences */
        $experiences = $dom->find('[itemprop="experienceRequirements"]');
        if ($experiences) {
            $experience         = $experiences[0];
            $result->experience = $this->getExperienceCode(trim($experience->text()));
        }

        /** @var \simple_html_dom_node[] $employers */
        $employers = $dom->find('[itemprop="hiringOrganization"] [itemprop="name"]');
        if ($employers) {
            $employer         = $employers[0];
            $result->employer = trim($employer->text());
        }

        /** @var \simple_html_dom_node[] $salaries */
        $salaries = $dom->find('[itemprop="baseSalary"]');
        if ($salaries) {

            $salary = $salaries[0];
            $salary = $salary->text();

            if (strpos($salary, '&ndash;') !== false) {
                list($salaryFrom, $salaryTo) = explode('&ndash;', $salary);
                $salaryFrom = (int)str_replace(' ', '', trim($salaryFrom));
                $salaryTo   = (int)str_replace([' ', 'Р'], '', trim($salaryTo));
            } else {
                $salaryFrom = $salaryTo = (int)trim($salary);
            }

            $result->salaryFrom = $salaryFrom;
            $result->salaryTo   = $salaryTo;

            $result->salaryCurrency = CurrencyCodes::CURRENCY_RUR;
        }

        $url        = trim($url, '/');
        $result->id = substr($url, strrpos($url, '/') + 1);

        /** @var \simple_html_dom_node[] $categories */
        $categories = $dom->find('[itemprop="industry"] a');
        foreach ($categories as $category) {
            if (trim($category->text()) !== 'Удаленная работа') {
                $result->categories[] = trim($category->text());
            }
        }

        /** @var \simple_html_dom_node[] $dates */
        $dates        = $dom->find('[itemprop="datePosted"]');
        $result->date = $this->parseDate($dates[0]->text());

        return $result;
    }

    /**
     * @param $schedule
     * @return int
     */
    private function getScheduleCode($schedule)
    {
        switch ($schedule) {
            case 'Удаленная работа':
                return ScheduleCodes::SCHEDULE_FULL_TIME;
        }

        return ScheduleCodes::SCHEDULE_UNKNOWN;
    }

    /**
     * @param $jobType
     * @return int
     */
    private function getJobTypeCode($jobType)
    {
        switch ($jobType) {
            case 'Удаленная работа':
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
            case 'Без опыта':
                return ExperienceCodes::EXPERIENCE_NONE;
            case 'Опыт 1-3 года':
                return ExperienceCodes::EXPERIENCE_ONE_YEAR;
            case 'Опыт 3-6 лет':
                return ExperienceCodes::EXPERIENCE_THREE_YEARS;
            case 'Опыт более 6 лет':
                return ExperienceCodes::EXPERIENCE_FIVE_YEARS;
        }

        return ExperienceCodes::EXPERIENCE_UNKNOWN;
    }
}