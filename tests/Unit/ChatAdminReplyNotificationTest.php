<?php

use App\Models\User;
use App\Notifications\ChatMessageNotification;
use App\Services\Chat\ChatPresenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

it('does not queue chat message notifications so admin bells update immediately', function () {
    expect((new ReflectionClass(ChatMessageNotification::class))->implementsInterface(ShouldQueue::class))->toBeFalse();
});

it('emails admins who have the dashboard open but are not viewing that thread', function () {
    Cache::flush();

    $admin = new User(['role' => 'admin', 'name' => 'Ada']);
    $admin->id = 11;
    $presence = new ChatPresenceService();

    $presence->update($admin, null, true);

    expect($presence->isViewingConversation($admin, 42))->toBeFalse()
        ->and($presence->shouldSendEmail($admin, 42))->toBeTrue();
});

it('skips email and in-thread alerts only while the admin is looking at that conversation', function () {
    Cache::flush();

    $ops = new User(['role' => 'admin', 'name' => 'Omar', 'can_manage_platform_settings' => false]);
    $ops->id = 12;
    $presence = new ChatPresenceService();

    $presence->update($ops, 42, true);

    expect($presence->isViewingConversation($ops, 42))->toBeTrue()
        ->and($presence->shouldSendEmail($ops, 42))->toBeFalse()
        ->and($presence->isViewingConversation($ops, 99))->toBeFalse()
        ->and($presence->shouldSendEmail($ops, 99))->toBeTrue();
});
