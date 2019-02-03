<?php namespace clients\pusher;

class Svc extends \ewma\Service\Service
{
    public static $instances = [];

    public $instance;

    /**
     * @return \clients\pusher\Svc
     */
    public static function getInstance($instance)
    {
        if (!isset(static::$instances[$instance])) {
            $svc = new self;

            $svc->instance = $instance;

            static::$instances[$instance] = $svc;
            static::$instances[$instance]->__register__();
        }

        return static::$instances[$instance];
    }

    private $connection;

    private $channel;

    public function boot()
    {
        $instanceArray = explode(':', $this->instance);

        if (count($instanceArray) == 2) {
            $connectionName = $instanceArray[0];
            $this->channel = $instanceArray[1];
        } elseif (count($instanceArray) == 1) {
            $connectionName = 'main';
            $this->channel = $instanceArray[0];
        } else {
            $connectionName = 'main';
            $this->channel = 'default';
        }

        $this->connection = dataSets()->get('pusher/connections:' . $connectionName);
    }

    //
    //
    //

    private $pusher;

    private function getPusher()
    {
        if (null === $this->pusher) {
            $options = [
                'encrypted' => true,
                'cluster'   => $this->connection['cluster'],
                'debug'     => true
            ];

            $this->pusher = new \Pusher\Pusher(
                $this->connection['key'],
                $this->connection['secret'],
                $this->connection['app_id'],
                $options
            );
        }

        return $this->pusher;
    }

    public function getChannelInfo()
    {
        return $this->getPusher()->get_channel_info($this->channel);
    }

    public function subscribe($event = 'trigger')
    {
        $appc = appc();

        $appc->js('\clients\pusher pusher.min');
        $appc->js('\clients\pusher~:.subscribe', [
            'key'     => $this->connection['key'],
            'self'    => md5(app()->session->getKey()),
            'channel' => $this->channel,
            'cluster' => $this->connection['cluster'],
            'event'   => $event
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
        appc('\ewma~queue:add|pusher', [
            'tab'   => app()->tab,
            'self'  => false,
            'event' => $event,
            'data'  => $data
        ]);

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
        appc('\ewma~queue:add|pusher', [
            'tab'   => app()->tab,
            'self'  => false,
            'event' => $event,
            'data'  => $data
        ]);
    }

    /**
     * Все вкладки текущего пользователя
     *
     * @param       $event
     * @param array $data
     */
    public function triggerSelf($event, $data = [])
    {
        appc('\ewma~queue:add|pusher', [
            'tab'   => app()->tab,
            'self'  => md5(app()->session->getKey()),
            'event' => $event,
            'data'  => $data
        ]);

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
        appc('\ewma~queue:add|pusher', [
            'tab'   => app()->tab,
            'self'  => md5(app()->session->getKey()),
            'event' => $event,
            'data'  => $data
        ]);
    }

    public function triggerNow($tab, $self, $event, $data = [])
    {
        $pusher = $this->getPusher();

        return $pusher->trigger($this->channel, 'trigger', [
            'tab'   => $tab,
            'self'  => $self,
            'event' => $event,
            'data'  => $data
        ]);
    }
}
