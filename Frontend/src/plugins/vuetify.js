import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'

export default createVuetify({
  theme: {
    defaultTheme: 'light',
    themes: {
      light: {
        dark: false,
        colors: {
          primary: '#2563eb', // Blue-600
          'primary-darken-1': '#1d4ed8',
          secondary: '#475569', // Slate-600
          accent: '#8b5cf6', // Violet-500
          success: '#10b981', // Emerald-500
          warning: '#f59e0b', // Amber-500
          error: '#ef4444', // Red-500
          info: '#0ea5e9', // Sky-500
          background: '#f8fafc',
          surface: '#ffffff',
          'surface-variant': '#f1f5f9',
          'on-surface-variant': '#64748b',
        },
      },
    },
  },
  defaults: {
    VBtn: {
      rounded: 'lg',
      elevation: 0,
      height: 40,
      style: [{ textTransform: 'none', fontWeight: 600, letterSpacing: 'normal' }],
    },
    VCard: {
      rounded: 'xl',
      elevation: 0,
      border: 'thin',
    },
    VTextField: {
      variant: 'outlined',
      density: 'comfortable',
      rounded: 'lg',
      color: 'primary',
    },
    VSelect: {
      variant: 'outlined',
      density: 'comfortable',
      rounded: 'lg',
      color: 'primary',
    },
    VTextarea: {
      variant: 'outlined',
      density: 'comfortable',
      rounded: 'lg',
      color: 'primary',
    },
    VChip: {
      rounded: 'lg',
      elevation: 0,
    },
    VDialog: {
      scrim: 'rgba(15, 23, 42, 0.45)',
    },
  },
})
