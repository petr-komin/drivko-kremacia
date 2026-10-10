<?php

namespace App\Mail\Transport;

use Brevo\Brevo;
use Brevo\TransactionalEmails\Requests\SendTransacEmailRequest;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestSender;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestToItem;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/**
 * Odesílání přes Brevo API (stejný účet a doména jako e-shop, kód je převzatý).
 * Zapíná se MAIL_MAILER=brevo + BREVO_API_KEY.
 */
class BrevoTransport extends AbstractTransport
{
    public function __construct(private string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $from = $email->getFrom()[0] ?? new Address(config('mail.from.address'), config('mail.from.name'));

        $request = new SendTransacEmailRequest([
            'sender' => new SendTransacEmailRequestSender([
                'email' => $from->getAddress(),
                'name'  => $from->getName() ?: null,
            ]),
            'to' => array_map(
                fn (Address $a) => new SendTransacEmailRequestToItem([
                    'email' => $a->getAddress(),
                    'name'  => $a->getName() ?: null,
                ]),
                $email->getTo(),
            ),
            'subject'     => $email->getSubject(),
            'htmlContent' => $email->getHtmlBody() ?: null,
            'textContent' => $email->getTextBody() ?: null,
        ]);

        (new Brevo($this->apiKey))->transactionalEmails->sendTransacEmail($request);
    }

    public function __toString(): string
    {
        return 'brevo';
    }
}
