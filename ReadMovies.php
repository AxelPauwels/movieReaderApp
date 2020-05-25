<?php

declare(strict_types=1);

namespace MovieReaderApp;

define('__ROOT__', dirname(__FILE__));
define('PHP_TAB', "\t");

require_once(__ROOT__ . '/getID3-master/getid3/getid3.php');
require_once(__ROOT__ . '/CustomCli.php');
require_once(__ROOT__ . '/SshTunneler.php');
require_once(__ROOT__ . '/Credentials.php');
require_once(__ROOT__ . '/Movie.php');
require_once(__ROOT__ . '/Episode.php');
require_once(__ROOT__ . '/EpisodeSeason.php');

use getID3;
use mysqli;

ini_set('memory_limit', '2G'); // increase memory for this php-process. (needed for get3ID process)

class ReadMovies
{
    // X_RAPIDAPI credentials
    const X_RAPIDAPI_HOST = Credentials::X_RAPIDAPI_HOST;
    const X_RAPIDAPI_KEY = Credentials::X_RAPIDAPI_KEY;

    const MEDIA_TYPE_MOVIE = 'movie';
    const MEDIA_TYPE_EPISODE = 'episode';
    const MOVIE_TYPE_MOVIE = 'movie';
    const MOVIE_TYPE_COMEDY = 'comedy';
    const MOVIE_TYPE_DOCUMENTARY = 'documentary';
    const DB_TABLE_MOVIE = "films";
    const DB_TABLE_DOCUMENTARY = "documentary";
    const DB_TABLE_COMEDY = "comedy";
    const DB_TABLE_EPISODES = "episodes";
    const DB_TABLE_EPISODES_SEASON = "episodesSeizoen";
    const DB_TABLE_DOCUMENTARY_SEASON = "documentarySeizoen";

    // Update these credentials in (Credentials.php) if TARGET database should be CHANGED
    // ----------------------------------------------------------------------------------------------------------------
    // Note: Before this works: 1. run vagrant up | 2. Add sshKey to ~/.ssh/config | 3. make a ssh tunnel (here in PHP)
    const DB_WEBSITE_TARGET = Credentials::DB_WEBSITE_TARGET;
    const DB_SERVERNAME = Credentials::DB_SERVERNAME;
    const DB_USERNAME = Credentials::DB_USERNAME;
    const DB_PASSWORD = Credentials::DB_PASSWORD;
    const DB_NAME = Credentials::DB_NAME;
    const DB_PORT = Credentials::DB_PORT;
    const DB_SSH_TUNNEL_REQUIRED = Credentials::DB_SSH_TUNNEL_REQUIRED;

    /**
     * @var CustomCli
     */
    private $cli;

    /**
     * @var getID3
     */
    private $getID3;

    /**
     * @var mysqli
     */
    private $dbConn;

    /**
     * @var SshTunneler
     */
    private $sshTunneler;

    /**
     * @var bool
     */
    private $isProcessFiles = false; // make objects or not

    /**
     * @var bool
     *
     */
    private $isProcessSubDirectories = false; // make objects or not

    /**
     * @var bool
     */
    private $isConfirmedFiles = false; // insert objects into the DB or not

    /**
     * @var bool
     */
    private $isConfirmedSubDirectories = false; // insert objects into the DB or not

    // settings that will be set by the user when the program starts running

    /**
     * @var string
     */
    private $configPathToDir = "";

    /**
     * @var string
     */
    private $configMovieOrEpisode = ""; // Note: should not be 'episode' for documentary(Seasons)

    /**
     * @var
     */
    private $configMovieType; // this will be ignored if CONFIG_MOVIE_OR_EPISODE is 'episode'

    /**
     * ReadMovies constructor.
     *
     * @param CustomCli $cli
     * @param getID3 $getID3
     * @param SshTunneler $sshTunneler
     */
    public function __construct(CustomCli $cli, getID3 $getID3, SshTunneler $sshTunneler)
    {
        $this->cli = $cli;
        $this->getID3 = $getID3;
        $this->sshTunneler = $sshTunneler;
    }

    //
    // private functions
    //
    /**
     * Ask the user for settings
     */
    private function getSettingsFromUser():void
    {
        $this->cli->newLine();

        $this->configPathToDir = \realpath('.');
        $this->configMovieOrEpisode = self::MEDIA_TYPE_MOVIE;
        $this->configMovieType = self::MOVIE_TYPE_MOVIE;

        $userInput = \readline($this->cli->colorOrange("Choose the type you want to process: movie, comedy, documentary or episodes ? (m,c,d,e) "));

        switch (\strtolower($userInput)) {
            case 'c':
                $this->configMovieType = self::MOVIE_TYPE_COMEDY;
                break;
            case 'd':
                $this->configMovieType = self::MOVIE_TYPE_DOCUMENTARY;
                break;
            case 'e':
                // when media_type is episode, movie_type will be ignored
                $this->configMovieOrEpisode = self::MEDIA_TYPE_EPISODE;
                break;
        }

        $this->cli->newLine();
    }

    /**
     * Ask the user a 1st confirmation: "Are these settings correct?"
     * Note: These settings are the 3 constants that should be updated before to run this script
     *
     * @return bool
     */
    private function askConfirmationSettings(): bool
    {
        $this->cli->message("Loading settings...", $this->cli::ICON_CHECK);
        $this->cli->newLine();

        $this->cli->message($this->cli->colorGray25("Found these settings:"));

        $this->cli->message(
            "Website target: " . PHP_TAB . PHP_TAB . PHP_TAB . PHP_TAB .
            $this->cli->colorWhite(self::DB_WEBSITE_TARGET),
            $this->cli::ICON_TRIANGLE
        );
        $this->cli->message(
            "SSH Tunnel required: " . PHP_TAB . PHP_TAB . PHP_TAB .
            $this->cli->colorWhite((self::DB_SSH_TUNNEL_REQUIRED) ? "true" : "false") .
            " (Should be 'true' for [Vagrant] or [RaspberryPi])",
            $this->cli::ICON_TRIANGLE
        );
        $this->cli->message(
            "Local directory to read: " . PHP_TAB . PHP_TAB . PHP_TAB .
            $this->cli->colorWhite($this->configPathToDir),
            $this->cli::ICON_TRIANGLE
        );
        $this->cli->message(
            "Media-type (movie | episode): " . PHP_TAB . PHP_TAB .
            $this->cli->colorWhite($this->configMovieOrEpisode),
            $this->cli::ICON_TRIANGLE
        );
        if ($this->configMovieOrEpisode !== self::MEDIA_TYPE_EPISODE) {
            $this->cli->message(
                "Movie-type (movie | comedy | documentary): " . PHP_TAB .
                $this->cli->colorWhite($this->configMovieType),
                $this->cli::ICON_TRIANGLE
            );
        }

        $userInput = \readline($this->cli->colorOrange("Are these settings correct? (y/n) "));
        $confirmation = \strtolower($userInput);
        $this->cli->newLine();

        if ($confirmation === "y" || $confirmation === "yes") {
            return true;
        }

        $this->cli->message("Stopping script...", $this->cli::ICON_CHECK);

        return false;
    }

    /**
     * Generic question for a user
     *
     * @param string $confirmationType allowed types: file | directory | object
     * @param int $typeCount
     * @return bool
     */
    private function askConfirmation(string $confirmationType, int $typeCount): bool
    {
        $message = "Process this $confirmationType? (y/n/quit) ";

        //correct directory -> directories
        if ("directory" === $confirmationType) {
            $confirmationType = "directorie";
        }

        if ($typeCount > 1) {
            $message = "Process these " . $confirmationType . "s? (y/n/quit) ";
        }

        $userInput = \readline($this->cli->colorOrange($message));
        $confirmation = \strtolower($userInput);
        $this->cli->newLine();

        if ($confirmation === "quit") {
            die($this->cli::ICON_CHECK . "Quit by user" . PHP_EOL);
        }

        if ($confirmation === "y" || $confirmation === "yes") {
            return true;
        }

        return false;
    }

    /**
     * Ask the user a 2nd (A) confirmation: "Process these files?"
     *
     * @param int $fileCount
     * @return bool
     */
    private function askConfirmationRawFileNames(int $fileCount): bool
    {
        return $this->askConfirmation("file", $fileCount);
    }

    /**
     * Ask the user a 2nd (B) confirmation: "Process these directories?"
     *
     * @param int $directoryCount
     * @return bool
     */
    private function askConfirmationSubDirectories(int $directoryCount): bool
    {
        return $this->askConfirmation("directory", $directoryCount);
    }

    /**
     * Ask the user a 3th confirmation. "Are these objects correct?"
     *
     * @param array $movies
     * @return bool
     */
    private function askConfirmationFileNames(array $movies): bool
    {
        return $this->askConfirmation("object", \count($movies));
    }

    /**
     * Get basic info: Movie (movie | comedy | documentary (not DocumentarySeasons)
     *
     * @param Movie $movie
     * @param string $rawFileName
     * @return Movie
     */
    private function getMovieBasicInfo($movie, string $rawFileName): Movie
    {
        // strip the extension ".mp4" from the filename (and remove a possible space at the end)
        $withoutExtension = \rtrim(\substr($rawFileName, 0, -4));

        // TYPE
        // get the type "HD" or "DVD". If this is not present, "DVD" will be used as default.
        // *note: check if the type should be '3D' later... (after the language is processed)
        $rawType = \substr($withoutExtension, \strlen($withoutExtension) - 2);
        $withoutExtensionType = $withoutExtension;
        if (\strtolower($rawType) == "hd") {
            $movie->type = Movie::TYPE_HD;
            // strip the type "HD" from the filename (and remove a possible space at the end)
            $withoutExtensionType = \rtrim(\substr($withoutExtensionType, 0, \strlen($withoutExtensionType) - 2));
        }

        // JAAR
        // get the year "(2020)" with brackets, and process
        $rawYearWithBrackets = \substr(
            $withoutExtensionType,
            \strlen($withoutExtensionType) - 6,
            \strlen($withoutExtensionType)
        );
        $rawYear = \substr($rawYearWithBrackets, 1, 4);
        $movie->jaar = $rawYear;

        // strip the year "(2020)" from the filename (and remove a possible space at the end)
        $withoutExtensionTypeYear = \rtrim(\substr(
            $withoutExtensionType,
            0,
            \strlen($withoutExtensionType) - 6
        ));

        // NAAM
        // Save currently this as "naam". Could be updated depending on the language
        $movie->naam = $withoutExtensionTypeYear;

        // TAAL
        // get the language "(NL)" with brackets, and process
        // This could be present if this is a non-english movie. Otherwise "ENG" will be used as default.
        // *note: This can be checked if there are still brackets in the filename.
        if (\strpos($withoutExtensionTypeYear, ')')) {
            $rawLanguageWithBrackets = \substr(
                $withoutExtensionTypeYear,
                \strlen($withoutExtensionTypeYear) - 4,
                \strlen($withoutExtensionTypeYear)
            );

            $rawLanguage = \substr($rawLanguageWithBrackets, 1, 2);
            $movie->taal = $rawLanguage;

            $rawNameWithoutLanguage = \rtrim(\substr(
                $withoutExtensionTypeYear,
                0,
                \strlen($withoutExtensionTypeYear) - 4
            ));

            // append the language without brackets to the name
            $movie->naam = $rawNameWithoutLanguage . " " . $rawLanguage;
        }

        // TYPE (update)
        // check if the type should be updated to "3D"
        if ($this->endsWith($movie->naam, " 3D")) {
            $movie->type = Movie::TYPE_3D;
        }

        // GROOTTE
        // get filesize in human readable format
        $rawFileSize = $this->humanFilesize((string)\filesize($this->configPathToDir . "/" . $rawFileName));
        if (\substr($rawFileSize, -1) === 'G') {
            $movie->grootte = \substr($rawFileSize, 0, \strlen($rawFileSize) - 1);
        } elseif (\substr($rawFileSize, -1) === 'M') {
            $megabite = \substr($rawFileSize, 0, \strlen($rawFileSize) - 1);
            $movie->grootte = \round(($megabite / 1000), 2);
        } else {
            $this->cli->message($this->cli->colorRed("Could not calculate the filesize (not G or M)"),
                $this->cli::ICON_EXCLAMATION);
        }

        // IMDB LINK
        $movie->imdb = $this->getImdbUrl($movie->naam);

        return $movie;
    }

    /**
     * Get basic info: Episode (and DocumentaryEpisode)
     *
     * @param Episode $episode
     * @param string $rawFileName
     * @param string $path
     * @return Episode
     */
    private function getEpisodeBasicInfo(Episode $episode, string $rawFileName, string $path): Episode
    {
        // strip the extension ".mp4" from the filename (and remove a possible space at the end)
        $withoutExtension = \rtrim(\substr($rawFileName, 0, -4));
        $episode->naam = \explode(' - ', $withoutExtension)[1];

        // GROOTTE
        // get filesize in human readable format
        $rawFileSize = $this->humanFilesize((string)\filesize($path . "/" . $rawFileName));
        if (\substr($rawFileSize, -1) === 'G') {
            $episode->grootte = \substr($rawFileSize, 0, \strlen($rawFileSize) - 1);
        } elseif (\substr($rawFileSize, -1) === 'M') {
            $megabite = \substr($rawFileSize, 0, \strlen($rawFileSize) - 1);
            $episode->grootte = \round(($megabite / 1000), 2);
        } else {
            $this->cli->message($this->cli->colorRed("Could not calculate the filesize (not G or M)"));
        }

        return $episode;
    }

    /**
     * Get extra info: from a file (or a file in a directory)
     *
     * @param Movie|Episode $object
     * @param string $rawFileName
     * @param string $path
     * @return Movie|Episode
     */
    private function getExtraInfo($object, string $rawFileName, string $path)
    {
        // EXTRA INFO
        // get extra info from the videofile by using library "getID3"
        $videoFileInfo = $this->getID3->analyze($path . "/" . $rawFileName);

        $object->duur = $videoFileInfo['playtime_string'];
        $object->fileFormat = $videoFileInfo['fileformat'];
        $object->mimeType = $videoFileInfo['mime_type'];
        $object->encoding = $videoFileInfo['encoding'];
        $object->bitrate = $this->humanBitrate($videoFileInfo['bitrate']);
        $object->videoDataformat = $videoFileInfo['video']['dataformat'];
        $object->videoResolution = $videoFileInfo['video']['resolution_x'] . "x" . $videoFileInfo['video']['resolution_y'];
        $object->videoPixelAspectRatio = $videoFileInfo['video']['pixel_aspect_ratio'];
        $object->videoFrameRate = $videoFileInfo['video']['frame_rate'];

        if ($videoFileInfo['audio']['streams']) {
            $object->audioCodec = $videoFileInfo['audio']['streams'][0]['codec'];
            $object->audioSampleRate = $videoFileInfo['audio']['streams'][0]['sample_rate'];
            $object->audioBitsPerSample = $videoFileInfo['audio']['streams'][0]['bits_per_sample'];
            $object->audioChannelmode = $videoFileInfo['audio']['streams'][0]['channel_mode'];
            $object->audioChannels = $videoFileInfo['audio']['streams'][0]['channels'];
        }
        return $object;
    }

    /**
     * Create a Movie object (for each rawFileName)
     *
     * @param array $rawFileNames
     * @return array contains Movie objects
     */
    private function createMoviesFromRawFileNames(array $rawFileNames): array
    {
        echo $this->cli::ICON_CHECK . "Creating objects ";
        $movies = [];

        foreach ($rawFileNames as $rawFileName) {
            $movie = new Movie();
            $movie = $this->getMovieBasicInfo($movie, $rawFileName);
            $movie = $this->getExtraInfo($movie, $rawFileName, $this->configPathToDir);

            echo ". ";
            \array_push($movies, $movie);
        }

        $this->showAtLeast3dots(\count($rawFileNames));
        echo PHP_EOL; // end "progress points"

        \sort($movies);

        $this->cli->newLine();
        $this->showCreationMessage($movies);

        return $movies;
    }

    /**
     * Create a Episode object (for each rawFileName)
     *
     * @param array $rawFileNames
     * @param string $path
     * @return array contains Episode objects
     */
    private function createEpisodesFromRawFileNames(array $rawFileNames, string $path): array
    {
        echo $this->cli::ICON_CHECK . "Creating objects ";
        $episodes = [];

        foreach ($rawFileNames as $rawFileName) {
            $episode = new Episode();
            $episode = $this->getEpisodeBasicInfo($episode, $rawFileName, $path);
            $episode = $this->getExtraInfo($episode, $rawFileName, $path);

            echo ". ";
            \array_push($episodes, $episode);
        }

        $this->showAtLeast3dots(\count($rawFileNames));
        echo PHP_EOL; // end "progress points"

        $this->cli->newLine();
        $this->showCreationMessage($episodes);

        return $episodes;
    }

    /**
     * todo: refactor substrings to array explode/implode/pop
     *
     * Create a EpisodeSeason object
     *
     * @param string $path
     * @return EpisodeSeason
     */
    private function createEpisodeSeasonFromSubDirectoryPath(string $path): EpisodeSeason
    {
        $rawName = \str_replace($this->configPathToDir . "/", '', $path);
        $episodeSeason = new EpisodeSeason();

        // TYPE
        // get the type "HD" or "DVD". If this is not present, "DVD" will be used as default.
        // *note: check if the type should be '3D' later... (after the language is processed)
        $rawType = \substr($rawName, \strlen($rawName) - 2);
        if (\strtolower($rawType) == "hd") {
            $episodeSeason->type = Movie::TYPE_HD;
            // strip the type "HD" from the filename (and remove a possible space at the end)
            $rawName = \rtrim(\substr($rawName, 0, \strlen($rawName) - 2));
        }

        // AANTAL EPISODES
        $nameParts = \explode(" ", $rawName);
        $rawEpisodeCount = $nameParts[\count($nameParts) - 2]; // get second last element (the episode count)
        $episodeSeason->aantalEpisodes = $rawEpisodeCount;

        \array_pop($nameParts); // remove the string "EPISODES"
        \array_pop($nameParts); // remove the number
        $rawName = \implode(" ", $nameParts);

        // JAAR
        // get the year "2020"
        $rawYear = \substr(
            $rawName,
            \strlen($rawName) - 5,
            4
        );
        $episodeSeason->jaar = $rawYear;

        // (strip)
        // strip the year "(2020)" from the filename (and remove a possible space at the end)
        $rawName = \str_replace(" ($episodeSeason->jaar)", "", $rawName);

        // NAAM
        // Save currently this as "naam". Could be updated depending on the language
        $episodeSeason->naam = \rtrim($rawName);

        // TAAL
        // get the language "(NL)" with brackets, and process
        // This could be present if this is a non-english movie. Otherwise "ENG" will be used as default.
        // *note: This can be checked if there are still brackets in the filename.
        if (\strpos($episodeSeason->naam, ')')) {
            $rawLanguage = \substr(
                $episodeSeason->naam,
                \strlen($episodeSeason->naam) - 3,
                2
            );

            $episodeSeason->taal = $rawLanguage;
            $episodeSeason->naam = \str_replace(" ($rawLanguage)", "", $episodeSeason->naam);
        }

        // TYPE (update)
        // check if the type should be updated to "3D"
        if ($this->endsWith($episodeSeason->naam, " 3D")) {
            $episodeSeason->type = Movie::TYPE_3D;
        }

        // COLLECTIE
        $nameParts = \explode(" ", $rawName);
        if ($episodeSeason->taal !== Movie::LANGUAGE_DEFAULT) {
            \array_pop($nameParts); // remove last item (NL) from array
        }
        if ($this->endsWith($episodeSeason->naam, " 3D")) {
            \array_pop($nameParts); // remove last item (3D) from array
        }

        // save the seasonNumber for imdb
        $seasonNumber = \intval($nameParts[\count($nameParts) - 1]);// get last element (the episode number)

        \array_pop($nameParts); // remove last item (season number) from array
        $episodeSeason->collectie = \implode(" ", $nameParts);

        // IMDB
        $episodeSeason->imdb = $this->getImdbUrl($episodeSeason->naam) . "episodes?season=$seasonNumber";

        return $episodeSeason;
    }

    /**
     * Check if a string starts with specific characters
     *
     * @param $haystack
     * @param $needle
     * @return bool
     */
    private function startsWith($haystack, $needle): bool
    {
        $length = \strlen($needle);

        return (\substr($haystack, 0, $length) === $needle);
    }

    /**
     * Check if a string ends on specific characters
     *
     * @param $haystack
     * @param $needle
     * @return bool
     */
    private function endsWith($haystack, $needle): bool
    {
        $length = \strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (\substr($haystack, -$length) === $needle);
    }

    /**
     * Convert filesize-bytes to human a readable format
     *
     * @param $bytes
     * @param int $decimals
     * @return string
     */
    private function humanFilesize($bytes, $decimals = 2): string
    {
        $sz = 'BKMGTP';
        $factor = \floor((\strlen($bytes) - 1) / 3);

        return \sprintf("%.{$decimals}f", $bytes / \pow(1024, $factor)) . @$sz[$factor];
    }

    /**
     * Convert bitrate-bytes to human a readable format
     *
     * @param $fileSizeInBytes
     * @return string
     */
    private function humanBitrate($fileSizeInBytes): string
    {
        $i = -1;
        $byteUnits = [' kbps', ' Mbps', ' Gbps', ' Tbps', 'Pbps', 'Ebps', 'Zbps', 'Ybps'];

        do {
            $fileSizeInBytes = $fileSizeInBytes / 1024;
            $i++;
        } while ($fileSizeInBytes > 1024);

        return \round(\max($fileSizeInBytes, 0.1), 2) . $byteUnits[$i];
    }

    /**
     * Get the imdb url for a movie
     *
     * @param string $title the title you want to search for
     * @return string
     */
    private function getImdbUrl(string $title): string
    {
        $imdbBaseUrl = "https://www.imdb.com";
        $imdbInfo = $this->getImdbInfo($title);

        if (!empty($imdbInfo)) {
            return $imdbBaseUrl . $imdbInfo['results'][0]['id'];
        }

        return $imdbBaseUrl;
    }

    /**
     * See Api docs: https://rapidapi.com/apidojo/api/imdb8/endpoints
     *
     * @param string $title
     * @return array
     */
    private function getImdbInfo(string $title): array
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://imdb8.p.rapidapi.com/title/find?q=" . \urlencode($title),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                self::X_RAPIDAPI_HOST,
                self::X_RAPIDAPI_KEY
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            return json_decode($response, true);
        }

        return [];
    }

    /**
     * Check if the raw file is a correct file to process.
     *
     * A correct file is a file that
     * - is visible (not hidden)
     * - doesn't start with an underscore
     * - has an 'mp4' extension
     *
     * @param $file
     * @return bool
     */
    private function isCorrectFile($file): bool
    {
        if (!$this->startsWith($file, ".") && !$this->startsWith($file, "_")) {
            if ($this->endsWith($file, ".mp4")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if it's an Episode + EpisodeSeason or a Documentary + DocumentarySeason
     *
     * @return bool
     */
    private function isEpisodeOrDocumentary(): bool
    {
        return ($this->configMovieOrEpisode === self::MEDIA_TYPE_EPISODE ||
            ($this->configMovieOrEpisode === self::MEDIA_TYPE_MOVIE &&
                $this->configMovieType === self::MOVIE_TYPE_DOCUMENTARY)
        );
    }

    /**
     * Check if it's a DocumentarySeason
     *
     * @return bool
     */
    private function isDocumentarySeason(): bool
    {
        return ($this->configMovieOrEpisode !== self::MEDIA_TYPE_EPISODE &&
            $this->configMovieType === self::MOVIE_TYPE_DOCUMENTARY);
    }

    /**
     * Read files in directory. Only return correct files, return the actual filenames as is.
     * (correct files: files that are visible, without underscore, with extension 'mp4')
     *
     * @param string $path
     * @return array structured like: [fileNames -> array(), fileCount => #]
     */
    private function readFilesFromDirectory(string $path): array
    {
        $data = [];
        $messages = [];

        if ("" !== $path) {
            $data['fileNames'] = [];
            $data['fileCount'] = 0;

            if (\is_dir($path)) {
                if ($dh = \opendir($path)) {
                    while (($file = \readdir($dh)) !== false) {
                        if ($this->isCorrectFile($file)) {
                            \array_push($data['fileNames'], $file);
                            \array_push($messages, $file);
                        }
                    }

                    $data['fileCount'] = \count($data['fileNames']);
                    \closedir($dh);
                }
            }

            // sort array alfabetically (default it will be sorted at creation date on a machine)
            \sort($data['fileNames']);
            \sort($messages);

            $this->showMessageFoundFiles($messages, $path);
        } else {
            $this->cli->message(
                "Error: The given path ('" . $this->configPathToDir . "') is incorrect.",
                $this->cli::ICON_EXCLAMATION
            );
        }

        return $data;
    }

    /**
     * Get the paths of sub directories
     *
     * @return array contains strings: subdirectory paths
     */
    private function getSubDirectoryPaths(): array
    {
        $subDirectoryPaths = [];

        if (\is_dir($this->configPathToDir)) {
            $subDirectoryPaths = \glob($this->configPathToDir . '/*', GLOB_ONLYDIR);
        }

        if (!empty($subDirectoryPaths)) {
            $messageTitle = "Found 1 directory:";
            if (\count($subDirectoryPaths) > 1) {
                $messageTitle = "Found " . \count($subDirectoryPaths) . " directories:";
            }
            $this->cli->message($this->cli->colorGray25($messageTitle));

            foreach ($subDirectoryPaths as $subDirectoryPath) {
                $this->cli->message(
                    $this->cli->colorBlue(\str_replace($this->configPathToDir . "/", '', $subDirectoryPath)),
                    $this->cli::ICON_DIRECTORY
                );
            }
        }
        sort($subDirectoryPaths);

        return $subDirectoryPaths;
    }

    /**
     * Make the database connection
     */
    private function makeDatabaseConnection(): void
    {
        // Create connection
        $this->dbConn = new mysqli(
            self::DB_SERVERNAME,
            self::DB_USERNAME,
            self::DB_PASSWORD,
            self::DB_NAME,
            self::DB_PORT
        );

        // Check connection
        if ($this->dbConn->connect_error) {
            die("Connection failed: " . $this->dbConn->connect_error . PHP_EOL);
        } else {
            $this->cli->message('Created database connection', $this->cli::ICON_CHECK);
        }
    }

    /**
     * Close the database connection
     */
    private function closeDatabaseConnection(): void
    {
        if ($this->dbConn->close()) {
            $this->cli->message("Closed database connection", $this->cli::ICON_CHECK);
        } else {
            $this->cli->message(
                $this->cli->colorRed("Could not close the database connection"),
                $this->cli::ICON_EXCLAMATION
            );
        }
    }

    /**
     * Insert movies into the database
     *
     * @param array $movies this array should contain Movie objects
     * @param int $documentarySeasonId only used when movie-type is "documentary" AND it belongs to "documentarySeason"
     * @return string tableName
     */
    private function insertMovies(array $movies, int $documentarySeasonId = 0): string
    {
        //        // select data example
        //        $sql = "SELECT * FROM films WHERE id<10";
        //        $result = $conn->query($sql);
        //        if ($result->num_rows > 0) {
        //            //output data of each row
        //            while ($row = $result->fetch_assoc()) {
        //                echo $row['naam'] . PHP_EOL;
        //            }
        //        } else {
        //            echo "0 results";
        //        }

        $insertedDocumentaryEpisodeIds = [];

        // set tableName
        switch ($this->configMovieType) {
            case self::MOVIE_TYPE_COMEDY:
                $tableName = self::DB_TABLE_COMEDY;
                break;
            case self::MOVIE_TYPE_DOCUMENTARY:
                $tableName = self::DB_TABLE_DOCUMENTARY;
                break;
            default:
                $tableName = self::DB_TABLE_MOVIE;
        }

        if ("" === $tableName) {
            $this->cli->message(
                $this->cli->colorRed("Table name to insert the movies was not found! (tableName='$tableName')"),
                $this->cli::ICON_EXCLAMATION
            );
        } else {
            $stmt = $this->dbConn->prepare(
                "INSERT INTO $tableName (
                    naam,
                    jaar,
                    type,
                    taal,
                    duur,
                    grootte,
                    toegevoegd,
                    download,
                    imdb,
                    aantalDownloads,
                    aantalRequests,
                    fileFormat,
                    mimeType,
                    encoding,
                    bitrate,
                    videoDataformat,
                    videoResolution,
                    videoPixelAspectRatio,
                    videoFrameRate,
                    audioCodec,
                    audioSampleRate,
                    audioBitsPerSample,
                    audioChannelmode,
                    audioChannels
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            // prepare and bind (allowed types: i - integer | d - double | s - string | b - BLOB)
            $stmt->bind_param(
                "sisssdsisiissssssddsdisi",
                $naam,
                $jaar,
                $type,
                $taal,
                $duur,
                $grootte,
                $toegevoegd,
                $download,
                $imdb,
                $aantalDownloads,
                $aantalRequests,
                $fileFormat,
                $mimeType,
                $encoding,
                $bitrate,
                $videoDataformat,
                $videoResolution,
                $videoPixelAspectRatio,
                $videoFrameRate,
                $audioCodec,
                $audioSampleRate,
                $audioBitsPerSample,
                $audioChannelmode,
                $audioChannels
            );

            foreach ($movies as $movie) {
                $naam = $movie->naam;
                $jaar = $movie->jaar;
                $type = $movie->type;
                $taal = $movie->taal;
                $duur = $movie->duur;
                $grootte = $movie->grootte;
                $toegevoegd = $movie->toegevoegd;
                $download = $movie->download;
                $imdb = $movie->imdb;
                $aantalDownloads = $movie->aantalDownloads;
                $aantalRequests = $movie->aantalRequests;
                $fileFormat = $movie->fileFormat;
                $mimeType = $movie->mimeType;
                $encoding = $movie->encoding;
                $bitrate = $movie->bitrate;
                $videoDataformat = $movie->videoDataformat;
                $videoResolution = $movie->videoResolution;
                $videoPixelAspectRatio = $movie->videoPixelAspectRatio;
                $videoFrameRate = $movie->videoFrameRate;
                $audioCodec = $movie->audioCodec;
                $audioSampleRate = $movie->audioSampleRate;
                $audioBitsPerSample = $movie->audioBitsPerSample;
                $audioChannelmode = $movie->audioChannelmode;
                $audioChannels = $movie->audioChannels;

                $stmt->execute();

                \array_push($insertedDocumentaryEpisodeIds, $this->dbConn->insert_id);
            }

            $stmt->close();

            // update seizoenIds voor documentaryEpisodes
            if ($this->isDocumentarySeason() && $documentarySeasonId > 0) {
                $stmt = $this->dbConn->prepare("UPDATE $tableName SET seizoenId=? WHERE id=?");
                $stmt->bind_param("ii", $seizoenId, $id);

                foreach ($insertedDocumentaryEpisodeIds as $insertedDocumentaryEpisodeId) {
                    $id = $insertedDocumentaryEpisodeId;
                    $seizoenId = $documentarySeasonId;

                    $stmt->execute();
                }

                $stmt->close();
            }
        }

        return $tableName;
    }

    /**
     * Insert into database: EpisodeSeason
     *
     * @param EpisodeSeason $episodeSeason
     * @param bool $isDocumentarySeason this could be set by function insertDocumentarySeason()
     * @return int the last inserted id
     */
    private function insertEpisodeSeason(EpisodeSeason $episodeSeason, bool $isDocumentarySeason = false): int
    {
        $tableName = self::DB_TABLE_EPISODES_SEASON;

        // update the table if it's DocumentarySeason
        if ($isDocumentarySeason) {
            $tableName = self::DB_TABLE_DOCUMENTARY_SEASON;
        }

        $stmt = $this->dbConn->prepare(
            "INSERT INTO $tableName (
                naam,
                jaar,
                type,
                taal,
                aantalEpisodes,
                collectie,
                toegevoegd,
                aantalDownloads,
                aantalRequests,
                imdb,
                download
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        // prepare and bind (allowed types: i - integer | d - double | s - string | b - BLOB)
        $stmt->bind_param(
            "sississiisi",
            $naam,
            $jaar,
            $type,
            $taal,
            $aantalEpisodes,
            $collectie,
            $toegevoegd,
            $aantalDownloads,
            $aantalRequests,
            $imdb,
            $download
        );

        $naam = $episodeSeason->naam;
        $jaar = $episodeSeason->jaar;
        $type = $episodeSeason->type;
        $taal = $episodeSeason->taal;
        $aantalEpisodes = $episodeSeason->aantalEpisodes;
        $collectie = $episodeSeason->collectie;
        $toegevoegd = $episodeSeason->toegevoegd;
        $aantalDownloads = $episodeSeason->aantalDownloads;
        $aantalRequests = $episodeSeason->aantalRequests;
        $imdb = $episodeSeason->imdb;
        $download = $episodeSeason->download;

        $stmt->execute();
        $stmt->close();

        return $this->dbConn->insert_id;
    }

    /**
     * Insert into database: DocumentarySeason
     *
     * @param EpisodeSeason $documentarySeason
     * @return int the last inserted id
     */
    private function insertDocumentarySeason(EpisodeSeason $documentarySeason): int
    {
        return $this->insertEpisodeSeason($documentarySeason, true);
    }

    /**
     * Insert into database: Episodes
     *
     * @param array $episodes contains Episode objects
     * @param int $seasonId
     * @return string table name
     */
    private function insertEpisodes(array $episodes, int $seasonId): string
    {
        $tableName = self::DB_TABLE_EPISODES;

        //reverse the order to insert these episodes
        rsort($episodes);

        $stmt = $this->dbConn->prepare(
            "INSERT INTO $tableName (
                seizoenId,
                naam,
                duur,
                grootte,
                download,
                aantalDownloads,
                aantalRequests,
                downloadNaam,
                fileFormat,
                mimeType,
                encoding,
                bitrate,
                videoDataformat,
                videoResolution,
                videoPixelAspectRatio,
                videoFrameRate,
                audioCodec,
                audioSampleRate,
                audioBitsPerSample,
                audioChannelmode,
                audioChannels
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        // prepare and bind (allowed types: i - integer | d - double | s - string | b - BLOB)
        $stmt->bind_param(
            "isssdiisssssssddsdisi",
            $seizoenId,
            $naam,
            $duur,
            $grootte,
            $download,
            $aantalDownloads,
            $aantalRequests,
            $downloadNaam,
            $fileFormat,
            $mimeType,
            $encoding,
            $bitrate,
            $videoDataformat,
            $videoResolution,
            $videoPixelAspectRatio,
            $videoFrameRate,
            $audioCodec,
            $audioSampleRate,
            $audioBitsPerSample,
            $audioChannelmode,
            $audioChannels
        );

        foreach ($episodes as $episode) {
            $seizoenId = $seasonId;
            $naam = $episode->naam;
            $duur = $episode->duur;
            $grootte = $episode->grootte;
            $download = $episode->download;
            $aantalDownloads = $episode->aantalDownloads;
            $aantalRequests = $episode->aantalRequests;
            $downloadNaam = $episode->downloadNaam;
            $fileFormat = $episode->fileFormat;
            $mimeType = $episode->mimeType;
            $encoding = $episode->encoding;
            $bitrate = $episode->bitrate;
            $videoDataformat = $episode->videoDataformat;
            $videoResolution = $episode->videoResolution;
            $videoPixelAspectRatio = $episode->videoPixelAspectRatio;
            $videoFrameRate = $episode->videoFrameRate;
            $audioCodec = $episode->audioCodec;
            $audioSampleRate = $episode->audioSampleRate;
            $audioBitsPerSample = $episode->audioBitsPerSample;
            $audioChannelmode = $episode->audioChannelmode;
            $audioChannels = $episode->audioChannels;

            $stmt->execute();
        }

        $stmt->close();

        return $tableName;
    }

    /**
     * Insert into database: documentaryEpisodes (convert to Movie objects and insert those)
     *
     * @param EpisodeSeason $season
     * @param array $episodes contains Episode objects
     * @param int $seasonId
     * @return string table name
     */
    private function insertDocumentaryEpisodes(EpisodeSeason $season, array $episodes, int $seasonId): string
    {
        $movies = $this->convertEpisodeToMovieObjects($season, $episodes);

        return $this->insertMovies($movies, $seasonId);
    }

    /**
     * Convert Episode objects to Movie objects.
     *
     * This could happen when reading documentaries, there is a directory with episodes.
     * Documentaries are treated like movies, but has a table 'documentarySeason'.
     * First read these episodes like normal episodes to get the correct information.
     * Finally make Movie objects to store these episodes in 'documentary' with a FK to documentarySeason
     *
     * @param EpisodeSeason $season
     * @param array $episodes contains Episode objects
     * @return array contains Movie objects
     */
    private function convertEpisodeToMovieObjects(EpisodeSeason $season, array $episodes): array
    {
        $movies = [];

        /** @var Episode $episode */
        foreach ($episodes as $episode) {
            $movie = new movie();

            $movie->naam = $episode->naam;
            $movie->jaar = $season->jaar;               // not used in frontend, will use data from season-table
            $movie->type = $season->type;               // not used in frontend, will use data from season-table
            $movie->taal = $season->taal;               // not used in frontend, will use data from season-table
            $movie->duur = $episode->duur;
            $movie->grootte = $episode->grootte;
            $movie->toegevoegd = $season->toegevoegd;   // not used in frontend, will use data from season-table
            $movie->download = $episode->download;
            $movie->imdb = $season->imdb;               // not used in frontend, will use data from season-table
            $movie->aantalDownloads = $episode->aantalRequests;
            $movie->aantalRequests = $episode->aantalRequests;

            // extra info
            $movie->fileFormat = $episode->fileFormat;
            $movie->mimeType = $episode->mimeType;
            $movie->encoding = $episode->encoding;
            $movie->bitrate = $episode->bitrate;
            $movie->videoDataformat = $episode->videoDataformat;
            $movie->videoResolution = $episode->videoResolution;
            $movie->videoPixelAspectRatio = $episode->videoPixelAspectRatio;
            $movie->videoFrameRate = $episode->videoFrameRate;
            $movie->audioCodec = $episode->audioCodec;
            $movie->audioSampleRate = $episode->audioSampleRate;
            $movie->audioBitsPerSample = $episode->audioBitsPerSample;
            $movie->audioChannelmode = $episode->audioChannelmode;
            $movie->audioChannels = $episode->audioChannels;

            \array_push($movies, $movie);
        }

        return $movies;
    }

    /**
     * Process files: movie, comedy, documentary (not documentary-episodes)
     *
     * @param array $movies contains Movie objects
     */
    private function processFiles(array $movies): void
    {
        /** @var Movie $movies */
        if (!empty($movies)) {
            $this->cli->message("Processing files", $this->cli::ICON_CHECK);

            $tableName = $this->insertMovies($movies);
            $this->cli->message(
                "Inserted " . $this->cli->colorPink("files") . " into table \"$tableName\"",
                $this->cli::ICON_CHECK
            );
        } else {
            $this->cli->newLine();
            $this->cli->message(
                $this->cli->colorRed("Tried to insert movies, but \$movies is empty!"),
                $this->cli::ICON_EXCLAMATION
            );
        }
    }

    /**
     * Process directories: EpisodeSeasons + Episodes or DocumentarySeason + Episodes (converted to Movies later)
     *
     * @param array $episodes contains Episode objects
     * @param array $episodeSeasons contains EpisodeSeason objects
     */
    private function processSubDirectories(array $episodes, array $episodeSeasons): void
    {
        $tableName = "";

        if (!empty($episodes)) {
            $this->cli->message("Processing directories", $this->cli::ICON_CHECK);

            /** @var EpisodeSeason $episodeSeason */
            foreach ($episodeSeasons as $key => $episodeSeason) {
                if (!$this->isDocumentarySeason()) {
                    $insertedSeasonId = $this->insertEpisodeSeason($episodeSeason);
                    $tableName = $this->insertEpisodes($episodes[$key], $insertedSeasonId);
                } else {
                    $insertedSeasonId = $this->insertDocumentarySeason($episodeSeason);
                    $tableName = $this->insertDocumentaryEpisodes($episodeSeason, $episodes[$key], $insertedSeasonId);
                }
            }
            $this->cli->message(
                "Inserted " . $this->cli->colorBlue("directories") . " into table \"$tableName\"",
                $this->cli::ICON_CHECK
            );
        } else {
            $this->cli->newLine();
            $this->cli->message(
                $this->cli->colorRed("Tried to insert episodes, but \$episodes is empty!"),
                $this->cli::ICON_EXCLAMATION
            );
        }
    }

    /**
     * Show message: Searching for files/directories
     */
    private function showMessageStartScanning(): void
    {
        $directoryMessage = ($this->isEpisodeOrDocumentary()) ? $this->cli->colorBlue("directories ") . "and " : "";

        $this->cli->message(
            "Searching for " . $directoryMessage . $this->cli->colorPink("files") . " . . .",
            $this->cli::ICON_CHECK
        );
        $this->cli->newLine();
    }

    /**
     * Show message: There was nothing confirmed to process
     */
    private function showMessageNothingToProcess(): void
    {
        $this->cli->message($this->cli->colorRed("There was nothing confirmed to process"),
            $this->cli::ICON_EXCLAMATION);
        $this->cli->newLine();
    }

    /**
     * Show message: Found # files + a list of those files
     *
     * @param array $messages
     * @param string $path
     */
    private function showMessageFoundFiles(array $messages, string $path): void
    {
        $fileCount = \count($messages);

        if ($fileCount > 0) {
            $messageTitle = "Found 1 file:";

            if ($fileCount > 1) {
                $messageTitle = "Found " . $fileCount . " files:";
            }

            $this->cli->message($this->cli->colorGray25($messageTitle));

            if ($path !== $this->configPathToDir) {
                $this->cli->message(
                    $this->cli->colorBlue(\str_replace($this->configPathToDir . "/", '', $path)),
                    $this->cli::ICON_DIRECTORY
                );
            }

            foreach ($messages as $message) {
                $this->cli->message($this->cli->colorPink($message), $this->cli::ICON_DISC);
            }
        }
    }

    /**
     * Show a message:Created # objects + a list of those objects
     *
     * @param array $objects (can be Movie or Episode)
     */
    private function showCreationMessage(array $objects): void
    {
        $messageTitle = "Created 1 object:";
        if (\count($objects) > 1) {
            $messageTitle = "Created " . \count($objects) . " objects:";
        }
        $this->cli->message($this->cli->colorGray25($messageTitle));

        foreach ($objects as $object) {
            $this->cli->message(
                $this->cli->colorGreen($object->naam),
                $this->cli->colorGreen($this->cli::ICON_TRIANGLE)
            );

            if (get_class($object) === Movie::class) {
                /** @var Movie $object */
                $this->cli->message(
                    "   " .
                    "Size: " . $object->grootte . "GB  " . PHP_TAB .
                    "Duration: " . $object->duur . "u" . PHP_TAB .
                    "Language: " . $object->taal . PHP_TAB .
                    "Resolution: " . $object->videoResolution
                );

            } elseif (get_class($object) === Episode::class) {
                /** @var Episode $object */
                $this->cli->message(
                    "   " .
                    "Size: " . $object->grootte . "GB  " . PHP_TAB .
                    "Duration: " . $object->duur . "u" . PHP_TAB .
                    "Resolution: " . $object->videoResolution
                );
            }
        }
    }

    /**
     * When looping through a list, there will be an "dot-echo" to show the progress.
     * If the listCount is less than 3, show 3 dots anyway
     *
     * @param $listCount
     */
    private function showAtLeast3dots($listCount): void
    {
        $numberOfdotsToShow = 3 - $listCount;

        for ($i = 0; $i < $numberOfdotsToShow; $i++) {
            echo ". ";
        }
    }

    //
    // public functions
    //

    /**
     * Main function: start running this script.
     */
    public function run(): void
    {
        $this->cli->newLine();
        $this->cli->message("Script is running", $this->cli::ICON_CHECK);
        $movies = [];
        $episodes = [];         // size/index is always the same like '$episodeSeasons'
        $episodeSeasons = [];   // size/index is always the same like '$episodes'
        $rawFileNames = [];

        $this->getSettingsFromUser();

        if ($this->askConfirmationSettings()) {
            $this->showMessageStartScanning();

            // scan directory: for files (skip this for episodes)
            if ($this->configMovieOrEpisode !== self::MEDIA_TYPE_EPISODE) {
                $rawFileNames = $this->readFilesFromDirectory($this->configPathToDir);

                if ($rawFileNames['fileCount'] > 0) {
                    $this->isProcessFiles = $this->askConfirmationRawFileNames($rawFileNames['fileCount']);
                }
            }

            // files: get info and create Movie objects
            if ($this->isProcessFiles) {
                if ($this->isProcessFiles) {
                    if (!empty($rawFileNames)) {
                        $movies = $this->createMoviesFromRawFileNames($rawFileNames['fileNames']);
                        $this->isConfirmedFiles = $this->askConfirmationFileNames($movies);
                    }
                }
            }

            // scan directory: for subdirectories (skip this for movie-type 'movie' and 'comedy')
            if ($this->isEpisodeOrDocumentary()) {
                $subDirectoryPaths = $this->getSubDirectoryPaths();

                if (!empty($subDirectoryPaths)) {
                    $this->isProcessSubDirectories = $this->askConfirmationSubDirectories(\count($subDirectoryPaths));
                }
            }

            // subdirectories: get info and create Episode objects (could also be documentarySeason episodes)
            if ($this->isProcessSubDirectories) {
                if ($this->isProcessSubDirectories) {
                    if (!empty($subDirectoryPaths)) {
                        // read files
                        foreach ($subDirectoryPaths as $subDirectoryPath) {
                            $isConfirmedProcessEpisodes = false;
                            $directoryName = \str_replace(
                                $this->configPathToDir . "/",
                                "",
                                $subDirectoryPath
                            );

                            $rawFileNames = $this->readFilesFromDirectory($subDirectoryPath);
                            if ($rawFileNames['fileCount'] > 0) {
                                $isConfirmedProcessEpisodes = $this->askConfirmationRawFileNames(
                                    $rawFileNames['fileCount']
                                );

                                // skip 'create Episode objects' if not confirmed
                                if (!$isConfirmedProcessEpisodes) {
                                    $this->cli->message("Skipping $directoryName", $this->cli::ICON_CHECK);
                                    $this->cli->newLine();
                                    continue;
                                }
                            }

                            // create Episode objects
                            if ($isConfirmedProcessEpisodes) {
                                if (!empty($rawFileNames)) {
                                    $currentEpisodes = $this->createEpisodesFromRawFileNames(
                                        $rawFileNames['fileNames'],
                                        $subDirectoryPath
                                    );
                                }
                                $isConfirmedEpisodeObjects = $this->askConfirmationFileNames($currentEpisodes);

                                // skip 'save Episode Objects to process later' if not confirmed
                                if (!$isConfirmedEpisodeObjects) {
                                    $this->cli->message("Skipping $directoryName", $this->cli::ICON_CHECK);
                                    $this->cli->newLine();
                                    continue;
                                }

                                // save Episode Objects to process later
                                if ($isConfirmedEpisodeObjects) {
                                    \array_push($episodes, $currentEpisodes);

                                    // create seasons for it
                                    $currentEpisodeSeason = $this->createEpisodeSeasonFromSubDirectoryPath($subDirectoryPath);
                                    \array_push($episodeSeasons, $currentEpisodeSeason);

                                    // set isConfirmedSubDirectories to true if there is at least 1 file to process
                                    if (!$this->isConfirmedSubDirectories) {
                                        $this->isConfirmedSubDirectories = true;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // open connections if needed
        if ($this->isConfirmedFiles || ($this->isConfirmedSubDirectories && !empty($episodes))) {
            // open SSH tunnel if needed
            if (self::DB_SSH_TUNNEL_REQUIRED) {
                $this->sshTunneler->makeSshTunnel();
            }

            $this->makeDatabaseConnection();

            // process files
            if ($this->isConfirmedFiles) {
                $this->processFiles($movies);
            }

            // process subdirectories (episodes or documentaryEpisodes)
            if ($this->isConfirmedSubDirectories) {
                $this->processSubDirectories($episodes, $episodeSeasons);
            }

            $this->closeDatabaseConnection();

            // close SSH tunnel if it was opened
            if (self::DB_SSH_TUNNEL_REQUIRED) {
                $this->sshTunneler->closeSshTunnel();
            }
        } else {
            $this->showMessageNothingToProcess();
        }
    }
}

// instantiate and run the main function of readMovies
$readMovies = new ReadMovies(new CustomCli(), new getID3, new SshTunneler());
$readMovies->run();
