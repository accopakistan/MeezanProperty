<?php

namespace App\Http\Requests;

use App\Enums\AreaUnit;
use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Models\Area;
use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property
            ? $this->user()->can('update', $property)
            : $this->user()->can('create', Property::class);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * The listing form sends the location as a path of selects (area_path[]:
     * society → phase → block). Use the deepest choice that really sits under
     * the one before it, so a stale lower select never wins.
     */
    protected function prepareForValidation(): void
    {
        $path = array_values(array_filter((array) $this->input('area_path', []), fn ($v) => is_numeric($v)));
        if (! $path) {
            return;
        }

        $parents = Area::whereIn('id', $path)->pluck('parent_id', 'id');
        $chosen = null;
        foreach ($path as $id) {
            $id = (int) $id;
            if (! $parents->has($id) || ($chosen !== null && (int) $parents[$id] !== $chosen)) {
                break;
            }
            $chosen = $id;
        }

        $this->merge(['area_id' => $chosen]);
    }

    public function rules(): array
    {
        $maxImages = (int) config('meezan.listing.max_images');
        $hasRooms = PropertyType::tryFrom((string) $this->input('type'))?->hasRooms() ?? false;
        $bounds = config('meezan.map.pakistan_bounds');

        return [
            'title' => ['required', 'string', 'min:10', 'max:160'],
            'purpose' => ['required', Rule::enum(Purpose::class)],
            'type' => ['required', Rule::enum(PropertyType::class)],
            'city' => ['required', Rule::in(config('meezan.cities'))],
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')->where('city', $this->input('city'))->where('is_active', true)],
            'address' => ['nullable', 'string', 'max:255'],
            // Exact location is optional, but always a pair inside Pakistan.
            'location_privacy' => ['nullable', Rule::in(array_keys(Property::LOCATION_PRIVACY))],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:'.$bounds['south'].','.$bounds['north']],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:'.$bounds['west'].','.$bounds['east']],
            'price' => ['required', 'integer', 'min:1000', 'max:50000000000'],
            'area_size' => ['required', 'numeric', 'min:0.25', 'max:1000000'],
            'area_unit' => ['required', Rule::enum(AreaUnit::class)],
            'bedrooms' => [$hasRooms ? 'required' : 'nullable', 'integer', 'between:0,30'],
            'bathrooms' => ['nullable', 'integer', 'between:0,30'],
            'furnishing' => ['nullable', Rule::in(['unfurnished', 'semi-furnished', 'furnished'])],
            'covered_area_sqft' => ['nullable', 'integer', 'min:50', 'max:1000000'],
            'completion_status' => ['nullable', Rule::in(array_keys(Property::COMPLETION))],
            'authorization_type' => ['required', Rule::in(array_keys(Property::AUTHORIZATION))],
            'authorization_confirmed' => ['accepted'],
            // Placeholder text is never published as a property description.
            'description' => ['required', 'string', 'min:50', 'max:5000', 'not_regex:/lorem\s+ipsum|dolor\s+sit\s+amet|placeholder\s+text/i'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => [Rule::in(array_keys(config('meezan.amenities')))],
            'images' => ['nullable', 'array', 'max:'.$maxImages],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('meezan.listing.max_image_kb'), 'dimensions:min_width=400,min_height=300,max_width=8000,max_height=8000'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
            'image_order' => ['nullable', 'array', 'max:50'],
            'image_order.*' => ['integer'],
            'cover_image' => ['nullable', 'integer'],
            'action' => ['nullable', Rule::in(['draft', 'submit'])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $property = $this->route('property');
                $existing = $property ? $property->images()->whereNotIn('id', $this->input('remove_images', []))->count() : 0;

                if ($existing + count($this->file('images', [])) > (int) config('meezan.listing.max_images')) {
                    $validator->errors()->add('images', 'A listing can have at most '.config('meezan.listing.max_images').' photos.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'images.*.dimensions' => 'Each photo must be between 400 × 300 and 8000 × 8000 pixels.',
            'images.*.image' => 'Each photo must be a JPG, PNG or WebP image.',
            'images.*.mimes' => 'Each photo must be a JPG, PNG or WebP image.',
            'images.*.uploaded' => 'A photo could not be uploaded. It may be larger than 5 MB.',
            'images.*.max' => 'Each photo must be smaller than 5 MB.',
            'price.min' => 'Enter the full price in rupees, e.g. 25000000 for 2.5 crore.',
            'latitude.between' => 'This location is outside Pakistan. Place the pin on the property.',
            'longitude.between' => 'This location is outside Pakistan. Place the pin on the property.',
            'latitude.numeric' => 'Latitude must be a number, e.g. 31.4697.',
            'longitude.numeric' => 'Longitude must be a number, e.g. 74.4106.',
            'latitude.required_with' => 'Enter both latitude and longitude, or clear the pin.',
            'longitude.required_with' => 'Enter both latitude and longitude, or clear the pin.',
            'area_id.required' => 'Choose the area or society.',
            'authorization_type.required' => 'Tell us your relationship to this property.',
            'authorization_confirmed.accepted' => 'Please confirm you are the owner or are authorised to market this property.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listingData(): array
    {
        $data = $this->safe()->except(['images', 'remove_images', 'image_order', 'cover_image', 'action', 'authorization_type', 'authorization_confirmed']);

        if (! PropertyType::from($data['type'])->hasRooms()) {
            $data['bedrooms'] = null;
            $data['furnishing'] = null;
            // Covered area is built-up area; open plots have none.
            if (PropertyType::from($data['type'])->group() === 'plots') {
                $data['covered_area_sqft'] = null;
            }
        }

        $data['amenities'] = array_values($data['amenities'] ?? []);
        if (empty($data['location_privacy'])) {
            unset($data['location_privacy']); // keep the listing's current choice
        }

        // Store at the column's precision (about 1 cm); an empty pair clears the pin.
        foreach (['latitude', 'longitude'] as $axis) {
            if (array_key_exists($axis, $data)) {
                $data[$axis] = $data[$axis] === null ? null : round((float) $data[$axis], 7);
            }
        }

        return $data;
    }
}
