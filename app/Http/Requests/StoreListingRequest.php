<?php

namespace App\Http\Requests;

use App\Enums\ListingType;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreListingRequest extends FormRequest
{
    public function rules(): array
    {
        // Katalog panelden yönetilir: sadece aktif kategori ve o kategorideki aktif ürün kabul edilir
        $category = Category::where('slug', (string) $this->input('category'))->where('is_active', true)->first();

        return [
            'type' => ['required', Rule::enum(ListingType::class)],
            'category' => ['required', Rule::exists('categories', 'slug')->where('is_active', true)],
            'product' => ['required', Rule::exists('products', 'name')->where('category_id', $category?->id ?? 0)->where('is_active', true)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'unit' => ['required', Rule::in(array_keys(config('catalog.units')))],
            'price_negotiable' => ['boolean'],
            'price' => [Rule::excludeIf($this->boolean('price_negotiable')), 'required', 'numeric', 'gt:0', 'max:99999999'],
            'available_on' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'district_id' => ['required', 'integer', Rule::exists('districts', 'id')->where('city_id', $this->integer('city_id'))],
            'show_phone' => ['boolean'],
            'accept_responsibility' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute zorunludur.',
            'category.required' => 'Kategori seçin.',
            'product.required' => 'Ürün seçin.',
            'amount.required' => 'Miktarı girin.',
            'price.required' => 'Fiyatı girin veya "Fiyat görüşülür"ü işaretleyin.',
            'city_id.required' => 'İl seçin.',
            'district_id.required' => 'İlçe seçin.',
            'numeric' => ':attribute bir sayı olmalı.',
            'gt' => ':attribute sıfırdan büyük olmalı.',
            'max' => ':attribute çok büyük.',
            'in' => 'Geçerli bir :attribute seçin.',
            'exists' => 'Geçerli bir :attribute seçin.',
            'district_id.exists' => 'Seçilen ilçe bu ile ait değil.',
            'date' => 'Geçerli bir tarih girin.',
            'description.max' => 'Açıklama en fazla 1000 karakter olabilir.',
            'accept_responsibility.accepted' => 'Devam etmek için ilan bilgilerinin doğruluğunu onaylamalısınız.',
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'İlan türü',
            'category' => 'kategori',
            'product' => 'ürün',
            'amount' => 'Miktar',
            'unit' => 'birim',
            'price' => 'Fiyat',
            'city_id' => 'il',
            'district_id' => 'ilçe',
        ];
    }
}
