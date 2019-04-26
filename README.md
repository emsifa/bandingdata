**BANDING**DATA
===============================

> Status: development

Bandingdata adalah situs yang menampilkan informasi perbandingan data inputan *real count* di setiap TPS
antara web [KPU](https://pemilu2019.kpu.go.id) dengan web [Kawal Pemilu](https://www.kawalpemilu.org).

Bandingdata dibuat dengan tujuan antara lain:

* Untuk mencari tahu TPS mana saja **potensi** kesalahan inputan KPU terjadi.
* Untuk melihat seberapa besar suara yang **berpotensi** hilang/bertambah pada masing-masing pasangan calon Pilpres.

## Bagaimana Cara Kerjanya?

Bandingdata terdiri dari 2 buah software, yakni crawler, dan website.

* **Crawler**: software untuk mengambil data pada ke-2 website, dan mengolahnya supaya dapat ditampilkan pada website.
* **Website**: website untuk menampilkan informasi terkait data yang sudah diambil.

Proses data diambil sampai dengan ditampilkan pada website kurang lebih sebagai berikut:

#### 1. Mengumpulkan Data Wilayah

Crawler mengumpulkan data wilayah pada website Kawal Pemilu. Data wilayah ini akan digunakan pada step berikutnya, yaitu saat crawling data *real count* per Kelurahan.

> Karena data wilayah pada Kawal Pemilu sama dengan website KPU, crawler tidak perlu mengambil data wilayah pada website KPU

Website Kawal Pemilu dipilih karena *response time*nya lebih cepat dibanding website KPU (karena mungkin lebih banyak trafik).

Data pada file ini akan disimpan di `crawler/data/subdistricts.json`.

#### 2. Mengumpulkan Data Real Count

Setelah crawler memiliki data wilayah, data wilayah digunakan untuk mengambil data *real count*.
Crawler mengambil data *real count* untuk setiap TPS dengan mengakses URL per kelurahan pada masing-masing website.

Berikut adalah contoh URL (berisi JSON) di kelurahan Cipinang Muara (ID: 26087) pada masing-masing website:

* KPU: [https://pemilu2019.kpu.go.id/static/json/hhcw/ppwp/25823/26065/26081/26087.json](https://pemilu2019.kpu.go.id/static/json/hhcw/ppwp/25823/26065/26081/26087.json)
* Kawal Pemilu: [https://kawal-c1.appspot.com/api/c/26087](https://kawal-c1.appspot.com/api/c/26087).

Jika kalian buka URL tersebut, maka masing-masing URL akan menampilkan data JSON seperti dibawah ini:

**KPU**

```
{
  "ts": "2019-04-26 06:15:04", // << waktu update
  "chart": {
    "21": 2767,                // << data pie chart untuk paslon 01
    "22": 3019                 // << data pie chart untuk paslon 02
  },
  "table": {
    "900182495": {            // << TPS 1
      "21": null,             // << null artinya data belum diinput
      "22": null
    },
    "900182496": {            // << TPS 2
      "21": 72,               // << vote paslon 01
      "22": 173               // << vote paslon 02
    },
    "900182497": {            // << TPS 3
      "21": null,
      "22": null
    },
    ... // *dst
  }
}
```

**Kawal Pemilu**

```
{
  "name": "CIPINANG MUARA", // << nama wilayah
  "parentNames": [          // << nama induk wilayah
    "IDN",
    "DKI JAKARTA",
    "JAKARTA TIMUR",
    "JATINEGARA"
  ],
  "id": 26087,              // << id wilayah
  "depth": 4,               // << kedalaman wilayah (provinsi = 1, kab/kota = 2, kecamatan = 3, kelurahan = 4)
  "data": {
    "1": { // << nomor TPS
      "photos": {              // << data untuk masing-masing foto yang di upload kontributor
        "http://lh3.googleusercontent.com/Ax_IpaAvb-jF3br-Pn4ZvWrXBCBSZ60iOPdu72A5ujAl4UlxSJa0RVHo0qUopkWmymcHA6pAjIDZV9MfaLU": {
          "ts": 1555520804756, // << waktu update
          "c1": {
            "type": 1,
            "plano": 1,
            "halaman": "2"
          },
          "sum": {
            "tSah": 1,
            "sah": 245,
            "pas2": 143,     // << vote paslon 02 (pada foto ini)
            "pas1": 102      // << vote paslon 01 (pada foto ini)
          }
        }
      },
      "sum": {
        "pending": 0,
        "pas2": 143,         // << vote paslon 02
        "tSah": 1,          
        "sah": 245,
        "janggal": 0,
        "cakupan": 1,
        "pas1": 102          // << vote paslon 01
      },
      "ts": 1555520804756,
      "c1": null
    },
    "2": { // << nomor TPS
      "ts": 1555920678905,
      "c1": null,
      "photos": {
        ... // *format data sama dengan diatas
      },
      "sum": {
        ... // *format data sama dengan diatas
      }
    },
    ... // *dst
  }
}
```

Crawler mengambil data pada 2 website tersebut pada waktu yang sama (secara concurrent).
Karena tujuan dari Bandingdata adalah untuk **membandingkan** data, maka jika salah satu website
belum menginput data pada TPS tertentu, data TPS yang sama pada website lain tidak disimpan.

Sebagai contoh pada data diatas, karena pada website KPU di TPS 1 belum diinput (masih null),
maka data TPS 1 di kelurahan Cipinang Muara tidak kami simpan.

Data pada step ini akan disimpan di file `crawler/data/comparisons.json`.

#### 3. Post Processing (Generate Static API)

Karena ukuran file data cukup besar untuk di load pada website, maka data yang sebelumnya di-*crawling* diproses
untuk dipecah menjadi bagian-bagian kecil supaya load website lebih cepat.

Pada tahap ini proses yang dilakukan adalah:

1. Memecah file `crawler/data/subdistricts.json` menjadi API:
   * '/provinces.json' yang berisi data semua provinsi
   * '/provinces/{provinceId}/regencies.json' yang berisi data kab/kota pada provinsi {provinceId}.
   * '/provinces/{provinceId}/regencies/{regencyId}/districts.json' yang berisi data kecamatan pada provinsi {provinceId} dan kab/kota {regencyId}.
   * '/provinces/{provinceId}/regencies/{regencyId}/districts/{districtId}/subdistricts.json' yang berisi data kelurahan pada provinsi {provinceId}, kab/kota {regencyId}, dan kecamatan {districtId}.
2. Memecah file `crawler/data/comparisons.json` menjadi API:
   * `/comparisons/all.json` data perbandingan secara keseluruhan (Nasional) beserta seluruh provinsi.
   * `/comparisons/provinces/{provinceId}.json` data perbandingan per provinsi beserta seluruh kab/kota di provinsi itu.
   * `/comparisons/provinces/{provinceId}/regencies/{regencyId}.json` data perbandingan per kab/kota beserta seluruh kecamatan di kab/kota itu.
   * `/comparisons/provinces/{provinceId}/regencies/{regencyId}/districts/{districtId}.json` data perbandingan per kecamatan beserta seluruh kelurahan di kecamatan itu.
   * `/comparisons/provinces/{provinceId}/regencies/{regencyId}/districts/{districtId}/subdistricts/{subdistrictId}.json` data perbandingan per kelurahan beserta seluruh TPS pada kelurahan itu.

#### 4. Menampilkan di Website

> Work in Progress

## Development

> Work in Progress