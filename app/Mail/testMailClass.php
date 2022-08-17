<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

#/ Helpers
use h1, h2;

/**
 * I use this to define mail themes for emails. There could be different themes with different mailable objects.
 * This mail class also is used to attach files to emails
*/

class testMailClass extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var string $subject; default "Lorem Ipsum Subject"
     */
    public string $subject = "Lorem Ipsum Subject";

    /**
     * @var string $msg
     */
    public string $msg;

    /**
     * @var array $attachment
     */
    public array $attachment;

    /**
     * @param string $msg
     * @param string $subject
     */
    public function __construct(string $msg, string $subject = '')
    {
        if (!empty($subject)) {
            $this->sub = $subject;
        }
        $this->msg = $msg;
    }

    /**
     * function build
     */
    public function build()
    {
        $setup = $this
        ->subject($this->sub)
        ->view('emails.lorem.ipsum') //template in the `views`
        ->withSwiftMessage(function ($message) {
             $headers = $message->getHeaders();
             $APP_DOMAIN = @str_replace('-', '.', $_ENV['APP_DOMAIN']);
             $headers->remove('Message-ID');
             $headers->addTextHeader('Message-ID', "<XE".time()."@{$APP_DOMAIN}>"); //overriding message ID (just for fun)
        });

        #/ Attachment work
        if (!empty($this->attachment)) {
            foreach($this->attachment as $atv) {
                if (!empty($this->settings['attachment_base64'])) {
                    $setup->attachData(base64_decode($atv['data']), $atv['name'], ['mime'=> $atv['mime']]);
                } else {
                    $setup->attachData($atv['data'], $atv['name'], ['mime'=> $atv['mime']]);
                }
            }
        }

        return $setup;
    }
}
