<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class CrawlSubdistricts extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'crawl:subdistricts {save-path?}';

    protected $subdistricts = [];

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Crawl subdistricts from website Kawal Pemilu';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->subdistricts = $this->load();
        $this->totalTps = 0;
        $this->totalSubdistricts = 0;
        $this->crawlRegion(0);
    }

    public function crawlRegion($id = null, $depth = 0, $parentId = null)
    {
        $data = $this->grabDataRegion($id);
        if (!$data) {
            return;
        }

        $isTps = $depth == 5;
        $shouldCrawlChildrens = $depth < 3;
        $isDistrict = $depth == 3;

        $childrens = array_get($data, 'children') ?: [];
        $tpsCount = $isTps ? 1 : collect($childrens)->sum(2);

        if ($isDistrict) {
            $parentNames = array_get($data, 'parentNames');
            $parentIds = array_get($data, 'parentIds');

            $parent = [
                'country' => array_get($parentNames, 0),
                'countryId' => array_get($parentIds, 0),
                'province' => array_get($parentNames, 1),
                'provinceId' => array_get($parentIds, 1),
                'regency' => array_get($parentNames, 2),
                'regencyId' => array_get($parentIds, 2),
                'district' => array_get($data, 'name'),
                'districtId' => $id
            ];

            foreach ($childrens as $children) {
                $this->subdistricts[$children[0]] = array_merge([
                    'id' => $children[0],
                    'name' => $children[1],
                    'tpsCount' => $children[2]
                ], $parent);

                $this->totalTps += $children[2];
            }
            $this->totalSubdistricts += count($childrens);

            $path = implode(' / ', array_merge($parentNames, [$data['name']]));
            $totalTps = number_format($this->totalTps, 0, ',', '.');
            $totalSubdistricts = number_format($this->totalSubdistricts, 0, ',', '.');
            $this->info("#{$id}: {$path} [TPS: {$totalTps}, Subdistricts: {$totalSubdistricts}]");

            $this->save();
        }

        if ($shouldCrawlChildrens) {
            foreach ($childrens as $children) {
                $this->crawlRegion($children[0], $depth + 1, $id);
            }
        }
    }

    public function grabDataRegion($id, $attempts = 0)
    {
        try {
            $url = "https://kawal-c1.appspot.com/api/c/{$id}";

            $json = file_get_contents($url);
            $data = json_decode($json, true);

            if (!$data) {
                $this->warn("Failed to decode JSON from url '{$url}'.");
            }

            return $data;
        } catch (\Exception $e) {
            return $attempts < 5 ? $this->grabDataRegion($id, $attempts + 1) : null;
        }
    }

    public function load()
    {
        $file = $this->getFilePath();
        $json = file_exists($file) ? file_get_contents($file) : '';
        return json_decode($json, true) ?: [];
    }

    public function save()
    {
        $file = $this->getFilePath();
        file_put_contents($file, json_encode($this->subdistricts));
    }

    public function getFilePath()
    {
        return $this->argument('save-path') ?: base_path('data/subdistricts.json');
    }

    public function getType($depth)
    {
        $types = [
            0 => 'Country',
            1 => 'Province',
            2 => 'Regency',
            3 => 'District',
            4 => 'Subdistrict',
            5 => 'TPS'
        ];

        return array_get($types, $depth);
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
