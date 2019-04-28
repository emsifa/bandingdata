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
                Bandingdata adalah situs yang dibuat untuk menampilkan informasi
                perbandingan inputan real count berbasiskan formulir C1
                pada 2 website, yakni
                <a href="https://pemilu2019.kpu.go.id" class="text-orange-light" target="_blank">KPU</a>
                dan
                <a href="https://www.kawalpemilu.org" class="text-orange-light" target="_blank">Kawal Pemilu</a>.
              </p>
              <p>
                Bandingdata dibuat berdasarkan keresahan masyarakat, khususnya di media sosial
                yang seringkali menemukan kesalahan input formulir C1 pada website KPU. Berdasarkan keresahan tersebut
                Bandingdata dibuat untuk mempermudah masyarakat menemukan kesalahan-kesalahan inputan C1 pada website KPU
                dengan cara membandingkannya dengan data pada website Kawal Pemilu.
              </p>
              <p>
                Tujuan dari bandingdata antara lain:
              </p>
              <ul>
                <li>Untuk mencari tahu di TPS mana saja potensi kesalahan inputan KPU terjadi.</li>
                <li>Untuk melihat seberapa besar suara yang berpotensi hilang/bertambah pada masing-masing pasangan calon Pilpres.</li>
              </ul>
              <br>
              <p>
                Source code dan data Bandingdata bersifat terbuka, sehingga siapapun dapat memeriksa kode Bandingdata,
                serta dapat memeriksa riwayat perubahan kode sekaligus data pada Bandingdata.
              </p>
              <p>
                Untuk lebih detail tentang cara kerja Bandingdata, kamu dapat lihat pada
                halaman repository Bandingdata di <a class="text-orange-light" href="https://github.com/emsifa/bandingdata">https://github.com/emsifa/bandingdata</a>.
              </p>
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
  @apply border-grey-light;
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
