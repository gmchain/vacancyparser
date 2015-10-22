<?php
/**
 * Created by IntelliJ IDEA.
 * User: McHain
 * Date: 23.09.2015
 * Time: 21:32
 */

namespace mchain\vacancyparser;


class ParsedVacancy
{
    /**
     * @var string
     */
    public $id = '';

    /**
     * @var string
     */
    public $url = '';

    /**
     * @var string
     */
    public $site = '';

    /**
     * @var string
     */
    public $employer = '';

    /**
     * @var string
     */
    public $name = '';

    /**
     * @var string
     */
    public $description = '';

    /**
     * @var array
     */
    public $categories = [];

    /**
     * @var bool
     */
    public $categorized = true;

    /**
     * @var int
     */
    public $experience;

    /**
     * @var int
     */
    public $schedule;

    /**
     * @var int
     */
    public $jobType;

    /**
     * @var float
     */
    public $salaryFrom = 0;

    /**
     * @var float
     */
    public $salaryTo = 0;

    /**
     * @var string
     */
    public $salaryCurrency;

    /**
     * @var \DateTime
     */
    public $date = null;

    public function out()
    {
        $cats = implode(', ', $this->categories);

        echo <<<TXT
ID: {$this->id}
URL: {$this->url}
SITE: {$this->site}
NAME: {$this->name}
DESCRIPTION: {$this->description}
CATEGORIES: {$cats}
TXT;
    }
}