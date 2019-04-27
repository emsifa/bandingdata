import Vuex from 'vuex'
import axios from 'axios'

const api = axios.create({
  baseURL: '/api'
})

// prettier-ignore
const createStore = () => new Vuex.Store({
  state: () => ({
    shownAbout: false,
    comparisons: {
      fetching: false,
      hasData: false,
      regionNames: [],
      regionIds: [],
      tpsCount: null,
      tpsDiffCount: null,
      votes: null,
      childs: []
    }
  }),
  mutations: {
    toggleAbout (state) {
      state.shownAbout = !state.shownAbout
    },
    updateRegions (state, data) {
      state.regions = {...state.regions, ...data}
    },
    updateComparisons (state, data) {
      state.comparisons = {...state.comparisons, ...data}
    }
  },
  getters: {
    regionPaths(state) {
      const names = state.comparisons.regionNames
      const ids = state.comparisons.regionIds
      const paths = []
      ids.forEach((id, i) => {
        paths.push({
          id: id,
          name: names[i],
          regionIds: i === 0 ? [id] : [...paths[i - 1].regionIds, id]
        })
      })
      return paths
    }
  },
  actions: {
    toggleAbout ({commit}) {
      commit('toggleAbout')
    },
    async fetchComparisons ({commit}, paths = []) {
      commit('updateComparisons', {fetching: true})

      let url
      // prettier-ignore
      switch (paths.length) {
        case 0:
        case 1:
          url = `comparisons/all.json`
          break
        case 2:
          url = `comparisons/provinces/${paths[1]}.json`
          break
        case 3:
          url = `comparisons/provinces/${paths[1]}/regencies/${paths[2]}.json`
          break
        case 4:
          url = `comparisons/provinces/${paths[1]}/regencies/${paths[2]}/districts/${paths[3]}.json`
          break
        case 5:
          url = `comparisons/provinces/${paths[1]}/regencies/${paths[2]}/districts/${paths[3]}/subdistricts/${paths[4]}.json`
          break
      }

      const res = await api.get(url)
      commit('updateComparisons', {
        fetching: false,
        hasData: true,
        ...res.data.data
      })
    }
  }
})

export default createStore
