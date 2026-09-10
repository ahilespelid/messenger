<?php

namespace App\Http\Controllers\Operator;

use App\Events\TeamMessageCreated;
use App\Events\TeamMessageRead;
use App\Events\TeamMessageUpdated;
use App\Http\Controllers\Controller;
use App\Models\TeamMessage;
use App\Models\User;
use App\Services\TeamVoiceMessageService;
use Illuminate\Http\Request;

class TeamMessageController extends Controller
{
    public function index(Request $request, User $colleague)
    {
        $me = $request->user()->id;

        $messages = TeamMessage::query()
            ->where(function ($q) use ($me, $colleague) {
                $q->where('sender_id', $me)->where('recipient_id', $colleague->id);
            })
            ->orWhere(function ($q) use ($me, $colleague) {
                $q->where('sender_id', $colleague->id)->where('recipient_id', $me);
            })
            ->orderBy('created_at')
            ->get();

        $this->markRead($request, $colleague);

        return response()->json([
            'colleague' => ['id' => $colleague->id, 'name' => $colleague->name],
            'messages' => $messages->map(fn ($m) => $m->toBroadcastArray()),
        ]);
    }

    public function store(Request $request, User $colleague)
    {
        abort_if($colleague->id === $request->user()->id, 422, "Can't message yourself.");

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $message = TeamMessage::create([
            'sender_id' => $request->user()->id,
            'recipient_id' => $colleague->id,
            'body' => $data['body'],
        ]);

        broadcast(new TeamMessageCreated($message))->toOthers();

        return response()->json(['id' => $message->id, 'created_at' => $message->created_at->toIso8601String()]);
    }

    public function storeVoice(Request $request, User $colleague, TeamVoiceMessageService $voiceMessages)
    {
        abort_if($colleague->id === $request->user()->id, 422, "Can't message yourself.");

        $data = $request->validate([
            'audio' => ['required', 'file', 'max:10240'],
            'duration' => ['required', 'integer', 'min:1', 'max:300'],
        ]);

        $message = $voiceMessages->store($request->user()->id, $colleague->id, $data['audio'], $data['duration']);

        broadcast(new TeamMessageCreated($message))->toOthers();

        return response()->json([
            'id' => $message->id,
            'voice_duration' => $message->voice_duration,
            'created_at' => $message->created_at->toIso8601String(),
        ]);
    }

    public function streamVoice(User $colleague, TeamMessage $message, TeamVoiceMessageService $voiceMessages)
    {
        abort_unless(
            in_array($message->sender_id, [$colleague->id], true) || in_array($message->recipient_id, [$colleague->id], true),
            404
        );

        return $voiceMessages->stream($message, allowDeleted: true);
    }

    public function update(Request $request, User $colleague, TeamMessage $message)
    {
        $this->authorizeOwnMessage($request, $colleague, $message);

        abort_if($message->deleted_at, 422, 'Message already deleted.');
        abort_if($message->type !== 'text', 422, 'Only text messages can be edited.');

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        if (! $message->edited_at) {
            $message->original_body = $message->body;
        }
        $message->body = $data['body'];
        $message->edited_at = now();
        $message->save();

        broadcast(new TeamMessageUpdated($message))->toOthers();

        return response()->json(['edited_at' => $message->edited_at->toIso8601String()]);
    }

    public function destroy(Request $request, User $colleague, TeamMessage $message)
    {
        $this->authorizeOwnMessage($request, $colleague, $message);

        if ($message->deleted_at) {
            return response()->noContent();
        }

        $message->forceFill(['deleted_at' => now()])->save();

        broadcast(new TeamMessageUpdated($message))->toOthers();

        return response()->noContent();
    }

    public function reveal(Request $request, User $colleague, TeamMessage $message)
    {
        abort_unless(
            in_array($request->user()->id, [$message->sender_id, $message->recipient_id], true),
            404
        );

        return response()->json($message->toRevealArray());
    }

    public function markRead(Request $request, User $colleague)
    {
        $me = $request->user()->id;

        $updated = TeamMessage::where('sender_id', $colleague->id)
            ->where('recipient_id', $me)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($updated) {
            broadcast(new TeamMessageRead($colleague->id, $me, $me))->toOthers();
        }

        return response()->noContent();
    }

    protected function authorizeOwnMessage(Request $request, User $colleague, TeamMessage $message): void
    {
        abort_unless(
            $message->sender_id === $request->user()->id
                && in_array($colleague->id, [$message->sender_id, $message->recipient_id], true),
            403
        );
    }
}
