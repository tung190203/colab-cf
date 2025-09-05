<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;

class BookingCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public $booking;

    /**
     * Create a new notification instance.
     *
     * @param  mixed  $booking
     * @return void
     */
    public function __construct($booking)
    {
        $this->booking = $booking;
    }

    /**
     * Determine the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', 'webpush']; // lưu vào DB và gửi WebPush
    }

    /**
     * Get the mail representation of the notification (nếu muốn mail).
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->subject('Booking mới')
                    ->line("Bạn có booking mới từ: {$this->booking->full_name}")
                    ->action('Xem booking', url('/bookings/'.$this->booking->id));
    }

    /**
     * Get the array representation for database channel.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'booking_id' => $this->booking->id,
            'full_name' => $this->booking->full_name,
            'package' => $this->booking->package->name ?? null,
        ];
    }

    /**
     * Get the WebPush representation of the notification.
     *
     * @param  mixed  $notifiable
     * @param  mixed  $notification
     * @return WebPushMessage
     */
    public function toWebPush($notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title('Booking mới')
            ->body("Bạn có booking mới từ: {$this->booking->full_name}")
            ->icon('/icon-192x192.png')
            ->action('view_booking', 'Xem chi tiết')
            ->data([
                'url' => url('/bookings/'.$this->booking->id)
            ]);
    }
}
