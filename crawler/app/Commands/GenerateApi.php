<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class GenerateApi extends BaseCommand
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'generate:api';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Generate static API endpoints';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        ini_set('memory_limit', '2048M');
        $this->backupPath = $this->apiPath("../.api-backup");
        $this->backupCurrent();

        $subdistricts = $this->loadData('subdistricts.json');
        $comparisons = $this->loadData('comparisons.json');

        $this->removeZeros($comparisons);

        $this->generateApiSummary($comparisons);
        $this->generateApiRegions($subdistricts);

        $this->removeBackup();
    }

    public function backupCurrent()
    {
        if (file_exists($this->backupPath)) {
            shell_exec("rm -rf ".$this->backupPath);
        }
        rename($this->apiPath(), $this->backupPath);
    }

    public function removeBackup()
    {
        if (file_exists($this->backupPath)) {
            shell_exec("rm -rf ".$this->backupPath);
        }
    }

    public function generateApiRegions(&$subdistricts)
    {
        $regions = $this->splitRegions($subdistricts);
        $this->createApi('/provinces', $regions['provinces']);

        $regenciesByProvinces = collect($regions['regencies'])->groupBy('provinceId');
        foreach ($regenciesByProvinces as $provinceId => $regencies) {
            $this->createApi("/provinces/{$provinceId}/regencies", $regencies->toArray());
        }

        $districtsByRegencies = collect($regions['districts'])->groupBy('regencyId');
        foreach ($districtsByRegencies as $regencyId => $districts) {
            $first = $districts[0];
            $this->createApi("/provinces/{$first['provinceId']}/regencies/{$regencyId}/districts", $districts->toArray());
        }

        $subdistrictsByDistricts = collect($regions['subdistricts'])->groupBy('districtId');
        foreach ($subdistrictsByDistricts as $districtId => $subdistricts) {
            $first = $subdistricts[0];
            $this->createApi("/provinces/{$first['provinceId']}/regencies/{$first['regencyId']}/districts/{$first['districtId']}/subdistricts", $subdistricts->toArray());
        }
    }

    public function generateApiSummary(&$comparisons)
    {
        $summaries = $this->getSummaries($comparisons);
        $getApiPath = function ($regIds) {
            $regLevel = count($regIds);

            switch ($regLevel) {
                case 1: return "comparisons/all";
                case 2: return "comparisons/provinces/{$regIds[1]}";
                case 3: return "comparisons/provinces/{$regIds[1]}/regencies/{$regIds[2]}";
                case 4: return "comparisons/provinces/{$regIds[1]}/regencies/{$regIds[2]}/districts/{$regIds[3]}";
                case 5: return "comparisons/provinces/{$regIds[1]}/regencies/{$regIds[2]}/districts/{$regIds[3]}/subdistricts/{$regIds[4]}";
            }
        };

        foreach ($summaries as $regId => $summary) {
            $path = $getApiPath($summary['regionIds']);
            $data = [
                'regionNames' => $summary['regionNames'],
                'regionIds' => $summary['regionIds'],
                'ts' => $summary['ts'],
                'tpsDiffCount' => $summary['tpsDiffCount'],
                'tpsCount' => $summary['tpsCount'],
                'votes' => $summary['votes'],
                'childs' => $summary['childs'],
            ];

            $this->createApi($path, $data);
        }
    }

    public function getSummaries(&$comparisons): array
    {
        $summaries = [];

        foreach ($comparisons as $comparison) {
            $a01 = array_get($comparison, "data.kpu.vote.01");
            $a02 = array_get($comparison, "data.kpu.vote.02");
            $b01 = array_get($comparison, "data.kawalPemilu.vote.01");
            $b02 = array_get($comparison, "data.kawalPemilu.vote.02");

            $isDifferent = (($a01 != $b01) || ($a02 != $b02));

            $regLevel = count($comparison['regionIds']);
            $regionIds = [];
            $regionNames = [];
            $ts = array_get($comparison, 'ts');

            foreach ($comparison['regionIds'] as $i => $regId) {
                $regionIds[] = $regId;
                $regionNames[] = $comparison['regionNames'][$i];
                if (!isset($summaries[$regId])) {
                    $summaries[$regId] = [
                        'regionIds' => $regionIds,
                        'regionNames' => $regionNames,
                        'id' => $regId,
                        'name' => array_last($regionNames),
                        'ts' => null,
                        'tpsDiffCount' => 0,
                        'tpsCount' => 0,
                        'votes' => [
                            '01' => [
                                'kpu' => 0,
                                'kawalPemilu' => 0,
                            ],
                            '02' => [
                                'kpu' => 0,
                                'kawalPemilu' => 0
                            ]
                        ],
                        'childs' => []
                    ];
                }

                $summaries[$regId]['tpsCount']++;
                if ($isDifferent) {
                    $summaries[$regId]['tpsDiffCount']++;
                }
                $summaries[$regId]['votes']['01']['kpu'] += $a01;
                $summaries[$regId]['votes']['02']['kpu'] += $a02;
                $summaries[$regId]['votes']['01']['kawalPemilu'] += $b01;
                $summaries[$regId]['votes']['02']['kawalPemilu'] += $b02;

                if (!$summaries[$regId]['ts'] || $summaries[$regId]['ts'] < $ts) {
                    $summaries[$regId]['ts'] = $ts;
                }

                if ($i == 4) { // if level is subdistricts, push tps as childs
                    $summaries[$regId]['childs'][$comparison['tps']] = [
                        'name' => str_pad($comparison['tps'], 3, '0', STR_PAD_LEFT),
                        'ts' => $ts,
                        'votes' => [
                            '01' => [
                                'kpu' => $a01,
                                'kawalPemilu' => $b01,
                            ],
                            '02' => [
                                'kpu' => $a02,
                                'kawalPemilu' => $b02,
                            ],
                        ]
                    ];
                }
            }
        }

        foreach ($summaries as $regId => $summary) {
            $regLevel = count($summary['regionIds']);
            if ($regLevel == 1) continue;

            $parentId = $summary['regionIds'][$regLevel - 2];
            $id = array_last($summary['regionIds']);
            $summaries[$parentId]['childs'][$id] = [
                'id' => $id,
                'name' => array_last($summary['regionNames']),
                'ts' => $summary['ts'],
                'tpsCount' => $summary['tpsCount'],
                'tpsDiffCount' => $summary['tpsDiffCount'],
                'votes' => $summary['votes'],
            ];
        }

        return $summaries;
    }

    public function removeZeros(&$comparisons)
    {
        $zerosCount = 0;
        foreach ($comparisons as $key => $comparison) {
            $a01 = array_get($comparison, "data.kpu.vote.01");
            $a02 = array_get($comparison, "data.kpu.vote.02");
            $b01 = array_get($comparison, "data.kawalPemilu.vote.01");
            $b02 = array_get($comparison, "data.kawalPemilu.vote.02");

            if (($a01 == 0 && $a02 == 0) || ($b01 == 0 && $b02 == 0)) {
                unset($comparisons[$key]);
                $zerosCount++;
            }
        }
        return $zerosCount;
    }

    public function splitRegions(&$subdistricts)
    {
        $_provinces = [];
        $_regencies = [];
        $_districts = [];
        $_subdistricts = [];

        foreach ($subdistricts as $subdistrict) {
            if ($subdistrict['id'] < 0) {
                continue;
            }

            $_provinces[$subdistrict['provinceId']] = [
                'id' => $subdistrict['provinceId'],
                'name' => $subdistrict['province']
            ];
            $_regencies[$subdistrict['regencyId']] = [
                'id' => $subdistrict['regencyId'],
                'name' => $subdistrict['regency'],
                'provinceId' => $subdistrict['provinceId'],
                'provinceName' => $subdistrict['province'],
            ];
            $_districts[$subdistrict['districtId']] = [
                'id' => $subdistrict['districtId'],
                'name' => $subdistrict['district'],
                'provinceId' => $subdistrict['provinceId'],
                'provinceName' => $subdistrict['province'],
                'regencyId' => $subdistrict['regencyId'],
                'regencyName' => $subdistrict['regency'],
            ];
            $_subdistricts[$subdistrict['id']] = [
                'id' => $subdistrict['id'],
                'name' => $subdistrict['name'],
                'provinceId' => $subdistrict['provinceId'],
                'provinceName' => $subdistrict['province'],
                'regencyId' => $subdistrict['regencyId'],
                'regencyName' => $subdistrict['regency'],
                'districtId' => $subdistrict['districtId'],
                'districtName' => $subdistrict['district'],
            ];
        }

        return [
            'provinces' => $_provinces,
            'regencies' => $_regencies,
            'districts' => $_districts,
            'subdistricts' => $_subdistricts,
        ];
    }

    public function getDifferentsOnly(&$comparisons)
    {
        return array_filter($comparisons, function ($c) {
            $a01 = array_get($c, "data.kpu.vote.01");
            $a02 = array_get($c, "data.kpu.vote.02");
            $b01 = array_get($c, "data.kawalPemilu.vote.01");
            $b02 = array_get($c, "data.kawalPemilu.vote.02");

            return (($a01 != $b01) || ($a02 != $b02));
        });
    }

    public function createApi($path, array $data)
    {
        $apiDest = $this->apiPath($path.'.json');
        $dir = dirname($apiDest);
        $this->createDirIfNotExists($dir);

        $data = [
            'status' => 'ok',
            'data' => $data
        ];

        echo "Generate API '{$path}.json' ... ";
        file_put_contents($apiDest, json_encode($data));
        $this->info("OK");
    }

    public function apiPath($path = ''): string
    {
        $apiDir = str_replace("\\", "/", realpath(base_path('..')).'/static/api');
        return $path ? $apiDir.'/'.ltrim($path, '/') : $apiDir;
    }

    public function createDirIfNotExists($dir)
    {
        $paths = explode("/", $dir); // "/d/dev/web/banding-data/static/api" ["", "d", "web"]
        $p = "";
        foreach ($paths as $i => $path) {
            $p .= $i == 0 ? $path : "/{$path}";
            if (!is_dir($p) && !file_exists($p)) {
                mkdir($p);
            }
        }
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
