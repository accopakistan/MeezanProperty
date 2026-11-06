<?php

namespace App\Models;

use App\Enums\AreaUnit;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Services\ListingCompleteness;
use App\Support\Format;
use App\Support\Geo;
use App\Support\SampleContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Property extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Dealer-editable attributes. Status, moderation fields, featured and
     * demo flags are only changed through explicit application code.
     */
    protected $fillable = [
        'title',
        'purpose',
        'type',
        'city',
        'area_id',
        'address',
        'latitude',
        'longitude',
        'location_privacy',
        'price',
        'area_size',
        'area_unit',
        'bedrooms',
        'bathrooms',
        'furnishing',
        'completion_status',
        'covered_area_sqft',
        'description',
        'amenities',
    ];

    /** Pakistani completion / possession states. Plots use the possession values. */
    /** Published within this many days: "New". */
    public const NEW_DAYS = 7;

    /** A price cut within this many days: "Price reduced". */
    public const REDUCED_DAYS = 90;

    public const COMPLETION = [
        'ready' => 'Ready to move',
        'under_construction' => 'Under construction',
        'possession' => 'Possession available',
        'no_possession' => 'Possession pending',
    ];

    /**
     * How the listing reached Meezan Property: the basis for publishing it. A dealer's
     * or owner's own submission, or an import under a named agreement. An advert seen
     * on another portal is not a basis.
     */
    public const PUBLICATION_BASES = [
        'direct_submission' => 'Submitted by the dealer or owner on Meezan Property',
        'authorized_feed' => 'Authorised feed from the listing agency',
        'licence' => 'Licensed data',
        'partnership' => 'Data partnership',
    ];

    /** The lister's declared relationship to the property (not legally verified). */
    public const AUTHORIZATION = [
        'owner' => 'I am the owner',
        'authorized_agent' => 'I am a dealer authorised by the owner',
        'developer' => 'I am the developer / builder',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => Purpose::class,
            'type' => PropertyType::class,
            'status' => PropertyStatus::class,
            'area_unit' => AreaUnit::class,
            'amenities' => 'array',
            'price' => 'integer',
            'area_size' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
            'public_latitude' => 'float',
            'public_longitude' => 'float',
            'location_verified_at' => 'datetime',
            'review_flags' => 'array',
            'flagged_at' => 'datetime',
            'is_featured' => 'boolean',
            'is_demo' => 'boolean',
            'source_verified_at' => 'datetime',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'expiry_reminder_sent_at' => 'datetime',
            'authorization_confirmed_at' => 'datetime',
            'covered_area_sqft' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Property $property) {
            // A listing created through the site is its lister's own submission; imports name their basis.
            if (! $property->exists && blank($property->publication_basis) && blank($property->source_url)) {
                $property->publication_basis = 'direct_submission';
            }
            // A listing by a test account is a sample from the start, so it can never become public.
            if (! $property->exists && $property->user_id && SampleContent::isTestAccount(User::find($property->user_id))) {
                $property->is_demo = true;
            }

            // No basis, no publication: whatever the route (approval, renewal, a script).
            if ($property->status === PropertyStatus::Published && $property->isDirty('status') && ! array_key_exists((string) $property->publication_basis, self::PUBLICATION_BASES)) {
                throw new \DomainException('This listing has no publication basis, so it cannot be published.');
            }

            if ($property->area_unit && $property->area_size) {
                $property->area_sqft = $property->area_unit->toSquareFeet($property->area_size);
            }

            if (! $property->slug || $property->isDirty('title')) {
                $property->slug = static::uniqueSlug($property->title, $property->id);
            }

            $property->applyLocationRules();
            $property->completeness_score = ListingCompleteness::for($property)['score'];
        });

        // Asking-price history: recorded only for listings that have been public.
        static::updated(function (Property $property) {
            if ($property->wasChanged('price') && $property->published_at && $property->getOriginal('price')) {
                $property->priceChanges()->create([
                    'old_price' => (int) $property->getOriginal('price'),
                    'new_price' => $property->price,
                    'changed_at' => now(),
                ]);
            }
        });

        static::created(function (Property $property) {
            $property->forceFill(['reference' => 'MP-'.str_pad((string) $property->id, 6, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug(Str::limit($title, 150, '')) ?: 'property';
        $slug = $base;
        $i = 2;

        while (static::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // Relationships -----------------------------------------------------

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function coverImage(): HasOne
    {
        return $this->hasOne(PropertyImage::class)->where('status', 'active')->orderBy('sort_order')->orderBy('id');
    }

    /** Photos the public may see (moderated-out photos are hidden, not deleted). */
    public function publicImages(): HasMany
    {
        return $this->images()->where('status', 'active');
    }

    /* Location privacy and quality ----------------------------------- */

    public const LOCATION_PRIVACY = [
        'exact' => 'Show the exact location',
        'approximate' => 'Show an approximate location (within about 500 m)',
        'area' => 'Show the area only',
    ];

    public const LOCATION_STATUSES = [
        'none' => 'No exact pin',
        'approximate' => 'Approximate area location',
        'exact' => 'Exact location supplied',
        'verified' => 'Location verified',
        'flagged' => 'Location flagged',
    ];

    /** Grid used to blur an approximate location (≈ 550 m north–south). */
    protected const APPROXIMATE_GRID = 0.005;

    /**
     * Derive the public coordinates and the location-quality state from the
     * dealer's pin and privacy choice. The area centre is never copied into
     * the pin. A pin far from the chosen city or area is flagged for review,
     * not rejected: city boundaries are not known precisely.
     */
    public function applyLocationRules(): void
    {
        if ($this->relationLoaded('area') && $this->area?->id !== $this->area_id) {
            $this->unsetRelation('area');
        }
        $this->location_privacy = array_key_exists((string) $this->location_privacy, self::LOCATION_PRIVACY) ? $this->location_privacy : 'exact';

        if (! $this->hasCoordinates()) {
            $this->public_latitude = $this->public_longitude = null;
            $this->location_status = $this->area?->hasCoordinates() ? 'approximate' : 'none';
            $this->location_flag_reason = null;
            $this->location_verified_at = null;
            $this->location_verified_by = null;

            return;
        }

        [$this->public_latitude, $this->public_longitude] = match ($this->location_privacy) {
            'exact' => [$this->latitude, $this->longitude],
            'approximate' => [
                floor($this->latitude / self::APPROXIMATE_GRID) * self::APPROXIMATE_GRID + self::APPROXIMATE_GRID / 2,
                floor($this->longitude / self::APPROXIMATE_GRID) * self::APPROXIMATE_GRID + self::APPROXIMATE_GRID / 2,
            ],
            default => [null, null],
        };

        // A moved pin, or a new area/city, needs a fresh check; an admin's
        // "verified" or "flagged" decision stands while nothing changes.
        if ($this->exists && ! $this->isDirty(['latitude', 'longitude', 'area_id', 'city']) && in_array($this->location_status, ['verified', 'flagged'], true)) {
            return;
        }

        $this->location_verified_at = null;
        $this->location_verified_by = null;
        $this->location_flag_reason = $this->locationMismatch();
        $this->location_status = $this->location_flag_reason ? 'flagged' : 'exact';
    }

    /** Why the pin looks wrong for the stated city/area, or null. */
    public function locationMismatch(): ?string
    {
        $point = [$this->latitude, $this->longitude];
        $cityCentre = City::centre($this->city);
        $cityRadius = (float) (config('meezan.map.city_radius_km')[$this->city] ?? config('meezan.map.city_radius_km.default', 40));
        if ($cityCentre && ($km = Geo::km($cityCentre, $point)) > $cityRadius) {
            return sprintf('Pin is %.0f km from the centre of %s (expected within about %d km).', $km, $this->city, $cityRadius);
        }

        $area = $this->area;
        $limit = (float) config('meezan.map.area_flag_km', 10);
        if ($area?->hasCoordinates() && ($km = Geo::km([$area->latitude, $area->longitude], $point)) > $limit) {
            return sprintf('Pin is %.1f km from the approximate centre of %s.', $km, $area->name);
        }

        return null;
    }

    /** @return array{0: float, 1: float}|null what public maps may show */
    public function publicCoordinates(): ?array
    {
        return $this->public_latitude !== null && $this->public_longitude !== null ? [(float) $this->public_latitude, (float) $this->public_longitude] : null;
    }

    public function showsExactLocation(): bool
    {
        return $this->location_privacy === 'exact' && $this->publicCoordinates() !== null;
    }

    public function showsApproximateLocation(): bool
    {
        return $this->location_privacy === 'approximate' && $this->publicCoordinates() !== null;
    }

    /** Recount completeness after photos change outside a normal save. */
    public function refreshCompleteness(): void
    {
        $this->unsetRelation('images');
        $this->forceFill(['completeness_score' => ListingCompleteness::for($this)['score']])->saveQuietly();
    }

    public function locationStatusLabel(): string
    {
        return self::LOCATION_STATUSES[$this->location_status] ?? 'No exact pin';
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function priceChanges(): HasMany
    {
        return $this->hasMany(PropertyPriceChange::class)->orderByDesc('changed_at');
    }

    public function latestPriceChange(): HasOne
    {
        return $this->hasOne(PropertyPriceChange::class)->latestOfMany('changed_at');
    }

    /**
     * Listings whose asking price was lowered to today's price within the last
     * REDUCED_DAYS (a later increase changes the price, so it no longer matches).
     */
    public function scopeRecentlyReduced(Builder $query): Builder
    {
        return $query->whereExists(fn ($q) => $q->selectRaw('1')
            ->from('property_price_changes')
            ->whereColumn('property_price_changes.property_id', 'properties.id')
            ->whereColumn('property_price_changes.new_price', '<', 'property_price_changes.old_price')
            ->whereColumn('property_price_changes.new_price', 'properties.price')
            ->where('property_price_changes.changed_at', '>=', now()->subDays(self::REDUCED_DAYS)));
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ListingReport::class);
    }

    public function savedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_properties')->withTimestamps();
    }

    // Scopes ------------------------------------------------------------

    /**
     * Listings that may be shown to the public: approved, not expired and not
     * sample data. Sample listings never reach a public page, even if one is
     * left published by mistake.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PropertyStatus::Published)->where('is_demo', false)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeInGroup(Builder $query, string $group): Builder
    {
        return $query->whereIn('type', array_map(fn ($t) => $t->value, PropertyType::inGroup($group)));
    }

    public function scopeForCards(Builder $query): Builder
    {
        return $query->with(['area:id,name,slug', 'coverImage', 'latestPriceChange', 'dealer.dealerProfile:id,user_id,agency_name,verification_status,business_type,public_phone,whatsapp_number,is_demo']);
    }

    // Presentation ------------------------------------------------------

    public function isPublic(): bool
    {
        return $this->status === PropertyStatus::Published && ! $this->is_demo
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    // Freshness ----------------------------------------------------------

    /**
     * The reduction behind a "Price reduced" badge, from the eager-loaded
     * latestPriceChange (never lazy-loaded), or null.
     */
    public function recentReduction(): ?PropertyPriceChange
    {
        $change = $this->relationLoaded('latestPriceChange') ? $this->latestPriceChange : null;

        return $change
            && $change->new_price < $change->old_price
            && $change->new_price === $this->price
            && $change->changed_at->gt(now()->subDays(self::REDUCED_DAYS)) ? $change : null;
    }

    public function isNewListing(): bool
    {
        return (bool) $this->published_at?->gt(now()->subDays(self::NEW_DAYS));
    }

    /** Whole days since first published, for "days on Meezan Property". */
    public function daysListed(): ?int
    {
        return $this->published_at ? (int) $this->published_at->startOfDay()->diffInDays(now()->startOfDay()) : null;
    }

    // Expiry lifecycle -------------------------------------------------

    /** Past its end date, whether or not the daily job has marked it Expired yet. */
    public function hasLapsed(): bool
    {
        return $this->status === PropertyStatus::Expired
            || ($this->status === PropertyStatus::Published && $this->expires_at?->isPast());
    }

    /**
     * Whether the dealer can renew in one click (no new review): a live
     * listing close to expiry, or one that lapsed within the grace period.
     */
    public function canBeRenewed(): bool
    {
        if (! $this->expires_at || ! in_array($this->status, [PropertyStatus::Published, PropertyStatus::Expired], true)) {
            return false;
        }

        if ($this->hasLapsed()) {
            return $this->expires_at->gt(now()->subDays((int) config('meezan.listing.renew_grace_days')));
        }

        return $this->expires_at->lt(now()->addDays((int) config('meezan.listing.renew_window_days')));
    }

    /** Expired too long ago to renew directly; it must be resubmitted for review. */
    public function needsReviewToRelist(): bool
    {
        return $this->hasLapsed() && ! $this->canBeRenewed();
    }

    public function locationLabel(): string
    {
        return collect([$this->area?->name, $this->city])->filter()->implode(', ');
    }

    public function priceLabel(): string
    {
        return Format::price($this->price).($this->purpose === Purpose::Rent ? ' / month' : '');
    }

    public function areaLabel(): string
    {
        return Format::number($this->area_size).' '.$this->area_unit->label();
    }

    public function coverUrl(): ?string
    {
        $image = $this->relationLoaded('coverImage') ? $this->coverImage : $this->images->first();

        return $image?->url();
    }

    /** Thumbnail of the cover photo for cards, lists and map pins. */
    public function coverThumbUrl(): ?string
    {
        $image = $this->relationLoaded('coverImage') ? $this->coverImage : $this->images->first();

        return $image?->thumbUrl();
    }

    public function placeholderUrl(): string
    {
        return asset('images/placeholders/'.$this->type->group().'.svg');
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function completionLabel(): ?string
    {
        return $this->completion_status ? (self::COMPLETION[$this->completion_status] ?? null) : null;
    }

    public function coveredAreaLabel(): ?string
    {
        return $this->covered_area_sqft ? number_format($this->covered_area_sqft).' Sq. Ft.' : null;
    }

    /** Price per marla of land, for sale listings measured in marla or kanal. */
    public function pricePerMarla(): ?int
    {
        if ($this->purpose === Purpose::Rent || ! in_array($this->area_unit, [AreaUnit::Marla, AreaUnit::Kanal], true)) {
            return null;
        }
        $marlas = $this->area_sqft / (int) config('meezan.marla_sqft', 225);

        return $marlas > 0 ? (int) round($this->price / $marlas) : null;
    }

    /** "Owner" when a private owner listed it, otherwise the dealer's agency. */
    public function listerIsOwner(): bool
    {
        return $this->dealer?->dealerProfile?->business_type === 'owner';
    }

    public function hasVerifiedDealer(): bool
    {
        return (bool) $this->dealer?->dealerProfile?->isVerified();
    }

    /**
     * @return list<string>
     */
    public function amenityLabels(): array
    {
        $all = config('meezan.amenities');

        return array_values(array_filter(array_map(fn ($key) => $all[$key] ?? null, $this->amenities ?? [])));
    }

    public function deleteImages(): void
    {
        foreach ($this->images as $image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
        }
    }
}
