<template>
  <section>
    <div v-if="comparisons.hasData" class="mb-6">
      <nav v-if="regionPaths.length > 1" class="rounded font-sans w-full">
        <ol class="breadcrumb list-reset text-grey-dark">
          <li v-for="(region, i) in regionPaths" :key="i">
            <a
              v-if="i < regionPaths.length - 1"
              class="text-blue font-bold"
              href="#"
              @click.prevent="fetchComparisons(region.regionIds)"
            >
              {{ i == 0 ? 'ALL' : region.name }}
            </a>
            <span v-if="regionPaths.length - 1 == i">
              {{ i == 0 ? 'ALL' : region.name }}
            </span>
          </li>
        </ol>
      </nav>
      <div class="table-responsive">
        <table class="table shadow-lg bg-white" style="min-width: 600px">
          <thead>
            <tr class="bg-white">
              <td>
                <!-- eslint-disable -->
                <strong>
                  {{ regionPaths.length < 5 ? 'WILAYAH' : 'TPS' }}
                </strong>
              </td>
              <td v-if="regionPaths.length < 5">
                <strong>JUMLAH TPS</strong>
                <br />
                <small>(Yang memiliki data berbeda)</small>
              </td>
              <td>
                <strong>JOKOWI MA'RUF</strong>
                <br />
                <small>(Kawal Pemilu &rarr; KPU)</small>
              </td>
              <td>
                <strong>PRABOWO SANDI</strong>
                <br />
                <small>(Kawal Pemilu &rarr; KPU)</small>
              </td>
            </tr>
          </thead>
          <tbody>
            <!-- prettier-ignore -->
            <tr v-for="(data, id) in comparisons.childs" :key="id">
              <td>
                <a @click.prevent="showData(data)" href="#" class="text-blue-dark nav-link">{{ data.name }}</a>
                <small class="text-grey-darker">(<timeago :datetime="toDate(data.ts)" :auto-update="60"></timeago>)</small>
              </td>
              <td v-if="regionPaths.length < 5" class="text-center">{{ data.tpsDiffCount | number }}</td>
              <td class="text-center">
                {{ data.votes['01']['kpu'] | number }}
                &rarr;
                {{ data.votes['01']['kawalPemilu'] | number }}
                <strong :class="{
                  'text-green': (data.votes['01']['kawalPemilu'] - data.votes['01']['kpu']) > 0,
                  'text-red': (data.votes['01']['kawalPemilu'] - data.votes['01']['kpu']) < 0,
                  'text-grey': (data.votes['01']['kawalPemilu'] - data.votes['01']['kpu']) == 0,
                }">
                  ({{ (data.votes['01']['kawalPemilu'] - data.votes['01']['kpu']) | number | signed }})
                </strong>
              </td>
              <td class="text-center">
                {{ data.votes['02']['kpu'] | number }}
                &rarr;
                {{ data.votes['02']['kawalPemilu'] | number }}
                <strong :class="{
                  'text-green': (data.votes['02']['kawalPemilu'] - data.votes['02']['kpu']) > 0,
                  'text-red': (data.votes['02']['kawalPemilu'] - data.votes['02']['kpu']) < 0,
                  'text-grey': (data.votes['02']['kawalPemilu'] - data.votes['02']['kpu']) == 0,
                }">
                  ({{ (data.votes['02']['kawalPemilu'] - data.votes['02']['kpu']) | number | signed }})
                </strong>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <!-- prettier-ignore -->
            <tr>
              <td>TOTAL</td>
              <td v-if="regionPaths.length < 5" class="text-center">{{ comparisons.tpsDiffCount | number }}</td>
              <td class="text-center">
                {{ comparisons.votes['01']['kpu'] | number }}
                &rarr;
                {{ comparisons.votes['01']['kawalPemilu'] | number }}
                <strong :class="{
                  'text-green': (comparisons.votes['01']['kawalPemilu'] - comparisons.votes['01']['kpu']) > 0,
                  'text-red': (comparisons.votes['01']['kawalPemilu'] - comparisons.votes['01']['kpu']) < 0,
                  'text-grey': (comparisons.votes['01']['kawalPemilu'] - comparisons.votes['01']['kpu']) == 0,
                }">
                  ({{ (comparisons.votes['01']['kawalPemilu'] - comparisons.votes['01']['kpu']) | number | signed }})
                </strong>
              </td>
              <td class="text-center">
                {{ comparisons.votes['02']['kpu'] | number }}
                &rarr;
                {{ comparisons.votes['02']['kawalPemilu'] | number }}
                <strong :class="{
                  'text-green': (comparisons.votes['02']['kawalPemilu'] - comparisons.votes['02']['kpu']) > 0,
                  'text-red': (comparisons.votes['02']['kawalPemilu'] - comparisons.votes['02']['kpu']) < 0,
                  'text-grey': (comparisons.votes['02']['kawalPemilu'] - comparisons.votes['02']['kpu']) == 0,
                }">
                  ({{ (comparisons.votes['02']['kawalPemilu'] - comparisons.votes['02']['kpu']) | number | signed }})
                </strong>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </section>
</template>

<script>
import { mapState, mapActions, mapGetters } from 'vuex'

export default {
  head: {
    bodyAttrs: {
      class: 'bg-grey-lighter font-sans leading-normal tracking-normal'
    }
  },
  filters: {
    // prettier-ignore
    number(number) {
      return (number || '').toString().replace(/^0|\./g,'').replace(/(\d)(?=(\d{3})+(?!\d))/g,'$1.') || 0
    },
    // prettier-ignore
    signed(number) {
      return number && number !== '0' && !number.match(/^-/) ? `+${number}` : number
    }
  },
  computed: {
    ...mapState(['comparisons']),
    ...mapGetters(['regionPaths'])
  },
  mounted() {
    this.fetchComparisons()
  },
  methods: {
    ...mapActions(['fetchComparisons']),
    toDate(timestamp) {
      return new Date(timestamp * 1000)
    },
    showData(data) {
      if (this.regionPaths.length < 5) {
        this.fetchComparisons(this.comparisons.regionIds.concat([data.id]))
      }
    }
  }
}
</script>

<style>
.table-responsive {
  overflow-x: auto;
  width: 100%;
  position: relative;
}

.table {
  width: 100%;
}

.table thead td {
  text-align: center;
}

.table td,
.table th {
  @apply border;
  @apply border-grey-light;
  @apply p-2;
}

.table tbody tr:hover {
  @apply bg-grey-lighter;
}

.table tfoot td {
  @apply bg-grey-lighter;
  font-size: 1.2rem;
  font-weight: 400;
}

a.nav-link {
  font-weight: 400;
}

.breadcrumb {
  @apply mb-6;
  @apply bg-white;
  overflow: hidden;
}

.breadcrumb li {
  float: left;
}

.breadcrumb li > span,
.breadcrumb li > a {
  display: inline-block;
  @apply py-3;
  @apply px-5;
  font-size: 1.2rem;
}

.breadcrumb li > a {
  @apply bg-blue;
  @apply text-white;
  position: relative;
  z-index: 99;
}

.breadcrumb li > a:after {
  content: '';
  position: absolute;
  right: -12px;
  top: 0px;
  width: 0px;
  height: 0px;
  border-left: 13px solid blue;
  @apply border-blue;
  border-right: none;
  border-top: none;
  border-bottom: 55px solid transparent;
}

.breadcrumb li > a:hover {
  @apply bg-orange;
}

.breadcrumb li > a:hover:after {
  @apply border-orange;
  border-bottom: 53px solid transparent;
}
</style>
