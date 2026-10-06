<?php namespace clients\pusher;

class ChannelSvc
{
    public static $instances = [];

    public $instance;

    /**
     * @return \clients\pusher\ChannelSvc
     */
    public static function getInstance($channel, $env = false)
    {
        if (!$env) {
            $env = app()->getEnvName();
        }

        if (!isset(static::$instances[$env][$channel])) {
            static::$instances[$env][$channel] = new self($channel, $env);
        }

        return static::$instances[$env][$channel];
    }

    private $svc;

    private $connection;

    private $channel;

    private $env;

    private $pusher;

    public function __construct($channel, $env)
    {
        $this->svc = Svc::getInstance();

        $this->channel = $channel;
        $this->env = $env;

        $this->connection = dataSets()->get('pusher/connections:' . $env);

        $this->pusher = $this->svc->getPusher($env);
    }

    public function disable()
    {
        $this->svc->disable();
    }

    public function enable()
    {
        $this->svc->enable();
    }

    public function subscribe()
    {
        $appc = appc();

        $appc->js('\clients\pusher pusher.min');
        $appc->js('\clients\pusher~:.subscribe', [
            'key'        => $this->connection['key'],
            'self'       => app()->session->getPublicKey(),
            'channel'    => $this->channel,
            'cluster'    => $this->connection['cluster'],
            'logEnabled' => $this->connection['debug'],
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
        if ($this->svc->enabled && $this->connection['enabled']) {
            $job = [
                'tab'     => app()->tab,
                'self'    => false,
                'channel' => $this->channel,
                'event'   => $event,
                'data'    => $data,
            ];

            $this->svc->queueController->add($job, $this->env);
        }

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
        if ($this->svc->enabled && $this->connection['enabled']) {
            $job = [
                'tab'     => app()->tab,
                'self'    => false,
                'channel' => $this->channel,
                'event'   => $event,
                'data'    => $data,
            ];

            $this->svc->queueController->add($job, $this->env);
        }
    }

    /**
     * Все вкладки текущего пользователя
     *
     * @param       $event
     * @param array $data
     */
    public function triggerSelf($event, $data = [])
    {
        $job = [
            'tab'     => app()->tab,
            'self'    => app()->session->getPublicKey(),
            'channel' => $this->channel,
            'event'   => $event,
            'data'    => $data,
        ];

        $this->svc->queueController->add($job, $this->env);

        appc()->jsCall('ewma.trigger', $event, $data);
    }

    /**
     * Текущая вкладка текущего пользователя
     *
     * @param       $event
     * @param array $data
     */
    public function triggerSelfTab($event, $data = [])
    {
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
        if ($this->svc->enabled && $this->connection['enabled']) {
            $job = [
                'tab'     => app()->tab,
                'self'    => app()->session->getPublicKey(),
                'channel' => $this->channel,
                'event'   => $event,
                'data'    => $data,
            ];

            $this->svc->queueController->add($job, $this->env);
        }
    }

    public function sendTriggerRequest($tab, $self, $event, $data = [])
    {
        try {
            $this->svc->log->row($this->env . ' ' . $this->channel . ':' . $event);
            $this->svc->log->json($data);

            return $this->pusher->trigger($this->channel, 'trigger', [
                'tab'   => $tab,
                'self'  => $self,
                'event' => $event,
                'data'  => $data,
            ]);
        } catch (\Pusher\PusherException $exception) {
            $this->svc->log->row('\white,red; error \; ' . $exception->getMessage());
        }
    }
}
