<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\SessionActivity;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index(Request $request)
    {
        $sessions = $this->list($request);

        if ($request->wantsJson()) {
            return response()->json(['sessions' => $sessions]);
        }

        return view('operator.sessions', ['sessions' => $sessions]);
    }

    public function destroy(Request $request, string $sessionId)
    {
        abort_if($sessionId === $request->session()->getId(), 422, 'Нельзя завершить собственную сессию отсюда — используйте "Выйти".');

        SessionActivity::kill($sessionId);

        return response()->noContent();
    }

    protected function list(Request $request): array
    {
        $currentId = $request->session()->getId();

        return SessionActivity::orderByDesc('last_seen_at')
            ->get()
            ->map(fn (SessionActivity $s) => [
                'session_id' => $s->session_id,
                'kind' => $s->kind,
                'name' => $s->name,
                'ip_address' => $s->ip_address,
                'user_agent' => $s->user_agent,
                'last_seen_at' => $s->last_seen_at->toIso8601String(),
                'is_current' => $s->session_id === $currentId,
            ])
            ->values()
            ->all();
    }
}
