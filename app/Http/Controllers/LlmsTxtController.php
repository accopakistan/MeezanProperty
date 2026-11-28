<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Property;
use App\Support\Cities;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * /llms.txt: a short plain-text guide for AI assistants and answer engines that
 * says what Meezan Property is, where its main public sections are and how to read
 * its research labels (https://llmstxt.org). Built from routes and live counts, so
 * links never go stale. It is a convenience for these tools, not a ranking signal.
 */
class LlmsTxtController extends Controller
{
    public function __invoke(): Response
    {
        $text = Cache::remember('llms-txt', 3600, function () {
            $listings = Property::published()->where('is_demo', false)->count();
            $projects = Project::indexable()->count();
            $marla = (int) config('meezan.marla_sqft', 225);
            $contact = config('meezan.contact');
            $cities = collect(Cities::names())->map(fn ($c) => '- ['.$c.']('.route('cities.show', Cities::slug($c)).'): listings, areas, housing societies and dealers in '.$c)->implode("\n");
            $categories = collect(config('investment_index.categories'))->map(fn ($c, $k) => '- ['.$c['label'].']('.route('rankings.category', $k).')')->implode("\n");
            $statuses = collect(config('investment_index.statuses'))->map(fn ($label, $k) => '- '.$label)->implode("\n");

            return <<<TXT
            # Meezan Property

            > Meezan Property ({$this->url('home')}) is Pakistan's property search, research and investment intelligence platform. It lists homes, plots and commercial property for sale and rent in Lahore, Karachi, Islamabad, Multan, Faisalabad, Quetta and Peshawar, publishes area guides and a sourced project directory, and runs the Meezan Investment Index, which ranks developments only from verified evidence.

            Facts on this site come from listings submitted by owners and dealers (checked when submitted), from development authorities' own published lists (official sources), and from secondary public references such as news reports of those lists. Each fact cites its source and the date it was read, and secondary sources are labelled as such. Meezan Property does not certify or approve any housing scheme; an approval status repeats what its source says and is not confirmed beyond that source. Asking prices are what sellers ask, not sale prices or valuations. At the time this file was generated there were {$listings} live listings and {$projects} researched project profiles.

            ## Main sections

            - [Property search]({$this->url('properties.index')}): live listings with price, size, location and dealer
            - [Homes for sale]({$this->url('properties.buy')}), [rentals]({$this->url('properties.rent')}), [plots]({$this->url('properties.plots')}), [commercial property]({$this->url('properties.commercial')})
            - [Area guides]({$this->url('areas.index')}): neighbourhoods and housing societies with authority status and source where recorded, and asking-price medians when enough listings exist
            - [Project directory]({$this->url('projects.index')}): housing societies, plazas, apartment buildings, malls and business towers, each with its sources
            - [Property dealers]({$this->url('dealers.index')}): agencies and dealers; the verified badge appears only after document review
            - [AI Property Finder]({$this->url('ai-finder')}): describe a requirement in plain words and get matching listings with reasons

            ## Cities

            {$cities}

            ## Research and methodology

            - [Meezan Investment Index]({$this->url('rankings.index')}): research on developments across Pakistan
            - [Index methodology]({$this->url('rankings.methodology')}): factors, weights, evidence rules and why a project is or is not ranked
            - [Market insight score methodology]({$this->url('investment.methodology')}): how verified price and rent records are scored
            - [Rental yield calculator]({$this->url('investment.yield')})

            Index categories:
            {$categories}

            ## How to read the labels

            Index status of a project:
            {$statuses}

            A project is ranked only when verified evidence covers enough of the weighted factors; otherwise the page says the data is insufficient and shows no score. Pages without ranked projects are not indexed.

            Units: Meezan Property converts 1 Marla to {$marla} square feet and 1 Kanal to 20 Marla. Prices are in Pakistani rupees (PKR); 1 crore is 10,000,000 and 1 lakh is 100,000.

            ## About and contact

            - [About Meezan Property]({$this->url('pages.about')})
            - [How Meezan Property works]({$this->url('pages.how-it-works')}): listing checks, dealer verification, AI matching and lead sharing
            - [Contact]({$this->url('pages.contact')}): {$contact['email']}, {$contact['address']}
            TXT;
        });

        return response($text, 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    protected function url(string $name): string
    {
        return route($name);
    }
}
