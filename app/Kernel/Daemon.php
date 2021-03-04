<?php

namespace App\Kernel;

use Exception;

class Daemon
{
    const ERR = -1;
    const OK  = 1;

    private $basePath;
    private $pidFilename;

    private $pid;
    private $childPids = [];

    private $handler;
    private $processNumber;
    private $argv;

    /**
     * Daemon constructor.
     *
     * @param string|null $path
     * @param int $processNumber
     * @throws Exception
     */
    public function __construct($path = null, $processNumber = 1)
    {
        global $argv;

        // Check environment first
        $this->checkEnvironment();

        // Initialize configs
        $this->setBasePath($path);
        $this->setProcessNumber($processNumber);
        $this->setPidFilename('daemon.pid');
        $this->argv = $argv;
    }

    /**
     * Set the base path to save pid files.
     *
     * @param $path
     * @return self
     */
    public function setBasePath($path)
    {
        $this->basePath = $path;
        return $this;
    }

    /**
     * Set pid filename
     *
     * @param $filename
     * @return self
     */
    public function setPidFilename($filename)
    {
        $this->pidFilename = $filename;
        return $this;
    }

    /**
     * Set daemon's handler
     *
     * @param $handler
     * @throws Exception
     * @return self
     */
    public function setHandler($handler)
    {
        if (! is_callable($handler)) {
            throw new Exception('Handler is not callable.');
        }

        $this->handler = $handler;
        return $this;
    }

    /**
     * Set this daemon's child processes number
     *
     * @param $number
     * @return self
     */
    public function setProcessNumber($number)
    {
        $this->processNumber = $number;
        return $this;
    }

    /**
     * Check environment is support or not
     *
     * @throws Exception
     */
    public function checkEnvironment()
    {
        if (!extension_loaded('pcntl')) {
            throw new Exception('Daemon needs support of pcntl extension');
        }

        if ('cli' != php_sapi_name()) {
            throw new Exception('Daemon only works in CLI mode.');
        }
    }

    /**
     * Get Pid file's path and name
     *
     * @return string
     */
    public function getPidFile()
    {
        return $this->basePath . DIRECTORY_SEPARATOR . $this->pidFilename;
    }

    /**
     * Run this daemon with arguments
     *
     * Usages:
     *      /bin/php demo.php start
     *      /bin/php demo.php stop
     *      /bin/php demo.php restart
     *
     * @throws Exception
     */
    public function run()
    {
        switch ($this->argv[1]) {
            case 'start':
                $this->start();
                break;
            case 'stop':
                $this->stop();
                break;
            case 'restart':
                $this->restart();
                break;
            default:
                $this->usage();
        }
    }

    /**
     * Start process
     * @throws Exception
     * @return void
     */
    private function start()
    {
        echo $this->getPidFile() . PHP_EOL;

        // Check if this process is running.
        if (is_file($this->getPidFile())) {
            $this->msg("{".$this->argv[0]."} is running (".file_get_contents($this->getPidFile()).")");
        } else {
            // Check there is a registered handler.
            if (empty($this->handler)) {
                throw new Exception("Process handler is not registered.");
            }

            // Demonize this application.
            $this->demonize();
            for ($i = 1; $i <= $this->processNumber; $i ++) {
                // Fork a new process
                $pid = pcntl_fork();

                if ($pid === -1) {
                    // Parent process fork child success.
                    $this->msg("fork() process #{$i}", self::ERR);
                } elseif ($pid) {
                    // Create new pid.
                    $this->childPids[$pid] = $i;
                } else {
                    // Child process. Go to handler.
                    $this->handle($i);
                    return;
                }
            }
        }

        // Waiting for child processes.
        while (count($this->childPids)) {
            $waipid = pcntl_waitpid(-1, $status, WNOHANG);

            unset($this->childPids[$waipid]);

            $this->checkPidFile();

            usleep(1000000);
        }

        return;
    }

    /**
     * Stop process
     */
    private function stop()
    {
        if (!is_file($this->getPidFile())) {
            $this->msg("{".$this->argv[0]."} is not running.");
        } else {
            $pid = file_get_contents($this->getPidFile());

            if (!@unlink($this->getPidFile())) {
                $this->msg("remove pid file: ".$this->getPidFile(), self::ERR);
            }

            sleep(1);

            $this->msg("stopping {".$this->argv[0]."} ({$pid})", self::OK);
        }
    }

    /**
     * Restart process
     * @throws Exception
     */
    private function restart()
    {
        $this->stop();
        sleep(1);
        $this->start();
    }

    /**
     * Print usage
     */
    private function usage()
    {
        global $argv;

        echo str_pad('', 50, '-')."\n";
        echo "usage:\n";
        echo "\t{$argv[0]} start\n";
        echo "\t{$argv[0]} stop\n";
        echo "\t{$argv[0]} restart\n";
        echo str_pad('', 50, '-')."\n";
    }

    /**
     * Check pid file exists or not.
     */
    private function checkPidFile()
    {
        clearstatcache();
        if (!is_file($this->getPidFile())) {
            foreach ($this->childPids as $pid => $pno) {
                posix_kill($pid, SIGKILL);
            }
            exit;
        }
    }

    /**
     * Demonize this application process.
     */
    private function demonize()
    {
        $pid = pcntl_fork();
        if ($pid == -1) {
            $this->msg("create main process", self::ERR);
        } elseif ($pid) {
            $this->msg("starting {".$this->argv[0]."}", self::OK);
            exit;
        } else {
            posix_setsid();
            $this->pid = posix_getpid();
            // TODO: 把一些資訊也寫入 pid cache file 內
            file_put_contents($this->getPidFile(), $this->pid);
        }
    }

    /**
     * Execute register handler with pno
     *
     * @param $pno
     * @return void
     */
    private function handle($pno)
    {
        if ($this->handler) {
            call_user_func($this->handler, $pno);
        }
        return;
    }

    /**
     * Output message to STDIN
     *
     * @param $msg
     * @param int $msgno
     */
    private function msg($msg, $msgno = 0)
    {
        if ($msgno == 0) {
            fprintf(STDIN, $msg . "\n");
        } else {
            fprintf(STDIN, $msg . " ...... ");
            if ($msgno == self::OK) {
                fprintf(STDIN, $this->colorize('success', 'green'));
            } else {
                fprintf(STDIN, $this->colorize('failed', 'red'));
                exit;
            }
            fprintf(STDIN, "\n");
        }
    }

    /**
     * Colorize message.
     *
     * @param $text
     * @param $color
     * @param bool $bold
     * @return string
     */
    private function colorize($text, $color, $bold = FALSE) {
        $colors = array_flip(array(30 => 'gray', 'red', 'green', 'yellow', 'blue', 'purple', 'cyan', 'white', 'black'));
        return "\033[" . ($bold ? '1' : '0') . ';' . $colors[$color] . "m$text\033[0m";
    }
}
