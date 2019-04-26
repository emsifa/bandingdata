<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\Log;

class CrawlDataC1 extends BaseCommand
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'crawl:c1 {--skip-exists} {--memory-limit=} {--timeout=}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Crawl data C1 untuk seluruh TPS di website KPU dan Kawal Pemilu';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $memoryLimit = $this->option('memory-limit') ?: '2000M';
        $this->timeout = $this->option('timeout') ?: 12;
        ini_set('memory_limit', $memoryLimit);

        $subdistricts = $this->loadSubdistricts();
        $collection = $this->load();

        $existingSubdistrictIds = $this->getExistingSubdistrictIds($collection);
        $skipExists = $this->option('skip-exists');
        $existsCount = count($existingSubdistrictIds);
        $subdistrictsCount = count($subdistricts);

        $no = 0;
        $grabsCount = 0;
        foreach ($subdistricts as $id => $subdistrict) {
            if ($skipExists && in_array($id, $existingSubdistrictIds)) {
                $this->info("Skip subdistrict id {$id}");
                continue;
            }

            try {
                $path = implode('/', [
                    $subdistrict['provinceId'],
                    $subdistrict['regencyId'],
                    $subdistrict['districtId'],
                    $subdistrict['id'],
                ]);

                $urlKpu = "https://pemilu2019.kpu.go.id/static/json/hhcw/ppwp/{$path}.json";
                $urlKawalPemilu = "https://kawal-c1.appspot.com/api/c/{$id}";

                $results = $this->multiRequestJson([
                    'kpu' => $this->asyncRequest('GET', $urlKpu),
                    'kawalPemilu' => $this->asyncRequest('GET', $urlKawalPemilu),
                ], 15);

                $grabsCount++;
                if ($grabsCount % 25 == 0) {
                    $this->showStatus($grabsCount, count($collection), $existsCount + $grabsCount, $subdistrictsCount);
                }

                if (!$results['kpu'] || !$results['kawalPemilu']) {
                    if (!$results['kpu']) {
                        Log::warning("Response API KPU tidak valid. URL: '{$urlKpu}'");
                    } elseif (!$results['kawalPemilu']) {
                        Log::warning("Response API Kawal Pemilu tidak valid. URL: '{$urlKawalPemilu}'");
                    }
                    continue;
                }

                $data['kpu'] = $this->resolveDataKpu($results['kpu']);
                $data['kawalPemilu'] = $this->resolveDataKawalPemilu($results['kawalPemilu']);

                if (!$data['kpu'] || !$data['kawalPemilu']) {
                    if (!$results['kpu']) {
                        Log::warning("Invalid data API KPU. URL: '{$urlKpu}'");
                    } elseif (!$results['kawalPemilu']) {
                        Log::warning("Invalid data API Kawal Pemilu. URL: '{$urlKawalPemilu}'");
                    }
                    continue;
                }

                foreach ($data['kawalPemilu'] as $tps => $dataKawalPemilu) {
                    $key = "{$id}:{$tps}";
                    $dataKpu = array_get($data['kpu'], $tps);

                    if (!$dataKpu) {
                        // $this->warn("Data TPS {$tps}, Kelurahan '{$subdistrict['name']}' pada website KPU belum di input.");
                        continue;
                    }

                    $collection[$key] = array_merge([
                        'regionNames' => [
                            array_get($subdistrict, 'country'),
                            array_get($subdistrict, 'province'),
                            array_get($subdistrict, 'regency'),
                            array_get($subdistrict, 'district'),
                            array_get($subdistrict, 'name'),
                        ],
                        'regionIds' => [
                            array_get($subdistrict, 'countryId'),
                            array_get($subdistrict, 'provinceId'),
                            array_get($subdistrict, 'regencyId'),
                            array_get($subdistrict, 'districtId'),
                            array_get($subdistrict, 'id'),
                        ],
                        'ts' => time(),
                        'tps' => $tps,
                        'data' => [
                            'kpu' => $dataKpu,
                            'kawalPemilu' => $dataKawalPemilu,
                        ]
                    ]);

                    $hasDifferent = (
                        $dataKpu['vote']['01'] != $dataKawalPemilu['vote']['01']
                        || $dataKpu['vote']['02'] != $dataKawalPemilu['vote']['02']
                    );

                    $region = implode(" / ", $collection[$key]['regionNames']) . " / TPS: {$tps}";

                    if ($hasDifferent) {
                        $a01 = str_pad($dataKpu['vote']['01'], 3, ' ', STR_PAD_LEFT);
                        $a02 = str_pad($dataKpu['vote']['02'], 3, ' ', STR_PAD_LEFT);
                        $b01 = str_pad($dataKawalPemilu['vote']['01'], 3, ' ', STR_PAD_LEFT);
                        $b02 = str_pad($dataKawalPemilu['vote']['02'], 3, ' ', STR_PAD_LEFT);
                        $no++;
                        $this->warn("[{$no}] $region");
                        $this->info("- Jokowi Ma'ruf: KPU={$a01} | KawalPemilu={$b01}");
                        $this->info("- Prabowo Sandi: KPU={$a02} | KawalPemilu={$b02}");
                        $this->info("");
                    }
                }

                $this->save($collection);
            } catch (\Exception $e) {
                $msg = "Failed to fetch data from region id {$id} ({$subdistrict['name']}). Message: ".$e->getMessage();
                $this->error($msg);
                Log::error($msg, [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                continue;
            }
        }
    }

    public function loadSubdistricts()
    {
        $json = file_get_contents(base_path('data/subdistricts.json'));
        $subdistricts = json_decode($json, true);
        return $subdistricts;
    }

    public function load()
    {
        $file = base_path('data/comparisons.json');
        $content = file_exists($file) ? file_get_contents($file) : "";
        return json_decode($content, true) ?: [];
    }

    public function getExistingSubdistrictIds(&$collection)
    {
        $ids = [];
        foreach ($collection as $key => $_) {
            $subdistrictId = strtok($key, ":");
            if (!in_array($subdistrictId, $ids)) {
                $ids[] = $subdistrictId;
            }
        }
        return $ids;
    }

    public function save(&$collection)
    {
        $file = base_path('data/comparisons.json');
        file_put_contents($file, json_encode($collection));
    }

    public function showStatus($grabsCount, $tpsCount, $subdistrictsCount, $totalSubdistricts)
    {
        $tpsCount = number_format($tpsCount, 0);
        $grabsCount = number_format($grabsCount, 0);
        $subdistrictsCount = number_format($subdistrictsCount, 0);
        $totalSubdistricts = number_format($totalSubdistricts, 0);
        $memory = number_format(memory_get_usage() / (1024*1024), 2)." M";

        echo "[status] ".implode(" | ", [
            "grabs count = {$grabsCount}",
            "tps count = {$tpsCount}",
            "subdistricts = {$subdistrictsCount}/{$totalSubdistricts}",
            "memory = {$memory}"
        ]).PHP_EOL;
    }

    public function resolveDataKpu($data)
    {
        if (!$data) {
            return [];
        }

        // Disini KPU ngirim data formatnya kayak gini:
        // {
        //     "ts": "2019-04-23 04:00:03",             <<< ini waktu update
        //     "chart": {"21": 11844, "22": 12018},     <<< ini total vote untuk paslon 01 (21) dan 02 (22)
        //     "table": {                               <<< ini data untuk table
        //         "900185783": {  << id TPS, tapi karena berurut kita bisa anggap ini TPS 1 di kelurahan X
        //             "21": 132,  << vote untuk paslon 01
        //             "22": 114   << vote untuk paslon 02
        //         },
        //         "900185784": {  << TPS 2 di kelurahan X
        //             "21": null, << null artinya data belum di input
        //             "22": null
        //         },
        //         ...
        //     }
        // }
        // Dari data diatas kita akan ubah jadi:
        // {
        //     "1": {           << nomor TPS
        //         "images": [] << url scan C1, karena di endpoint ini tidak ada, jadi dikosongin aja
        //         "vote": {
        //             "01": 132,   << vote untuk paslon 01
        //             "02": 114    << vote untuk paslon 02
        //         }
        //     },
        //     "2": null << null artinya data belum di input
        // }

        $resolved = [];
        $dataTps = array_get($data, 'table', []);
        $n = 0;
        foreach ($dataTps as $tps) {
            $n++;
            $noTps = (string) $n;
            $vote01 = array_get($tps, '21');
            $vote02 = array_get($tps, '22');

            $resolved[$noTps] = (is_null($vote01) || is_null($vote02)) ? null : [
                'images' => [],
                'vote' => [
                    '01' => $vote01,
                    '02' => $vote02,
                ]
            ];
        }
        return $resolved;
    }

    public function resolveDataKawalPemilu($data)
    {
        if (!$data) {
            return [];
        }

        // Di kawal pemilu dia kirim data dalam struktur seperti ini:
        // {
        //     "id": 12322,       << id kelurahan
        //     "name": "PARIGI",  << nama kelurahan
        //     "depth": 4,
        //     "parentIds": [0, 6728, 12208, 12254],  << id negara, provinsi, kabkota, kecamatan
        //     "parentNames": ["IDN",  "SUMATERA UTARA", "PADANG LAWAS UTARA", "DOLOK"],  << nama negara, provinsi, kabkota, kecamatan
        //     "children": [
        //        [
        //         1,           << nomor TPS
        //         138,         << vote paslon 01
        //         127          << vote paslon 02
        //       ],
        //       [
        //         2,
        //         139,
        //         135
        //       ],
        //       [
        //         3,
        //         90,
        //         93
        //       ]
        //     ],
        //     "data": {
        //       "1": {
        //         "sum": {
        //           "pending": 1,
        //           "janggal": 0,
        //           "cakupan": 1
        //         },
        //         "ts": 0,
        //         "c1": null,
        //         "photos": {}
        //       },
        //       "2": {
        //         "sum": {
        //           "pending": 1,
        //           "janggal": 0,
        //           "cakupan": 1
        //         },
        //         "ts": 0,
        //         "c1": null,
        //         "photos": {}
        //       }
        //     }
        //   }

        // Dari data diatas kita akan ubah jadi:
        // {
        //     "1": {           << nomor TPS
        //         "images": [] << url scan C1
        //         "vote": {
        //             "01": 132,   << vote untuk paslon 01
        //             "02": 114    << vote untuk paslon 02
        //         }
        //     },
        //     "2": null << null artinya data belum di input
        // }

        $resolved = [];
        $dataData = array_get($data, 'data');
        foreach ($dataData as $noTps => $d) {
            $vote01 = array_get($d, 'sum.pas1');
            $vote02 = array_get($d, 'sum.pas2');
            $images = array_keys(array_get($d, "photos", []));

            if (is_null($vote01) || is_null($vote02)) {
                continue;
            }

            $resolved[$noTps] = [
                'images' => $images,
                'vote' => [
                    '01' => $vote01,
                    '02' => $vote02
                ]
            ];
        }
        return $resolved;
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
