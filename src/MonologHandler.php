<?php
namespace Airbrake;

use Monolog\Logger;

/**
 * Monolog handler that sends logs to Airbrake.
 */
class MonologHandler extends \Monolog\Handler\AbstractProcessingHandler
{
    /**
     * @var Notifier
     */
    private $notifier;

    /**
     * @param Notifier $notifier Notifier instance
     * @param integer  $level    Level above which entries should be logged
     * @param boolean  $bubble   Whether to bubble to the next handler or not
     */
    public function __construct(\Airbrake\Notifier $notifier, $level = Logger::ERROR, $bubble = true)
    {
        $this->notifier = $notifier;
        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        $trace = array_slice(debug_backtrace(), 3);
        $exc = new Errors\Base($record['message'], '', 0, $trace);
        $notice = $this->notifier->buildNotice($exc);
        $type = $record['channel'] . '.' . $record['level_name'];
        $notice['errors'][0]['type'] = $type;
        if (!empty($record['context'])) {
            $notice['params']['monolog_context'] = $record['context'];
        }
        if (!empty($record['extra'])) {
            $notice['params']['monolog_extra'] = $record['extra'];
        }
        return $this->notifier->sendNotice($notice);
    }
}
