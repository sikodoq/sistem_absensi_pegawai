<?php

namespace App\Repositories;

use Carbon\Carbon;
use App\Models\PaidLeave;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Notifications\PaidLeaveCreatedNotification;
use App\Notifications\PaidLeaveApprovedNotification;
use App\Notifications\PaidLeaveRejectedNotification;
use App\Repositories\Interfaces\PaidLeaveRepositoryInterface;

class PaidLeaveRepository implements PaidLeaveRepositoryInterface
{
    public function all()
    {
        return PaidLeave::orderBy('created_at', 'desc')->with(['kategori', 'user'])->get();
    }

    public function save(Request $request, $categoryName)
    {
        $realImage = base64_decode($request->photo);
        $imageName = $request->title . "-" . now()->translatedFormat('l, d F Y') . "-" . $request->file_name;
        $path = "cuti/" . $request->user()->name . "/" . $categoryName . '/'  . $imageName;
        Storage::disk('public')->put($path,   $realImage);

        $paid_leave = PaidLeave::create([
            'user_id' => $request->user()->id,
            'leave_category_id' => $request->category,
            'title' => $request->title,
            'description' => $request->description,
            'photo' => $path,
            'start_date' => Carbon::parse($request->start_date),
            'due_date' => Carbon::parse($request->due_date),
            'is_approved' => true
        ]);

        if ($paid_leave) {
            $request->user()->notify(new PaidLeaveCreatedNotification($paid_leave));
        }

        return $paid_leave;
    }

    public function approve(Request $request)
    {
        $paidLeave = PaidLeave::where([
            ['id', $request->paid_leave_id],
            ['user_id', $request->user_id],
        ])->with('user')->first();

        $update = $paidLeave->update([
            'is_approved' => $request->is_approved
        ]);

        $notification = $paidLeave->is_approved ?
            new PaidLeaveApprovedNotification($paidLeave) :
            new PaidLeaveRejectedNotification($paidLeave, $request->reason);
        if ($update) {
            $paidLeave->user->notify($notification);
        }
        return $update;
    }

    public function getByUser($userId)
    {
        return PaidLeave::where('user_id', $userId)->latest()->get();
    }

    public function getBetweenDate($date)
    {
        return PaidLeave::with(['user', 'user.departemen'])->whereDate('start_date', '<=', $date)
            ->whereDate('due_date', '>=', $date)->get();
    }

    public function getByUserAndYear($userId, $year)
    {
        return PaidLeave::select(['id', 'leave_category_id', 'user_id', 'start_date', 'due_date'])->where([
            ['is_approved', 1],
            ['user_id', $userId]
        ])
            ->with(['kategori'])
            ->whereYear('created_at', $year)
            ->get();
    }

    public function getByUserAndStartDate($userId, $startDate)
    {
        return PaidLeave::whereDate('start_date', Carbon::parse($startDate))
            ->where([
                ['user_id', $userId],
                ['is_approved', true]
            ])
            ->get();
    }

    public function getByUserAndCategory($userId, $categoryId)
    {

        return PaidLeave::whereYear('start_date', now()->year)
            ->where([
                ['user_id', $userId],
                ['is_approved', true],
                ['leave_category_id', $categoryId]
            ])->get();
    }
}
