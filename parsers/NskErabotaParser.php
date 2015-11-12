<?php
/**
 * Created by IntelliJ IDEA.
 * User: McHain
 * Date: 19.10.2015
 * Time: 5:01
 */

namespace mchain\vacancyparser\parsers;


use DateTime;
use mchain\vacancyparser\codes\CurrencyCodes;
use mchain\vacancyparser\codes\ExperienceCodes;
use mchain\vacancyparser\codes\JobTypeCodes;
use mchain\vacancyparser\codes\ScheduleCodes;
use mchain\vacancyparser\ParsedVacancy;
use Sunra\PhpSimple\HtmlDomParser;

class NskErabotaParser extends AbstractVacancyParser
{

    /**
     * @var string
     */
    protected static $address = 'nsk.erabota.ru';

    /**
     * @var string
     */
    protected $name = 'Site nsk.erabota.ru';

    /**
     * @var string
     */
    protected $url = 'http://nsk.erabota.ru';

    /**
     * @var string
     */
    protected $startUrl = 'http://nsk.erabota.ru/vacancies/search/?sort=published&schedule=4';

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
        return $this->startUrl . '&page=' . $this->count . '&direstion=desc';
    }

    /**
     * @param mixed $page
     * @return array
     */
    protected function getLinks($page)
    {
        $result = [];

        /** @var \simple_html_dom $page */
        $links = $page->find('.vacancies .vacancy h2 a');

        foreach ($links as $link) {
            /** @var \simple_html_dom_node $link */
            $result[] = $this->url . $link->attr['href'];
        }

        return $result;
    }

    /**
     * @param $string
     * @return \DateTime
     */
    private function parseDate($string)
    {
        $string = str_replace(
            ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря', 'вчера', 'сегодня'],
            ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'Yesterday', 'Today'],
            $string
        );

        $string = trim($string);

        $result = \DateTime::createFromFormat('d F, H:i', $string);

        if (!$result) {
            $result = \DateTime::createFromFormat('d F Y, H:i', $string);
        }

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
        $dates = $page->find('.vacancy.found-item abbr.date');

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
        $dom = $this->getDom($url);
        if (!$dom) {
            return null;
        }

        $result = new ParsedVacancy();

        $result->url  = $url;
        $result->site = $this->url;

        /** @var \simple_html_dom_node[] $names */
        $names = $dom->find('.title-block h1');
        if ($names) {
            $result->name = trim($names[0]->text());
        }

        $description = [];
        /** @var \simple_html_dom_node[] $chapters */
        $chapters = $dom->find('.vacancy > h2, .vacancy > .description');
        foreach ($chapters as $chapter) {
            $text = trim($chapter->text());
            if ($text != 'Условия работы' && $text != 'Профессиональные требования') {
                $description[] = $text;
            }
        }

        $result->description = implode("\n", $description);

        /** @var \simple_html_dom_node[] $infoBlocks */
        $infoBlocks         = $dom->find('.vacancy .content-group dt, .vacancy .content-group dd');
        $result->experience = ExperienceCodes::EXPERIENCE_UNKNOWN;
        $result->jobType    = JobTypeCodes::JOB_TYPE_FREELANCE;

        $nextCategories = false;
        $nextTimetable  = false;
        $nextSalary     = false;

        /** @var \simple_html_dom_node[] $dates */
        $dates        = $dom->find('.title-block abbr');
        if (!$dates) {
            return null;
        }
        $result->date = $this->parseDate(trim($dates[0]->text()));

        foreach ($infoBlocks as $infoBlock) {
            if (trim($infoBlock->text()) == 'Зарплата') {
                $nextSalary = true;
                continue;
            }

            if (trim($infoBlock->text()) == 'График') {
                $nextTimetable = true;
                continue;
            }

            if (trim($infoBlock->text()) == 'Опыт работы') {
                $nextCategories = true;
            }

            if ($nextCategories) {
                /** @var \simple_html_dom_node[] $categories */
                $categories = $infoBlock->find('li');
                foreach ($categories as $category) {
                    $text = $category->text();
                    if (strpos($text, '<span') !== false) {
                        $text = substr($text, 0, strpos($text, '<span'));
                    }
                    $result->categories[] = $text;
                }
                $nextCategories = false;
                continue;
            }

            if ($nextTimetable) {
                $result->schedule = $this->getScheduleCode(trim($infoBlock->text()));
                $nextTimetable    = false;
                continue;
            }

            if ($nextSalary) {
                $salary = trim($infoBlock->text());
                $salary = str_replace(' ', '', $salary);

                if (strpos($salary, 'от') === 0) {
                    $salary = str_replace('от', '', $salary);
                    if (strpos($salary, 'до') !== false) {
                        list($salaryFrom, $salaryTo) = explode('до', $salary);
                        $result->salaryFrom = (int)$salaryFrom;
                        $result->salaryTo   = (int)$salaryTo;
                    } else {
                        $result->salaryFrom = (int)$salary;
                    }
                } else {
                    $result->salaryFrom = $result->salaryTo = (int)$salary;
                }
                $result->salaryCurrency = CurrencyCodes::CURRENCY_RUR;
                $nextSalary             = false;
                continue;
            }
        }

        $url        = trim($url, '/');
        $result->id = substr($url, strrpos($url, '/') + 1);

        /** @var \simple_html_dom_node[] $employers */
        $employers = $dom->find('.company-card strong.h3');
        if ($employers) {
            $employer         = $employers[0];
            $result->employer = trim($employer->text());
        }

        return $result;
    }

    /**
     * @param $schedule
     * @return int
     */
    private function getScheduleCode($schedule)
    {
        switch ($schedule) {
            case 'Удалённо (фриланс)':
                return ScheduleCodes::SCHEDULE_PART_TIME;
        }

        return ScheduleCodes::SCHEDULE_UNKNOWN;
    }
}