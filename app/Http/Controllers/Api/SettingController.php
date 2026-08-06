<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShopSettingResource;
use App\Repositories\PixelRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingController extends Controller
{
    protected PixelRepository $pixelRepo;

    public function __construct(PixelRepository $pixelRepo)
    {
        $this->pixelRepo = $pixelRepo;
    }

    public function index()
    {
        $shop = Auth::user();
        
        $localeLabelMap = [
            'ar' => 'Arabic', 'ca' => 'Catalan', 'de' => 'German', 'en' => 'English', 'es' => 'Spanish',
            'fr' => 'French', 'hi' => 'Hindi', 'it' => 'Italian', 'ja' => 'Japanese', 'pt' => 'Portuguese',
            'zh-cn' => 'Chinese (Simplified)',
        ];
        
        $languageOptions = collect($localeLabelMap)->map(function ($label, $value) {
            return ['value' => $value, 'label' => $label];
        })->values();

        $settings = $this->pixelRepo->getSettingsByUserId($shop->id);

        return response()->json([
            'success' => true,
            'languageOptions' => $languageOptions,
            'settings' => new ShopSettingResource($settings),
            'shop' => $shop->name,
        ]);
    }

    public function store(Request $request)
    {
        $shop = Auth::user();
        
        $settings = $this->pixelRepo->updateOrCreateSettings($shop->id, [
            'pixel_id' => $request->input('pixel_id', ''),
            'capi_key' => $request->input('capi_key'),
            'advertiser_key' => $request->input('advertiser_key'),
            'tracking_enabled' => $request->boolean('tracking_enabled', true),
            'pixel_helper_enabled' => $request->boolean('pixel_helper_enabled', true),
        ]);

        return response()->json([
            'success' => true,
            'settings' => new ShopSettingResource($settings),
        ]);
    }
}
