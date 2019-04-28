importScripts('/bandingdata/_nuxt/workbox.4c4f5ca6.js')

workbox.precaching.precacheAndRoute([
  {
    "url": "/bandingdata/_nuxt/2abca01b48f450ebc093.js",
    "revision": "b9d25cb63689dc0ab08a68b136668881"
  },
  {
    "url": "/bandingdata/_nuxt/3da463fd96d4d24210c4.js",
    "revision": "4f0a9e18c36d579cde1b7c94546db0a5"
  },
  {
    "url": "/bandingdata/_nuxt/692e2c7899c7672a72e1.js",
    "revision": "d1d6d2cfab4d2abba9e0d8fde955dea8"
  },
  {
    "url": "/bandingdata/_nuxt/8846a2793c930aac3e16.js",
    "revision": "d703c97a1d2c627d87cb9360ec56f946"
  },
  {
    "url": "/bandingdata/_nuxt/d5d6bc093a28c7dace9a.js",
    "revision": "39bc22a9554eac2168143cdb867710ea"
  }
], {
  "cacheId": "bandingdata",
  "directoryIndex": "/",
  "cleanUrls": false
})

workbox.clientsClaim()
workbox.skipWaiting()

workbox.routing.registerRoute(new RegExp('/bandingdata/_nuxt/.*'), workbox.strategies.cacheFirst({}), 'GET')

workbox.routing.registerRoute(new RegExp('/bandingdata/.*'), workbox.strategies.networkFirst({}), 'GET')
