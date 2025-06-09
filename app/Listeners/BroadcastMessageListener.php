<?php

declare(strict_types=1);

namespace App\Listeners;

use Laravel\Reverb\Events\MessageReceived;

class BroadcastMessageListener
{
    public function handle(MessageReceived $event): void
    {
        $message = json_decode($event->message);
        $data = $message->data;

        if(!$message->event || ! $message->event !== 'SendMessage') {
            return ;
        }

        $data = json_decode($data);
    }
}
