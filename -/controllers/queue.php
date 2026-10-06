<?php namespace clients\pusher\controllers;

class Queue extends \Controller
{
    public function __create()
    {
        $this->initFiles();
    }

    private $queueFilePath;

    private function initFiles()
    {
        $this->queueFilePath = $this->_protected('default.queue');

        if (!file_exists($this->queueFilePath)) {
            write($this->queueFilePath);
        }
    }

    public function handle()
    {
        $d = $this->d('|', [
            'log' => false,
        ]);

        $input = [
            'sleep_ms' => $this->data('sleep_ms') ?? 100,
            'ttl'      => $this->data('ttl') ?? 10,
            'log'      => $d['log'],
        ];

        $process = $this->proc(':loop|')->pathLock()->run($input);

        if ($process) {
            $this->d(':pid|', $process->getPid(), RR);

            return [
                'time' => dt(),
                'pid'  => $process->getPid(),
            ];
        }
    }

    private $channels = [];

    /**
     * @return \clients\pusher\Svc
     */
    private function getPusher($channel, $env)
    {
        if (!isset($this->channels[$env][$channel])) {
            $this->channels[$env][$channel] = pusher($channel, $env);

            $log = \std\log('clients/pusher/queue'); // todo del

            $log->row('create instance ' . $channel . '[' . $env . ']');
        }

        return $this->channels[$env][$channel];
    }

    public function loop()
    {
        $process = process();

        $log = \std\log('clients/pusher/queue'); // todo del

        $process->outputRR('nothing happened');

        $queueFileMTime = filemtime($this->queueFilePath);

        $totalIterations = 0;
        $totalJobsCount = 0;
        $expiresCount = 0;

        $processInput = $process->_input();

        while (true) {
            if (true === $process->handleIteration($processInput['sleep_ms'])) {
                break;
            }

//            clearstatcache(true, $this->queueFilePath);

//            if ($queueFileMTime != filemtime($this->queueFilePath)) {
            $processInput = $process->_input();

            aa($processInput, [
                'sleep_ms' => 10,
                'ttl'      => 10,
                'log'      => false,
            ]);

            $ttl = $processInput['ttl'];

            $jobs = file($this->queueFilePath);

            write($this->queueFilePath, '');

            if ($jobsCount = count($jobs)) {
                foreach ($jobs as $job) {
                    $jobData = _j($job);

                    [$env, $time, $tab, $self, $channel, $event, $data] = $jobData;

                    $expired = false;
                    $response = '';

                    $tte = $time + $ttl - time();

                    if ($tte >= 0) {
                        $response = $this->getPusher($channel, $env)->sendTriggerRequest($tab, $self, $event, $data);
                    } else {
                        $expired = true;
                        $expiresCount++;
                    }

                    if ($processInput['log']) {
                        $this->log('env: ' . $env . ', channel: ' . $channel . ', tab: ' . $tab . ($self ? ', session: ' . $self : '') . ', ttl: ' . $ttl . ', tte: ' . $tte);
                        $this->log(($expired ? 'EXPIRED ' : '>>> ') . $event . ' ' . j_($data));

                        if (!$expired) {
                            $this->log('<<< ' . j_($response));
                        }

                        $this->log();
                    }
                }

                $totalJobsCount += $jobsCount;

                $totalIterations++;

                $process->outputRR([
                                       'iterations'    => $totalIterations,
                                       'jobs count'    => $totalJobsCount,
                                       'expires count' => $expiresCount,
                                   ]);

                // todo del {

                $controllersCount = $this->app->controllers->getControllersCount();

                $log->row('i: ' . $totalIterations . ', j: +' . $jobsCount . ' (' . $totalJobsCount . '), e: ' . $expiresCount . ', c: ' . $controllersCount);

                if ($controllersCount > 9) {
                    $controllers = $this->app->controllers->getControllers();

                    foreach ($controllers as $controllerId => $controller) {
                        $log->row('    ' . $controller->__meta__->callerId . ' > ' . $controllerId . ' ' . $controller->__meta__->absPath);

                        if ($controllerId > 20 && $controllerId % 100 != 0) {
                            break;
                        }
                    }
                }

                // todo del }

//                }
            }


        }
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
                'time' => dt(),
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

            $process->inputRA(['log' => $log]);

            return 'log ' . ($log ? 'enabled' : 'disabled');
        } else {
            return 'not running';
        }
    }

    public function getInfo()
    {
        if ($process = $this->openInstanceProcess()) {
            return $process->_output();
        } else {
            return 'not running';
        }
    }

    public function add($jobData, $env)
    {
        $job = [$env, time(), $jobData['tab'], $jobData['self'], $jobData['channel'], $jobData['event'], $jobData['data']];

        $queueFile = fopen($this->queueFilePath, 'a+');

        fwrite($queueFile, j_($job) . PHP_EOL);
        fclose($queueFile);
    }
}
