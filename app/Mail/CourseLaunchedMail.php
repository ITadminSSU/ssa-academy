<?php

namespace App\Mail;

use App\Models\Course\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CourseLaunchedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Course $course) {}

    public function envelope(): Envelope
    {
        $siteName = (string) config('app.name');

        return new Envelope(
            subject: "{$this->course->title} is now available on {$siteName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.course-launched',
            with: [
                'courseTitle' => $this->course->title,
                'courseUrl' => route('course.details', [
                    'slug' => $this->course->slug,
                    'id' => $this->course->id,
                ]),
                'siteName' => (string) config('app.name'),
            ],
        );
    }
}
