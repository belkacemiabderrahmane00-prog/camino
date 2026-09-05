<?php

namespace App\Http\Controllers;

use App\Models\Place;
use App\Models\PlaceAlert;
use App\Models\PlacePhoto;
use App\Models\PlaceReport;
use Illuminate\Http\Request;

/**
 * Espace de modération (administrateurs) : lieux proposés, photos, alertes, signalements.
 */
class ModerationController extends Controller
{
    public function index()
    {
        return view('moderation.index', [
            'pendingPlaces' => Place::with(['category', 'creator'])->where('status', 'pending')->latest()->limit(50)->get(),
            'pendingPhotos' => PlacePhoto::with(['place', 'user'])->where('status', 'pending')->latest()->limit(50)->get(),
            'alerts' => PlaceAlert::active()->with(['place', 'user'])->latest()->limit(50)->get(),
            'reports' => PlaceReport::with(['place', 'user'])->latest()->limit(50)->get(),
            'counts' => [
                'places' => Place::where('status', 'pending')->count(),
                'photos' => PlacePhoto::where('status', 'pending')->count(),
                'alerts' => PlaceAlert::active()->count(),
                'reports' => PlaceReport::count(),
            ],
        ]);
    }

    public function updatePlace(Request $request, Place $place)
    {
        $data = $request->validate(['action' => ['required', 'in:approve,reject,hide']]);
        $place->update(['status' => match ($data['action']) {
            'approve' => 'approved',
            'reject' => 'rejected',
            default => 'hidden',
        }]);

        return back()->with('status', 'Lieu « ' . $place->title . ' » : ' . $data['action'] . '.');
    }

    public function updatePhoto(Request $request, PlacePhoto $photo)
    {
        $data = $request->validate(['action' => ['required', 'in:approve,reject']]);
        $photo->update(['status' => $data['action'] === 'approve' ? 'approved' : 'rejected']);

        if ($photo->status === 'approved' && ! $photo->place->cover_image_url) {
            $photo->place->update(['cover_image_url' => $photo->url, 'cover_image_source' => 'community', 'cover_image_author' => $photo->user?->name]);
        }

        return back()->with('status', 'Photo ' . ($photo->status === 'approved' ? 'publiée' : 'refusée') . '.');
    }

    public function hideAlert(PlaceAlert $alert)
    {
        $alert->update(['status' => 'hidden']);

        return back()->with('status', 'Alerte masquée.');
    }

    public function resolveReport(PlaceReport $report)
    {
        $report->delete();

        return back()->with('status', 'Signalement traité.');
    }
}
