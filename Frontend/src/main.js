import { createApp } from 'vue'
import App from './App.vue'

// 1. Google Font (Plus Jakarta Sans tải về local offline hoàn toàn)
import '@fontsource/plus-jakarta-sans/400.css'
import '@fontsource/plus-jakarta-sans/500.css'
import '@fontsource/plus-jakarta-sans/600.css'
import '@fontsource/plus-jakarta-sans/700.css'

// 2. Styles
import '@/assets/styles/tailwind.css'
import '@/assets/styles/main.css'

// 3. Plugins
import vuetify from './plugins/vuetify'
import pinia from './plugins/pinia'
import router from './router'

// 4. CASL Ability
import { abilitiesPlugin } from '@casl/vue'
import { ability } from '@/core/casl/ability'

const app = createApp(App)

app.use(pinia)
app.use(router)
app.use(vuetify)
app.use(abilitiesPlugin, ability, {
  useGlobalProperties: true,
})

app.mount('#app')
