<?php

use App\Models\User;
use App\Notifications\ChatMessageNotification;
use App\Notifications\CourseApprovalNotification;
use App\Services\NotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    Schema::disableForeignKeyConstraints();
    Schema::dropIfExists('notifications');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->string('role')->nullable();
        $table->string('user_type')->nullable();
        $table->string('candidate_status')->nullable();
        $table->timestamps();
    });

    Schema::create('notifications', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('type');
        $table->morphs('notifiable');
        $table->text('data');
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
    });
});

it('hides chat messages from the bell and the notifications page', function () {
    $instructor = bellUser('Trainer', 'trainer@example.com');
    Auth::login($instructor);

    bellNotification($instructor, ChatMessageNotification::class, ['type' => 'chat_message', 'title' => 'Message: Class chat']);
    bellNotification($instructor, CourseApprovalNotification::class, ['title' => 'Course approved']);

    $service = app(NotificationService::class);
    $unread = $service->notifications(['unread' => true]);
    $page = $service->notifications([]);

    expect($unread)->toHaveCount(1)
        ->and($unread->first()->data['title'])->toBe('Course approved')
        ->and($page->total())->toBe(1)
        ->and($page->first()->data['title'])->toBe('Course approved');
});

it('marks existing unread chat bell rows read without touching other notifications', function () {
    $instructor = bellUser('Trainer', 'trainer-read@example.com');
    bellNotification($instructor, ChatMessageNotification::class, ['type' => 'chat_message', 'title' => 'Message: Class chat']);
    bellNotification($instructor, CourseApprovalNotification::class, ['title' => 'Course approved']);

    $migration = require database_path('migrations/2026_10_30_000004_mark_chat_bell_notifications_read.php');
    $migration->up();

    expect(DB::table('notifications')->where('type', ChatMessageNotification::class)->whereNull('read_at')->count())->toBe(0)
        ->and(DB::table('notifications')->where('type', CourseApprovalNotification::class)->whereNull('read_at')->count())->toBe(1);
});

function bellUser(string $name, string $email): User
{
    return User::create([
        'name' => $name,
        'email' => $email,
        'password' => 'password',
        'role' => 'instructor',
        'user_type' => 'external',
    ]);
}

function bellNotification(User $user, string $type, array $data): void
{
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => $type,
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode($data),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
