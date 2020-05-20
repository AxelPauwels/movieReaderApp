<?php

declare(strict_types=1);

namespace MovieReaderApp;

class CustomCli
{
    // colors
    const COLOR_RED = "\e[38;5;196m";
    const COLOR_GREEN = "\e[38;5;46m";
    const COLOR_BLUE = "\e[38;5;39m";
    const COLOR_WHITE = "\e[38;5;231m";
    const COLOR_GRAY25 = "\e[38;5;250m";
    const COLOR_GRAY50 = "\e[38;5;244m";
    const COLOR_GRAY75 = "\e[38;5;238m";
    const COLOR_ORANGE = "\e[38;5;214m";
    const COLOR_PINK = "\e[38;5;201m";
    const COLOR_RESET = "\e[0m";

    // icons (emoji)
    // See all: https://apps.timwhitlock.info/emoji/tables/unicode
    // update these unicodes  from "U+26AA" to "\u{26AA}
    const ICON_CHECK = " \e[38;5;46m\u{2714}\e[0m ";
    const ICON_EXCLAMATION = "\u{2757} ";
    const ICON_TRIANGLE = " \u{25B6} ";
    const ICON_WRENCH = "\u{1F527} ";
    const ICON_FILE = "\u{1F4C4} ";
    const ICON_DISC = "\u{1F4C0} ";
    const ICON_DIRECTORY = "\u{1F4C1} ";

    /**
     * ReadMovies constructor.
     */
    public function __construct()
    {
    }

    /**
     * Create a new line
     * @param int $numberOfLines
     */
    public function newLine(int $numberOfLines = 1): void
    {
        for ($i = 0; $i < $numberOfLines; $i++) {
            echo PHP_EOL;
        }
    }

    /**
     * @param string $message
     * @param string $icon
     */
    public function message(string $message, string $icon = ""): void
    {
        echo $icon . $message . PHP_EOL;
    }

    // COLOR FUNCTIONS
    // ---------------

    /**
     * @param string $text
     * @return string
     */
    public function colorRed(string $text): string
    {
        return $this::COLOR_RED . $text . $this::COLOR_RESET;
    }

    /**
     * @param string $text
     * @return string
     */
    public function colorGreen(string $text): string
    {
        return $this::COLOR_GREEN . $text . $this::COLOR_RESET;
    }

    /**
     * @param string $text
     * @return string
     */
    public function colorBlue(string $text): string
    {
        return $this::COLOR_BLUE . $text . $this::COLOR_RESET;
    }

    /**
     * @param string $text
     * @return string
     */
    public function colorWhite(string $text): string
    {
        return $this::COLOR_WHITE . $text . $this::COLOR_RESET;
    }

    /**
     * @param string $text
     * @return string
     */
    public function colorGray25(string $text): string
    {
        return $this::COLOR_GRAY25 . $text . $this::COLOR_RESET;
    }

    /**
     * @param string $text
     * @return string
     */
    public function colorGray50(string $text): string
    {
        return $this::COLOR_GRAY50 . $text . $this::COLOR_RESET;
    }

    /**
     * @param string $text
     * @return string
     */
    public function colorGray75(string $text): string
    {
        return $this::COLOR_GRAY75 . $text . $this::COLOR_RESET;
    }

    /**
     * @param string $text
     * @return string
     */
    public function colorOrange(string $text): string
    {
        return $this::COLOR_ORANGE . $text . $this::COLOR_RESET;
    }

    /**
     * @param string $text
     * @return string
     */
    public function colorPink(string $text): string
    {
        return $this::COLOR_PINK . $text . $this::COLOR_RESET;
    }
}

?>
