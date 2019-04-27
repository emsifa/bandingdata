<template>
  <section id="about">
    <div class="about--container border-t border-b border-grey-light">
      <div ref="content" class="about--content">
        <!-- prettier-ignore -->
        <transition
          :css="false"
          @before-enter="beforeEnter"
          @enter="enter"
          @after-enter="afterEnter"
          @before-leave="beforeLeave"
          @leave="leave"
          @after-leave="afterLeave"
        >
          <div v-if="shownAbout" class="container mx-auto">
            <div class="wrapper p-6">
              <p>
                <em>Bandingdata</em> adalah situs yang dibuat khusus untuk menampilkan informasi
                perbandingan inputan formulir C1 pada pilpres 2019 antara website
                <a href="https://pemilu2019.kpu.go.id" class="text-orange" target="_blank">KPU</a>
                dengan
                <a href="https://www.kawalpemilu.org" class="text-orange" target="_blank">Kawal Pemilu</a>.
                <em>Bandingdata</em> tidak berafiliasi dengan siapapun. Data yang ditampilkan pada <em>Bandingdata</em> diambil
                melalui teknik <em>crawling</em> ke website KPU dan website Kawal Pemilu.
              </p>
              <p>
                <em>Bandingdata</em> dibuat berdasarkan keresahan masyarakat, khususnya di media sosial
                yang seringkali menemukan kesalahan input formulir C1 pada website KPU. Berdasarkan keresahan tersebut
                Bandingdata dibuat untuk mempermudah masyarakat menemukan kesalahan-kesalahan inputan C1 pada website KPU
                dengan cara membandingkannya dengan data pada website Kawal Pemilu.
              </p>
              <p>
                Diharapkan melalui informasi yang ditampilkan pada Bandingdata, awareness masyarakat untuk memperhatikan
                data real count pada website KPU semakin meningkat. Serta diharapkan masyarakat menjadi
                aktif dalam 'mengingatkan' KPU untuk memeriksa, sekaligus mengubah datanya jika memang benar terdapat kesalahan.
              </p>
              <p>
                Source code dan data Bandingdata bersifat terbuka, sehingga siapapun dapat memeriksa kode Bandingdata,
                serta dapat memeriksa riwayat perubahan kode sekaligus data pada Bandingdata.
              </p>
              <h2 class="text-orange"><strong>Apa itu Kawal Pemilu?</strong></h2>
              <p>
                Kawal Pemilu adalah proyek urun daya (crowdsourcing) netizen
                pro data Indonesia yang didirikan tahun 2014 untuk menjaga suara rakyat
                di Pemilihan Umum melalui penggunaan teknologi untuk melakukan real count secara cepat dan akurat.
              </p>
              <p>
                Kawal Pemilu dipilih karena banyaknya kontributor, serta keterbukaannya terhadap data C1 mereka.
                Kawal Pemilu juga dipilih karena siapapun (termasuk kamu) dapat berpartisipasi untuk menginput formulir C1 disana.
                Sehingga diharapkan dapat meminimalisir kecurigaan masyarakat terkait keberpihakan.
              </p>

              <h2 class="text-orange"><strong>Cara Kerja</strong></h2>
              <p>
                Bandingdata bekerja dengan 2 buah kode yang berjalan terpisah.
                Aplikasi pertama adalah Crawler, sedangkan aplikasi kedua adalah Website yang sedang kamu lihat ini.
                Alur data diambil sampai dengan tampil pada halaman ini adalah sebagai berikut:
              </p>
              <ol>
                <li>Crawler melakukan crawling untuk mengambil data voting paslon 01 dan 02 pada website KPU dan Kawal Pemilu di seluruh TPS se-Indonesia.</li>
                <li>Setelah selesai melakukan crawling, karena data cukup besar, crawler melakukan post-processing data supaya </li>
              </ol>
            </div>
          </div>
        </transition>
      </div>
      <div class="about--toggle-container">
        <button
          class="about--toggle text-center rounded-full"
          @click.prevent="$store.dispatch('toggleAbout')"
        >
          <span v-if="!shownAbout">apa itu bandingdata?</span>
          <span v-if="shownAbout">tutup</span>
        </button>
      </div>
    </div>
  </section>
</template>

<script>
import { mapState } from 'vuex'

export default {
  computed: mapState(['shownAbout']),
  methods: {
    beforeEnter(el) {
      el.style.display = 'block'
      el.style.height = 0
      el.style.opacity = 0
      el.style.overflow = 'hidden'
    },
    enter(el, done) {
      el.style.transition = 'all 250ms ease-out'
      el.style.height = el.scrollHeight + 'px'
      el.style.opacity = 1
      setTimeout(() => done(), 250) // maksa njir
    },
    afterEnter(el) {
      el.styleHeight = el.style.height
      el.style.height = 'auto'
    },
    beforeLeave(el) {
      el.style.height = el.styleHeight
    },
    leave(el, done) {
      // maksa banget njir
      setTimeout(() => {
        el.style.transition = 'all 250ms ease-out'
        el.style.height = '0px'
        el.style.opacity = 0
        setTimeout(() => done(), 250)
      }, 1)
    },
    afterLeave(el) {
      el.style.display = 'none'
      el.className = el.className.replace(/height-transition/g, '').trim()
    }
  }
}
</script>

<style>
.about--container {
  position: relative;
  @apply bg-blue-dark;
  @apply text-white;
}

.about--toggle {
  @apply px-2;
  @apply py-1;
  @apply border;
  @apply border-grey;
  @apply bg-white;
  @apply text-grey-dark;
  font-size: 0.8em;
}

.about--toggle:hover {
  @apply bg-blue;
  @apply border-blue;
  @apply text-white;
}

.about--toggle:focus {
  outline: none;
}

.about--content p {
  @apply mb-3;
  overflow: hidden;
}

.about--content .container {
  overflow: hidden;
}

.about--toggle-container {
  position: absolute;
  z-index: 99;
  margin: auto;
  top: auto;
  bottom: -13px;
  left: 0;
  width: 100%;
  text-align: center;
}
</style>
