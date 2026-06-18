<?php

namespace App\Http\Controllers;

use App\Models\InAppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        if (! $user) {
            abort(403);
        }

        $activeFilter = (string) $request->query('filter', 'all');
        if (! in_array($activeFilter, ['all', 'unread', 'read'], true)) {
            $activeFilter = 'all';
        }

        $baseQuery = InAppNotification::query()
            ->where('user_id', (int) $user->id)
            ->orderByDesc('id');

        $notificationsQuery = clone $baseQuery;
        if ($activeFilter === 'unread') {
            $notificationsQuery->whereNull('read_at');
        }
        if ($activeFilter === 'read') {
            $notificationsQuery->whereNotNull('read_at');
        }

        return view('notifications.index', [
            'activeFilter' => $activeFilter,
            'notifications' => $notificationsQuery->paginate(15)->withQueryString(),
            'summary' => [
                'all' => (clone $baseQuery)->count(),
                'unread' => (clone $baseQuery)->whereNull('read_at')->count(),
                'read' => (clone $baseQuery)->whereNotNull('read_at')->count(),
            ],
        ]);
    }

    public function open(InAppNotification $notification): RedirectResponse
    {
        $user = auth()->user();
        if (! $user || (int) $notification->user_id !== (int) $user->id) {
            abort(403);
        }

        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();
        }

        return redirect()->to(
            notificationDestinationUrl($user, $notification, route('notifications.index'))
        );
    }

    public function markRead(Request $request, InAppNotification $notification): RedirectResponse
    {
        if ((int) $notification->user_id !== (int) auth()->id()) {
            abort(403);
        }

        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();
        }

        $returnUrl = $this->safeReturnUrl(
            $request->input('return_url'),
            url()->previous() ?: route('ikans.index')
        );

        return redirect()->to($returnUrl)->with('sukses', 'Notifikasi ditandai sebagai dibaca.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $user = auth()->user();
        if (! $user) {
            abort(403);
        }

        $user->inAppNotifications()
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        $returnUrl = $this->safeReturnUrl(
            $request->input('return_url'),
            url()->previous() ?: route('ikans.index')
        );

        return redirect()->to($returnUrl)->with('sukses', 'Semua notifikasi ditandai sebagai dibaca.');
    }

    private function safeReturnUrl(mixed $candidate, string $fallback): string
    {
        return safeInternalReturnUrl($candidate, $fallback);
    }
}
