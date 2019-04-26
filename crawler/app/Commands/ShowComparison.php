<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class ShowComparison extends BaseCommand
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'show:comparison {--level=}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Show real count comparison between KPU and Kawal Pemilu';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        ini_set('memory_limit', '2048M');
        $level = $this->option('level') ?: 1;
        $comparisons = $this->loadData('comparisons.json');

        $comparisons = array_filter($comparisons, function ($d) {
            $a01 = array_get($d, "data.kpu.vote.01");
            $a02 = array_get($d, "data.kpu.vote.02");
            $b01 = array_get($d, "data.kawalPemilu.vote.01");
            $b02 = array_get($d, "data.kawalPemilu.vote.02");

            if (($a01 == 0 && $a02 == 0) || ($b01 == 0 && $b02 == 0)) {
                return false;
            }
            return true;
        });

        $subdistrictsCount = count($this->getExistingSubdistrictIds($comparisons));
        $tpsCount = count($comparisons);

        $subdistrictsCount = number_format($subdistrictsCount, 0);
        $tpsCount = number_format($tpsCount, 0);

        $this->info("");
        $this->info("# Perbandingan Data Real Count antara KPU dan Kawal Pemilu");
        $this->info("-----------------------------------------------------------");
        $this->info("Jumlah Kelurahan yang sudah di grab : {$subdistrictsCount}");
        $this->info("Jumlah TPS yang sudah di grab       : {$tpsCount}");

        $grouped = collect($comparisons)->groupBy("regionNames.{$level}")->toArray();
        $data = $this->resolveData($grouped);
        $this->info("");
        $this->renderTable($data['rows']);
        $this->info("");

        $this->info("# KESIMPULAN:");
        print("Karena POTENSI kesalahan input KPU pada provinsi diatas,".PHP_EOL);
        print("pasangan Jokowi Ma'ruf diuntungkan sebesar {$data['totalDiff01']} suara,".PHP_EOL);
        print("sedangkan pasangan Prabowo Sandi diuntungkan sebesar {$data['totalDiff02']} suara.".PHP_EOL);
        $this->info("");

        $this->warn("* Data yang ditampilkan hanya data yang ada di ke-2 website.");
        $this->warn("* Jika salah satu website belum menginput data pada TPS tertentu, data TPS yang sama pada website lain tidak dihitung.");
        $this->warn("");
    }

    public function resolveData($grouped): array
    {
        $rows = [];
        $total01Kpu = 0;
        $total02Kpu = 0;
        $total01KawalPemilu = 0;
        $total02KawalPemilu = 0;

        $rows[] = "---";
        $rows[] = [
            " PROVINSI ",
            " Paslon 01 (KawalPemilu -> KPU) ",
            " Paslon 02 (KawalPemilu -> KPU) ",
        ];

        $rows[] = "---";
        $num = function($number) {
            return str_pad(number_format($number, 0, ',', '.'), 8, " ", STR_PAD_LEFT);
        };

        foreach ($grouped as $province => $comparisons) {
            $sum01Kpu = collect($comparisons)->sum("data.kpu.vote.01");
            $sum02Kpu = collect($comparisons)->sum("data.kpu.vote.02");
            $sum01KawalPemilu = collect($comparisons)->sum("data.kawalPemilu.vote.01");
            $sum02KawalPemilu = collect($comparisons)->sum("data.kawalPemilu.vote.02");

            $total01Kpu += $sum01Kpu;
            $total02Kpu += $sum02Kpu;
            $total01KawalPemilu += $sum01KawalPemilu;
            $total02KawalPemilu += $sum02KawalPemilu;

            $diff01 = $sum01Kpu - $sum01KawalPemilu;
            $diff02 = $sum02Kpu - $sum02KawalPemilu;

            if ($diff01 > 0) $diff01 = "+{$diff01}";
            if ($diff02 > 0) $diff02 = "+{$diff02}";

            $rows[] = [
                " {$province} ",
                " ".$num($sum01KawalPemilu)." -> ".$num($sum01Kpu)." ($diff01) ",
                " ".$num($sum02KawalPemilu)." -> ".$num($sum02Kpu)." ($diff02) ",
            ];
        }

        $totalDiff01 = $total01Kpu - $total01KawalPemilu;
        $totalDiff02 = $total02Kpu - $total02KawalPemilu;
        if ($totalDiff01 > -1) $totalDiff01 = "+{$totalDiff01}";
        if ($totalDiff02 > -1) $totalDiff02 = "+{$totalDiff02}";

        $rows[] = "---";

        $rows[] = [
            " TOTAL ",
            " ".$num($total01KawalPemilu)." -> ".$num($total01Kpu)." ($totalDiff01) ",
            " ".$num($total02KawalPemilu)." -> ".$num($total02Kpu)." ($totalDiff02) ",
        ];
        $rows[] = "---";

        return [
            'rows' => $rows,
            'total01Kpu' => $total01Kpu,
            'total02Kpu' => $total02Kpu,
            'total01KawalPemilu' => $total01KawalPemilu,
            'total02KawalPemilu' => $total02KawalPemilu,
            'total01Kpu' => $total01Kpu,
            'total02Kpu' => $total02Kpu,
            'totalDiff01' => $totalDiff01,
            'totalDiff02' => $totalDiff02,
        ];
    }

    public function renderTable(array &$rows)
    {
        $colWidths = [];
        $colCount = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) continue;

            if (!$colCount) {
                $colCount = count($row);
            }

            foreach ($row as $i => $col) {
                $len = strlen($col);
                if (!isset($colWidths[$i]) || $colWidths[$i] < $len) {
                    $colWidths[$i] = $len;
                }
            }
        }

        $totalWidths = array_sum($colWidths) - 1;
        $separator = str_repeat("-", $totalWidths);

        foreach ($rows as $row) {
            if (!is_array($row)) {
                if ($row == "---") {
                    echo $separator.PHP_EOL;
                }
                continue;
            }
            foreach ($row as $i => $col) {
                $width = $colWidths[$i];
                $text = str_pad($col, $width, " ", STR_PAD_RIGHT);
                echo "|".$text;
                if ($i == count($row) - 1) {
                    echo "|".PHP_EOL;
                }
            }
        }
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
