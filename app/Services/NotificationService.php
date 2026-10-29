<?php

namespace App\Services;

use App\Notifications\ChatMessageNotification;
use App\Services\MediaService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class NotificationService extends MediaService
{
   function notifications(array $data): LengthAwarePaginator|Collection
   {
      $user = Auth::user();
      $page = array_key_exists('per_page', $data) ? intval($data['per_page']) : 10;
      $notifications = $this->visibleNotifications($user);

      if (array_key_exists('unread', $data) && $data['unread']) {
         return $notifications->whereNull('read_at')->get();
      }

      return $notifications->paginate($page);
   }

   private function visibleNotifications($user)
   {
      return $user->notifications()->where('type', '!=', ChatMessageNotification::class);
   }

   function markAllAsRead(): void
   {
      Auth::user()->unreadNotifications->markAsRead();
   }

   function markAsRead(string $id)
   {
      $notification = Auth::user()->notifications()->whereKey($id)->first();

      if ($notification) {
         $notification->markAsRead();
      }

      return $notification;
   }
}
