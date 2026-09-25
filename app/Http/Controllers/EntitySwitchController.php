<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Actions\SwitchEntity;
use App\Domain\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EntitySwitchController extends Controller
{
    public function __invoke(Request $request, SwitchEntity $switchEntity): RedirectResponse
    {
        $data = $request->validate(['entity_id' => ['required', 'integer']]);

        /** @var User $user */
        $user = $request->user();

        $entity = $switchEntity->handle($request, $user, (int) $data['entity_id']);

        return redirect()->route('dashboard')->with('status', __('You are now working in :entity.', ['entity' => $entity->name]));
    }
}
