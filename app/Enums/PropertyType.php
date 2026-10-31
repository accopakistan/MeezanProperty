<?php

namespace App\Enums;

enum PropertyType: string
{
    case House = 'house';
    case Apartment = 'apartment';
    case UpperPortion = 'upper_portion';
    case LowerPortion = 'lower_portion';
    case Farmhouse = 'farmhouse';
    case Penthouse = 'penthouse';
    case ResidentialPlot = 'residential_plot';
    case CommercialPlot = 'commercial_plot';
    case AgriculturalLand = 'agricultural_land';
    case PlotFile = 'plot_file';
    case Shop = 'shop';
    case Office = 'office';
    case Warehouse = 'warehouse';
    case Building = 'building';
    case Factory = 'factory';

    public function label(): string
    {
        return match ($this) {
            self::House => 'House',
            self::Apartment => 'Apartment',
            self::UpperPortion => 'Upper Portion',
            self::LowerPortion => 'Lower Portion',
            self::Farmhouse => 'Farmhouse',
            self::Penthouse => 'Penthouse',
            self::ResidentialPlot => 'Residential Plot',
            self::CommercialPlot => 'Commercial Plot',
            self::AgriculturalLand => 'Agricultural Land',
            self::PlotFile => 'Plot File',
            self::Shop => 'Shop',
            self::Office => 'Office',
            self::Warehouse => 'Warehouse',
            self::Building => 'Building',
            self::Factory => 'Factory',
        };
    }

    /**
     * The top-level category used for navigation: homes, plots or commercial.
     */
    public function group(): string
    {
        return match ($this) {
            self::House, self::Apartment, self::UpperPortion, self::LowerPortion, self::Farmhouse, self::Penthouse => 'homes',
            self::ResidentialPlot, self::CommercialPlot, self::AgriculturalLand, self::PlotFile => 'plots',
            self::Shop, self::Office, self::Warehouse, self::Building, self::Factory => 'commercial',
        };
    }

    public function hasRooms(): bool
    {
        return $this->group() === 'homes';
    }

    /**
     * @return list<self>
     */
    public static function inGroup(string $group): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => $type->group() === $group));
    }

    public static function groupLabel(string $group): string
    {
        return match ($group) {
            'homes' => 'Homes',
            'plots' => 'Plots',
            'commercial' => 'Commercial',
            default => ucfirst($group),
        };
    }
}
