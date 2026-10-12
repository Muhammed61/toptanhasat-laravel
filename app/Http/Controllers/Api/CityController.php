<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\JsonResponse;

class CityController extends Controller
{
    /** İl listesi, isme göre (Türkçe sıralama) */
    public function index(): JsonResponse
    {
        return $this->cached(City::orderBy('name')->get(['id', 'name']));
    }

    /** Bir ilin ilçeleri */
    public function districts(City $city): JsonResponse
    {
        return $this->cached($city->districts()->orderBy('name')->get(['id', 'name']));
    }

    /** İl/ilçe verisi neredeyse hiç değişmez; tarayıcı bir gün önbellekte tutabilir. */
    private function cached($data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'public, max-age=86400');
    }
}
