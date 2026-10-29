<?php

namespace App\Mail;

use App\Models\Course\Course;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PefPackageMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<Course>  $courses
     */
    public function __construct(
        public User $student,
        public array $courses,
        public ?CarbonInterface $accessEndsAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your PEF courses are ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.pef-package',
            with: [
                'studentName' => $this->student->name,
                'courses' => collect($this->courses)->map(fn (Course $course) => [
                    'title' => $course->title,
                    'url' => route('course.details', [
                        'slug' => $course->slug,
                        'id' => $course->id,
                    ]),
                ])->all(),
                'accessEndsAt' => $this->accessEndsAt?->timezone(config('app.timezone'))->format('F j, Y'),
                'dashboardUrl' => route('student.index'),
            ],
        );
    }
}
