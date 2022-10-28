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
 * @since         0.1.9
 * @license       https://opensource.org/licenses/MIT MIT License
 */
namespace Cake\Queue\Job;

use Cake\Queue\Queue\Processor;

/**
 * SendMailJob class to be used by QueueTransport to enqueue emails
 */
class SendMailJob implements JobInterface
{
    /**
     * @inheritDoc
     */
    public function execute(Message $message): ?string
    {
        $transportClassName = $message->getArgument('transport');
        $config = $message->getArgument('config');
        $emailMessage = unserialize($message->getArgument('emailMessage'));
        try {
            /** @var \Cake\Mailer\AbstractTransport $transport */
            $transport = new $transportClassName($config);
            $result = $transport->send($emailMessage);
        } catch (\Exception $e) {
            return Processor::REJECT;
        }

        return Processor::ACK;
    }
}
