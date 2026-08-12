<?php

namespace App\Services;

use App\Models\TeamMember;
use Illuminate\Http\UploadedFile;

class TeamMemberService extends MediaService
{
    public function listForAdmin()
    {
        return TeamMember::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function listForPublic()
    {
        return TeamMember::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'role', 'photo', 'sort_order']);
    }

    public function create(array $data, ?UploadedFile $photo = null): TeamMember
    {
        $member = TeamMember::create([
            'name' => $data['name'],
            'role' => $data['role'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if ($photo) {
            $this->storePhoto($member, $photo);
        }

        return $member->fresh();
    }

    public function update(TeamMember $member, array $data, ?UploadedFile $photo = null): TeamMember
    {
        $member->fill([
            'name' => $data['name'],
            'role' => $data['role'],
            'sort_order' => $data['sort_order'] ?? $member->sort_order,
            'is_active' => $data['is_active'] ?? $member->is_active,
        ]);

        if ($photo) {
            $this->storePhoto($member, $photo);
        }

        $member->save();

        return $member->fresh();
    }

    private function storePhoto(TeamMember $member, UploadedFile $photo): void
    {
        $this->addNewDeletePrev($member, $photo, 'photo');

        $media = $member->getMedia('default')
            ->first(fn ($item) => $item->getCustomProperty('name') === 'photo')
            ?? $member->getMedia('*', ['name' => 'photo'])->first();

        $member->photo = $media?->getPathRelativeToRoot();
        $member->save();
    }

    public function delete(TeamMember $member): void
    {
        if ($member->hasMedia()) {
            $member->clearMediaCollection();
        }

        $member->delete();
    }
}
