<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PaymentFailedNotification extends Notification
{
    use Queueable;

    protected array $paymentData;

    public function __construct(array $paymentData)
    {
        $this->paymentData = $paymentData;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Failed - ' . $this->paymentData['reference'])
            ->greeting('Hello ' . $notifiable->name)
            ->line('Unfortunately, your payment could not be processed.')
            ->line('**Payment Details:**')
            ->line('Amount: ₱' . number_format($this->paymentData['amount'], 2))
            ->line('Reference Number: ' . $this->paymentData['reference'])
            ->line('Reason: ' . $this->paymentData['reason'])
            ->line('**What to do next:**')
            ->line('- Check your payment details and try again')
            ->line('- Contact your payment provider for assistance')
            ->line('- Or make a payment through other available methods')
            ->action('Try Again', route('student.payment.create'))
            ->line('If you continue to experience issues, please contact our support team.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'payment_failed',
            'title' => 'Payment Failed',
            'message' => 'Your payment of ₱' . number_format($this->paymentData['amount'], 2) . ' failed.',
            'amount' => $this->paymentData['amount'],
            'reference' => $this->paymentData['reference'],
            'reason' => $this->paymentData['reason'],
            'action_url' => route('student.payment.create'),
        ];
    }
}