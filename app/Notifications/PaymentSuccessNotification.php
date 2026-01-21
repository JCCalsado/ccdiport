<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class PaymentSuccessNotification extends Notification
{
    use Queueable;

    protected array $paymentData;

    public function __construct(array $paymentData)
    {
        $this->paymentData = $paymentData;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Received - ' . $this->paymentData['reference'])
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('We have successfully received your payment.')
            ->line('**Payment Details:**')
            ->line('Amount: ₱' . number_format($this->paymentData['amount'], 2))
            ->line('Reference Number: ' . $this->paymentData['reference'])
            ->line('Payment Method: ' . $this->paymentData['method'])
            ->line('Remaining Balance: ₱' . number_format($this->paymentData['balance'], 2))
            ->action('View Transaction', route('student.account'))
            ->line('Thank you for your payment!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'payment_success',
            'title' => 'Payment Received',
            'message' => 'Your payment of ₱' . number_format($this->paymentData['amount'], 2) . ' has been received.',
            'amount' => $this->paymentData['amount'],
            'reference' => $this->paymentData['reference'],
            'method' => $this->paymentData['method'],
            'balance' => $this->paymentData['balance'],
            'action_url' => route('student.account'),
        ];
    }
}