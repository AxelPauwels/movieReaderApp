<?php

declare(strict_types=1);

namespace MovieReaderApp;

class Episode
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $seizoenId;

    /**
     * @var string
     */
    public $naam = "";

    /**
     * @var string
     */
    public $duur = "0:0";

    /**
     * @var float
     */
    public $grootte = 0.0;

    /**
     * @var int
     */
    public $download = 0;

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
    public $downloadNaam = "";

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
    }

}