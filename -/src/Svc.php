<?php namespace clients\pusher;

class Svc
{
    public static $instance;

    /**
     * @return \clients\pusher\Svc
     */
    public static function getInstance()
    {
        if (!isset(static::$instance)) {
            static::$instance = new self;
        }

        return static::$instance;
    }

    /**
     * @var $queueController \clients\pusher\controllers\Queue
     */
    public $queueController;

    public $enabled = true;

    public $log;

    public function __construct()
    {
        $this->queueController = appc('\clients\pusher queue');

        $this->log = \std\log('clients/pusher');
    }

    public function disable()
    {
        $this->enabled = false;
    }

    public function enable()
    {
        $this->enabled = true;
    }

    private $pusherInstances = [];

    /**
     * @return \Pusher\Pusher|\BlackHole
     * @throws \Pusher\PusherException
     */
    public function getPusher($env)
    {
        if (!isset($this->pusherInstances[$env])) {
            $connection = dataSets()->get('pusher/connections:' . $env);

            $options = [
                'encrypted' => true,
                'cluster'   => $connection['cluster'],
                'debug'     => $connection['debug'],
            ];

            try {
                $this->pusherInstances[$env] = new \Pusher\Pusher(
                    $connection['key'],
                    $connection['secret'],
                    $connection['app_id'],
                    $options
                );
            } catch (\Pusher\PusherException $exception) {
                $this->log->row('\white,red; error \; ' . $exception->getMessage());
            }
        }

        return $this->pusherInstances[$env];
    }
}
