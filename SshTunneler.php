<?php

declare(strict_types=1);

namespace MovieReaderApp;

class SshTunneler
{
    // shell command to see all specific tunneling processes:
    // ps -A | grep 'ssh vagrant@movies.local -L 3307:127.0.0.1:3306 -N'

    const CONNECTION_DELAY = 6; // seconds
    const LOCAL_MACHINE = "vagrant@movies.local";

    /**
     * @var CustomCli
     */
    private $cli;

    /**
     * @var int
     */
    private $tunnelPid;

    /**
     * SshTunneler constructor.
     */
    public function __construct()
    {
        $this->cli = new CustomCli;
    }

    /**
     * Creating an SSH tunnelling background process for vagrant, raspberryPi,...
     * Note: To run in the background ( > /dev/null 2>&1 & )
     */
    public function makeSshTunnel(): void
    {
        shell_exec("ssh " . self::LOCAL_MACHINE . " -L 3307:127.0.0.1:3306 -N > /dev/null 2>&1 &");

        $this->tunnelPid = (int)shell_exec(
            "ps -A | grep '[s]sh " . self::LOCAL_MACHINE . " -L 3307:127.0.0.1:3306 -N' | awk '{print $1}'"
        );

        echo $this->cli::ICON_CHECK . "Creating SSH tunnel ";
        for ($i = 0; $i < self::CONNECTION_DELAY; $i++) {
            echo ". ";
            sleep(1);
        }
        echo PHP_EOL;
        $this->cli->message("Created SSH tunnel [PID $this->tunnelPid]", $this->cli::ICON_CHECK);
    }

    /**
     * Closing the SSH tunnelling background process
     */
    public function closeSshTunnel(): void
    {
        shell_exec("kill -9 $this->tunnelPid");
        $tunnelProcess = shell_exec("ps $this->tunnelPid | grep $this->tunnelPid");

        if ($tunnelProcess) {
            $this->cli->message(
                $this->cli->colorRed('Could not find the SSH tunnel process to stop'),
                $this->cli::ICON_EXCLAMATION
            );
        } else {
            $this->cli->message('Closed SSH tunnel', $this->cli::ICON_CHECK);
        }
    }
}

?>
