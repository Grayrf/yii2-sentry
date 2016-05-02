<?php

namespace mito\sentry;

use Raven_Stacktrace;
use yii\base\ErrorException;
use yii\base\InvalidConfigException;
use yii\di\Instance;
use yii\log\Logger;
use yii\log\Target;

class SentryTarget extends Target
{
    /**
     * @var bool Write the context information. The default implementation will dump user information, system variables, etc.
     */
    public $context = true;

    /**
     * @var \Raven_Client
     */
    protected $client;

    /**
     * @var string|SentryComponent
     */
    protected $sentry = 'sentry';

    /**
     * Initializes the object.
     * This method is invoked at the end of the constructor after the object is initialized with the
     * given configuration.
     */
    public function init()
    {
        parent::init();

        $this->sentry = Instance::ensure($this->sentry, SentryComponent::className());

        if (!$this->sentry->enabled) {
            return;
        }

        $this->client = $this->sentry->getClient();
    }

    /**
     * Generates the context information to be logged.
     * The default implementation will dump user information, system variables, etc.
     * @return string the context information. If an empty string, it means no context information.
     */
    protected function getContextMessage()
    {
        return '';
    }

    /**
     * Exports log [[messages]] to a specific destination.
     * Child classes must implement this method.
     */
    public function export()
    {
        if (!$this->sentry->enabled) {
            return;
        }

        foreach ($this->messages as $message) {
            list($context, $level, $category, $timestamp, $traces) = $message;

            $description = $context;

            $extra = [];
            if ($context instanceof \Exception) {
                $this->client->captureException($context);
                $description = $context->getMessage();
            } elseif (isset($context['msg'])) {
                $description = $context['msg'];
                $extra = $context;
                unset($extra['msg']);
            }

            if ($this->context) {
                $extra['context'] = parent::getContextMessage();
            }

            $data = [
                'level' => static::getLevelName($level),
                'timestamp' => $timestamp,
                'message' => $description,
                'extra' => $extra,
                'tags' => [
                    'category' => $category
                ]
            ];

            $this->client->capture($data, $traces);
        }
    }

    /**
     * Returns the text display of the specified level for the Sentry.
     *
     * @param integer $level The message level, e.g. [[LEVEL_ERROR]], [[LEVEL_WARNING]].
     * @return string
     */
    public static function getLevelName($level)
    {
        static $levels = [
            Logger::LEVEL_ERROR => 'error',
            Logger::LEVEL_WARNING => 'warning',
            Logger::LEVEL_INFO => 'info',
            Logger::LEVEL_TRACE => 'debug',
            Logger::LEVEL_PROFILE_BEGIN => 'debug',
            Logger::LEVEL_PROFILE_END => 'debug',
        ];

        return isset($levels[$level]) ? $levels[$level] : 'error';
    }
}
