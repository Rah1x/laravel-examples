<?php
/**
 * Using this to define mail themes for emails. There could be different themes with different classes
*/

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

use h1, h2;

class testMailObject extends Mailable
{
    use Queueable, SerializesModels;

    public $subject = "Lorem Ipsum Subject";
    public $msg, $attachment;

    public function __construct($msg, $subject='')
    {
        if(!empty($subject)) $this->sub = $subject;
        $this->msg = $msg;
    }

    public function build()
    {
        $setup = $this
        ->subject($this->sub)
        ->view('emails.lorem.ipsum')
        ->withSwiftMessage(function ($message)
        {
             $headers = $message->getHeaders();

             $APP_DOMAIN = @str_replace('-', '.', $_ENV['APP_DOMAIN']);
             $headers->remove('Message-ID');
             $headers->addTextHeader('Message-ID', "<XE".time()."@{$APP_DOMAIN}>");
        });

        if(!empty($this->attachment))
        foreach($this->attachment as $atv)
        {
            if(!empty($this->settings['attachment_base64']))
            $setup->attachData(base64_decode($atv['data']), $atv['name'], ['mime'=> $atv['mime']]);
            else
            $setup->attachData($atv['data'], $atv['name'], ['mime'=> $atv['mime']]);
        }

        return $setup;
    }
}
