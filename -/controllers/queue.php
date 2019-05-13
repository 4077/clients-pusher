<?php namespace clients\pusher\controllers;

class Queue extends \Controller
{
    public $channel;

    public function __create()
    {
        $this->channel = $this->_instance('default');

        $this->initFiles();
    }

    public function handle()
    {
        $d = $this->d('|', [
            'log' => false
        ]);

        $input = [
            'sleep_ms' => $this->data('sleep_ms') ?? 100,
            'ttl'      => $this->data('ttl') ?? 10,
            'log'      => $d['log']
        ];

        $process = $this->proc(':loop|')->pathLock($this->channel)->run($input);

        if ($process) {
            $this->d(':pid|', $process->getPid(), RR);

            return [
                'time' => dt(),
                'pid'  => $process->getPid()
            ];
        }
    }

    public function loop()
    {
        $process = process();

        $process->output('nothing happened');

        $pusher = pusher($this->channel);

        $queueFileMTime = filemtime($this->queueFilePath);

        $totalIterations = 0;
        $totalJobsCount = 0;
        $expiresCount = 0;

        $processInput = $process->input();

        while (true) {
            if (true === $process->handleIteration($processInput['sleep_ms'])) {
                break;
            }

            clearstatcache(true, $this->queueFilePath);

            if ($queueFileMTime != filemtime($this->queueFilePath)) {
                $queueFileMTime = filemtime($this->queueFilePath);

                $processInput = $process->input();

                $ttl = $processInput['ttl'];

                $jobs = file($this->queueFilePath);

                if ($jobsCount = count($jobs)) {
                    foreach ($jobs as $job) {
                        $jobData = _j($job);

                        list($time, $tab, $self, $event, $data) = $jobData;

                        $expired = false;
                        $response = '';

                        $tte = $time + $ttl - time();

                        if ($tte >= 0) {
                            $response = $pusher->sendTriggerRequest($tab, $self, $event, $data);
                        } else {
                            $expired = true;
                            $expiresCount++;
                        }

                        if ($processInput['log']) {
                            $this->log('[' . $this->channel . '] tab: ' . $tab . ($self ? ', session: ' . $self : '') . ', ttl: ' . $ttl . ', tte: ' . $tte);
                            $this->log(($expired ? 'EXPIRED ' : '>>> ') . $event . ' ' . j_($data));

                            if (!$expired) {
                                $this->log('<<< ' . j_($response));
                            }

                            $this->log();
                        }
                    }

                    $totalJobsCount += $jobsCount;

                    write($this->queueFilePath, '');

                    $totalIterations++;

                    $process->output([
                                         'iterations'    => $totalIterations,
                                         'jobs count'    => $totalJobsCount,
                                         'expires count' => $expiresCount
                                     ]);
                }
            }
        }
    }

    private $queueFile;

    private $queueFilePath;

    private function initFiles()
    {
        $this->queueFilePath = $this->_protected($this->channel . '.queue');

        if (!file_exists($this->queueFilePath)) {
            write($this->queueFilePath);
        }

        $this->queueFile = fopen($this->queueFilePath, 'r');
    }

    private function openInstanceProcess()
    {
        $pid = $this->d(':pid|');

        return $this->app->processDispatcher->open($pid);
    }

    public function pause()
    {
        if ($process = $this->openInstanceProcess()) {
            $process->pause();
        } else {
            return 'not running';
        }
    }

    public function resume()
    {
        if ($process = $this->openInstanceProcess()) {
            $process->resume();
        } else {
            return 'not running';
        }
    }

    public function togglePause()
    {
        if ($process = $this->openInstanceProcess()) {
            $paused = $process->togglePause();

            return $paused ? 'paused' : 'resumed';
        } else {
            return 'not running';
        }
    }

    public function stop()
    {
        if ($process = $this->openInstanceProcess()) {
            $process->break();

            return [
                'time' => dt()
            ];
        } else {
            return 'not running';
        }
    }

    public function toggleLog()
    {
        if ($process = $this->openInstanceProcess()) {
            $log = &$this->d(':log|');

            invert($log);

            $this->openInstanceProcess()->ra(['log' => $log]);

            return 'log ' . ($log ? 'enabled' : 'disabled');
        } else {
            return 'not running';
        }
    }

    public function getInfo()
    {
        if ($process = $this->openInstanceProcess()) {
            return $process->output();
        } else {
            return 'not running';
        }
    }

    public function add($jobData)
    {
        $job = [time(), $jobData['tab'], $jobData['self'], $jobData['event'], $jobData['data']];

        $queueFile = fopen($this->queueFilePath, 'a+');

        fwrite($queueFile, j_($job) . PHP_EOL);
        fclose($queueFile);
    }
}

