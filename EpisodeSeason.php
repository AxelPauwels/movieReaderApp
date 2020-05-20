<?php

declare(strict_types=1);

namespace MovieReaderApp;

use Cassandra\Date;

class EpisodeSeason
{
    const TYPE_DVD = "DVD";
    const TYPE_HD = "HD";
    const TYPE_3D = "3D";
    const LANGUAGE_ENG = "ENG";

    const TYPE_DEFAULT = self::TYPE_DVD;
    const LANGUAGE_DEFAULT = self::LANGUAGE_ENG;

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $naam = "";

    /**
     * @var int
     */
    public $jaar;

    /**
     * @var string
     */
    public $type = self::TYPE_DEFAULT;

    /**
     * @var string
     */
    public $taal = self::LANGUAGE_DEFAULT;

    /**
     * @var int
     */
    public $aantalEpisodes;

    /**
     * @var string
     */
    public $collectie = "";

    /**
     * @var Date
     */
    public $toegevoegd;

    /**
     * @var int
     */
    public $aantalDownloads = 0;

    /**
     * @var int
     */
    public $aantalRequests = 0;

    /**
     * @var string
     */
    public $imdb = "";

    /**
     * @var int
     */
    public $download = 0;

    /**
     * Movie constructor.
     */
    public function __construct()
    {
        // set default values
        $this->jaar = date('Y', time());
        $this->toegevoegd = date('Y-m-d', time());
    }
}