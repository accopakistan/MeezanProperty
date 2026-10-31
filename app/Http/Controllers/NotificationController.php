<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * In-app notification centre for every signed-in role. Entries come from
 * the database channel of the service notifications (see
 * App\Notifications\QueuedMailNotification::inApp()).
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        return view('notifications.index', [
            'notifications' => ($filter === 'unread' ? $user->unreadNotifications() : $user->notifications())->paginate(20)->withQueryString(),
            'filter' => $filter,
            'unread' => $user->unreadNotifications()->count(),
            'section' => match ($user->role->value) {
                'dealer' => 'dealer',
                'admin' => 'admin',
                default => 'account',
            },
        ]);
    }

    /** Mark one as read and follow its link, which must stay on this site. */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        $url = (string) ($notification->data['url'] ?? '');
        $internal = str_starts_with($url, url('/').'/') || $url === url('/');

        return $internal ? redirect()->to($url) : redirect()->route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }
}
