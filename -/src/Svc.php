<?php namespace clients\pusher;

class Svc
{
    /**
     * @var $mainController \clients\pusher\controllers\Main
     */
    private $mainController;

    /**
     * @var $queueController \clients\pusher\controllers\Queue
     */
    private $queueController;

    /**
     * @return \clients\pusher\Svc
     */
    public static $instances = [];

    public $instance;

    /**
     * @return \clients\pusher\Svc
     */
    public static function getInstance($channel)
    {
        if (!isset(static::$instances[$channel])) {
            static::$instances[$channel] = new self($channel);
        }

        return static::$instances[$channel];
    }

    private $connection;

    private $channel;

    private $pusher;

    public function __construct($channel)
    {
        $this->channel = $channel;
        $this->connection = dataSets()->get('pusher/connections:' . app()->getEnv());

        $this->mainController = appc('\clients\pusher~');
        $this->queueController = appc('\clients\pusher queue|' . $this->channel);

        $this->pusher = $this->getPusher();
    }

    /**
     * @return \Pusher\Pusher
     * @throws \Pusher\PusherException
     */
    private function getPusher()
    {
        if (null === $this->pusher) {

            $options = [
                'encrypted' => true,
                'cluster'   => $this->connection['cluster'],
                'debug'     => $this->connection['debug']
            ];

            try {
                $this->pusher = new \Pusher\Pusher(
                    $this->connection['key'],
                    $this->connection['secret'],
                    $this->connection['app_id'],
                    $options
                );
            } catch (\Pusher\PusherException $exception) {
                $this->mainController->log($exception->getMessage());
            }
        }

        return $this->pusher;
    }

    public function subscribe()
    {
        $appc = appc();

        $appc->js('\clients\pusher pusher.min');
        $appc->js('\clients\pusher~:.subscribe', [
            'key'        => $this->connection['key'],
            'self'       => md5(app()->session->getKey()),
            'channel'    => $this->channel,
            'cluster'    => $this->connection['cluster'],
            'logEnabled' => $this->connection['debug']
        ]);
    }

    /**
     * Все вкладки всех подписчиков
     *
     * @param       $event
     * @param array $data
     */
    public function trigger($event, $data = [])
    {
        $job = [
            'tab'   => app()->tab,
            'self'  => false,
            'event' => $event,
            'data'  => $data
        ];

        $this->queueController->add($job);

        appc()->jsCall('ewma.trigger', $event, $data);
    }

    /**
     * Все вкладки всех подписчиков кроме текущей
     *
     * @param       $event
     * @param array $data
     */
    public function triggerOthers($event, $data = [])
    {
        $job = [
            'tab'   => app()->tab,
            'self'  => false,
            'event' => $event,
            'data'  => $data
        ];

        $this->queueController->add($job);
    }

    /**
     * Все вкладки текущего пользователя
     *
     * @param       $event
     * @param array $data
     */
    public function triggerSelf($event, $data = [])
    {
        $jobData = [
            'tab'   => app()->tab,
            'self'  => md5(app()->session->getKey()),
            'event' => $event,
            'data'  => $data
        ];

        $this->queueController->add($jobData);

        appc()->jsCall('ewma.trigger', $event, $data);
    }

    /**
     * Все вкладки текущего подписчика кроме текущей
     *
     * @param       $event
     * @param array $data
     */
    public function triggerSelfOthers($event, $data = [])
    {
        $job = [
            'tab'   => app()->tab,
            'self'  => md5(app()->session->getKey()),
            'event' => $event,
            'data'  => $data
        ];

        $this->queueController->add($job);
    }

    public function sendTriggerRequest($tab, $self, $event, $data = [])
    {
        try {
            return $this->pusher->trigger($this->channel, 'trigger', [
                'tab'   => $tab,
                'self'  => $self,
                'event' => $event,
                'data'  => $data
            ]);
        } catch (\Pusher\PusherException $exception) {
            $this->mainController->log($exception->getMessage());
        }
    }
}
