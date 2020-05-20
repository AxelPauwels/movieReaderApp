<?php

declare(strict_types=1);

namespace MovieReaderApp;

use Cassandra\Date;

class Movie
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
     * @var string
     */
    public $duur = "0:0";

    /**
     * @var float
     */
    public $grootte = 0.0;

    /**
     * @var Date
     */
    public $toegevoegd;

    /**
     * @var int
     */
    public $download = 0;

    /**
     * @var string
     */
    public $imdb = "https://";

    /**
     * @var int
     */
    public $aantalDownloads = 0;

    /**
     * @var int
     */
    public $aantalRequests = 0;


    // extra info

    /**
     * @var string
     */
    public $fileFormat = "";

    /**
     * @var string
     */
    public $mimeType = "";

    /**
     * @var string
     */
    public $encoding = "";

    /**
     * @var string
     */
    public $bitrate = "";

    /**
     * @var string
     */
    public $videoDataformat = "";

    /**
     * @var string
     */
    public $videoResolution = "";

    /**
     * @var float
     */
    public $videoPixelAspectRatio = 0.0;

    /**
     * @var float
     */
    public $videoFrameRate = 0.0;

    /**
     * @var string
     */
    public $audioCodec = "";

    /**
     * @var float
     */
    public $audioSampleRate = 0.0;

    /**
     * @var int
     */
    public $audioBitsPerSample = 0;

    /**
     * @var string
     */
    public $audioChannelmode = "";

    /**
     * @var int
     */
    public $audioChannels = 0;

    /**
     * Movie constructor.
     */
    public function __construct()
    {
        // set default values
        $this->jaar = date('Y', time());
        $this->toegevoegd = date('Y-m-d', time());
    }


//SQL STATEMENTS -> TODO FOR EPISODES
//
//ALTER TABLE episodes
//ADD fileFormat varchar(15),
//ADD mimeType varchar(25),
//ADD encoding varchar(15),
//ADD bitrate varchar(25),
//ADD videoDataformat varchar(25),
//ADD videoResolution varchar(15),
//ADD videoPixelAspectRatio decimal(5,2),
//ADD videoFrameRate decimal(5,2),
//ADD audioCodec varchar(35),
//ADD audioSampleRate decimal(10,2),
//ADD audioBitsPerSample int(25),
//ADD audioChannelmode varchar(15),
//ADD audioChannels int(5);

//ALTER TABLE episodes
//MODIFY duur varchar(10);

//UPDATE films SET duur = REPLACE(duur, '.', ':');

}