<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org/)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.1.0
 * @license       https://opensource.org/licenses/MIT MIT License
 */
namespace Cake\Queue\Job;

use Cake\Core\ContainerInterface;
use Cake\Utility\Hash;
use Closure;
use Interop\Queue\Context;
use Interop\Queue\Message as QueueMessage;
use JsonSerializable;
use RuntimeException;

class Message implements JsonSerializable
{
    /**
     * @var \Interop\Queue\Context
     */
    protected $context;

    /**
     * @var \Interop\Queue\Message
     */
    protected $originalMessage;

    /**
     * @var array
     */
    protected $parsedBody;

    /**
     * @var \Closure|null
     */
    protected $callable;

    /**
     * @var \Cake\Core\ContainerInterface|null
     */
    protected $container;

    /**
     * @param \Interop\Queue\Message $originalMessage Queue message.
     * @param \Interop\Queue\Context $context Context.
     * @param \Cake\Core\ContainerInterface|null $container DI container instance
     */
    public function __construct(QueueMessage $originalMessage, Context $context, ?ContainerInterface $container = null)
    {
        $this->context = $context;
        $this->originalMessage = $originalMessage;
        $this->parsedBody = json_decode($originalMessage->getBody(), true);
        $this->container = $container;
    }

    /**
     * @return \Interop\Queue\Context
     */
    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return \Interop\Queue\Message
     */
    public function getOriginalMessage(): QueueMessage
    {
        return $this->originalMessage;
    }

    /**
     * @return array
     */
    public function getParsedBody(): array
    {
        return $this->parsedBody;
    }

    /**
     * Get a closure containing the callable in the job.
     *
     * Supported callables include:
     * - array of [class, method]. The class will be constructed with no constructor parameters.
     *
     * @return \Closure
     */
    public function getCallable()
    {
        if ($this->callable) {
            return $this->callable;
        }

        $target = $this->getTarget();
        if ($this->container && $this->container->has($target[0])) {
            $object = $this->container->get($target[0]);
        } else {
            $object = new $target[0]();
        }

        $this->callable = Closure::fromCallable([$object, $target[1]]);

        return $this->callable;
    }

    /**
     * Get the target class and method.
     *
     * @return array{string, string}
     * @psalm-return array{class-string, string}
     */
    public function getTarget(): array
    {
        $target = $this->parsedBody['class'] ?? null;

        if (!is_array($target) || count($target) !== 2) {
            throw new RuntimeException(sprintf(
                'Message class should be in the form `[class, method]` got `%s`',
                json_encode($target)
            ));
        }

        return $target;
    }

    /**
     * @param mixed $key Key
     * @param mixed $default Default value.
     * @return mixed
     */
    public function getArgument($key = null, $default = null)
    {
        if (array_key_exists('data', $this->parsedBody)) {
            $data = $this->parsedBody['data'];
        } else {
            // support old jobs that still use args key
            $data = $this->parsedBody['args'][0];
        }

        if ($key === null) {
            return $data;
        }

        return Hash::get($data, $key, $default);
    }

    /**
     * The maximum number of attempts allowed by the job.
     *
     * @return null|int
     */
    public function getMaxAttempts(): ?int
    {
        $target = $this->getTarget();

        $class = $target[0];

        /** @psalm-suppress InvalidPropertyFetch */
        return $class::$maxAttempts ?? null;
    }

    /**
     * @return string
     * @psalm-suppress InvalidToString
     */
    public function __toString()
    {
        return json_encode($this);
    }

    /**
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->parsedBody;
    }
}
