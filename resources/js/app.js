import './bootstrap'
import { BootstrapVue, IconsPlugin } from 'bootstrap-vue'
import Vue from 'vue'
import store from './store'
import App from './views/App'
import router from './router'

// Install BootstrapVue
Vue.use(BootstrapVue)

// Optionally install the BootstrapVue icon components plugin
Vue.use(IconsPlugin)

export default new Vue({
  el: '#app',
  components: { App },
  router,
  store
})