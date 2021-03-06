<?php

namespace App\Repositories;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\AbsentPermission;
use Illuminate\Support\Facades\Storage;
use App\Notifications\AbsentPermissionCreatedNotification;
use App\Notifications\AbsentPermissionApprovedNotification;
use App\Notifications\AbsentPermissionRejectedNotification;
use App\Repositories\Interfaces\AbsentPermissionRepositoryInterface;

class AbsentPermissionRepository implements AbsentPermissionRepositoryInterface
{

    public function all()
    {
        return AbsentPermission::orderBy('created_at', 'desc')->get();
    }

    public function save(Request $request, $name = null, $id = null)
    {
        $folder = $request->user()->name;
        $userId = $request->user()->id;
        if (!is_null($name)) {
            $folder = $name;
        }
        if (!is_null($id)) {
            $userId = $id;
        }
        $realImage = base64_decode($request->photo);
        $imageName = $request->title . "-" . now()->translatedFormat('l, d F Y') . "-" . $request->file_name;

        Storage::disk('public')->put("izin/" . $folder . "/"   . $imageName,  $realImage);

        $permission =  AbsentPermission::create([
            'user_id' => $userId,
            'title' => $request->title,
            'description' => $request->description,
            'photo' => "izin/" . $folder . "/"   . $imageName,
            'due_date' => Carbon::parse($request->due_date),
            'start_date' => Carbon::parse($request->start_date),
            'is_approved' => true
        ]);

        if ($permission) {
            $request->user()->notify(new AbsentPermissionCreatedNotification($permission));
        }

        return $permission;
    }

    public function approve(Request $request)
    {
        $permission = AbsentPermission::where([
            ['id', $request->permission_id],
            ['user_id', $request->user_id]
        ])
            ->with(['user'])
            ->first();
        $update = $permission->update([
            'is_approved' => $request->is_approved
        ]);

        $notification = $permission->is_approved ?
            new AbsentPermissionApprovedNotification($permission) :
            new AbsentPermissionRejectedNotification($permission, $request->reason);

        if ($update) {
            $permission->user->notify($notification);
        }
        return $update;
    }

    public function getByUser($userId)
    {
        return AbsentPermission::where('user_id', $userId)->latest()->get();
    }

    public function getBetweenDate($date)
    {
        return AbsentPermission::with(['user', 'user.departemen'])->whereDate('start_date', '<=', $date)
            ->whereDate('due_date', '>=', $date)->get();
    }

    public function getByUserAndYear($userId, $year)
    {
        return AbsentPermission::select(['id', 'user_id', 'start_date', 'due_date'])->where([
            ['is_approved', 1],
            ['user_id', $userId]
        ])
            ->whereYear('created_at', $year)
            ->get();
    }

    public function getByUserAndStartDate($userId, $startDate)
    {
        return AbsentPermission::whereDate('start_date', Carbon::parse($startDate))
            ->where([
                ['user_id', $userId],
                ['is_approved', true]
            ])->get();
    }
}
